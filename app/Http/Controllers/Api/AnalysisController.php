<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunAnalysisJob;
use App\Models\Analysis;
use App\Models\SectionSlot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnalysisController extends Controller
{
    public function run(Request $request)
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

        // One run at a time per user. Surfaces the in-flight analysis so the
        // client can route the user to it instead of double-charging slots.
        $active = Analysis::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'running'])
            ->orderByDesc('id')
            ->first();
        if ($active) {
            return response()->json([
                'message' => 'An audit is already running. View it in your dashboard or wait for it to finish.',
                'code' => 'audit_in_progress',
                'analysis' => $this->present($active),
            ], 409);
        }

        // Atomically create the pending analysis row + reserve one slot per
        // category. We reserve up-front (set used_at + used_by_analysis_id)
        // so two concurrent dispatches can't grab the same slot. If the job
        // fails, RunAnalysisJob::markFailed() refunds them.
        try {
            [$analysis, $slotIds] = DB::transaction(function () use ($user, $valid, $repoSpec) {
                $analysis = Analysis::create([
                    'user_id' => $user->id,
                    'repo_full_name' => $repoSpec,
                    'status' => 'pending',
                    'selected_categories' => $valid,
                ]);

                $slotIds = [];
                foreach ($valid as $category) {
                    $slot = SectionSlot::query()
                        ->where('user_id', $user->id)
                        ->forCategory($category)
                        ->available()
                        ->orderBy('expires_at')
                        ->lockForUpdate()
                        ->first();

                    if (! $slot) {
                        throw new \RuntimeException(
                            "You don't have any {$category} slots available. Buy one to continue.",
                            402,
                        );
                    }
                    $slot->update([
                        'used_at' => now(),
                        'used_by_analysis_id' => $analysis->id,
                    ]);
                    $slotIds[] = $slot->id;
                }

                return [$analysis, $slotIds];
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'no_slots',
            ], $e->getCode() ?: 402);
        }

        // Pin to the database connection so dispatch never falls back to
        // the global QUEUE_CONNECTION=sync, which would re-block the request.
        RunAnalysisJob::dispatch(
            analysisId: $analysis->id,
            repoSpec: $repoSpec,
            userId: $user->id,
            categories: $valid,
            slotIds: $slotIds,
        )->onConnection('database');

        return response()->json([
            'analysis' => $this->present($analysis),
            'sectionsRemaining' => $this->sectionBreakdown($user),
        ], 202);
    }

    public function cancel(Request $request, int $id)
    {
        $analysis = Analysis::findOrFail($id);
        $user = $request->user();
        if (! $user || $analysis->user_id !== $user->id) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }
        if (! in_array($analysis->status, ['pending', 'running'], true)) {
            return response()->json([
                'message' => 'This audit has already finished and cannot be cancelled.',
                'analysis' => $this->present($analysis),
            ], 422);
        }

        DB::transaction(function () use ($analysis) {
            // Refund every slot we reserved for this run. The queue worker's
            // failed() handler checks status, so if it picks up the job after
            // this it will be a no-op.
            SectionSlot::where('used_by_analysis_id', $analysis->id)
                ->update(['used_at' => null, 'used_by_analysis_id' => null]);
            $analysis->update([
                'status' => 'failed',
                'error_message' => 'Cancelled by user.',
            ]);
        });

        return response()->json([
            'analysis' => $this->present($analysis->fresh()),
            'sectionsRemaining' => $this->sectionBreakdown($user),
        ]);
    }

    public function status(Request $request, int $id)
    {
        $analysis = Analysis::findOrFail($id);
        $user = $request->user();
        $isOwner = $analysis->user_id && $analysis->user_id === $user?->id;
        $isReviewer = (bool) ($user?->is_reviewer ?? false);
        if (! $isOwner && ! $isReviewer) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        return response()->json([
            'id' => $analysis->id,
            'status' => $analysis->status,
            'repoFullName' => $analysis->repo_full_name,
            'errorMessage' => $analysis->error_message,
            'sectionsRemaining' => $user ? $this->sectionBreakdown($user) : null,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $analysis = Analysis::with('reviewer')->findOrFail($id);
        $user = $request->user();
        $isOwner = $analysis->user_id && $analysis->user_id === $user?->id;
        $isReviewer = (bool) ($user?->is_reviewer ?? false);
        if (! $isOwner && ! $isReviewer) {
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
                'readinessScore' => $a->readiness_score,
                'readinessStatus' => $a->readiness_status,
                'criticalBlockerCount' => $a->critical_blocker_count,
                'highBlockerCount' => $a->high_blocker_count,
                'verificationStatus' => $a->verification_status,
                'verifiedAt' => $a->verified_at?->toIso8601String(),
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
        $analysis = Analysis::with('reviewer')->findOrFail($id);
        $user = $request->user();
        $isOwner = $analysis->user_id && $analysis->user_id === $user?->id;
        $isReviewer = (bool) ($user?->is_reviewer ?? false);
        if (! $isOwner && ! $isReviewer) {
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
            'status' => $a->status,
            'errorMessage' => $a->error_message,
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

            // Readiness layer
            'readinessScore' => $a->readiness_score,
            'readinessStatus' => $a->readiness_status,
            'criticalBlockerCount' => $a->critical_blocker_count,
            'highBlockerCount' => $a->high_blocker_count,

            // Executive summary (Phase B will populate this; null for now)
            'executiveSummary' => $a->executive_summary_json,

            // Verification workflow
            'verificationStatus' => $a->verification_status,
            'reviewerNotes' => $a->reviewer_notes,
            'verifiedAt' => $a->verified_at?->toIso8601String(),
            'reviewer' => $a->reviewer ? [
                'id' => $a->reviewer->id,
                'name' => $a->reviewer->name,
            ] : null,
        ];
    }
}
