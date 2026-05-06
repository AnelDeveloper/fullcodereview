<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RedeemCodeMail;
use App\Models\RedeemCode;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        try {
            $session = $stripe->createCheckoutSession($user->email, $selected, $user->id, $discountPct);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not start checkout.'], 500);
        }

        // Stash selection for the webhook to read after payment succeeds
        cache()->put(
            "stripe:session:{$session['id']}:categories",
            array_column($selected, 'key'),
            now()->addHours(2),
        );

        return response()->json($session);
    }

    public function fetchCodeForSession(Request $request, StripeService $stripe, string $sessionId)
    {
        try {
            $session = $stripe->client()->checkout->sessions->retrieve($sessionId, []);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        if ($session->payment_status !== 'paid') {
            return response()->json(['message' => 'Payment not completed.'], 402);
        }

        $code = RedeemCode::where('stripe_session_id', $sessionId)->first();

        if (! $code) {
            $code = $this->issueCodeForSession($session);
        }

        return response()->json([
            'code' => $code->code,
            'email' => $code->email,
            'selectedCategories' => $code->selected_categories ?? [],
        ]);
    }

    public function webhook(Request $request, StripeService $stripe)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $stripe->constructEvent($payload, $signature);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature failed', ['error' => $e->getMessage()]);
            return response('Bad signature.', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $this->issueCodeForSession($session);
        }

        return response()->json(['received' => true]);
    }

    protected function issueCodeForSession($session): RedeemCode
    {
        return DB::transaction(function () use ($session) {
            $existing = RedeemCode::where('stripe_session_id', $session->id)->first();
            if ($existing) return $existing;

            $email = $session->customer_email ?? $session->customer_details->email ?? null;
            $userId = $session->metadata->user_id ?? null;
            $categoryKeys = $session->metadata->category_keys ?? null;

            $selected = $categoryKeys
                ? array_values(array_filter(explode(',', $categoryKeys)))
                : (cache()->pull("stripe:session:{$session->id}:categories") ?? []);

            $code = RedeemCode::create([
                'code' => $this->generateUniqueCode(),
                'email' => strtolower($email),
                'user_id' => $userId ?: null,
                'stripe_session_id' => $session->id,
                'amount_cents' => $session->amount_total ?? 0,
                'selected_categories' => $selected,
                'expires_at' => Carbon::now()->addDays(30),
            ]);

            try {
                Mail::to($email)->send(new RedeemCodeMail($code));
            } catch (\Throwable $e) {
                Log::warning('Redeem code email failed', ['error' => $e->getMessage(), 'code_id' => $code->id]);
            }

            return $code;
        });
    }

    protected function generateUniqueCode(): string
    {
        do {
            $candidate = RedeemCode::generate();
        } while (RedeemCode::where('code', $candidate)->exists());

        return $candidate;
    }
}
