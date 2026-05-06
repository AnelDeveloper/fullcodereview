<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubService
{
    public const MAX_FILE_BYTES = 40_000;
    public const MAX_FILES = 600;

    public function __construct(public ?string $token = null) {}

    public static function withToken(?string $token): self
    {
        return new self($token);
    }

    public function ghHeaders(): array
    {
        $h = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'codereview-app',
        ];
        if ($this->token) $h['Authorization'] = 'Bearer ' . $this->token;
        return $h;
    }

    public function exchangeOauthCode(string $code): array
    {
        $response = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'code' => $code,
                'redirect_uri' => config('services.github.redirect_uri'),
            ]);

        if (! $response->ok() || ! $response->json('access_token')) {
            throw new RuntimeException('GitHub OAuth exchange failed.');
        }
        return [
            'access_token' => $response->json('access_token'),
            'scope' => $response->json('scope', ''),
        ];
    }

    public function fetchUser(): array
    {
        $r = Http::withHeaders($this->ghHeaders())->get('https://api.github.com/user');
        if (! $r->ok()) throw new RuntimeException('Could not load GitHub user.');
        return $r->json();
    }

    public function fetchUserRepos(): array
    {
        $repos = [];
        for ($page = 1; $page <= 5; $page++) {
            $r = Http::withHeaders($this->ghHeaders())
                ->get('https://api.github.com/user/repos', [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);
            if (! $r->ok()) break;
            $batch = $r->json();
            if (empty($batch)) break;
            foreach ($batch as $repo) {
                $repos[] = [
                    'fullName' => $repo['full_name'],
                    'name' => $repo['name'],
                    'description' => $repo['description'],
                    'private' => $repo['private'],
                    'language' => $repo['language'],
                    'defaultBranch' => $repo['default_branch'],
                    'updatedAt' => $repo['updated_at'],
                ];
            }
            if (count($batch) < 100) break;
        }
        return $repos;
    }

    public function getRepo(string $owner, string $repo): array
    {
        $r = Http::withHeaders($this->ghHeaders())
            ->get("https://api.github.com/repos/{$owner}/{$repo}");
        if ($r->status() === 404) throw new RuntimeException('Repository not found or not accessible.');
        if (! $r->ok()) throw new RuntimeException("GitHub repo fetch failed ({$r->status()}).");
        return $r->json();
    }

    public function getTree(string $owner, string $repo, string $branch): array
    {
        $r = Http::withHeaders($this->ghHeaders())
            ->get("https://api.github.com/repos/{$owner}/{$repo}/git/trees/{$branch}", ['recursive' => 1]);
        if (! $r->ok()) throw new RuntimeException("Could not read repo tree ({$r->status()}).");
        $tree = $r->json('tree') ?? [];
        return array_values(array_filter($tree, fn ($e) => ($e['type'] ?? '') === 'blob'));
    }

    public function fetchFile(string $owner, string $repo, string $branch, string $path): ?string
    {
        if ($this->token) {
            $r = Http::withHeaders($this->ghHeaders())
                ->get("https://api.github.com/repos/{$owner}/{$repo}/contents/" . rawurlencode($path), ['ref' => $branch]);
            if (! $r->ok()) return null;
            $data = $r->json();
            if (($data['encoding'] ?? '') !== 'base64' || empty($data['content'])) return null;
            $content = base64_decode($data['content']);
        } else {
            $r = Http::get("https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/" . str_replace('%2F', '/', rawurlencode($path)));
            if (! $r->ok()) return null;
            $content = $r->body();
        }

        if ($content === false || $content === null) return null;
        if (strlen($content) > self::MAX_FILE_BYTES) {
            return substr($content, 0, self::MAX_FILE_BYTES) . "\n// ...truncated";
        }
        return $content;
    }

    public static function parseRepoUrl(string $urlOrFullName): array
    {
        $s = trim($urlOrFullName);
        $s = preg_replace('#^https?://(www\.)?github\.com/#i', '', $s);
        $s = preg_replace('#\.git$#', '', $s);
        $parts = explode('/', $s);
        if (count($parts) < 2 || ! $parts[0] || ! $parts[1]) {
            throw new RuntimeException('Invalid GitHub URL or repo name.');
        }
        return ['owner' => $parts[0], 'repo' => $parts[1]];
    }
}
