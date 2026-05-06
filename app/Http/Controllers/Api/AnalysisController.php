<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AnalysisReportMail;
use App\Models\Analysis;
use App\Services\AnalysisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AnalysisController extends Controller
{
    public function run(Request $request, AnalysisService $service)
    {
        $request->validate([
            'repoUrl' => ['nullable', 'string'],
            'repoFullName' => ['nullable', 'string'],
        ]);

        $repoSpec = $request->input('repoFullName') ?: $request->input('repoUrl');
        if (! $repoSpec) {
            return response()->json(['message' => 'Provide a repository URL or full name.'], 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'You must be signed in.'], 401);
        }

        // Pick the oldest available credit so users burn the ones closest to expiry first.
        $credit = CreditsController::availableQuery($user)
            ->orderBy('expires_at')
            ->first();

        if (! $credit) {
            return response()->json([
                'message' => 'You have no review credits. Buy a review to continue.',
                'code' => 'no_credits',
            ], 402);
        }

        try {
            $analysis = $service->runForRepo(
                repoSpec: $repoSpec,
                user: $user,
                code: $credit,
            );
        } catch (\Throwable $e) {
            Log::error('Analysis failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $credit->update(['used_at' => now()]);

        try {
            Mail::to($user->email)->send(new AnalysisReportMail($analysis));
        } catch (\Throwable $e) {
            Log::warning('Could not email analysis report', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'analysis' => $this->present($analysis),
            'creditsRemaining' => CreditsController::availableQuery($user)->count(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $analysis = Analysis::findOrFail($id);
        $user = $request->user();
        if ($analysis->user_id && $analysis->user_id !== $user?->id) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }
        return response()->json(['analysis' => $this->present($analysis)]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);

        $items = Analysis::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'repoFullName' => $a->repo_full_name,
                'repoUrl' => $a->repo_url,
                'overallScore' => $a->overall_score,
                'securityScore' => $a->security_score,
                'performanceScore' => $a->performance_score,
                'qualityScore' => $a->quality_score,
                'filesScanned' => $a->files_scanned,
                'linesAnalyzed' => $a->lines_analyzed,
                'totalIssues' => collect($a->issues_json ?? [])->flatten(1)->count(),
                'selectedCategories' => $a->selected_categories ?? [],
                'createdAt' => $a->created_at->toIso8601String(),
            ]);
        return response()->json(['items' => $items]);
    }

    public function reportPdf(Request $request, int $id)
    {
        $analysis = Analysis::findOrFail($id);
        $user = $request->user();
        if ($analysis->user_id && $analysis->user_id !== $user?->id) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $pdf = Pdf::loadView('reports.analysis_pdf', ['a' => $analysis]);
        $filename = str_replace('/', '_', $analysis->repo_full_name) . '-codereview.pdf';
        return $pdf->download($filename);
    }

    protected function present(Analysis $a): array
    {
        return [
            'id' => $a->id,
            'repoName' => $a->repo_full_name,
            'repoUrl' => $a->repo_url,
            'overallScore' => $a->overall_score,
            'securityScore' => $a->security_score,
            'performanceScore' => $a->performance_score,
            'qualityScore' => $a->quality_score,
            'filesScanned' => $a->files_scanned,
            'linesAnalyzed' => $a->lines_analyzed,
            'issues' => $a->issues_json,
            'selectedCategories' => $a->selected_categories ?? [],
            'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }
}
