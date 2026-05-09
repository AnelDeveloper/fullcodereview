<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GithubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GitHub OAuth tied to the user account.
 *
 * The login route requires auth so we know which user is connecting; we
 * stash a one-shot state token in cache, hand it to GitHub, and look it
 * up on the callback to find the user. The token is stored on the User
 * row so any subsequent analysis can scan that user's private repos.
 */
class GithubController extends Controller
{
    public function login(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Sign in first.'], 401);

        $state = Str::random(40);
        Cache::put("github:oauth:state:{$state}", $user->id, now()->addMinutes(15));

        $params = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => config('services.github.redirect_uri'),
            'scope' => 'repo read:user user:email',
            'state' => $state,
        ]);

        // Browser-initiated; redirect rather than JSON.
        return redirect("https://github.com/login/oauth/authorize?{$params}");
    }

    public function callback(Request $request, GithubService $github)
    {
        $oauthCode = (string) $request->query('code');
        $state = (string) $request->query('state');

        if (! $oauthCode || ! $state) {
            return redirect(config('app.url') . '/?gh_error=' . urlencode('Missing code or state.'));
        }

        $userId = Cache::pull("github:oauth:state:{$state}");
        if (! $userId) {
            return redirect(config('app.url') . '/?gh_error=' . urlencode('OAuth state expired. Try again.'));
        }

        $user = User::find($userId);
        if (! $user) {
            return redirect(config('app.url') . '/?gh_error=' . urlencode('User not found.'));
        }

        try {
            $token = $github->exchangeOauthCode($oauthCode);
            $svc = GithubService::withToken($token['access_token']);
            $ghUser = $svc->fetchUser();
        } catch (\Throwable $e) {
            return redirect(config('app.url') . '/?gh_error=' . urlencode($e->getMessage()));
        }

        $user->update([
            'github_access_token' => $token['access_token'],
            'github_login' => $ghUser['login'] ?? null,
            'github_avatar_url' => $ghUser['avatar_url'] ?? null,
        ]);

        return redirect(config('app.url') . '/?gh_connected=1');
    }

    public function repos(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->github_access_token) {
            return response()->json(['message' => 'GitHub not connected.', 'code' => 'not_connected'], 400);
        }

        $svc = GithubService::withToken($user->github_access_token);
        try {
            $repos = $svc->fetchUserRepos();
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'login' => $user->github_login,
            'avatarUrl' => $user->github_avatar_url,
            'repos' => $repos,
        ]);
    }

    /**
     * Clears the user's stored GitHub access token (and login/avatar metadata).
     * The OAuth grant on GitHub's side stays in place — the user must remove
     * "Full Code Review" from github.com/settings/applications to revoke it
     * fully — but our app immediately stops being able to read their repos.
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Sign in first.'], 401);

        $user->update([
            'github_access_token' => null,
            'github_login' => null,
            'github_avatar_url' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'GitHub disconnected. To fully revoke access on GitHub\'s side, visit github.com/settings/applications.',
        ]);
    }
}
