<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicService
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
You are a principal engineer doing a deep code review of a GitHub repository. You receive a curated set of files and must produce a JSON report focused on REAL, IMPACTFUL problems — not style nitpicks.

The repo can be in ANY language or framework (Next.js, Nuxt, Rails, Laravel, Django, FastAPI, Spring, .NET, Go, Rust, Elixir, etc.).

# Review procedure (follow in order)

Step 1 — Map the stack. Identify language(s), framework(s), and whether the app has: (a) a database layer (any ORM schema, migrations, model files, or raw SQL), (b) a backend layer (HTTP handlers, route files), (c) a frontend layer (UI components). If a layer is absent, skip it cleanly.

Step 2 — Database (if present). Look for: missing indexes on WHERE/JOIN/ORDER BY/foreign keys; missing unique constraints; N+1 queries (queries inside loops, missing eager-load); over-fetching / missing pagination; unsafe migrations; transaction boundary issues.

Step 3 — Backend. Review for: security (authn/authz gaps, IDOR, unvalidated input, SQL/NoSQL/command injection, secrets in code, weak crypto, missing rate limiting, CSRF, SSRF, open redirects, mass assignment); performance (blocking I/O on hot paths, unbounded loops, missing caching, large payloads, missing pagination); correctness (race conditions, swallowed errors, incorrect status codes).

Step 4 — Frontend (lowest priority). XSS via innerHTML/v-html/dangerouslySetInnerHTML; auth tokens in localStorage; leaked secrets in client bundles; obvious perf cliffs.

# Hard rules
- Only report issues you can ACTUALLY SEE in the provided code. Never invent files, functions, or line numbers.
- Every issue must cite a real file path from the input. Include line number when you can.
- Categorize by primary impact: "security", "performance", "quality".
- Max 10 issues per category. Aim for severity diversity.
- Severity: "critical", "high", "medium", "low", "info".
- Scores 0-100 (100 = flawless). Anchor on severity and count of REAL issues.
- Suggestions must be concrete and actionable.
- Output ONLY the JSON object wrapped in <json>...</json> tags. No prose.

Response schema:
<json>
{
  "overallScore": number,
  "securityScore": number,
  "performanceScore": number,
  "qualityScore": number,
  "issues": {
    "security":    [{ "severity": "critical|high|medium|low|info", "title": "...", "description": "...", "file": "path/from/input", "line": number-or-null, "suggestion": "..." }],
    "performance": [...],
    "quality":     [...]
  }
}
</json>
PROMPT;

    /**
     * @param  array<int, array{path: string, content: string}>  $files
     * @param  array<int, string>  $categories  Subset of catalog keys to focus on. Empty = all.
     */
    public function review(array $files, array $categories = []): array
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) throw new RuntimeException('ANTHROPIC_API_KEY is not set.');

        $userMessage = $this->buildUserMessage($files, $categories);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(600)
            ->post(rtrim(config('services.anthropic.base_url'), '/') . '/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 16_000,
                'system' => self::SYSTEM_PROMPT,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('Anthropic API error: ' . $response->status() . ' ' . $response->body());
        }

        $text = '';
        foreach ($response->json('content') ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') $text .= $block['text'];
        }

        return $this->parseResponse($text);
    }

    protected function buildUserMessage(array $files, array $categories = []): string
    {
        $scopeLine = "";
        if (! empty($categories)) {
            $catalog = config('codereview.categories', []);
            $names = array_values(array_filter(array_map(
                fn ($k) => $catalog[$k]['name'] ?? null,
                $categories,
            )));

            if (! empty($names)) {
                $scopeLine =
                    "\n\n**Review scope** — the buyer paid for these categories only: "
                    . implode(', ', $names) . ". "
                    . "Heavily prioritize issues in those areas. You may still note critical issues outside scope but cap them at 1-2 per other category. "
                    . "Map scope to JSON keys as follows: Security/Backend → `security`, Database → `performance`, Frontend → `quality`. "
                    . "Use the category descriptions in the system prompt to guide what to look for.";
            }
        }

        $inventory = "File inventory (" . count($files) . " files, ordered by review priority):\n"
            . collect($files)->map(fn ($f) => "- {$f['path']}")->implode("\n")
            . $scopeLine
            . "\n\nFirst, identify the stack from the inventory above. Then follow the 4-step review procedure. Return the JSON report now.\n\n";

        $body = collect($files)
            ->map(fn ($f) => "<<<FILE: {$f['path']}>>>\n{$f['content']}\n<<<END FILE>>>")
            ->implode("\n\n");

        return $inventory . $body;
    }

    protected function parseResponse(string $text): array
    {
        if (preg_match('#<json>([\s\S]*?)</json>#i', $text, $m)) {
            $jsonText = trim($m[1]);
        } else {
            $jsonText = trim($text);
        }

        $parsed = json_decode($jsonText, true);
        if ($parsed === null) {
            // try to find first { ... } block
            if (preg_match('/\{[\s\S]*\}/', $jsonText, $m2)) {
                $candidate = $m2[0];
                $candidate = preg_replace('/,(\s*[\]}])/', '$1', $candidate);
                $parsed = json_decode($candidate, true);
            }
        }

        if (! is_array($parsed)) {
            throw new RuntimeException('Analysis response was not valid JSON.');
        }

        $clamp = fn ($n) => max(0, min(100, (int) round(is_numeric($n) ? $n : 70)));
        $allowedSev = ['critical', 'high', 'medium', 'low', 'info'];

        $sanitize = function (?array $arr, string $prefix) use ($allowedSev): array {
            $out = [];
            foreach (array_slice($arr ?? [], 0, 10) as $idx => $it) {
                $sev = $it['severity'] ?? 'medium';
                if (! in_array($sev, $allowedSev, true)) $sev = 'medium';
                $out[] = [
                    'id' => $prefix . ($idx + 1),
                    'severity' => $sev,
                    'title' => (string) ($it['title'] ?? 'Issue'),
                    'description' => (string) ($it['description'] ?? ''),
                    'file' => (string) ($it['file'] ?? ''),
                    'line' => isset($it['line']) && is_numeric($it['line']) ? (int) $it['line'] : null,
                    'suggestion' => (string) ($it['suggestion'] ?? ''),
                ];
            }
            return $out;
        };

        return [
            'overallScore' => $clamp($parsed['overallScore'] ?? null),
            'securityScore' => $clamp($parsed['securityScore'] ?? null),
            'performanceScore' => $clamp($parsed['performanceScore'] ?? null),
            'qualityScore' => $clamp($parsed['qualityScore'] ?? null),
            'issues' => [
                'security' => $sanitize($parsed['issues']['security'] ?? null, 's'),
                'performance' => $sanitize($parsed['issues']['performance'] ?? null, 'p'),
                'quality' => $sanitize($parsed['issues']['quality'] ?? null, 'q'),
            ],
        ];
    }
}
