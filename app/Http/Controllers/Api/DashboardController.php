<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $analyses = Analysis::where('user_id', $user->id);

        $totalReviews = (clone $analyses)->count();
        $monthReviews = (clone $analyses)->where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        $avgScore = (clone $analyses)->avg('overall_score');
        $totalIssues = (clone $analyses)->get()
            ->reduce(fn ($carry, $a) => $carry + collect($a->issues_json ?? [])->flatten(1)->count(), 0);

        $recent = (clone $analyses)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Analysis $a) => [
                'id' => $a->id,
                'repoFullName' => $a->repo_full_name,
                'overallScore' => $a->overall_score,
                'totalIssues' => collect($a->issues_json ?? [])->flatten(1)->count(),
                'createdAt' => $a->created_at->toIso8601String(),
            ]);

        $credits = CreditsController::availableQuery($user)->count();

        return response()->json([
            'stats' => [
                'totalReviews' => $totalReviews,
                'monthReviews' => $monthReviews,
                'avgScore' => $avgScore ? (int) round($avgScore) : null,
                'totalIssues' => $totalIssues,
                'credits' => $credits,
            ],
            'recent' => $recent,
        ]);
    }
}
