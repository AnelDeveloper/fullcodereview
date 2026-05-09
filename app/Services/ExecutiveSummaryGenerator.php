<?php

namespace App\Services;

use App\Models\Analysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generates a business-language executive summary on top of an existing
 * Analysis. Takes the *findings* (not source code) as input and asks Claude
 * to translate them into a non-technical risk briefing for founders /
 * investors / CTOs.
 *
 * Output schema (saved to analyses.executive_summary_json):
 *   {
 *     "plain_english": "...",          // 1-2 paragraphs, no jargon
 *     "business_risks": [
 *       { "title": "...", "impact": "..." }
 *     ],
 *     "top_critical": [                // up to 5
 *       { "title", "category", "severity", "file", "line", "fix_summary" }
 *     ],
 *     "next_steps": ["...", "..."]     // ordered action list
 *   }
 *
 * Cost: 1 small Claude call per scan (~$0.05). Wrap callers in try/catch —
 * a failure here must NEVER fail the underlying scan.
 */
class ExecutiveSummaryGenerator
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
You are a senior technical advisor briefing a non-technical stakeholder (founder, investor, CTO) on the results of a code audit.

You receive: an audit's category scores plus a structured list of findings (each with severity, title, description, file path, line, suggestion). You do NOT see the underlying source code — your job is to translate the findings into a plain-English risk briefing focused on business impact, not implementation detail.

Your tone: calm, concrete, business-grade. Not alarmist. Not jargony. No "AI-flavored" filler ("we believe", "it appears that", "consider"). State the risk, name the cost, point at the next action.

Output ONLY a JSON object wrapped in <json>...</json> tags. No prose. The schema is:

<json>
{
  "plain_english": "1-2 short paragraphs (max 80 words total). What's the overall state of the codebase? What are the 1-2 things a non-technical founder/investor must know? Plain English — no jargon, no file paths.",

  "business_risks": [
    {
      "title": "Short risk name (4-6 words)",
      "impact": "What it costs the business if shipped: lost contracts, compliance failures, downtime cost, reputation, legal exposure. 1 sentence."
    }
  ],

  "top_critical": [
    {
      "title": "Concrete name of the issue (matches a finding from the input)",
      "category": "security|performance|quality",
      "severity": "critical|high|medium|low",
      "file": "exact file path from the input",
      "line": number-or-null,
      "fix_summary": "1 sentence — what to do, in plain English"
    }
  ],

  "next_steps": [
    "Concrete action items in priority order. 1-line each. Plain English. Reference issues by what they fix, not by file path."
  ]
}
</json>

Hard rules:
- Pick the top 5 most-impactful findings for `top_critical` — prioritize criticals, then highs that affect security/data/scale. Never invent issues.
- `business_risks`: 3-5 items max. Lead with money/contract/compliance impact. Examples: "Customer data exposure → SOC2 / ISO failure, lost enterprise contracts, legal exposure." Skip if there are zero risks.
- `next_steps`: 3-7 items. The order should be the order to fix them. Plain English ("Fix the SQL injection in user search" not "Patch SearchController.php:48").
- `plain_english`: a founder reading this once should know exactly how worried to be and what to do next.
PROMPT;

    /**
     * @return array Structured exec summary, OR null on failure (caller must handle).
     */
    public function generate(Analysis $analysis): ?array
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            Log::warning('Executive summary skipped: ANTHROPIC_API_KEY not set');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(120)
                ->post(rtrim(config('services.anthropic.base_url'), '/') . '/messages', [
                    'model' => config('services.anthropic.model'),
                    'max_tokens' => 4_000,
                    'system' => self::SYSTEM_PROMPT,
                    'messages' => [
                        ['role' => 'user', 'content' => $this->buildUserMessage($analysis)],
                    ],
                ]);

            if (! $response->ok()) {
                Log::warning('Anthropic exec-summary API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $text = '';
            foreach ($response->json('content') ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') $text .= $block['text'];
            }

            return $this->parseResponse($text);
        } catch (\Throwable $e) {
            Log::warning('Executive summary generation failed', [
                'analysis_id' => $analysis->id,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function buildUserMessage(Analysis $a): string
    {
        $payload = [
            'repo' => $a->repo_full_name,
            'scores' => [
                'overall'     => $a->overall_score,
                'security'    => $a->security_score,
                'performance' => $a->performance_score,
                'quality'     => $a->quality_score,
                'readiness'   => $a->readiness_score,
            ],
            'readiness_status' => $a->readiness_status,
            'critical_blocker_count' => $a->critical_blocker_count,
            'high_blocker_count'     => $a->high_blocker_count,
            'findings' => $a->issues_json ?? [],
        ];

        return "Audit results to summarize:\n\n"
            . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n\nReturn the structured JSON briefing now.";
    }

    protected function parseResponse(string $text): ?array
    {
        if (preg_match('#<json>([\s\S]*?)</json>#i', $text, $m)) {
            $jsonText = trim($m[1]);
        } else {
            $jsonText = trim($text);
        }

        $parsed = json_decode($jsonText, true);
        if ($parsed === null && preg_match('/\{[\s\S]*\}/', $jsonText, $m2)) {
            $cleaned = preg_replace('/,(\s*[\]}])/', '$1', $m2[0]);
            $parsed = json_decode($cleaned, true);
        }
        if (! is_array($parsed)) {
            Log::warning('Executive summary response was not valid JSON');
            return null;
        }

        // Defensive sanitization — never trust Claude output blindly.
        return [
            'plain_english' => (string) ($parsed['plain_english'] ?? ''),
            'business_risks' => collect($parsed['business_risks'] ?? [])
                ->take(5)
                ->map(fn ($r) => [
                    'title'  => (string) ($r['title'] ?? ''),
                    'impact' => (string) ($r['impact'] ?? ''),
                ])
                ->values()
                ->all(),
            'top_critical' => collect($parsed['top_critical'] ?? [])
                ->take(5)
                ->map(fn ($i) => [
                    'title'       => (string) ($i['title'] ?? ''),
                    'category'    => (string) ($i['category'] ?? 'quality'),
                    'severity'    => (string) ($i['severity'] ?? 'medium'),
                    'file'        => (string) ($i['file'] ?? ''),
                    'line'        => isset($i['line']) && is_numeric($i['line']) ? (int) $i['line'] : null,
                    'fix_summary' => (string) ($i['fix_summary'] ?? ''),
                ])
                ->values()
                ->all(),
            'next_steps' => collect($parsed['next_steps'] ?? [])
                ->take(7)
                ->map(fn ($s) => (string) $s)
                ->values()
                ->all(),
        ];
    }
}
