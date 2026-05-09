<?php

namespace App\Services;

use App\Models\Analysis;

/**
 * Computes a Production Readiness layer on top of an existing Analysis.
 *
 * Pure function: takes the AI scan results (overall_score + issues_json) and
 * produces a readiness score, a launch-status verdict, and blocker counts.
 *
 *   readiness_status:
 *     - 'launch_ready'    → 0 critical, ≤1 high, score ≥ 75
 *     - 'needs_attention' → some highs / mediums but no criticals
 *     - 'blocked'         → at least one critical OR readiness_score < 50
 *
 * No AI / network / DB calls. Safe to run offline, in queues, or for backfill.
 */
class ReadinessScorer
{
    /** Severity weight applied as a penalty against the base score. */
    protected const PENALTY = [
        'critical' => 12,
        'high'     => 5,
        'medium'   => 1.5,
        'low'      => 0.25,
        'info'     => 0,
    ];

    /**
     * @return array{
     *   readinessScore: int,
     *   readinessStatus: string,
     *   criticalBlockerCount: int,
     *   highBlockerCount: int
     * }
     */
    public function score(Analysis $a): array
    {
        $base = (int) ($a->overall_score ?? 0);
        $counts = $this->countBySeverity($a->issues_json ?? []);

        $penalty =
              ($counts['critical'] * self::PENALTY['critical'])
            + ($counts['high']     * self::PENALTY['high'])
            + ($counts['medium']   * self::PENALTY['medium'])
            + ($counts['low']      * self::PENALTY['low']);

        $readinessScore = (int) max(0, min(100, round($base - $penalty)));

        $status = match (true) {
            $counts['critical'] > 0 || $readinessScore < 50 => Analysis::READINESS_BLOCKED,
            $counts['high'] > 1     || $readinessScore < 75 => Analysis::READINESS_NEEDS_ATTENTION,
            default                                          => Analysis::READINESS_LAUNCH_READY,
        };

        return [
            'readinessScore'        => $readinessScore,
            'readinessStatus'       => $status,
            'criticalBlockerCount'  => $counts['critical'],
            'highBlockerCount'      => $counts['high'],
        ];
    }

    /**
     * issues_json shape: ['security' => [...], 'performance' => [...], 'quality' => [...]]
     * Each issue has a 'severity' key.
     */
    protected function countBySeverity(array $issuesJson): array
    {
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];

        foreach ($issuesJson as $bucket) {
            if (! is_array($bucket)) continue;
            foreach ($bucket as $issue) {
                $sev = strtolower((string) ($issue['severity'] ?? 'medium'));
                if (isset($counts[$sev])) {
                    $counts[$sev]++;
                }
            }
        }

        return $counts;
    }

    /** Human-friendly label for a status enum. */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            Analysis::READINESS_LAUNCH_READY    => 'Launch ready',
            Analysis::READINESS_NEEDS_ATTENTION => 'Needs attention',
            Analysis::READINESS_BLOCKED         => 'Blocked',
            default                             => 'Pending',
        };
    }
}
