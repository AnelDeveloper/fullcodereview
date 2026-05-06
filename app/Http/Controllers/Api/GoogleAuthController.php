<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle(Request $request)
    {
        $driver = Socialite::driver('google')->stateless();

        if ($request->has('email')) {
            $driver->with(['login_hint' => $request->email]);
        }

        return $driver->redirect();
    }

    public function handleGoogleCallback()
    {
        $appUrl = rtrim(config('app.url'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect("{$appUrl}/login?error=" . urlencode('Google sign-in failed. Please try again.'));
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $user->avatar ?: $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'avatar' => $googleUser->getAvatar(),
                'api_token' => User::generateApiToken(),
            ]);
        }

        if (empty($user->api_token)) {
            $user->api_token = User::generateApiToken();
            $user->save();
        }

        // Land on a tiny SPA route that picks the token + user out of the
        // query string and stores them client-side, then redirects to /
        $payload = [
            'token' => $user->api_token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => ! is_null($user->email_verified_at),
                'avatar' => $user->avatar,
            ],
        ];

        return redirect("{$appUrl}/auth/google/callback?data=" . urlencode(json_encode($payload)));
    }
}
