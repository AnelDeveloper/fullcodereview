<?php

namespace App\Console\Commands;

use App\Models\SectionSlot;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant section slots to a user without going through Lemon Squeezy.
 * Useful for local dev (where LS webhooks can't reach codereview.test)
 * or for manual ops (refunds, gifts, support replays).
 *
 * Usage:
 *   php artisan codereview:grant-slots demo@codereview.test security database
 *   php artisan codereview:grant-slots demo@codereview.test security --days=60
 *   php artisan codereview:grant-slots demo@codereview.test security database backend frontend
 */
class GrantSlots extends Command
{
    protected $signature = 'codereview:grant-slots
        {email : Email of the user receiving the slots}
        {categories* : One or more category keys (security, database, backend, frontend)}
        {--days=30 : How many days before the slots expire}';

    protected $description = 'Grant section slots to a user (local dev / manual ops)';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $categories = $this->argument('categories');
        $days = (int) $this->option('days');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User {$email} not found.");
            return self::FAILURE;
        }

        $catalog = array_keys(config('codereview.categories', []));
        $invalid = array_diff($categories, $catalog);
        if (! empty($invalid)) {
            $this->error('Unknown categories: ' . implode(', ', $invalid));
            $this->line('Valid: ' . implode(', ', $catalog));
            return self::FAILURE;
        }

        foreach ($categories as $cat) {
            SectionSlot::create([
                'user_id' => $user->id,
                'category' => $cat,
                'lemon_order_id' => 'manual-' . uniqid(),
                'amount_cents' => 0,
                'expires_at' => now()->addDays($days),
            ]);
        }

        $this->info("Granted " . count($categories) . " slot(s) to {$email}: " . implode(', ', $categories));
        return self::SUCCESS;
    }
}
