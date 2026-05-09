<?php

namespace App\Console\Commands;

use App\Models\Analysis;
use App\Services\ReadinessScorer;
use Illuminate\Console\Command;

/**
 * Backfills readiness_score / readiness_status / blocker counts for analyses
 * created before the readiness layer existed. Pure compute over existing
 * data — no AI calls, no external I/O. Idempotent: safe to run repeatedly.
 *
 *   php artisan analyses:backfill-readiness            # dry run
 *   php artisan analyses:backfill-readiness --apply    # write
 *   php artisan analyses:backfill-readiness --apply --all   # also recompute already-scored rows
 */
class BackfillReadiness extends Command
{
    protected $signature = 'analyses:backfill-readiness {--apply : actually write changes} {--all : recompute even rows that already have a readiness_score}';

    protected $description = 'Backfill the readiness scoring layer on existing analyses';

    public function handle(ReadinessScorer $scorer): int
    {
        $query = Analysis::query()->whereNotNull('overall_score');
        if (! $this->option('all')) {
            $query->whereNull('readiness_score');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill.');
            return self::SUCCESS;
        }

        $apply = $this->option('apply');
        $this->info(($apply ? 'Backfilling' : '[DRY RUN] Would backfill') . " {$total} analyses…");
        $bar = $this->output->createProgressBar($total);

        $query->chunkById(200, function ($chunk) use ($scorer, $apply, $bar) {
            foreach ($chunk as $analysis) {
                $r = $scorer->score($analysis);
                if ($apply) {
                    $analysis->fill([
                        'readiness_score'        => $r['readinessScore'],
                        'readiness_status'       => $r['readinessStatus'],
                        'critical_blocker_count' => $r['criticalBlockerCount'],
                        'high_blocker_count'     => $r['highBlockerCount'],
                    ])->saveQuietly();
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info($apply ? 'Done.' : 'Dry run complete. Re-run with --apply to write.');
        return self::SUCCESS;
    }
}
