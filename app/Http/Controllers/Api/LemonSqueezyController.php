<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $orders = $ls->listRecentOrdersForEmail($user->email);
        $created = 0;

        foreach ($orders as $order) {
            $orderId = (string) ($order['id'] ?? '');
            if (! $orderId) continue;

            // Skip if we've already issued slots for this order
            if (SectionSlot::where('lemon_order_id', $orderId)->exists()) continue;

            $attrs = $order['attributes'] ?? [];
            $custom = $attrs['first_order_item']['custom_data']
                ?? ($order['meta']['custom_data'] ?? null)
                ?? [];

            // Only honor orders that originated from our checkout flow
            // (we set user_id + category_keys in custom_data)
            $orderUserId = isset($custom['user_id']) ? (int) $custom['user_id'] : null;
            if ($orderUserId !== $user->id) continue;

            $categoryKeys = $custom['category_keys'] ?? '';
            $categories = array_values(array_filter(explode(',', $categoryKeys)));
            if (empty($categories)) continue;

            $usdSubtotalCents = isset($custom['usd_subtotal_cents']) ? (int) $custom['usd_subtotal_cents'] : null;

            $this->issueSlotsForOrder([
                'order_id' => $orderId,
                'user_id' => $user->id,
                'email' => $user->email,
                'amount_cents' => $usdSubtotalCents ?? (int) ($attrs['total'] ?? 0),
                'categories' => $categories,
            ]);

            $created++;
        }

        return response()->json([
            'createdOrders' => $created,
            'sections' => AnalysisController::sectionBreakdown($user),
        ]);
    }

    /**
     * Idempotent: one section slot per purchased category. If we've
     * already issued slots for this order_id, no-op.
     */
    protected function issueSlotsForOrder(array $data): void
    {
        DB::transaction(function () use ($data) {
            $exists = SectionSlot::where('lemon_order_id', $data['order_id'])->exists();
            if ($exists) return;

            foreach ($data['categories'] as $category) {
                SectionSlot::create([
                    'user_id' => $data['user_id'],
                    'lemon_order_id' => $data['order_id'],
                    'amount_cents' => $data['amount_cents'],
                    'category' => $category,
                    'expires_at' => Carbon::now()->addDays(30),
                ]);
            }
        });
    }
}
