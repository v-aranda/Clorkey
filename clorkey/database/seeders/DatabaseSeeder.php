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
        User::withTrashed()->updateOrCreate(
            ['email' => 'aurora@pmbok.sys'],
            [
                'name' => 'Aurora',
                'password' => 'admin123',
                'role' => 'admin',
                'deleted_at' => null,
            ]
        );

        User::withTrashed()->updateOrCreate(
            ['email' => 'prisma@pmbok.sys'],
            [
                'name' => 'Prisma',
                'password' => 'user123',
                'role' => 'user',
                'deleted_at' => null,
            ]
        );
    }
}
