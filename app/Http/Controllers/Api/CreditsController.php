<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RedeemCode;
use Illuminate\Http\Request;

class CreditsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $byCategory = AnalysisController::sectionBreakdown($user);
        $total = array_sum($byCategory);

        return response()->json([
            'total' => $total,
            'byCategory' => $byCategory,
        ]);
    }

    /**
     * Kept for back-compat with anywhere still calling this. Returns a
     * total count of unused, unexpired slots for the user.
     */
    public static function availableQuery($user)
    {
        return RedeemCode::query()
            ->where('user_id', $user->id)
            ->available();
    }
}
