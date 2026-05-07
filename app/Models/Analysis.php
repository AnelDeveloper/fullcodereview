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
        'issues_json',
        'selected_categories',
        'summary',
        'status',
        'error_message',
    ];

    protected $casts = [
        'issues_json' => 'array',
        'selected_categories' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The section slots that were consumed by this analysis (one per
     * category that was reviewed).
     */
    public function consumedSlots()
    {
        return $this->hasMany(RedeemCode::class, 'used_by_analysis_id');
    }
}
