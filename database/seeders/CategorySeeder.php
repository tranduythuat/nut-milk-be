<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Sữa hạt',
            'slug' => 'sua-hat',
            'description' => 'Các loại sữa hạt nguyên chất, thơm ngon và bổ dưỡng.',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
