<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin endpoints — gated by the `reviewer` middleware. Lets a reviewer
 * see all users and toggle the is_reviewer flag on any of them.
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
     * Optional ?search= filters by name/email substring (case-insensitive).
     * Optional ?reviewers_only=1 returns only is_reviewer = true rows.
     */
    public function users(Request $request)
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'is_reviewer', 'email_verified_at', 'created_at'])
            ->orderByDesc('is_reviewer')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ILIKE', "%{$search}%")
                  ->orWhere('name', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->boolean('reviewers_only')) {
            $query->where('is_reviewer', true);
        }

        $users = $query->limit(200)->get()->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'isReviewer' => (bool) $u->is_reviewer,
            'verified'   => (bool) $u->email_verified_at,
            'createdAt'  => $u->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'items' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * POST /api/admin/users/{id}/reviewer
     * Body: { "is_reviewer": true|false }
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

        // Belt-and-suspenders: don't let the current reviewer accidentally
        // demote themselves and lose access to this very page.
        if ($target->id === $self->id && ! $request->boolean('is_reviewer')) {
            return response()->json([
                'message' => "You can't demote yourself. Ask another reviewer.",
            ], 422);
        }

        $target->is_reviewer = $request->boolean('is_reviewer');
        $target->save();

        return response()->json([
            'ok' => true,
            'user' => [
                'id'         => $target->id,
                'name'       => $target->name,
                'email'      => $target->email,
                'isReviewer' => (bool) $target->fresh()->is_reviewer,
            ],
        ]);
    }
}
