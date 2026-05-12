<?php

namespace App\Services;

use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Thin wrapper around Stripe Checkout for the QodeShark per-category flow.
 *
 * We charge in USD (matches our catalog directly — no FX conversion needed).
 * One Checkout Session per purchase, with one line_item per selected
 * category. The bundle discount is folded into each line item's
 * `unit_amount` so the receipt shows itemized categories at the right
 * per-line price without us having to spin up a one-off Stripe Coupon.
 *
 * Unlike Lemon Squeezy, Stripe returns our `metadata` on every
 * `sessions.retrieve()` call — that's why the post-checkout reconciliation
 * (`sync`) can fall back to fetching a specific session by id instead of
 * scanning store-wide order lists.
 */
class StripeService
{
    protected function client(): StripeClient
    {
        $key = config('services.stripe.secret_key');
        if (! $key) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET_KEY.');
        }
        return new StripeClient($key);
    }

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
        $stripe = $this->client();

        // Fold the bundle discount into each line's unit_amount so the
        // receipt itemizes by category. unit_amount is rounded per-line,
        // which means total post-discount can drift by a cent or two from
        // a top-down `subtotal * (1 - pct)` calc — close enough.
        $lineItems = array_map(function ($cat) use ($discountPct) {
            $discounted = (int) round($cat['price_cents'] * (1 - $discountPct / 100));
            return [
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $discounted,
                    'product_data' => [
                        'name' => 'QodeShark — ' . $cat['name'],
                        'description' => $cat['tagline'] ?? 'AI-powered code review.',
                    ],
                ],
            ];
        }, $categories);

        $subtotal = array_sum(array_column($categories, 'price_cents'));
        $usdAfterDiscount = $subtotal - (int) round($subtotal * $discountPct / 100);

        try {
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'customer_email' => $email,
                'line_items' => $lineItems,
                // Stripe metadata is flat string→string and capped at 500 chars
                // per value, so we serialize the category list as a CSV.
                'metadata' => [
                    'user_id' => (string) $userId,
                    'user_name' => $userName,
                    'category_keys' => implode(',', array_column($categories, 'key')),
                    'discount_pct' => (string) $discountPct,
                    'usd_subtotal_cents' => (string) $subtotal,
                    'usd_total_cents' => (string) $usdAfterDiscount,
                ],
                'success_url' => $this->successUrl(),
                'cancel_url' => $this->cancelUrl(),
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Stripe checkout creation failed: ' . $e->getMessage(), 0, $e);
        }

        if (! $session->url) {
            throw new RuntimeException('Stripe did not return a checkout URL.');
        }

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function retrieveSession(string $sessionId): ?Session
    {
        try {
            return $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Verifies and constructs a Stripe Event from the raw request body.
     * Throws on bad signature or missing secret.
     */
    public function verifyWebhook(string $payload, string $signature): \Stripe\Event
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            throw new RuntimeException('STRIPE_WEBHOOK_SECRET is not configured.');
        }
        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            throw new RuntimeException('Bad Stripe signature: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function successUrl(): string
    {
        return config('services.stripe.success_url')
            ?: rtrim(config('app.url'), '/') . '/review?stripe_success=1&session_id={CHECKOUT_SESSION_ID}';
    }

    protected function cancelUrl(): string
    {
        return config('services.stripe.cancel_url')
            ?: rtrim(config('app.url'), '/') . '/review?stripe_canceled=1';
    }
}
