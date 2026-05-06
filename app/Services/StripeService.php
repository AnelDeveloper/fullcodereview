<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    public function client(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    public function constructEvent(string $payload, string $signature)
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret'),
        );
    }

    /**
     * @param  array<int, array{key: string, name: string, price_cents: int}>  $categories
     */
    public function createCheckoutSession(string $email, array $categories, int $userId, int $discountPct = 0): array
    {
        $lineItems = array_map(fn ($cat) => [
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $cat['price_cents'],
                'product_data' => [
                    'name' => "Code Review · {$cat['name']}",
                    'description' => $cat['tagline'] ?? '',
                ],
            ],
            'quantity' => 1,
        ], $categories);

        $params = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $email,
            'line_items' => $lineItems,
            'metadata' => [
                'user_id' => $userId,
                'category_keys' => implode(',', array_column($categories, 'key')),
                'discount_pct' => $discountPct,
            ],
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
        ];

        if ($discountPct > 0) {
            $coupon = $this->client()->coupons->create([
                'percent_off' => $discountPct,
                'duration' => 'once',
                'name' => "Bundle " . count($categories) . " · {$discountPct}% off",
            ]);
            $params['discounts'] = [['coupon' => $coupon->id]];
        }

        $session = $this->client()->checkout->sessions->create($params);

        return ['id' => $session->id, 'url' => $session->url];
    }
}
