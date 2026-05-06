<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RedeemCode;
use Illuminate\Http\Request;

/**
 * "Credits" = unused, unexpired RedeemCode rows owned by the user.
 *
 * We keep the underlying redeem_codes table because it already stores the
 * Stripe session, the chosen scope and the expiry — credits are just the
 * user-friendly framing on top of that.
 */
class CreditsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $items = $this->availableQuery($user)
            ->orderBy('created_at')
            ->get()
            ->map(fn (RedeemCode $rc) => [
                'id' => $rc->id,
                'selectedCategories' => $rc->selected_categories ?? [],
                'amountCents' => $rc->amount_cents,
                'expiresAt' => $rc->expires_at?->toIso8601String(),
                'createdAt' => $rc->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public static function availableQuery($user)
    {
        return RedeemCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
