<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicService
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
You are a principal engineer / senior QA reviewer doing a deep code review of a GitHub repository. You receive a curated set of files and must produce a JSON report focused on REAL, IMPACTFUL problems — not style nitpicks.

The repo can be in ANY language or framework (Next.js, Nuxt, Rails, Laravel, Django, FastAPI, Spring, .NET, Go, Rust, Elixir, etc.). Apply the universal Engineering Quality Rubric below regardless of stack — translate each principle into the framework's idioms (e.g., "dedicated validation layer" = Form Requests in Laravel, Pydantic models in FastAPI, zod schemas in Next.js, ActiveModel validations in Rails, struct tags + go-playground/validator in Go).

# Review procedure (follow in order)

Step 1 — Map the stack. Identify language(s), framework(s), and whether the app has: (a) a database layer (ORM schema, migrations, model files, or raw SQL), (b) a backend layer (HTTP handlers, route files), (c) a frontend layer (UI components). If a layer is absent, skip it cleanly.

Step 2 — Database (if present). Look for: missing indexes on WHERE/JOIN/ORDER BY/foreign keys; missing unique constraints; N+1 queries (queries inside loops, missing eager-load); over-fetching / missing pagination; unsafe migrations; transaction boundary issues; missing soft-delete or status-based scoping that lets deleted/inactive records leak into reads.

Step 3 — Backend. Apply the Engineering Quality Rubric (below). Flag any deviation that has real impact (security/perf/maintainability), not stylistic differences.

Step 4 — Frontend (lowest priority). XSS via innerHTML/v-html/dangerouslySetInnerHTML; auth tokens in localStorage; leaked secrets in client bundles; obvious perf cliffs.

# Engineering Quality Rubric (applies to any stack)

A high-quality codebase exhibits the separations and patterns below. When you see code that violates these principles, flag it — citing the principle, not the framework name.

**Separation of concerns**
- Request handlers (controllers, route handlers, view functions) are THIN orchestrators: parse input → delegate → return response. Handlers containing DB queries, business logic, or complex branching = quality issue.
- Business logic lives in a dedicated layer (services, use-cases, actions, domain modules).
- Data access is encapsulated (repositories, query objects, ORM scopes) — not duplicated across handlers.

**Validation**
- Input validation lives in dedicated objects/schemas (Form Requests, Pydantic models, zod schemas, JSON Schema, DTO classes), NOT inline in handlers.
- Validation rules are declarative and exhaustive: type, required, format, allowed values, foreign-key existence, ownership scoping (e.g., "this category_id must belong to the current user").
- Scattered `if (!param || param.length < 3)` checks throughout handler code = quality issue.

**Authorization**
- Centralized in policy/permission objects (Policies, Guards, ability classes, middleware), NOT ad-hoc `if (user.id !== resource.user_id)` checks inside handlers.
- Relationship-aware: checks ownership through the chain (tenant → org → project → resource), not just direct ownership. IDOR comes from missing chain checks.
- Authorization runs BEFORE business logic; failures return 401/403 with no information leak.

**Data models**
- Mass-assignment is guarded — explicit allowlist (fillable, attr_accessible) or DTO mapping. Patterns like `Object.assign(model, req.body)`, `Model(**request.json)`, or unrestricted `update($request->all())` = security issue.
- Field types are explicit (casts, decorators, Prisma types, struct tags) — avoid implicit string/JSON columns hiding structured data.
- Soft deletes are used for user-facing data so audit/recovery is possible.
- Relationships are declared on the model, not assembled via raw joins everywhere.

**API response shape**
- Consistent envelope across endpoints (e.g., `{ data, meta, errors }`).
- Serialization in a dedicated layer (Transformers, Resources, Serializers, response DTOs). Handlers should not be hand-shaping JSON.

**Database schema discipline**
- Foreign keys declared with ON DELETE behavior thought through (cascade vs restrict vs set null).
- Indexes on every column used in WHERE / JOIN / ORDER BY / foreign-key lookups.
- Unique constraints where business rules require uniqueness (and composite uniques for tenanted data).
- Standard timestamps (created_at, updated_at) and soft-delete columns where applicable.
- Migrations are reversible and don't lock production tables.

**Background work and side effects**
- Slow operations (email, external API calls, image/file processing, exports, AI calls) run in queued jobs / background workers, NOT inline in the request lifecycle.
- Cross-cutting concerns (audit logs, notifications, search indexing, cache invalidation) use lifecycle hooks (observers, listeners, signals, model events) — not duplicated in every handler.

**Error handling and observability**
- Errors caught at the service layer, logged with structured context (user_id, resource_id, operation, request_id), then translated to HTTP responses by handlers.
- No silent `catch { }` / `except: pass` blocks.
- A real logger is used — not `console.log`, `print`, `dump`, or `var_dump` in production code paths.

**Configuration**
- Secrets and config read via a config abstraction (config(), settings module, env-validation library like envalid/zod-env).
- Direct `process.env.X` / `os.environ['X']` / `getenv()` calls in business code = quality issue. Wrap them at a config layer with type/default/required validation.
- Secrets must never be committed (look for hard-coded API keys, tokens, passwords).

**Routing**
- Routes are versioned (`/v1/...` or equivalent), grouped, and shared middleware (auth, rate-limit, throttle, CORS) is applied at the group level — not duplicated per-route.

**Testing**
- Existence of integration/feature tests for critical paths is itself a quality signal.
- Lack of any test files for non-trivial business logic is a quality issue worth flagging.
- Tests that mock the system under test, snapshot tests with no assertions, or commented-out tests = quality issues.

# Hard rules
- Only report issues you can ACTUALLY SEE in the provided code. Never invent files, functions, or line numbers.
- Every issue must cite a real file path from the input. Include line number when you can.
- Categorize by primary impact: "security", "performance", "quality".
- Max 10 issues per category. Aim for severity diversity.
- Severity: "critical", "high", "medium", "low", "info".
- Scores 0-100 (100 = flawless). Anchor on severity and count of REAL issues. A repo that violates many rubric items in `quality` should not score above ~70 on qualityScore even if there are no critical bugs.
- Suggestions must be concrete and actionable. Reference the rubric principle when relevant (e.g., "Move validation into a dedicated request object — currently scattered inline in the handler, see the Validation principle").
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
