<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    protected $fillable = [
        'user_id',
        'redeem_code_id',
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

    public function redeemCode()
    {
        return $this->belongsTo(RedeemCode::class);
    }
}
