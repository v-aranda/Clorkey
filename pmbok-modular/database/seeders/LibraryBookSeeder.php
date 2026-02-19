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
        \App\Models\LibraryBook::create([
            'title' => 'Base de Conhecimento',
            'color' => 'bg-purple-600',
            'text_color' => 'text-white',
            'icon' => 'Brain',
            'image' => null,
        ]);
    }
}
