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

        // No token returned — user must verify their email and then log in.
        return response()->json([
            'message' => 'Account created. Check your email to verify before signing in.',
            'email' => $user->email,
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

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Please confirm your email before signing in. Check your inbox for the verification link.',
                'code' => 'unverified_email',
                'email' => $user->email,
            ], 403);
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

        $byCategory = $user ? AnalysisController::sectionBreakdown($user) : [];
        $credits = array_sum($byCategory);

        return response()->json([
            'user' => $user ? $this->presentUser($user) : null,
            'credits' => $credits,
            'sections' => $byCategory,
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
     * Resend the verification email. Public + throttled because users on the
     * post-register check-email page have no auth token yet.
     *
     * Always returns 200 with a generic message — never reveal whether an
     * email is registered (avoids email enumeration).
     */
    public function resendVerification(Request $request)
    {
        $email = strtolower((string) $request->input('email', ''));
        if ($email === '') {
            return response()->json(['message' => 'Email is required.'], 422);
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('Could not resend verification email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            }
        }

        return response()->json(['message' => 'If that email is registered and unverified, a new link is on its way.']);
    }

    /**
     * PUT /me/profile — update display name and/or email.
     * If the email changes, we re-verify (clear email_verified_at and
     * fire the verification mail again).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);

        $validator = Validator::make($request->all(), [
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,' . $user->id],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $newEmail = strtolower($request->input('email'));
        $emailChanged = $newEmail !== $user->email;

        $user->name = $request->input('name');
        if ($emailChanged) {
            $user->email = $newEmail;
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('Could not send re-verification email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            }
        }

        return response()->json([
            'ok' => true,
            'emailChanged' => $emailChanged,
            'message' => $emailChanged
                ? 'Profile updated. Please verify your new email address — we sent you a confirmation link.'
                : 'Profile updated.',
            'user' => $this->presentUser($user->fresh()),
        ]);
    }

    /**
     * POST /me/password — change password (requires current password).
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors'  => ['current_password' => ['Current password is incorrect.']],
            ], 422);
        }

        $user->password = $request->input('password');
        // Rotate the API token so any other sessions are kicked out.
        $user->api_token = User::generateApiToken();
        $user->save();

        return response()->json([
            'ok' => true,
            'message' => 'Password updated. Other sessions have been signed out.',
            'token' => $user->api_token,
        ]);
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
            'isReviewer' => (bool) ($user->is_reviewer ?? false),
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
