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

        \App\Models\LibraryBook::query()
            ->where('icon', 'Heart')
            ->update([
                'title' => 'Meu Diário',
                'color' => 'bg-red-600',
                'text_color' => 'text-white',
                'image' => '/images/heart.png',
            ]);

        \App\Models\LibraryBook::updateOrCreate(
            ['icon' => 'Heart'],
            [
                'title' => 'Meu Diário',
                'color' => 'bg-red-600',
                'text_color' => 'text-white',
                'image' => '/images/heart.png',
            ],
        );
    }
}
