<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Wraps the GitHub App auth + API.
 *
 * Two token types in play here:
 *
 *  - User-to-server (OAuth): obtained at install time, used once to read the
 *    user's identity (login, avatar). We don't store it.
 *  - Installation token (server-to-server): minted on demand from a JWT signed
 *    with the App's private key. Used for every repo read. Cached ~50 minutes
 *    per installation (GitHub gives us 60). No refresh dance needed — if the
 *    cache misses or the installation was uninstalled, we just mint again or
 *    surface a "reconnect" error.
 *
 * Everything the App can do is gated by the permissions configured on the App
 * itself (Contents: read, Metadata: read). The consent screen the user sees
 * reflects that.
 */
class GithubService
{
    public const MAX_FILE_BYTES = 40_000;
    public const MAX_FILES = 600;

    public function __construct(public ?string $token = null) {}

    public static function withToken(?string $token): self
    {
        return new self($token);
    }

    /**
     * Returns a service primed with a freshly-minted installation token for the
     * given installation. Throws if the App is misconfigured or the install is
     * gone (user revoked it on github.com).
     */
    public static function forInstallation(string $installationId): self
    {
        return new self(self::mintInstallationToken($installationId));
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

    /**
     * Lists every repo the given installation has access to. Replaces the old
     * "GET /user/repos" call — the installation only sees repos the user
     * selected during install, so this is naturally scoped.
     */
    public function fetchInstallationRepos(): array
    {
        $repos = [];
        for ($page = 1; $page <= 5; $page++) {
            $r = Http::withHeaders($this->ghHeaders())
                ->get('https://api.github.com/installation/repositories', [
                    'per_page' => 100,
                    'page' => $page,
                ]);
            if (! $r->ok()) break;
            $batch = $r->json('repositories') ?? [];
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
        // GitHub returns most-recent installs first; sort by updatedAt to keep
        // the old UI ordering ("recently updated repo first").
        usort($repos, fn ($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
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

    /**
     * Mints an installation access token by signing a short-lived JWT with the
     * App's private key and exchanging it at GitHub. Cached per-installation
     * for slightly less than the 1-hour TTL GitHub gives us.
     */
    public static function mintInstallationToken(string $installationId): string
    {
        return Cache::remember(
            "github:installation_token:{$installationId}",
            now()->addMinutes(50),
            function () use ($installationId) {
                $jwt = self::generateAppJwt();
                $r = Http::withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer ' . $jwt,
                    'User-Agent' => 'codereview-app',
                ])->post("https://api.github.com/app/installations/{$installationId}/access_tokens");

                if (! $r->ok() || ! $r->json('token')) {
                    throw new RuntimeException('Could not mint GitHub installation token. The user may have uninstalled the app — ask them to reconnect.');
                }
                return $r->json('token');
            }
        );
    }

    /**
     * RS256-signed JWT identifying our App. GitHub allows max 10-minute lifetime;
     * we use 9 to be safe on clock skew. No third-party JWT lib needed — RS256
     * is just base64url(header).base64url(payload) signed with openssl_sign.
     */
    private static function generateAppJwt(): string
    {
        $appId = config('services.github.app_id');
        $privateKey = config('services.github.app_private_key');
        if (! $appId || ! $privateKey) {
            throw new RuntimeException('GitHub App is not configured (missing GITHUB_APP_ID or GITHUB_APP_PRIVATE_KEY).');
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        // iat is set 60s in the past to tolerate clock drift between us and GitHub.
        $payload = ['iat' => $now - 60, 'exp' => $now + (9 * 60), 'iss' => (string) $appId];

        $b64 = fn ($data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $signingInput = $b64(json_encode($header)) . '.' . $b64(json_encode($payload));

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign GitHub App JWT — private key may be malformed.');
        }
        return $signingInput . '.' . $b64($signature);
    }
}
