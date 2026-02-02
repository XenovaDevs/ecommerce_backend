<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Run CategorySeeder first.');
            return;
        }

        // Create featured products
        Product::factory()
            ->count(5)
            ->featured()
            ->create([
                'category_id' => $categories->random()->id,
            ]);

        // Create regular products
        Product::factory()
            ->count(20)
            ->create([
                'category_id' => $categories->random()->id,
            ]);

        // Create some out of stock products
        Product::factory()
            ->count(3)
            ->outOfStock()
            ->create([
                'category_id' => $categories->random()->id,
            ]);
    }
}
