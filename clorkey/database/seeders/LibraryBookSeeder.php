<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LibraryBookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Idempotent: keep a single entry identified by title
        \App\Models\LibraryBook::updateOrCreate(
            ['title' => 'Base de Conhecimento'],
            [
                'color' => 'bg-purple-600',
                'text_color' => 'text-white',
                'icon' => 'Brain',
                'image' => '/images/brain.png', // already present in public/images
            ],
        );
    }
}
