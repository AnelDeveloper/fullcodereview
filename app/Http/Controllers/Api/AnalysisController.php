<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AnalysisReportMail;
use App\Models\Analysis;
use App\Models\SectionSlot;
use App\Services\AnalysisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AnalysisController extends Controller
{
    public function run(Request $request, AnalysisService $service)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'You must be signed in.'], 401);

        $validator = Validator::make($request->all(), [
            'repoUrl' => ['nullable', 'string'],
            'repoFullName' => ['nullable', 'string'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        $repoSpec = $request->input('repoFullName') ?: $request->input('repoUrl');
        if (! $repoSpec) {
            return response()->json(['message' => 'Provide a repository URL or full name.'], 422);
        }

        $catalog = config('codereview.categories', []);
        $requested = array_values(array_unique($request->input('categories')));
        $valid = array_values(array_intersect($requested, array_keys($catalog)));

        if (empty($valid)) {
            return response()->json(['message' => 'No valid categories selected.'], 422);
        }

        // Reserve one slot per requested category. If any category has none
        // available, fail before charging the user a real Anthropic call.
        $reservations = [];
        foreach ($valid as $category) {
            $slot = SectionSlot::query()
                ->where('user_id', $user->id)
                ->forCategory($category)
                ->available()
                ->orderBy('expires_at')
                ->first();

            if (! $slot) {
                return response()->json([
                    'message' => "You don't have any {$category} slots available. Buy one to continue.",
                    'code' => 'no_slots',
                    'missingCategory' => $category,
                ], 402);
            }
            $reservations[$category] = $slot;
        }

        try {
            $analysis = $service->runForRepo(
                repoSpec: $repoSpec,
                user: $user,
                githubToken: null,
                categories: $valid,
            );
        } catch (\Throwable $e) {
            Log::error('Analysis failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['message' => $e->getMessage()], 502);
        }

        // Mark each reserved slot as consumed by this analysis
        DB::transaction(function () use ($reservations, $analysis) {
            foreach ($reservations as $slot) {
                $slot->update([
                    'used_at' => now(),
                    'used_by_analysis_id' => $analysis->id,
                ]);
            }
        });

        try {
            Mail::to($user->email)->send(new AnalysisReportMail($analysis));
        } catch (\Throwable $e) {
            Log::warning('Could not email analysis report', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'analysis' => $this->present($analysis),
            'sectionsRemaining' => $this->sectionBreakdown($user),
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

    public static function sectionBreakdown($user): array
    {
        $catalog = array_keys(config('codereview.categories', []));
        $counts = SectionSlot::query()
            ->where('user_id', $user->id)
            ->available()
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category')
            ->toArray();

        $result = [];
        foreach ($catalog as $key) {
            $result[$key] = (int) ($counts[$key] ?? 0);
        }
        return $result;
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
