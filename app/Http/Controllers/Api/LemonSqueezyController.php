<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RedeemCodeMail;
use App\Models\RedeemCode;
use App\Services\LemonSqueezyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $this->issueCodeForOrder([
            'order_id' => $orderId,
            'user_id' => $userId,
            'email' => $email,
            'amount_cents' => $usdSubtotalCents ?? $totalCents,
            'selected_categories' => $selected,
        ]);

        return response()->json(['received' => true]);
    }

    protected function issueCodeForOrder(array $data): RedeemCode
    {
        return DB::transaction(function () use ($data) {
            $existing = RedeemCode::where('lemon_order_id', $data['order_id'])->first();
            if ($existing) return $existing;

            $code = RedeemCode::create([
                'code' => $this->generateUniqueCode(),
                'email' => $data['email'],
                'user_id' => $data['user_id'],
                'lemon_order_id' => $data['order_id'],
                'amount_cents' => $data['amount_cents'],
                'selected_categories' => $data['selected_categories'],
                'expires_at' => Carbon::now()->addDays(30),
            ]);

            try {
                if ($data['email']) {
                    Mail::to($data['email'])->send(new RedeemCodeMail($code));
                }
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
