<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and accessories',
                'position' => 1,
                'is_active' => true,
                'children' => [
                    ['name' => 'Smartphones', 'slug' => 'smartphones', 'position' => 1],
                    ['name' => 'Laptops', 'slug' => 'laptops', 'position' => 2],
                    ['name' => 'Tablets', 'slug' => 'tablets', 'position' => 3],
                ],
            ],
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'Fashion and apparel',
                'position' => 2,
                'is_active' => true,
                'children' => [
                    ['name' => 'Men', 'slug' => 'men', 'position' => 1],
                    ['name' => 'Women', 'slug' => 'women', 'position' => 2],
                    ['name' => 'Kids', 'slug' => 'kids', 'position' => 3],
                ],
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'description' => 'Home improvement and garden supplies',
                'position' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Sports equipment and fitness',
                'position' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = Category::create($categoryData);

            foreach ($children as $childData) {
                $category->children()->create(array_merge($childData, ['is_active' => true]));
            }
        }
    }
}
