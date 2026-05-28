<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $roots = [
            ['name' => 'Plomberie', 'slug' => 'plomberie', 'icon' => '🔧', 'order_position' => 1],
            ['name' => 'Électricité', 'slug' => 'electricite', 'icon' => '💡', 'order_position' => 2],
            ['name' => 'Ménage', 'slug' => 'menage', 'icon' => '🧹', 'order_position' => 3],
            ['name' => 'Coiffure & Beauté', 'slug' => 'coiffure-beaute', 'icon' => '💇', 'order_position' => 4],
            ['name' => 'Mécanique auto', 'slug' => 'mecanique-auto', 'icon' => '🚗', 'order_position' => 5],
            ['name' => 'Transport & Livraison', 'slug' => 'transport', 'icon' => '🚚', 'order_position' => 6],
            ['name' => 'Cours particuliers', 'slug' => 'cours-particuliers', 'icon' => '📚', 'order_position' => 7],
            ['name' => 'Bâtiment & BTP', 'slug' => 'btp', 'icon' => '🏗️', 'order_position' => 8],
            ['name' => 'Informatique', 'slug' => 'informatique', 'icon' => '💻', 'order_position' => 9],
            ['name' => 'Événementiel', 'slug' => 'evenementiel', 'icon' => '🎉', 'order_position' => 10],
        ];

        $children = [
            'plomberie' => [
                ['name' => 'Réparation fuite', 'slug' => 'plomberie-fuite'],
                ['name' => 'Installation sanitaires', 'slug' => 'plomberie-sanitaires'],
                ['name' => 'Débouchage canalisation', 'slug' => 'plomberie-debouchage'],
            ],
            'electricite' => [
                ['name' => 'Dépannage électrique', 'slug' => 'electricite-depannage'],
                ['name' => 'Installation prise/lustre', 'slug' => 'electricite-installation'],
            ],
            'menage' => [
                ['name' => 'Ménage régulier', 'slug' => 'menage-regulier'],
                ['name' => 'Grand ménage', 'slug' => 'menage-grand'],
            ],
            'coiffure-beaute' => [
                ['name' => 'Coiffure femme', 'slug' => 'coiffure-femme'],
                ['name' => 'Coiffure homme', 'slug' => 'coiffure-homme'],
                ['name' => 'Tressage', 'slug' => 'tressage'],
                ['name' => 'Manucure & Pédicure', 'slug' => 'manucure-pedicure'],
            ],
            'mecanique-auto' => [
                ['name' => 'Vidange', 'slug' => 'vidange'],
                ['name' => 'Diagnostic électronique', 'slug' => 'diagnostic-auto'],
            ],
            'transport' => [
                ['name' => 'Déménagement', 'slug' => 'demenagement'],
                ['name' => 'Livraison course', 'slug' => 'livraison-course'],
            ],
        ];

        $rootMap = [];
        foreach ($roots as $root) {
            $cat = Category::updateOrCreate(
                ['slug' => $root['slug']],
                $root + ['parent_id' => null, 'is_active' => true, 'description' => null]
            );
            $rootMap[$root['slug']] = $cat->id;
        }

        foreach ($children as $parentSlug => $childList) {
            $parentId = $rootMap[$parentSlug];
            foreach ($childList as $position => $child) {
                Category::updateOrCreate(
                    ['slug' => $child['slug']],
                    $child + [
                        'parent_id' => $parentId,
                        'icon' => null,
                        'description' => null,
                        'order_position' => $position + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
