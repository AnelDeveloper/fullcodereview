<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Admin endpoints — gated by the `reviewer` middleware. Lets a reviewer
 * create / edit / soft-delete / restore users and toggle the is_reviewer
 * + verified flags.
 *
 * NOTE on privilege escalation: any reviewer can promote anyone to
 * reviewer here. For a single-founder app this is fine; if you ever
 * separate admin from reviewer, gate this controller behind a stricter
 * role check.
 */
class AdminController extends Controller
{
    /**
     * GET /api/admin/users
     *
     * ?search=        filter by name/email substring (case-insensitive)
     * ?reviewers_only=1   only is_reviewer = true rows
     * ?include_trashed=1  also include soft-deleted users
     * ?trashed_only=1     only soft-deleted users
     */
    public function users(Request $request)
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'is_reviewer', 'email_verified_at', 'created_at', 'deleted_at'])
            ->orderByDesc('is_reviewer')
            ->orderByDesc('created_at');

        if ($request->boolean('trashed_only')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('include_trashed')) {
            $query->withTrashed();
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ILIKE', "%{$search}%")
                  ->orWhere('name', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->boolean('reviewers_only')) {
            $query->where('is_reviewer', true);
        }

        $users = $query->limit(200)->get()->map(fn ($u) => $this->serialize($u));

        return response()->json([
            'items' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * POST /api/admin/users
     * Body: { name, email, password, is_reviewer?, verified? }
     *
     * Admin-set password (no invite email). Users created here can log in
     * immediately. `verified` defaults to true so the account is usable
     * straight away.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => ['required', 'string', 'max:120'],
            // Unique against non-trashed users only (matches the partial
            // unique index from the soft-delete migration).
            'email'       => ['required', 'email', 'max:190', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password'    => ['required', 'string', 'min:8', 'max:120'],
            'is_reviewer' => ['sometimes', 'boolean'],
            'verified'    => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        $user = new User();
        $user->name              = $request->string('name');
        $user->email             = strtolower(trim($request->string('email')));
        $user->password          = Hash::make($request->string('password'));
        $user->is_reviewer       = $request->boolean('is_reviewer');
        $user->email_verified_at = $request->boolean('verified', true) ? now() : null;
        $user->save();

        return response()->json(['ok' => true, 'user' => $this->serialize($user)], 201);
    }

    /**
     * PATCH /api/admin/users/{id}
     * Body: { name?, email?, password?, is_reviewer?, verified? }
     *
     * All fields optional. Password is only updated if non-empty.
     */
    public function update(Request $request, int $id)
    {
        $target = User::withTrashed()->findOrFail($id);
        $self = $request->user();

        $validator = Validator::make($request->all(), [
            'name'        => ['sometimes', 'string', 'max:120'],
            'email'       => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($target->id)->whereNull('deleted_at')],
            'password'    => ['sometimes', 'nullable', 'string', 'min:8', 'max:120'],
            'is_reviewer' => ['sometimes', 'boolean'],
            'verified'    => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        // Don't let the current reviewer accidentally demote themselves and
        // lose access to this very page.
        if ($target->id === $self->id && $request->has('is_reviewer') && ! $request->boolean('is_reviewer')) {
            return response()->json([
                'message' => "You can't demote yourself. Ask another reviewer.",
            ], 422);
        }

        if ($request->filled('name')) {
            $target->name = $request->string('name');
        }
        if ($request->filled('email')) {
            $target->email = strtolower(trim($request->string('email')));
        }
        if ($request->filled('password')) {
            $target->password = Hash::make($request->string('password'));
        }
        if ($request->has('is_reviewer')) {
            $target->is_reviewer = $request->boolean('is_reviewer');
        }
        if ($request->has('verified')) {
            $target->email_verified_at = $request->boolean('verified') ? ($target->email_verified_at ?? now()) : null;
        }

        $target->save();

        return response()->json(['ok' => true, 'user' => $this->serialize($target->fresh())]);
    }

    /**
     * DELETE /api/admin/users/{id} — soft delete.
     */
    public function destroy(Request $request, int $id)
    {
        $target = User::findOrFail($id);
        $self = $request->user();

        if ($target->id === $self->id) {
            return response()->json(['message' => "You can't delete yourself."], 422);
        }

        $target->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/admin/users/{id}/restore — restore a soft-deleted user.
     *
     * Will fail with 422 if another live user has since registered with the
     * same email (because the partial unique index would block the restore).
     */
    public function restore(Request $request, int $id)
    {
        $target = User::onlyTrashed()->findOrFail($id);

        $collision = User::where('email', $target->email)->exists();
        if ($collision) {
            return response()->json([
                'message' => "Can't restore: another user with this email already exists. Change their email first.",
            ], 422);
        }

        $target->restore();

        return response()->json(['ok' => true, 'user' => $this->serialize($target->fresh())]);
    }

    /**
     * POST /api/admin/users/{id}/reviewer
     * Body: { "is_reviewer": true|false }
     *
     * Kept as a thin wrapper around update() for back-compat with the
     * existing reviewer toggle in the UI.
     */
    public function setReviewer(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'is_reviewer' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        $target = User::findOrFail($id);
        $self = $request->user();

        if ($target->id === $self->id && ! $request->boolean('is_reviewer')) {
            return response()->json([
                'message' => "You can't demote yourself. Ask another reviewer.",
            ], 422);
        }

        $target->is_reviewer = $request->boolean('is_reviewer');
        $target->save();

        return response()->json([
            'ok' => true,
            'user' => $this->serialize($target->fresh()),
        ]);
    }

    private function serialize(User $u): array
    {
        return [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'isReviewer' => (bool) $u->is_reviewer,
            'verified'   => (bool) $u->email_verified_at,
            'createdAt'  => $u->created_at?->toIso8601String(),
            'deletedAt'  => $u->deleted_at?->toIso8601String(),
        ];
    }
}
