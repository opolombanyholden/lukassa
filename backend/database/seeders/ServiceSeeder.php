<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'plomberie-fuite' => [
                ['name' => 'Réparation fuite robinet', 'slug' => 'fuite-robinet', 'price' => 8000],
                ['name' => 'Réparation fuite WC', 'slug' => 'fuite-wc', 'price' => 12000],
                ['name' => 'Détection fuite cachée', 'slug' => 'detection-fuite', 'price' => null, 'quote' => true],
            ],
            'plomberie-sanitaires' => [
                ['name' => 'Installation lavabo', 'slug' => 'install-lavabo', 'price' => 25000],
                ['name' => 'Installation douche', 'slug' => 'install-douche', 'price' => 40000],
                ['name' => 'Installation WC', 'slug' => 'install-wc', 'price' => 35000],
            ],
            'plomberie-debouchage' => [
                ['name' => 'Débouchage évier', 'slug' => 'debouchage-evier', 'price' => 6000],
                ['name' => 'Débouchage WC', 'slug' => 'debouchage-wc', 'price' => 8000],
            ],
            'electricite-depannage' => [
                ['name' => 'Diagnostic panne', 'slug' => 'diag-panne-elec', 'price' => 10000],
                ['name' => 'Remplacement disjoncteur', 'slug' => 'remplacement-disjoncteur', 'price' => 15000],
            ],
            'electricite-installation' => [
                ['name' => 'Installation prise', 'slug' => 'install-prise', 'price' => 5000],
                ['name' => 'Installation lustre', 'slug' => 'install-lustre', 'price' => 8000],
            ],
            'menage-regulier' => [
                ['name' => 'Ménage 1 pièce', 'slug' => 'menage-1p', 'price' => 5000],
                ['name' => 'Ménage 3 pièces', 'slug' => 'menage-3p', 'price' => 12000],
                ['name' => 'Ménage 5 pièces+', 'slug' => 'menage-5p', 'price' => 20000],
            ],
            'menage-grand' => [
                ['name' => 'Grand ménage après chantier', 'slug' => 'grand-menage-chantier', 'price' => null, 'quote' => true],
                ['name' => 'Grand ménage déménagement', 'slug' => 'grand-menage-demenage', 'price' => 30000],
            ],
            'coiffure-femme' => [
                ['name' => 'Tissage', 'slug' => 'tissage', 'price' => 25000],
                ['name' => 'Coloration', 'slug' => 'coloration', 'price' => 30000],
                ['name' => 'Soin capillaire', 'slug' => 'soin-capillaire', 'price' => 15000],
                ['name' => 'Brushing', 'slug' => 'brushing', 'price' => 10000],
            ],
            'coiffure-homme' => [
                ['name' => 'Coupe homme', 'slug' => 'coupe-homme', 'price' => 5000],
                ['name' => 'Coupe + barbe', 'slug' => 'coupe-barbe', 'price' => 7000],
            ],
            'tressage' => [
                ['name' => 'Tresses simples', 'slug' => 'tresses-simples', 'price' => 8000],
                ['name' => 'Tresses africaines', 'slug' => 'tresses-africaines', 'price' => 15000],
                ['name' => 'Locks', 'slug' => 'locks', 'price' => 20000],
            ],
            'manucure-pedicure' => [
                ['name' => 'Manucure simple', 'slug' => 'manucure-simple', 'price' => 5000],
                ['name' => 'Pédicure', 'slug' => 'pedicure', 'price' => 7000],
                ['name' => 'Pose vernis semi-perm.', 'slug' => 'vernis-semi-perm', 'price' => 10000],
            ],
            'vidange' => [
                ['name' => 'Vidange standard', 'slug' => 'vidange-standard', 'price' => 15000],
                ['name' => 'Vidange + filtres', 'slug' => 'vidange-filtres', 'price' => 25000],
            ],
            'diagnostic-auto' => [
                ['name' => 'Diagnostic OBD2', 'slug' => 'diag-obd2', 'price' => 10000],
            ],
            'demenagement' => [
                ['name' => 'Déménagement studio', 'slug' => 'demenagement-studio', 'price' => 30000],
                ['name' => 'Déménagement maison', 'slug' => 'demenagement-maison', 'price' => null, 'quote' => true],
            ],
            'livraison-course' => [
                ['name' => 'Livraison course rapide', 'slug' => 'livraison-rapide', 'price' => 3000],
                ['name' => 'Livraison gros volume', 'slug' => 'livraison-volume', 'price' => 8000],
            ],
        ];

        foreach ($catalog as $catSlug => $services) {
            $category = Category::where('slug', $catSlug)->first();
            if (!$category) {
                continue;
            }
            foreach ($services as $svc) {
                Service::updateOrCreate(
                    ['slug' => $svc['slug']],
                    [
                        'category_id' => $category->id,
                        'name' => $svc['name'],
                        'description' => null,
                        'icon' => null,
                        'cover_image' => null,
                        'min_price_estimate' => $svc['price'] ?? null,
                        'is_active' => true,
                        'requires_quote' => $svc['quote'] ?? false,
                    ]
                );
            }
        }
    }
}
