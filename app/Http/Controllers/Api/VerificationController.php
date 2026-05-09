<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Human-verification workflow on top of an Analysis.
 *
 *   POST /analyses/{id}/verification/submit-for-review   (owner)
 *     ai_scan_complete  →  human_review_pending
 *
 *   POST /analyses/{id}/verification/approve             (reviewer)
 *     human_review_pending  →  human_verified
 *     stamps reviewer_id, reviewer_notes, verified_at
 *
 *   POST /analyses/{id}/verification/finalize            (reviewer)
 *     human_verified  →  finalized   (locks the report — used for invoiceable
 *     enterprise audits where the report shouldn't change after sign-off)
 *
 *   GET  /reviewer/queue                                 (reviewer)
 *     Lists analyses awaiting human review.
 *
 * Reviewer endpoints are gated by the `reviewer` middleware which checks
 * users.is_reviewer.
 */
class VerificationController extends Controller
{
    /** Owner: send the analysis to the reviewer queue. */
    public function submitForReview(Request $request, int $id)
    {
        $analysis = Analysis::findOrFail($id);
        $user = $request->user();

        if ($analysis->user_id !== $user?->id) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        if ($analysis->verification_status !== Analysis::VERIFICATION_AI_SCAN_COMPLETE) {
            return response()->json([
                'message' => "Analysis is in '{$analysis->verification_status}' — only 'ai_scan_complete' analyses can be submitted for review.",
            ], 422);
        }

        $analysis->update([
            'verification_status' => Analysis::VERIFICATION_HUMAN_REVIEW_PENDING,
        ]);

        return response()->json([
            'message' => 'Submitted for senior-engineer review.',
            'verificationStatus' => $analysis->verification_status,
        ]);
    }

    /** Reviewer: approve a pending review with notes. */
    public function approve(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'reviewer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_comments' => ['nullable', 'string', 'max:10000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        $analysis = Analysis::findOrFail($id);

        if ($analysis->verification_status !== Analysis::VERIFICATION_HUMAN_REVIEW_PENDING) {
            return response()->json([
                'message' => "Analysis is in '{$analysis->verification_status}' — only 'human_review_pending' analyses can be approved.",
            ], 422);
        }

        $reviewer = $request->user();
        $analysis->update([
            'verification_status' => Analysis::VERIFICATION_HUMAN_VERIFIED,
            'reviewer_id' => $reviewer->id,
            'reviewer_notes' => $request->input('reviewer_notes'),
            'internal_comments' => $request->input('internal_comments'),
            'verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Verified.',
            'verificationStatus' => $analysis->verification_status,
            'verifiedAt' => $analysis->verified_at?->toIso8601String(),
        ]);
    }

    /** Reviewer: lock the report (post-verification). */
    public function finalize(Request $request, int $id)
    {
        $analysis = Analysis::findOrFail($id);

        if ($analysis->verification_status !== Analysis::VERIFICATION_HUMAN_VERIFIED) {
            return response()->json([
                'message' => "Analysis is in '{$analysis->verification_status}' — only 'human_verified' analyses can be finalized.",
            ], 422);
        }

        $analysis->update([
            'verification_status' => Analysis::VERIFICATION_FINALIZED,
        ]);

        return response()->json([
            'message' => 'Finalized.',
            'verificationStatus' => $analysis->verification_status,
        ]);
    }

    /** Reviewer: queue of analyses pending human review. */
    public function queue(Request $request)
    {
        $items = Analysis::where('verification_status', Analysis::VERIFICATION_HUMAN_REVIEW_PENDING)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'repoFullName' => $a->repo_full_name,
                'repoUrl' => $a->repo_url,
                'overallScore' => $a->overall_score,
                'readinessScore' => $a->readiness_score,
                'readinessStatus' => $a->readiness_status,
                'criticalBlockerCount' => $a->critical_blocker_count,
                'highBlockerCount' => $a->high_blocker_count,
                'totalIssues' => collect($a->issues_json ?? [])->flatten(1)->count(),
                'requesterEmail' => $a->user?->email,
                'submittedAt' => $a->updated_at?->toIso8601String(),
            ]);

        return response()->json(['items' => $items]);
    }
}
