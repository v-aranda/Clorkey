<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Aurora',
            'email' => 'aurora@pmbok.sys',
            'password' => 'admin123',
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Prisma',
            'email' => 'prisma@pmbok.sys',
            'password' => 'user123',
            'role' => 'user',
        ]);
    }
}
