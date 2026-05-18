<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GithubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GitHub App connection tied to the user account.
 *
 * Connect flow:
 *   1. login()    - stash a one-shot state token in cache, redirect the user
 *                   to the App's install page (combined install + OAuth auth).
 *   2. callback() - GitHub redirects back with ?code, ?installation_id, ?state.
 *                   We use the code once to read identity (login, avatar) and
 *                   then store the installation_id. All future repo reads mint
 *                   fresh installation tokens from our private key.
 *
 * Why we don't store the user OAuth token: the App's permissions (Contents:
 * read-only) gate everything, and installation tokens are easier to manage
 * server-side — no refresh, no long-lived secret on our DB.
 */
class GithubController extends Controller
{
    public function login(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Sign in first.'], 401);

        $slug = config('services.github.app_slug');
        if (! $slug) {
            return response()->json(['message' => 'GitHub App is not configured.'], 500);
        }

        $state = Str::random(40);
        Cache::put("github:oauth:state:{$state}", $user->id, now()->addMinutes(15));

        // The App's install page handles both the install (granting our App
        // access to selected repos) and OAuth user-authorization in one screen.
        $params = http_build_query(['state' => $state]);
        return redirect("https://github.com/apps/{$slug}/installations/new?{$params}");
    }

    public function callback(Request $request, GithubService $github)
    {
        $oauthCode = (string) $request->query('code');
        $state = (string) $request->query('state');
        $installationId = (string) $request->query('installation_id');

        if (! $oauthCode || ! $state || ! $installationId) {
            return redirect(config('app.url') . '/?gh_error=' . urlencode('Missing parameters from GitHub.'));
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
            // Exchange the OAuth code once to read identity. We don't keep the
            // user token afterwards — repo reads use installation tokens.
            $token = $github->exchangeOauthCode($oauthCode);
            $ghUser = GithubService::withToken($token['access_token'])->fetchUser();
        } catch (\Throwable $e) {
            return redirect(config('app.url') . '/?gh_error=' . urlencode($e->getMessage()));
        }

        $user->update([
            'github_installation_id' => $installationId,
            'github_login' => $ghUser['login'] ?? null,
            'github_avatar_url' => $ghUser['avatar_url'] ?? null,
            'github_access_token' => null,
        ]);

        return redirect(config('app.url') . '/?gh_connected=1');
    }

    public function repos(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->github_installation_id) {
            return response()->json(['message' => 'GitHub not connected.', 'code' => 'not_connected'], 400);
        }

        try {
            $repos = GithubService::forInstallation($user->github_installation_id)->fetchInstallationRepos();
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
     * Clears the user's installation reference on our side. The App install
     * itself stays on GitHub — users have to remove "QodeShark" from
     * github.com/settings/installations to fully revoke. We immediately
     * stop being able to mint tokens for them once the column is null.
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Sign in first.'], 401);

        $user->update([
            'github_installation_id' => null,
            'github_login' => null,
            'github_avatar_url' => null,
            'github_access_token' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'GitHub disconnected. To fully revoke access on GitHub\'s side, visit github.com/settings/installations.',
        ]);
    }
}
