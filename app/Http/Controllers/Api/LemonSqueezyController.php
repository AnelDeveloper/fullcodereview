<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingCheckout;
use App\Models\SectionSlot;
use App\Services\LemonSqueezyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LemonSqueezyController extends Controller
{
    public function checkout(Request $request, LemonSqueezyService $ls)
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
            $checkout = $ls->createCheckout(
                email: $user->email,
                userId: $user->id,
                userName: $user->name,
                categories: $selected,
                discountPct: $discountPct,
            );
        } catch (\Throwable $e) {
            Log::error('Lemon Squeezy checkout failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not start checkout.'], 500);
        }

        // Persist what we'll need to credit the user after payment. LS doesn't
        // surface our custom_data via the Orders API, so reconciliation has to
        // come from our own record. Match against this row at sync time.
        PendingCheckout::create([
            'user_id'         => $user->id,
            'ls_checkout_id'  => $checkout['id'],
            'category_keys'   => array_column($selected, 'key'),
            'usd_total_cents' => $usdAfterDiscount,
            'discount_pct'    => $discountPct,
            'status'          => 'pending',
        ]);

        return response()->json($checkout);
    }

    /**
     * Webhook handler. LS signs the body with HMAC-SHA256 using the secret
     * we set when creating the webhook. The signature comes in
     * `X-Signature` header.
     */
    public function webhook(Request $request, LemonSqueezyService $ls)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Signature', '');

        if (! $ls->verifySignature($payload, $signature)) {
            Log::warning('Lemon Squeezy webhook bad signature');
            return response('Bad signature.', 400);
        }

        $event = $request->header('X-Event-Name');
        $body = json_decode($payload, true) ?? [];

        if ($event !== 'order_created') {
            return response()->json(['received' => true]);
        }

        $order = $body['data'] ?? null;
        if (! $order) return response('No data.', 400);

        $orderId = (string) ($order['id'] ?? '');
        $attrs = $order['attributes'] ?? [];
        $customData = $body['meta']['custom_data'] ?? [];
        // LS sometimes nests custom in attributes.first_order_item.custom_data — handle both
        if (empty($customData) && isset($attrs['first_order_item']['custom_data'])) {
            $customData = $attrs['first_order_item']['custom_data'];
        }

        $userId = isset($customData['user_id']) ? (int) $customData['user_id'] : null;
        $email = strtolower($attrs['user_email'] ?? '');
        $totalCents = (int) ($attrs['total'] ?? 0); // store-currency cents
        $usdSubtotalCents = isset($customData['usd_subtotal_cents']) ? (int) $customData['usd_subtotal_cents'] : null;
        $categoryKeys = $customData['category_keys'] ?? null;
        $selected = $categoryKeys ? array_values(array_filter(explode(',', $categoryKeys))) : [];

        $this->issueSlotsForOrder([
            'order_id' => $orderId,
            'user_id' => $userId,
            'email' => $email,
            'amount_cents' => $usdSubtotalCents ?? $totalCents,
            'categories' => $selected,
        ]);

        // Mark a matching pending_checkout as completed so the sync fallback
        // doesn't try to re-match this order to a different unmatched row.
        if ($userId && $usdSubtotalCents !== null && ! empty($selected)) {
            PendingCheckout::query()
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->where('usd_total_cents', $usdSubtotalCents)
                ->orderBy('created_at')
                ->limit(1)
                ->update([
                    'status'           => 'completed',
                    'matched_order_id' => $orderId,
                    'matched_at'       => now(),
                ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Webhook fallback. Frontend calls this after the LS post-checkout
     * redirect lands on /review?lemon_success=1. Pulls the user's recent
     * LS orders by email and creates any missing slots — handles the
     * case where the webhook is delayed, blocked, or dropped.
     */
    public function sync(Request $request, LemonSqueezyService $ls)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'You must be signed in.'], 401);

        // LS's Orders API doesn't return our custom_data, so we reconcile
        // against PendingCheckout rows we wrote at checkout creation time.
        // Match each unmatched paid order to a pending checkout by USD total.
        $orders = $ls->listRecentOrdersForEmail($user->email);
        $pending = PendingCheckout::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $alreadyMatched = PendingCheckout::query()
            ->where('user_id', $user->id)
            ->whereNotNull('matched_order_id')
            ->pluck('matched_order_id')
            ->all();

        $created = 0;

        foreach ($orders as $order) {
            $orderId = (string) ($order['id'] ?? '');
            if (! $orderId) continue;
            if (in_array($orderId, $alreadyMatched, true)) continue;

            $attrs = $order['attributes'] ?? [];
            if (($attrs['status'] ?? '') !== 'paid') continue;

            $totalUsdCents = (int) ($attrs['total_usd'] ?? 0);
            if ($totalUsdCents <= 0) continue;

            // Take the most recent matching pending checkout with the same total.
            $matchKey = $pending->search(fn ($p) => $p->usd_total_cents === $totalUsdCents);
            if ($matchKey === false) continue;
            $match = $pending->get($matchKey);

            $this->issueSlotsForOrder([
                'order_id'     => $orderId,
                'user_id'      => $user->id,
                'email'        => $user->email,
                'amount_cents' => $totalUsdCents,
                'categories'   => $match->category_keys,
            ]);

            $match->update([
                'status'           => 'completed',
                'matched_order_id' => $orderId,
                'matched_at'       => now(),
            ]);

            // Remove from in-memory list so we don't double-match it.
            $pending->forget($matchKey);

            $created++;
        }

        return response()->json([
            'createdOrders' => $created,
            'sections'      => AnalysisController::sectionBreakdown($user),
        ]);
    }

    /**
     * Idempotent at the (order_id, category) grain. Skips any (order, cat)
     * pair already on disk so a partially-credited order — webhook crashed
     * mid-loop, sync retries, etc. — gets fully reconciled on the next
     * call instead of either erroring or no-op'ing entirely.
     */
    protected function issueSlotsForOrder(array $data): void
    {
        DB::transaction(function () use ($data) {
            $existing = SectionSlot::where('lemon_order_id', $data['order_id'])
                ->pluck('category')
                ->all();

            foreach ($data['categories'] as $category) {
                if (in_array($category, $existing, true)) continue;

                SectionSlot::create([
                    'user_id'        => $data['user_id'],
                    'lemon_order_id' => $data['order_id'],
                    'amount_cents'   => $data['amount_cents'],
                    'category'       => $category,
                    'expires_at'     => Carbon::now()->addDays(30),
                ]);
            }
        });
    }
}
