<?php

namespace App\Jobs;

use App\Mail\AnalysisReportMail;
use App\Models\Analysis;
use App\Models\SectionSlot;
use App\Models\User;
use App\Services\AnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RunAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        public int $analysisId,
        public string $repoSpec,
        public ?int $userId,
        public array $categories,
        public array $slotIds,
    ) {}

    public function handle(AnalysisService $service): void
    {
        $analysis = Analysis::find($this->analysisId);
        if (! $analysis) return;

        // If the user cancelled before the worker picked the job up, leave
        // the row alone — cancel() already refunded the slots.
        if (! in_array($analysis->status, ['pending', 'running'], true)) return;

        $user = $this->userId ? User::find($this->userId) : null;

        $analysis->update(['status' => 'running', 'error_message' => null]);

        try {
            $service->populateForRepo(
                analysis: $analysis,
                repoSpec: $this->repoSpec,
                user: $user,
                githubToken: $user?->github_access_token,
                categories: $this->categories,
            );
        } catch (Throwable $e) {
            $this->markFailed($analysis, $e->getMessage());
            throw $e;
        }

        if ($user) {
            try {
                Mail::to($user->email)->send(new AnalysisReportMail($analysis));
            } catch (Throwable $e) {
                Log::warning('Could not email analysis report', ['error' => $e->getMessage()]);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        $analysis = Analysis::find($this->analysisId);
        if ($analysis) $this->markFailed($analysis, $e->getMessage());
    }

    protected function markFailed(Analysis $analysis, string $message): void
    {
        $analysis->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        // Refund the slots that were reserved at request time so the user
        // doesn't lose credits to a backend error.
        SectionSlot::whereIn('id', $this->slotIds)
            ->where('used_by_analysis_id', $analysis->id)
            ->update([
                'used_at' => null,
                'used_by_analysis_id' => null,
            ]);
    }
}
