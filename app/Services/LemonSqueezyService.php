<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Wraps the Lemon Squeezy REST API for our checkout flow.
 *
 * One product / one "Pay what you want" variant in LS — we drive the
 * actual amount via `custom_price` per checkout, computed server-side
 * from the catalog + bundle discount.
 *
 * LS expects amounts in the store's currency cents (BAM here). Our
 * catalog is USD cents, so we multiply by `usd_to_store_rate` before
 * sending.
 */
class LemonSqueezyService
{
    public const API_BASE = 'https://api.lemonsqueezy.com/v1';

    /**
     * @param  array<int, array{key: string, name: string, price_cents: int}>  $categories
     */
    public function createCheckout(
        string $email,
        int $userId,
        string $userName,
        array $categories,
        int $discountPct,
    ): array {
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');
        $variantId = config('services.lemonsqueezy.variant_id');
        $rate = (float) config('services.lemonsqueezy.usd_to_store_rate', 1.83);

        if (! $apiKey || ! $storeId || ! $variantId) {
            throw new RuntimeException('Lemon Squeezy is not configured. Set LEMONSQUEEZY_API_KEY / STORE_ID / VARIANT_ID.');
        }

        $usdSubtotalCents = array_sum(array_column($categories, 'price_cents'));
        $usdAfterDiscount = $usdSubtotalCents - (int) round($usdSubtotalCents * $discountPct / 100);
        $storeCents = (int) round($usdAfterDiscount * $rate);

        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'product_options' => [
                        'name' => 'Code Review',
                        'description' => 'AI-powered code review of one GitHub repository.',
                        'redirect_url' => config('services.lemonsqueezy.success_url')
                            ?: rtrim(config('app.url'), '/') . '/review?lemon_success=1',
                        'receipt_button_text' => 'Back to Full Code Review',
                        'receipt_link_url' => rtrim(config('app.url'), '/') . '/review',
                    ],
                    'checkout_options' => [
                        'embed' => false,
                        'media' => false,
                        'logo' => true,
                    ],
                    'checkout_data' => [
                        'email' => $email,
                        'name' => $userName,
                        'custom' => [
                            'user_id' => (string) $userId,
                            'category_keys' => implode(',', array_column($categories, 'key')),
                            'usd_subtotal_cents' => (string) $usdSubtotalCents,
                            'discount_pct' => (string) $discountPct,
                        ],
                    ],
                    // amount in store-currency cents
                    'custom_price' => $storeCents,
                ],
                'relationships' => [
                    'store' => ['data' => ['type' => 'stores', 'id' => (string) $storeId]],
                    'variant' => ['data' => ['type' => 'variants', 'id' => (string) $variantId]],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])
            ->timeout(30)
            ->post(self::API_BASE . '/checkouts', $payload);

        // LS returns 201 Created on success — `ok()` only matches 200, so use `successful()` (200-299)
        if (! $response->successful()) {
            throw new RuntimeException('Lemon Squeezy checkout creation failed: ' . $response->status() . ' ' . $response->body());
        }

        $url = $response->json('data.attributes.url');
        $id = $response->json('data.id');

        if (! $url) {
            throw new RuntimeException('Lemon Squeezy returned no checkout URL.');
        }

        return ['id' => $id, 'url' => $url];
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        $secret = config('services.lemonsqueezy.webhook_secret');
        if (! $secret) return false;

        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computed, $signature);
    }

    /**
     * Pull a single order by id (used by the post-checkout redirect to
     * heal the case where the webhook hasn't landed yet).
     */
    public function getOrder(string $orderId): ?array
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        if (! $apiKey) return null;

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])
            ->timeout(15)
            ->get(self::API_BASE . '/orders/' . $orderId);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * List recent orders for our store filtered by customer email — lets
     * us reconcile a successful checkout we just got redirected back from
     * even when the webhook is delayed or missed.
     */
    public function listRecentOrdersForEmail(string $email): array
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');
        if (! $apiKey || ! $storeId) return [];

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])
            ->timeout(20)
            ->get(self::API_BASE . '/orders', [
                'filter[store_id]' => $storeId,
                'filter[user_email]' => $email,
                'page[size]' => 20,
            ]);

        return $response->successful() ? ($response->json('data') ?? []) : [];
    }
}
