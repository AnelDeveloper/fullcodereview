<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\RedeemCode;
use App\Models\User;
use RuntimeException;

class AnalysisService
{
    public function __construct(
        protected AnthropicService $anthropic,
    ) {}

    public function runForRepo(string $repoSpec, ?User $user = null, ?RedeemCode $code = null, ?string $githubToken = null, array $categories = []): Analysis
    {
        ['owner' => $owner, 'repo' => $repoName] = GithubService::parseRepoUrl($repoSpec);
        $token = $githubToken ?: $user?->github_access_token ?: $code?->github_access_token;
        $github = GithubService::withToken($token);

        $repo = $github->getRepo($owner, $repoName);
        if (($repo['private'] ?? false) && ! $token) {
            throw new RuntimeException('Repository is private. Connect your GitHub account to scan private repos.');
        }
        $branch = $repo['default_branch'] ?? 'main';
        $tree = $github->getTree($owner, $repoName, $branch);

        $candidates = array_filter($tree, [RepoFileSelector::class, 'shouldKeepFile']);
        usort($candidates, fn ($a, $b) => RepoFileSelector::rankFile($a['path']) - RepoFileSelector::rankFile($b['path']));

        $files = [];
        $estimatedTokens = RepoFileSelector::BASE_TOKEN_OVERHEAD;
        $linesAnalyzed = 0;

        foreach ($candidates as $entry) {
            if (count($files) >= GithubService::MAX_FILES) break;
            if ($estimatedTokens >= RepoFileSelector::MAX_INPUT_TOKENS) break;

            $content = $github->fetchFile($owner, $repoName, $branch, $entry['path']);
            if ($content === null) continue;

            $tokens = RepoFileSelector::estimateFileTokens($entry['path'], $content);
            if ($estimatedTokens + $tokens > RepoFileSelector::MAX_INPUT_TOKENS) continue;

            $files[] = ['path' => $entry['path'], 'content' => $content];
            $estimatedTokens += $tokens;
            $linesAnalyzed += substr_count($content, "\n") + 1;
        }

        if (empty($files)) {
            throw new RuntimeException('No reviewable code files found in this repo.');
        }

        $effectiveCategories = $categories ?: ($code?->selected_categories ?? []);

        $review = $this->anthropic->review($files, $effectiveCategories);

        return Analysis::create([
            'user_id' => $user?->id,
            'redeem_code_id' => $code?->id,
            'repo_full_name' => "{$owner}/{$repoName}",
            'repo_url' => "https://github.com/{$owner}/{$repoName}",
            'repo_default_branch' => $branch,
            'files_scanned' => count($files),
            'lines_analyzed' => $linesAnalyzed,
            'overall_score' => $review['overallScore'],
            'security_score' => $review['securityScore'],
            'performance_score' => $review['performanceScore'],
            'quality_score' => $review['qualityScore'],
            'issues_json' => $review['issues'],
            'selected_categories' => $effectiveCategories ?: null,
            'status' => 'completed',
        ]);
    }
}
