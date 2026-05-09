<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    protected $fillable = [
        'user_id',
        'repo_full_name',
        'repo_url',
        'repo_default_branch',
        'files_scanned',
        'lines_analyzed',
        'overall_score',
        'security_score',
        'performance_score',
        'quality_score',
        // Readiness layer (computed from existing scores + issues_json — no AI)
        'readiness_score',
        'readiness_status',
        'critical_blocker_count',
        'high_blocker_count',
        'issues_json',
        'executive_summary_json',
        'selected_categories',
        'summary',
        'status',
        'error_message',
        // Human verification workflow
        'verification_status',
        'reviewer_id',
        'reviewer_notes',
        'internal_comments',
        'verified_at',
    ];

    protected $casts = [
        'issues_json' => 'array',
        'executive_summary_json' => 'array',
        'selected_categories' => 'array',
        'verified_at' => 'datetime',
    ];

    public const VERIFICATION_AI_SCAN_COMPLETE = 'ai_scan_complete';
    public const VERIFICATION_HUMAN_REVIEW_PENDING = 'human_review_pending';
    public const VERIFICATION_HUMAN_VERIFIED = 'human_verified';
    public const VERIFICATION_FINALIZED = 'finalized';

    public const READINESS_LAUNCH_READY = 'launch_ready';
    public const READINESS_NEEDS_ATTENTION = 'needs_attention';
    public const READINESS_BLOCKED = 'blocked';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * The section slots that were consumed by this analysis (one per
     * category that was reviewed).
     */
    public function consumedSlots()
    {
        return $this->hasMany(SectionSlot::class, 'used_by_analysis_id');
    }
}
