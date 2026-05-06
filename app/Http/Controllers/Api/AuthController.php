<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => strtolower($request->input('email')),
            'password' => $request->input('password'),
            'api_token' => User::generateApiToken(),
        ]);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('Could not send verification email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }

        return response()->json([
            'token' => $user->api_token,
            'user' => $this->presentUser($user),
            'credits' => 0,
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', strtolower($request->input('email')))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], 401);
        }

        if (empty($user->api_token)) {
            $user->api_token = User::generateApiToken();
            $user->save();
        }

        return response()->json([
            'token' => $user->api_token,
            'user' => $this->presentUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->api_token = User::generateApiToken();
            $user->save();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $credits = $user ? CreditsController::availableQuery($user)->count() : 0;

        return response()->json([
            'user' => $user ? $this->presentUser($user) : null,
            'credits' => $credits,
        ]);
    }

    /**
     * Verify endpoint hit from the email link. The route is `signed` middleware
     * protected, so a valid signature proves the link is authentic. We look up
     * the user from the URL `id` param and compare the `hash` against sha1 of
     * their current email — that way an email change invalidates old links.
     */
    public function verify(Request $request, int $id, string $hash)
    {
        $appUrl = rtrim(config('app.url'), '/');
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect("{$appUrl}/?verified=invalid");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect("{$appUrl}/?verified=already");
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect("{$appUrl}/?verified=1");
    }

    /**
     * Resend the verification email for the authenticated user.
     */
    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified.'], 200);
        }
        $user->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification email sent.']);
    }

    protected function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'emailVerified' => ! is_null($user->email_verified_at),
            'githubLogin' => $user->github_login,
            'githubAvatarUrl' => $user->github_avatar_url,
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
