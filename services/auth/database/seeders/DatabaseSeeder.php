<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Local development accounts. Never run against production. */
    public function run(): void
    {
        foreach ([
            ['Admin', 'admin@example.com', User::ROLE_ADMIN],
            ['Customer', 'customer@example.com', User::ROLE_CUSTOMER],
        ] as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'password' => 'password']);
            $user->role = $role;
            $user->save();
        }
    }
}
