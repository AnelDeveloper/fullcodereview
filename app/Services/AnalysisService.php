<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\User;
use RuntimeException;

class AnalysisService
{
    /**
     * Each picked category maps to ONE issue bucket in the response schema.
     * Multiple categories can map to the same bucket (Security + Backend → security).
     */
    protected const CATEGORY_TO_BUCKET = [
        'security' => 'security',
        'backend'  => 'security',
        'database' => 'performance',
        'frontend' => 'quality',
    ];

    public function __construct(
        protected AnthropicService $anthropic,
    ) {}

    public function runForRepo(
        string $repoSpec,
        ?User $user = null,
        ?string $githubToken = null,
        array $categories = [],
    ): Analysis {
        ['owner' => $owner, 'repo' => $repoName] = GithubService::parseRepoUrl($repoSpec);
        $token = $githubToken ?: $user?->github_access_token;
        $github = GithubService::withToken($token);

        $repo = $github->getRepo($owner, $repoName);
        if (($repo['private'] ?? false) && ! $token) {
            throw new RuntimeException('Repository is private. Connect your GitHub account to scan private repos.');
        }
        $branch = $repo['default_branch'] ?? 'main';
        $tree = $github->getTree($owner, $repoName, $branch);

        $candidates = array_values(array_filter($tree, [RepoFileSelector::class, 'shouldKeepFile']));
        $sections = ! empty($categories) ? $categories : ['security', 'database', 'backend', 'frontend'];

        $globalTokens = RepoFileSelector::BASE_TOKEN_OVERHEAD;
        $contentCache = [];          // path => content (avoid refetching files reused across sections)
        $allFilesByPath = [];        // path => content (for stats)
        $sectionResults = [];        // category => ['review' => [...], 'fileCount' => n]

        foreach ($sections as $category) {
            if ($globalTokens >= RepoFileSelector::GLOBAL_TOKEN_BUDGET) break;

            $catRanked = $candidates;
            usort(
                $catRanked,
                fn ($a, $b) => RepoFileSelector::rankFileForCategory($a['path'], $category)
                             - RepoFileSelector::rankFileForCategory($b['path'], $category),
            );

            $sectionFiles = [];
            $sectionTokens = 0;

            foreach ($catRanked as $entry) {
                if (count($sectionFiles) >= GithubService::MAX_FILES) break;
                if ($sectionTokens >= RepoFileSelector::PER_SECTION_TOKEN_BUDGET) break;
                if ($globalTokens >= RepoFileSelector::GLOBAL_TOKEN_BUDGET) break;

                $path = $entry['path'];
                if (! array_key_exists($path, $contentCache)) {
                    $contentCache[$path] = $github->fetchFile($owner, $repoName, $branch, $path);
                }
                $content = $contentCache[$path];
                if ($content === null) continue;

                $tokens = RepoFileSelector::estimateFileTokens($path, $content);
                if ($sectionTokens + $tokens > RepoFileSelector::PER_SECTION_TOKEN_BUDGET) continue;
                if ($globalTokens  + $tokens > RepoFileSelector::GLOBAL_TOKEN_BUDGET)  continue;

                $sectionFiles[] = ['path' => $path, 'content' => $content];
                $sectionTokens += $tokens;
                $globalTokens  += $tokens;
                $allFilesByPath[$path] = $content;
            }

            if (empty($sectionFiles)) continue;

            $review = $this->anthropic->review($sectionFiles, [$category]);
            $sectionResults[$category] = ['review' => $review, 'fileCount' => count($sectionFiles)];
        }

        if (empty($sectionResults)) {
            throw new RuntimeException('No reviewable code files found in this repo.');
        }

        $merged = $this->mergeSectionResults($sectionResults);

        $linesAnalyzed = 0;
        foreach ($allFilesByPath as $content) $linesAnalyzed += substr_count($content, "\n") + 1;

        return Analysis::create([
            'user_id' => $user?->id,
            'repo_full_name' => "{$owner}/{$repoName}",
            'repo_url' => "https://github.com/{$owner}/{$repoName}",
            'repo_default_branch' => $branch,
            'files_scanned' => count($allFilesByPath),
            'lines_analyzed' => $linesAnalyzed,
            'overall_score' => $merged['overallScore'],
            'security_score' => $merged['securityScore'],
            'performance_score' => $merged['performanceScore'],
            'quality_score' => $merged['qualityScore'],
            'issues_json' => $merged['issues'],
            'selected_categories' => $categories ?: null,
            'status' => 'completed',
        ]);
    }

    /**
     * Combine per-category review results into a single response shape.
     * Issues are concatenated into the bucket each category maps to (capped at 10/bucket).
     * Bucket scores are averaged across categories that contributed to that bucket.
     */
    protected function mergeSectionResults(array $sectionResults): array
    {
        $issues = ['security' => [], 'performance' => [], 'quality' => []];
        $bucketScores = ['security' => [], 'performance' => [], 'quality' => []];
        $overallScores = [];

        foreach ($sectionResults as $category => $r) {
            $bucket = self::CATEGORY_TO_BUCKET[$category] ?? 'quality';
            $review = $r['review'];

            $issues[$bucket] = array_merge($issues[$bucket], $review['issues'][$bucket] ?? []);

            $bucketScoreKey = $bucket . 'Score';
            if (isset($review[$bucketScoreKey])) $bucketScores[$bucket][] = $review[$bucketScoreKey];
            if (isset($review['overallScore']))  $overallScores[] = $review['overallScore'];
        }

        // Cap each bucket at 10 issues, regenerate stable IDs (s1, p1, q1 …)
        $prefixes = ['security' => 's', 'performance' => 'p', 'quality' => 'q'];
        foreach ($issues as $bucket => $list) {
            $list = array_slice($list, 0, 10);
            foreach ($list as $i => &$issue) {
                $issue['id'] = $prefixes[$bucket] . ($i + 1);
            }
            unset($issue);
            $issues[$bucket] = $list;
        }

        $avg = fn (array $arr) => empty($arr) ? 70 : (int) round(array_sum($arr) / count($arr));

        return [
            'overallScore'     => $avg($overallScores),
            'securityScore'    => $avg($bucketScores['security']),
            'performanceScore' => $avg($bucketScores['performance']),
            'qualityScore'     => $avg($bucketScores['quality']),
            'issues'           => $issues,
        ];
    }
}
