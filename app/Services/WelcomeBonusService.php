<?php

namespace App\Services;

use App\Models\SectionSlot;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Grants the launch-promo bonus credit to a newly registered user when
 * they are among the first N registrations.
 *
 * "First N" is measured against User::withTrashed()->count() so a
 * soft-deleted user still counts — the giveaway is about historical
 * registration order, not currently-active accounts.
 *
 * Safe to call multiple times for the same user: it checks first whether
 * the user already has a bonus slot (matched by the synthetic
 * `welcome-bonus-*` stripe_session_id prefix) and skips if so.
 */
class WelcomeBonusService
{
    public function grant(User $user): void
    {
        $cfg = config('codereview.welcome_bonus', []);
        $firstN  = (int) ($cfg['first_n']  ?? 0);
        $category = (string) ($cfg['category'] ?? '');
        $count   = (int) ($cfg['count']    ?? 0);

        if ($firstN <= 0 || $count <= 0 || $category === '') {
            return;
        }

        // The just-created user counts toward the threshold (their row exists
        // by the time this is called). So user #100 still passes.
        $totalRegistered = User::withTrashed()->count();
        if ($totalRegistered > $firstN) {
            return;
        }

        // Idempotency: don't double-grant if a retry or duplicate webhook ever
        // calls this twice for the same user.
        $alreadyGranted = SectionSlot::query()
            ->where('user_id', $user->id)
            ->where('stripe_session_id', 'LIKE', 'welcome-bonus-%')
            ->exists();
        if ($alreadyGranted) {
            return;
        }

        try {
            $now = now();
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'user_id'             => $user->id,
                    'stripe_session_id'   => 'welcome-bonus-'.$user->id.'-'.$now->getTimestamp().'-'.Str::random(6),
                    'amount_cents'        => 0,
                    'category'            => $category,
                    'used_at'             => null,
                    'used_by_analysis_id' => null,
                    'expires_at'          => null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
            SectionSlot::insert($rows);
        } catch (\Throwable $e) {
            // Never let a bonus-grant failure block registration.
            Log::warning('Welcome bonus grant failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
