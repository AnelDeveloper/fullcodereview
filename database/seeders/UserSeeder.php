<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@codereview.test'],
            [
                'name' => 'Demo User',
                'password' => 'password',
                'api_token' => User::generateApiToken(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'anel@codereview.test'],
            [
                'name' => 'Anel',
                'password' => 'password',
                'api_token' => User::generateApiToken(),
            ],
        );
    }
}
