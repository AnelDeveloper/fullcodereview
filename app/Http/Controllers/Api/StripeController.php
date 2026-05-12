<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingCheckout;
use App\Models\SectionSlot;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StripeController extends Controller
{
    public function checkout(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'You must be signed in.'], 401);

        $validator = Validator::make($request->all(), [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        $catalog = config('codereview.categories');
        $minTotal = (int) config('codereview.min_total_cents');
        $tiers = config('codereview.bundle_discount_pct', []);

        $selected = array_values(array_intersect_key($catalog, array_flip($request->input('categories'))));
        if (empty($selected)) {
            return response()->json(['message' => 'No valid categories selected.'], 422);
        }

        $subtotal = array_sum(array_column($selected, 'price_cents'));
        if ($subtotal < $minTotal) {
            return response()->json([
                'message' => 'Minimum order is $' . number_format($minTotal / 100, 0) . '. Add another category.',
            ], 422);
        }

        $discountPct = (int) ($tiers[count($selected)] ?? 0);
        $usdAfterDiscount = $subtotal - (int) round($subtotal * $discountPct / 100);

        try {
            $checkout = $stripe->createCheckout(
                email: $user->email,
                userId: $user->id,
                userName: $user->name,
                categories: $selected,
                discountPct: $discountPct,
            );
        } catch (\Throwable $e) {
            Log::error('Stripe checkout failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not start checkout.'], 500);
        }

        // Persist what we'll need to credit the user after payment. Stripe
        // does return metadata on session retrieve, but we still mirror it
        // so we can match by user_id + session id without round-tripping to
        // Stripe on every history page load.
        PendingCheckout::create([
            'user_id'           => $user->id,
            'stripe_session_id' => $checkout['id'],
            'category_keys'     => array_column($selected, 'key'),
            'usd_total_cents'   => $usdAfterDiscount,
            'discount_pct'      => $discountPct,
            'status'            => 'pending',
        ]);

        return response()->json($checkout);
    }

    /**
     * Stripe sends `Stripe-Signature` over the raw body. The verification
     * lives in StripeService::verifyWebhook — it throws on a bad signature
     * which we surface as a 400. We only act on `checkout.session.completed`
     * because that's the moment payment is collected and the session is
     * final (vs. `created` which fires earlier and `expired` which we don't
     * care about).
     */
    public function webhook(Request $request, StripeService $stripe)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $stripe->verifyWebhook($payload, $signature);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook bad signature', ['error' => $e->getMessage()]);
            return response('Bad signature.', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->json(['received' => true]);
        }

        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        $this->creditSession($session);

        return response()->json(['received' => true]);
    }

    /**
     * Webhook fallback. The frontend hits this after the Stripe-hosted
     * redirect lands on /review?stripe_success=1&session_id=…. If the
     * webhook hasn't fired yet (delayed / blocked) this fetches the
     * session directly and issues the slots.
     *
     * Can also be called without a session_id — in that case we walk the
     * user's pending checkouts and reconcile each one. Cheap because we
     * cap at the most recent few and bail when one isn't paid yet.
     */
    public function sync(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'You must be signed in.'], 401);

        $sessionId = (string) $request->input('session_id', '');
        $created = 0;

        if ($sessionId !== '') {
            $session = $stripe->retrieveSession($sessionId);
            if ($session && $session->payment_status === 'paid') {
                if ($this->creditSession($session)) $created++;
            }
        } else {
            $pending = PendingCheckout::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            foreach ($pending as $row) {
                $session = $stripe->retrieveSession($row->stripe_session_id);
                if (! $session || $session->payment_status !== 'paid') continue;
                if ($this->creditSession($session)) $created++;
            }
        }

        return response()->json([
            'createdOrders' => $created,
            'sections'      => AnalysisController::sectionBreakdown($user),
        ]);
    }

    /**
     * Idempotent at the (session_id, category) grain. Credits the user's
     * section_slots for every category named in the session metadata, and
     * marks the matching pending_checkout completed.
     *
     * Returns true if at least one slot was created (i.e. this call did
     * something), false if the session was already fully reconciled.
     */
    protected function creditSession(\Stripe\Checkout\Session $session): bool
    {
        $metadata = $session->metadata ? $session->metadata->toArray() : [];
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;
        $categoryKeys = $metadata['category_keys'] ?? '';
        $categories = array_values(array_filter(explode(',', $categoryKeys)));
        $amountCents = $session->amount_total ?? 0;

        if (! $userId || empty($categories)) {
            Log::warning('Stripe session missing metadata for credit', [
                'session_id' => $session->id,
                'metadata' => $metadata,
            ]);
            return false;
        }

        $createdAny = false;

        DB::transaction(function () use ($session, $userId, $categories, $amountCents, &$createdAny) {
            $existing = SectionSlot::where('stripe_session_id', $session->id)
                ->pluck('category')
                ->all();

            foreach ($categories as $category) {
                if (in_array($category, $existing, true)) continue;

                SectionSlot::create([
                    'user_id'           => $userId,
                    'stripe_session_id' => $session->id,
                    'amount_cents'      => $amountCents,
                    'category'          => $category,
                    'expires_at'        => Carbon::now()->addDays(30),
                ]);
                $createdAny = true;
            }

            PendingCheckout::query()
                ->where('user_id', $userId)
                ->where('stripe_session_id', $session->id)
                ->where('status', 'pending')
                ->update([
                    'status'           => 'completed',
                    'matched_order_id' => $session->id,
                    'matched_at'       => now(),
                ]);
        });

        return $createdAny;
    }
}
