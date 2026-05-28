# LUKASSA Sprint 2 — Catalogue & Recherche Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer le catalogue public LUKASSA (catégories tree + services), la gestion d'offres prestataire (CRUD + IDOR protection), et la recherche géolocalisée de prestataires (Haversine SQL + filtres rating/prix/sort/pagination).

**Architecture:** Laravel 12, 3 nouveaux models (Category/Service/ProviderService) UUID, 4 controllers (Public/Category, Public/Service, Public/Provider, Provider/Service), 1 service (ProviderSearchService Haversine), seeders Gabon-flavored, cache categories:tree avec Observer. Pattern Resource Laravel pour éviter le leak de champs sensibles.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL 16 (Haversine via `acos/cos/sin/radians`), Redis cache, Sanctum, PHPUnit 11.

**Référence spec :** `docs/superpowers/specs/2026-05-28-sprint2-catalog-search-design.md`
**Prérequis :** Sprint 1 ✅ (auth, ApiException, ApiResponse, User/Profile models, 70 tests).

---

## Structure de fichiers cible

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Public/
│   │   │   │   ├── CategoryController.php       ← Task 7
│   │   │   │   ├── ServiceController.php        ← Task 8
│   │   │   │   └── ProviderController.php       ← Task 10
│   │   │   └── Provider/
│   │   │       └── ServiceController.php        ← Task 11
│   │   ├── Requests/Api/V1/
│   │   │   ├── Provider/
│   │   │   │   ├── StoreProviderServiceRequest.php   ← Task 11
│   │   │   │   └── UpdateProviderServiceRequest.php  ← Task 11
│   │   │   └── Public/
│   │   │       └── SearchProvidersRequest.php        ← Task 10
│   │   ├── Resources/Api/V1/
│   │   │   ├── CategoryResource.php             ← Task 6
│   │   │   ├── ServiceResource.php              ← Task 6
│   │   │   ├── ProviderServiceResource.php      ← Task 6
│   │   │   ├── PublicProviderResource.php       ← Task 6
│   │   │   └── ProviderSearchResultResource.php ← Task 6
│   │   └── Responses/
│   │       └── ApiResponse.php                  ← Task 1 (modify, add paginated())
│   ├── Models/
│   │   ├── Category.php                         ← Task 2
│   │   ├── Service.php                          ← Task 3
│   │   ├── ProviderService.php                  ← Task 4
│   │   └── User.php                             ← Task 4 (add providerServices relation)
│   ├── Observers/
│   │   └── CategoryObserver.php                 ← Task 12
│   ├── Providers/
│   │   └── AppServiceProvider.php               ← Task 12 (register observer)
│   ├── Services/
│   │   └── ProviderSearch/
│   │       └── ProviderSearchService.php        ← Task 9
│   └── Exceptions/
│       └── ApiException.php                     ← Task 1 (modify, add factories)
├── database/
│   ├── factories/
│   │   ├── CategoryFactory.php                  ← Task 2
│   │   ├── ServiceFactory.php                   ← Task 3
│   │   └── ProviderServiceFactory.php           ← Task 4
│   └── seeders/
│       ├── DatabaseSeeder.php                   ← Task 5 (modify, call CategorySeeder + ServiceSeeder)
│       ├── CategorySeeder.php                   ← Task 5
│       └── ServiceSeeder.php                    ← Task 5
├── routes/
│   └── api.php                                  ← Task 13 (modify, add public + provider routes)
└── tests/
    ├── Unit/
    │   ├── Services/ProviderSearch/
    │   │   └── ProviderSearchServiceTest.php    ← Task 9
    │   └── Resources/
    │       └── CategoryWithChildrenTest.php     ← Task 7
    └── Feature/Api/V1/
        ├── Public/
        │   ├── CategoriesTest.php               ← Task 7
        │   ├── ServicesTest.php                 ← Task 8
        │   └── ProviderSearchTest.php           ← Task 10
        └── Provider/
            └── ServicesTest.php                 ← Task 11
```

---

## Task 1 : ApiException factories + ApiResponse::paginated

**Files:**
- Modify: `backend/app/Exceptions/ApiException.php`
- Modify: `backend/app/Http/Responses/ApiResponse.php`
- Test: `backend/tests/Unit/Exceptions/ApiExceptionTest.php` (extend existing)

- [ ] **Step 1.1 : Ajouter les tests pour les nouvelles factories**

Open `backend/tests/Unit/Exceptions/ApiExceptionTest.php`. Append new tests inside the existing class:

```php
public function test_account_unauthorized_factory(): void
{
    $e = ApiException::accountUnauthorized();
    $this->assertSame('AUTH_008', $e->errorCode);
    $this->assertSame(403, $e->httpStatus);
}

public function test_category_not_found_factory(): void
{
    $e = ApiException::categoryNotFound();
    $this->assertSame('CATALOG_002', $e->errorCode);
    $this->assertSame(422, $e->httpStatus);
}

public function test_service_not_found_factory(): void
{
    $e = ApiException::serviceNotFound();
    $this->assertSame('CATALOG_003', $e->errorCode);
    $this->assertSame(422, $e->httpStatus);
}
```

- [ ] **Step 1.2 : Run tests (must fail)**

Run: `cd backend && php artisan test tests/Unit/Exceptions/ApiExceptionTest.php 2>&1 | tail -10`
Expected: 3 new tests fail (factories don't exist).

- [ ] **Step 1.3 : Add factories to ApiException.php**

Append inside `class ApiException` (after `invalidAccountType()`):

```php
public static function accountUnauthorized(): self
{
    return new self('AUTH_008', 403, 'Action réservée aux prestataires.');
}

public static function categoryNotFound(): self
{
    return new self('CATALOG_002', 422, 'Catégorie introuvable.');
}

public static function serviceNotFound(): self
{
    return new self('CATALOG_003', 422, 'Service introuvable.');
}
```

- [ ] **Step 1.4 : Add `paginated()` helper to ApiResponse**

Modify `backend/app/Http/Responses/ApiResponse.php`. Add `use` and method:

```php
<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, callable $mapper): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}
```

- [ ] **Step 1.5 : Run tests (must pass)**

Run: `cd backend && php artisan test tests/Unit/Exceptions/ApiExceptionTest.php 2>&1 | tail -10`
Expected: 6 tests pass (3 existing + 3 new).

- [ ] **Step 1.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Exceptions/ApiException.php backend/app/Http/Responses/ApiResponse.php backend/tests/Unit/Exceptions/ApiExceptionTest.php && \
  git commit -m "feat(sprint-2): add AUTH_008 + CATALOG_002/003 factories + ApiResponse::paginated

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2 : Model Category + factory

**Files:**
- Create: `backend/app/Models/Category.php`
- Create: `backend/database/factories/CategoryFactory.php`
- Test: `backend/tests/Unit/Models/CategoryTest.php`

- [ ] **Step 2.1 : Write tests**

Create `backend/tests/Unit/Models/CategoryTest.php` :

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $cat = Category::factory()->create();
        $this->assertSame(36, strlen($cat->id));
    }

    public function test_slug_must_be_unique(): void
    {
        Category::factory()->create(['slug' => 'plomberie']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Category::factory()->create(['slug' => 'plomberie']);
    }

    public function test_parent_children_relations(): void
    {
        $parent = Category::factory()->create(['name' => 'Plomberie', 'slug' => 'plomberie']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'plomberie-fuite']);

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertSame($child->id, $parent->children->first()->id);
    }

    public function test_route_key_is_slug(): void
    {
        $cat = Category::factory()->create(['slug' => 'electricite']);
        $this->assertSame('slug', $cat->getRouteKeyName());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $cat = Category::factory()->create(['is_active' => 1]);
        $this->assertSame(true, $cat->fresh()->is_active);
    }
}
```

- [ ] **Step 2.2 : Run (fail)**

Run: `cd backend && php artisan test tests/Unit/Models/CategoryTest.php 2>&1 | tail -10`
Expected: errors `Class "App\Models\Category" not found`.

- [ ] **Step 2.3 : Create Category model**

Create `backend/app/Models/Category.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'description',
        'order_position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_position' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

- [ ] **Step 2.4 : Create CategoryFactory**

Create `backend/database/factories/CategoryFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(6),
            'icon' => fake()->randomElement(['🔧', '💡', '🧹', '💇', '🚗']),
            'description' => fake()->sentence(),
            'order_position' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

- [ ] **Step 2.5 : Run (pass)**

Run: `cd backend && php artisan test tests/Unit/Models/CategoryTest.php 2>&1 | tail -10`
Expected: 5 tests pass.

- [ ] **Step 2.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Models/Category.php backend/database/factories/CategoryFactory.php backend/tests/Unit/Models/CategoryTest.php && \
  git commit -m "feat(sprint-2): Category model + factory (HasUuids, parent/children, slug routing)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3 : Model Service + factory

**Files:**
- Create: `backend/app/Models/Service.php`
- Create: `backend/database/factories/ServiceFactory.php`
- Test: `backend/tests/Unit/Models/ServiceTest.php`

- [ ] **Step 3.1 : Tests**

Create `backend/tests/Unit/Models/ServiceTest.php` :

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $service = Service::factory()->create();
        $this->assertSame(36, strlen($service->id));
    }

    public function test_belongs_to_category(): void
    {
        $cat = Category::factory()->create();
        $service = Service::factory()->create(['category_id' => $cat->id]);
        $this->assertSame($cat->id, $service->category->id);
    }

    public function test_route_key_is_slug(): void
    {
        $service = Service::factory()->create(['slug' => 'tissage']);
        $this->assertSame('slug', $service->getRouteKeyName());
    }

    public function test_requires_quote_cast(): void
    {
        $service = Service::factory()->create(['requires_quote' => 1]);
        $this->assertTrue($service->fresh()->requires_quote);
    }
}
```

- [ ] **Step 3.2 : Run (fail)**

- [ ] **Step 3.3 : Create Service model**

Create `backend/app/Models/Service.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'icon',
        'cover_image',
        'min_price_estimate',
        'is_active',
        'requires_quote',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_quote' => 'boolean',
        'min_price_estimate' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function providerServices(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

- [ ] **Step 3.4 : Create ServiceFactory**

Create `backend/database/factories/ServiceFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(6),
            'description' => fake()->paragraph(),
            'icon' => null,
            'cover_image' => null,
            'min_price_estimate' => fake()->numberBetween(5000, 50000),
            'is_active' => true,
            'requires_quote' => false,
        ];
    }

    public function quoteOnly(): static
    {
        return $this->state(fn () => [
            'requires_quote' => true,
            'min_price_estimate' => null,
        ]);
    }
}
```

- [ ] **Step 3.5 : Run (pass) — 4 tests**

- [ ] **Step 3.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Models/Service.php backend/database/factories/ServiceFactory.php backend/tests/Unit/Models/ServiceTest.php && \
  git commit -m "feat(sprint-2): Service model + factory (belongsTo Category, slug routing)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4 : Model ProviderService + factory + User::providerServices relation

**Files:**
- Create: `backend/app/Models/ProviderService.php`
- Create: `backend/database/factories/ProviderServiceFactory.php`
- Modify: `backend/app/Models/User.php` (add providerServices relation)
- Test: `backend/tests/Unit/Models/ProviderServiceTest.php`

- [ ] **Step 4.1 : Tests**

Create `backend/tests/Unit/Models/ProviderServiceTest.php` :

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $ps = ProviderService::factory()->create();
        $this->assertSame(36, strlen($ps->id));
    }

    public function test_belongs_to_provider_and_service(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        $ps = ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
        ]);

        $this->assertSame($provider->id, $ps->provider->id);
        $this->assertSame($service->id, $ps->service->id);
    }

    public function test_unique_constraint_provider_service(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_user_provider_services_relation(): void
    {
        $provider = User::factory()->prestataire()->create();
        ProviderService::factory()->count(3)->create(['provider_id' => $provider->id]);

        $this->assertCount(3, $provider->providerServices);
    }

    public function test_is_available_and_price_amount_casts(): void
    {
        $ps = ProviderService::factory()->create(['is_available' => 1, 'price_amount' => '12000']);
        $fresh = $ps->fresh();
        $this->assertTrue($fresh->is_available);
        $this->assertSame(12000, $fresh->price_amount);
    }
}
```

- [ ] **Step 4.2 : Run (fail)**

- [ ] **Step 4.3 : Create ProviderService model**

Create `backend/app/Models/ProviderService.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderService extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'provider_id',
        'service_id',
        'price_model',
        'price_amount',
        'custom_description',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price_amount' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
```

- [ ] **Step 4.4 : Create ProviderServiceFactory**

Create `backend/database/factories/ProviderServiceFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderServiceFactory extends Factory
{
    protected $model = ProviderService::class;

    public function definition(): array
    {
        return [
            'provider_id' => User::factory()->prestataire(),
            'service_id' => Service::factory(),
            'price_model' => fake()->randomElement(['fixed', 'hourly', 'quote']),
            'price_amount' => fake()->numberBetween(5000, 50000),
            'custom_description' => fake()->sentence(),
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
```

- [ ] **Step 4.5 : Add User::providerServices relation**

In `backend/app/Models/User.php`, add `use Illuminate\Database\Eloquent\Relations\HasMany;` if missing, and add at the end of the class (after `profile()` method):

```php
public function providerServices(): HasMany
{
    return $this->hasMany(ProviderService::class, 'provider_id');
}
```

- [ ] **Step 4.6 : Run (pass) — 5 tests**

Run: `cd backend && php artisan test tests/Unit/Models/ProviderServiceTest.php 2>&1 | tail -10`
Expected: 5 tests pass.

- [ ] **Step 4.7 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Models backend/database/factories/ProviderServiceFactory.php backend/tests/Unit/Models/ProviderServiceTest.php && \
  git commit -m "feat(sprint-2): ProviderService model + factory + User::providerServices relation

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5 : Seeders Gabon (Categories + Services)

**Files:**
- Create: `backend/database/seeders/CategorySeeder.php`
- Create: `backend/database/seeders/ServiceSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

Note : seeders are not TDD-driven directly. We verify via running `db:seed` after creation.

- [ ] **Step 5.1 : Create CategorySeeder**

Create `backend/database/seeders/CategorySeeder.php` :

```php
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
        foreach ($roots as $position => $root) {
            $cat = Category::updateOrCreate(
                ['slug' => $root['slug']],
                $root + ['parent_id' => null, 'is_active' => true]
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
```

- [ ] **Step 5.2 : Create ServiceSeeder**

Create `backend/database/seeders/ServiceSeeder.php` :

```php
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
```

- [ ] **Step 5.3 : Update DatabaseSeeder**

Replace `backend/database/seeders/DatabaseSeeder.php` content (keep namespace, replace body) :

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
```

- [ ] **Step 5.4 : Run seeders against dev DB**

Run:
```bash
cd backend && php artisan db:seed --class=CategorySeeder 2>&1 | tail -3
php artisan db:seed --class=ServiceSeeder 2>&1 | tail -3
```

Expected: each completes without error.

- [ ] **Step 5.5 : Verify counts in dev DB**

Run:
```bash
docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "SELECT count(*) FROM categories;"
docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "SELECT count(*) FROM services;"
```

Expected:
- categories : 25 (10 root + 15 children)
- services : ~40-45 (depending on the catalog above)

- [ ] **Step 5.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/database/seeders && \
  git commit -m "feat(sprint-2): CategorySeeder + ServiceSeeder (catalogue Gabon ~25 cat + ~40 svc)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6 : Resources (Category, Service, ProviderService, PublicProvider, ProviderSearchResult)

**Files:**
- Create: `backend/app/Http/Resources/Api/V1/CategoryResource.php`
- Create: `backend/app/Http/Resources/Api/V1/ServiceResource.php`
- Create: `backend/app/Http/Resources/Api/V1/ProviderServiceResource.php`
- Create: `backend/app/Http/Resources/Api/V1/PublicProviderResource.php`
- Create: `backend/app/Http/Resources/Api/V1/ProviderSearchResultResource.php`

Note : Resources are mostly transcription. Tests covered through feature tests in later tasks.

- [ ] **Step 6.1 : Create CategoryResource**

Create `backend/app/Http/Resources/Api/V1/CategoryResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'order_position' => $this->order_position,
        ];
    }
}
```

- [ ] **Step 6.2 : Create ServiceResource**

Create `backend/app/Http/Resources/Api/V1/ServiceResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'cover_image' => $this->cover_image,
            'min_price_estimate' => $this->min_price_estimate,
            'requires_quote' => (bool) $this->requires_quote,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ]),
        ];
    }
}
```

- [ ] **Step 6.3 : Create ProviderServiceResource**

Create `backend/app/Http/Resources/Api/V1/ProviderServiceResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service' => $this->whenLoaded('service', fn () => (new ServiceResource($this->service))->toArray($request)),
            'price_model' => $this->price_model,
            'price_amount' => $this->price_amount,
            'custom_description' => $this->custom_description,
            'is_available' => (bool) $this->is_available,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6.4 : Create PublicProviderResource**

Create `backend/app/Http/Resources/Api/V1/PublicProviderResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        return [
            'id' => $this->id,
            'firstname' => $profile?->firstname,
            'lastname' => $profile?->lastname,
            'bio' => $profile?->bio,
            'city' => $profile?->city,
            'country' => $profile?->country,
            'average_rating' => $profile ? (float) $profile->average_rating : 0.0,
            'total_reviews' => $profile?->total_reviews ?? 0,
            'intervention_radius_km' => $profile?->intervention_radius_km,
            'services' => $this->whenLoaded('providerServices', function () use ($request) {
                return $this->providerServices
                    ->where('is_available', true)
                    ->map(fn ($ps) => (new ProviderServiceResource($ps))->toArray($request))
                    ->values()
                    ->all();
            }),
        ];
    }
}
```

- [ ] **Step 6.5 : Create ProviderSearchResultResource**

Create `backend/app/Http/Resources/Api/V1/ProviderSearchResultResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderSearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $matchingOffer = $this->providerServices->first();

        return [
            'id' => $this->id,
            'firstname' => $profile?->firstname,
            'lastname' => $profile?->lastname,
            'city' => $profile?->city,
            'average_rating' => $profile ? (float) $profile->average_rating : 0.0,
            'total_reviews' => $profile?->total_reviews ?? 0,
            'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
            'service' => $matchingOffer ? [
                'id' => $matchingOffer->service_id,
                'name' => $matchingOffer->service?->name,
                'price_amount' => $matchingOffer->price_amount,
                'price_model' => $matchingOffer->price_model,
            ] : null,
        ];
    }
}
```

- [ ] **Step 6.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Resources/Api/V1/CategoryResource.php backend/app/Http/Resources/Api/V1/ServiceResource.php backend/app/Http/Resources/Api/V1/ProviderServiceResource.php backend/app/Http/Resources/Api/V1/PublicProviderResource.php backend/app/Http/Resources/Api/V1/ProviderSearchResultResource.php && \
  git commit -m "feat(sprint-2): 5 Resources (Category, Service, ProviderService, PublicProvider, SearchResult)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7 : Public CategoryController (index, tree, services) + tests

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Public/CategoryController.php`
- Test: `backend/tests/Feature/Api/V1/Public/CategoriesTest.php`
- Test: `backend/tests/Unit/Resources/CategoryWithChildrenTest.php`

- [ ] **Step 7.1 : Write feature tests**

Create `backend/tests/Feature/Api/V1/Public/CategoriesTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_index_returns_active_categories(): void
    {
        Category::factory()->create(['name' => 'Plomberie', 'is_active' => true]);
        Category::factory()->inactive()->create(['name' => 'Hidden']);

        $response = $this->getJson('/api/v1/public/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Plomberie');
    }

    public function test_tree_returns_hierarchical_structure(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Plomberie', 'slug' => 'plomberie', 'parent_id' => null,
        ]);
        Category::factory()->create([
            'name' => 'Fuite', 'slug' => 'fuite', 'parent_id' => $parent->id,
        ]);

        $response = $this->getJson('/api/v1/public/categories/tree');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.slug', 'plomberie')
            ->assertJsonPath('data.0.children.0.slug', 'fuite');
    }

    public function test_tree_excludes_inactive_categories(): void
    {
        Category::factory()->inactive()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => null]);

        $response = $this->getJson('/api/v1/public/categories/tree');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_tree_result_is_cached(): void
    {
        Category::factory()->create(['parent_id' => null]);
        $this->getJson('/api/v1/public/categories/tree')->assertStatus(200);
        $this->assertTrue(Cache::has('categories:tree'));
    }

    public function test_services_in_category_returns_paginated_list(): void
    {
        $cat = Category::factory()->create(['slug' => 'plomberie']);
        Service::factory()->count(3)->create(['category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/public/categories/plomberie/services');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_services_in_unknown_category_returns_404(): void
    {
        $this->getJson('/api/v1/public/categories/nonexistent/services')
            ->assertStatus(404);
    }
}
```

- [ ] **Step 7.2 : Write unit test for tree builder**

Create `backend/tests/Unit/Resources/CategoryWithChildrenTest.php` :

```php
<?php

namespace Tests\Unit\Resources;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryWithChildrenTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_tree_returns_only_root_with_nested_children(): void
    {
        $parent = Category::factory()->create([
            'slug' => 'plomberie', 'parent_id' => null, 'order_position' => 1,
        ]);
        $child1 = Category::factory()->create([
            'slug' => 'fuite', 'parent_id' => $parent->id, 'order_position' => 1,
        ]);
        $child2 = Category::factory()->create([
            'slug' => 'sanitaires', 'parent_id' => $parent->id, 'order_position' => 2,
        ]);

        $all = Category::all();

        // We'll add this method in the controller file inline
        // (using App\Http\Controllers\Api\V1\Public\CategoryController::buildTree)
        $tree = \App\Http\Controllers\Api\V1\Public\CategoryController::buildTree($all);

        $this->assertCount(1, $tree);
        $this->assertSame('plomberie', $tree[0]['slug']);
        $this->assertCount(2, $tree[0]['children']);
        $this->assertSame('fuite', $tree[0]['children'][0]['slug']);
        $this->assertSame('sanitaires', $tree[0]['children'][1]['slug']);
    }

    public function test_build_tree_respects_order_position(): void
    {
        Category::factory()->create(['slug' => 'b', 'parent_id' => null, 'order_position' => 2]);
        Category::factory()->create(['slug' => 'a', 'parent_id' => null, 'order_position' => 1]);

        $tree = \App\Http\Controllers\Api\V1\Public\CategoryController::buildTree(Category::all());

        $this->assertSame('a', $tree[0]['slug']);
        $this->assertSame('b', $tree[1]['slug']);
    }
}
```

- [ ] **Step 7.3 : Run all (fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Public/CategoriesTest.php tests/Unit/Resources/CategoryWithChildrenTest.php 2>&1 | tail -10`
Expected: 8 tests fail (controller doesn't exist).

- [ ] **Step 7.4 : Create CategoryController**

Create `backend/app/Http/Controllers/Api/V1/Public/CategoryController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('order_position')
            ->get();

        return ApiResponse::success(
            $categories->map(fn ($cat) => (new CategoryResource($cat))->toArray(request()))->values()->all()
        );
    }

    public function tree()
    {
        $tree = Cache::remember('categories:tree', now()->addHour(), function () {
            $all = Category::where('is_active', true)
                ->orderBy('order_position')
                ->get();
            return self::buildTree($all);
        });

        return ApiResponse::success($tree);
    }

    public function services(Category $category, Request $request)
    {
        $services = $category->services()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->paginate(15);

        return ApiResponse::paginated(
            $services,
            fn ($svc) => (new ServiceResource($svc))->toArray($request)
        );
    }

    public static function buildTree(Collection $all): array
    {
        return $all->whereNull('parent_id')
            ->sortBy('order_position')
            ->map(fn ($cat) => self::nodeArray($cat, $all))
            ->values()
            ->all();
    }

    private static function nodeArray(Category $cat, Collection $all): array
    {
        return [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'name' => $cat->name,
            'icon' => $cat->icon,
            'description' => $cat->description,
            'children' => $all->where('parent_id', $cat->id)
                ->sortBy('order_position')
                ->map(fn ($child) => self::nodeArray($child, $all))
                ->values()
                ->all(),
        ];
    }
}
```

- [ ] **Step 7.5 : Add temporary routes for testing**

In `backend/routes/api.php`, append inside the `Route::prefix('v1')` group:

```php
Route::prefix('public')->group(function () {
    Route::get('categories', [\App\Http\Controllers\Api\V1\Public\CategoryController::class, 'index']);
    Route::get('categories/tree', [\App\Http\Controllers\Api\V1\Public\CategoryController::class, 'tree']);
    Route::get('categories/{category:slug}/services', [\App\Http\Controllers\Api\V1\Public\CategoryController::class, 'services']);
});
```

- [ ] **Step 7.6 : Run (pass)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Public/CategoriesTest.php tests/Unit/Resources/CategoryWithChildrenTest.php 2>&1 | tail -15`
Expected: 8 tests pass.

- [ ] **Step 7.7 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Controllers/Api/V1/Public/CategoryController.php backend/routes/api.php backend/tests/Feature/Api/V1/Public/CategoriesTest.php backend/tests/Unit/Resources/CategoryWithChildrenTest.php && \
  git commit -m "feat(sprint-2): Public/CategoryController (index, tree, services) + cache

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8 : Public ServiceController (index, show) + tests

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Public/ServiceController.php`
- Modify: `backend/routes/api.php` (add 2 routes)
- Test: `backend/tests/Feature/Api/V1/Public/ServicesTest.php`

- [ ] **Step 8.1 : Tests**

Create `backend/tests/Feature/Api/V1/Public/ServicesTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_services(): void
    {
        Service::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/public/services');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['pagination' => ['current_page', 'last_page', 'per_page', 'total']]]);

        $this->assertSame(15, count($response->json('data')));
        $this->assertSame(20, $response->json('meta.pagination.total'));
    }

    public function test_index_filters_by_category_slug(): void
    {
        $cat = Category::factory()->create(['slug' => 'plomberie']);
        Service::factory()->count(3)->create(['category_id' => $cat->id]);
        Service::factory()->count(2)->create(); // other category

        $response = $this->getJson('/api/v1/public/services?category_slug=plomberie');

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('meta.pagination.total'));
    }

    public function test_index_supports_q_param(): void
    {
        Service::factory()->create(['name' => 'Tissage femme']);
        Service::factory()->create(['name' => 'Vidange voiture']);

        $response = $this->getJson('/api/v1/public/services?q=tissage');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.pagination.total'));
        $this->assertSame('Tissage femme', $response->json('data.0.name'));
    }

    public function test_show_returns_service_by_slug(): void
    {
        $cat = Category::factory()->create(['name' => 'Plomberie']);
        Service::factory()->create(['slug' => 'fuite-robinet', 'name' => 'Fuite robinet', 'category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/public/services/fuite-robinet');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Fuite robinet')
            ->assertJsonPath('data.category.name', 'Plomberie');
    }

    public function test_show_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/v1/public/services/nonexistent')->assertStatus(404);
    }
}
```

- [ ] **Step 8.2 : Run (fail)**

- [ ] **Step 8.3 : Create ServiceController**

Create `backend/app/Http/Controllers/Api/V1/Public/ServiceController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query()
            ->where('is_active', true)
            ->with('category');

        if ($request->filled('category_slug')) {
            $cat = Category::where('slug', $request->input('category_slug'))->first();
            if ($cat) {
                $query->where('category_id', $cat->id);
            } else {
                $query->whereRaw('1 = 0'); // no results
            }
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ILIKE', '%'.$q.'%')
                  ->orWhere('description', 'ILIKE', '%'.$q.'%');
            });
        }

        $services = $query->orderBy('name')->paginate(15);

        return ApiResponse::paginated(
            $services,
            fn ($svc) => (new ServiceResource($svc))->toArray($request)
        );
    }

    public function show(Service $service, Request $request)
    {
        $service->load('category');
        return ApiResponse::success((new ServiceResource($service))->toArray($request));
    }
}
```

Note : `ILIKE` is PostgreSQL case-insensitive LIKE. Standard syntax.

- [ ] **Step 8.4 : Add routes**

In `backend/routes/api.php`, inside the `Route::prefix('public')` block (from Task 7), add:

```php
Route::get('services', [\App\Http\Controllers\Api\V1\Public\ServiceController::class, 'index']);
Route::get('services/{service:slug}', [\App\Http\Controllers\Api\V1\Public\ServiceController::class, 'show']);
```

- [ ] **Step 8.5 : Run (pass) — 5 tests**

- [ ] **Step 8.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Controllers/Api/V1/Public/ServiceController.php backend/routes/api.php backend/tests/Feature/Api/V1/Public/ServicesTest.php && \
  git commit -m "feat(sprint-2): Public/ServiceController (index paginé filtrable + show)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9 : ProviderSearchService (unit tests)

**Files:**
- Create: `backend/app/Services/ProviderSearch/ProviderSearchService.php`
- Test: `backend/tests/Unit/Services/ProviderSearch/ProviderSearchServiceTest.php`

- [ ] **Step 9.1 : Tests**

Create `backend/tests/Unit/Services/ProviderSearch/ProviderSearchServiceTest.php` :

```php
<?php

namespace Tests\Unit\Services\ProviderSearch;

use App\Models\Profile;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use App\Services\ProviderSearch\ProviderSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $phone, float $lat, float $lng, float $rating = 4.0): User
    {
        $user = User::factory()->prestataire()->create(['phone' => $phone, 'status' => 'active']);
        Profile::factory()->create([
            'user_id' => $user->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'average_rating' => $rating,
        ]);
        return $user;
    }

    public function test_filters_by_service_id(): void
    {
        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();
        $p1 = $this->makeProvider('+24107001', 0.4, 9.4);
        $p2 = $this->makeProvider('+24107002', 0.4, 9.4);
        ProviderService::factory()->create(['provider_id' => $p1->id, 'service_id' => $service1->id]);
        ProviderService::factory()->create(['provider_id' => $p2->id, 'service_id' => $service2->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service1->id]);

        $this->assertSame(1, $result->total());
        $this->assertSame($p1->id, $result->items()[0]->id);
    }

    public function test_filters_by_geo_distance(): void
    {
        $service = Service::factory()->create();
        // Libreville approx
        $near = $this->makeProvider('+24107003', 0.4162, 9.4673);
        // 30 km away (approx)
        $far = $this->makeProvider('+24107004', 0.7, 9.4673);
        ProviderService::factory()->create(['provider_id' => $near->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $far->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'lat' => 0.4162,
            'lng' => 9.4673,
            'radius_km' => 10,
        ]);

        $this->assertSame(1, $result->total());
        $this->assertSame($near->id, $result->items()[0]->id);
    }

    public function test_applies_rating_min(): void
    {
        $service = Service::factory()->create();
        $high = $this->makeProvider('+24107005', 0.4, 9.4, 4.7);
        $low = $this->makeProvider('+24107006', 0.4, 9.4, 3.2);
        ProviderService::factory()->create(['provider_id' => $high->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $low->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'rating_min' => 4.0,
        ]);

        $this->assertSame(1, $result->total());
        $this->assertSame($high->id, $result->items()[0]->id);
    }

    public function test_applies_price_max_including_null_prices(): void
    {
        $service = Service::factory()->create();
        $cheap = $this->makeProvider('+24107007', 0.4, 9.4);
        $expensive = $this->makeProvider('+24107008', 0.4, 9.4);
        $quote = $this->makeProvider('+24107009', 0.4, 9.4);
        ProviderService::factory()->create(['provider_id' => $cheap->id, 'service_id' => $service->id, 'price_amount' => 10000]);
        ProviderService::factory()->create(['provider_id' => $expensive->id, 'service_id' => $service->id, 'price_amount' => 30000]);
        ProviderService::factory()->create(['provider_id' => $quote->id, 'service_id' => $service->id, 'price_amount' => null]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'price_max' => 15000,
        ]);

        // cheap (10000 <= 15000) + quote (null) = 2 results
        $this->assertSame(2, $result->total());
    }

    public function test_sorts_by_rating_desc_when_no_geo(): void
    {
        $service = Service::factory()->create();
        $a = $this->makeProvider('+24107010', 0.4, 9.4, 3.0);
        $b = $this->makeProvider('+24107011', 0.4, 9.4, 4.8);
        $c = $this->makeProvider('+24107012', 0.4, 9.4, 4.0);
        ProviderService::factory()->create(['provider_id' => $a->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $b->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $c->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $ids = collect($result->items())->pluck('id')->all();
        $this->assertSame([$b->id, $c->id, $a->id], $ids);
    }

    public function test_excludes_pending_and_suspended_providers(): void
    {
        $service = Service::factory()->create();
        $active = $this->makeProvider('+24107013', 0.4, 9.4);
        $pending = User::factory()->prestataire()->pending()->create(['phone' => '+24107014']);
        Profile::factory()->create(['user_id' => $pending->id, 'latitude' => 0.4, 'longitude' => 9.4]);
        $suspended = User::factory()->prestataire()->suspended()->create(['phone' => '+24107015']);
        Profile::factory()->create(['user_id' => $suspended->id, 'latitude' => 0.4, 'longitude' => 9.4]);

        ProviderService::factory()->create(['provider_id' => $active->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $pending->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $suspended->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $this->assertSame(1, $result->total());
        $this->assertSame($active->id, $result->items()[0]->id);
    }

    public function test_excludes_unavailable_offers(): void
    {
        $service = Service::factory()->create();
        $p1 = $this->makeProvider('+24107016', 0.4, 9.4);
        $p2 = $this->makeProvider('+24107017', 0.4, 9.4);
        ProviderService::factory()->create(['provider_id' => $p1->id, 'service_id' => $service->id, 'is_available' => true]);
        ProviderService::factory()->unavailable()->create(['provider_id' => $p2->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $this->assertSame(1, $result->total());
        $this->assertSame($p1->id, $result->items()[0]->id);
    }

    public function test_paginates_15_per_page(): void
    {
        $service = Service::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            $p = $this->makeProvider('+24107' . str_pad((string)(100+$i), 3, '0', STR_PAD_LEFT), 0.4, 9.4);
            ProviderService::factory()->create(['provider_id' => $p->id, 'service_id' => $service->id]);
        }

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $this->assertSame(15, $result->perPage());
        $this->assertSame(20, $result->total());
        $this->assertSame(2, $result->lastPage());
    }

    public function test_sort_by_distance_when_geo(): void
    {
        $service = Service::factory()->create();
        // closer
        $near = $this->makeProvider('+24107018', 0.42, 9.47);
        // farther
        $far = $this->makeProvider('+24107019', 0.5, 9.47);
        ProviderService::factory()->create(['provider_id' => $near->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $far->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'lat' => 0.4162,
            'lng' => 9.4673,
            'radius_km' => 100,
        ]);

        $items = $result->items();
        $this->assertSame($near->id, $items[0]->id);
        $this->assertSame($far->id, $items[1]->id);
        $this->assertLessThan($items[1]->distance_km, $items[0]->distance_km);
    }
}
```

- [ ] **Step 9.2 : Run (fail)**

- [ ] **Step 9.3 : Create ProviderSearchService**

Create `backend/app/Services/ProviderSearch/ProviderSearchService.php` :

```php
<?php

namespace App\Services\ProviderSearch;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ProviderSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $hasGeo = isset($filters['lat'], $filters['lng']);

        $query = User::query()
            ->select([
                'users.*',
                DB::raw($hasGeo
                    ? $this->distanceExpr((float) $filters['lat'], (float) $filters['lng']).' AS distance_km'
                    : 'NULL::float AS distance_km'),
            ])
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->join('provider_services', 'provider_services.provider_id', '=', 'users.id')
            ->where('users.type', 'prestataire')
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->where('provider_services.service_id', $filters['service_id'])
            ->where('provider_services.is_available', true)
            ->with([
                'profile',
                'providerServices' => fn ($q) => $q->where('service_id', $filters['service_id'])
                                                    ->where('is_available', true)
                                                    ->with('service'),
            ]);

        if (isset($filters['rating_min'])) {
            $query->where('profiles.average_rating', '>=', $filters['rating_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('provider_services.price_amount')
                  ->orWhere('provider_services.price_amount', '<=', $filters['price_max']);
            });
        }

        if ($hasGeo) {
            $radius = $filters['radius_km'] ?? 20;
            $query->whereRaw(
                $this->distanceExpr((float) $filters['lat'], (float) $filters['lng']).' <= ?',
                [$radius]
            );
        }

        $sortBy = $filters['sort_by'] ?? ($hasGeo ? 'distance' : 'rating');
        match ($sortBy) {
            'distance' => $query->orderBy('distance_km'),
            'rating'   => $query->orderByDesc('profiles.average_rating'),
            'price'    => $query->orderBy('provider_services.price_amount'),
        };

        return $query->paginate(15);
    }

    private function distanceExpr(float $lat, float $lng): string
    {
        // SQL injection safe : (float) cast applied after FormRequest validation.
        return "(6371 * acos(LEAST(1, "
             . "cos(radians($lat)) * cos(radians(profiles.latitude)) "
             . "* cos(radians(profiles.longitude) - radians($lng)) "
             . "+ sin(radians($lat)) * sin(radians(profiles.latitude))"
             . ")))";
    }
}
```

- [ ] **Step 9.4 : Run (pass)**

Run: `cd backend && php artisan test tests/Unit/Services/ProviderSearch/ProviderSearchServiceTest.php 2>&1 | tail -15`
Expected: 9 tests pass.

- [ ] **Step 9.5 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Services/ProviderSearch/ProviderSearchService.php backend/tests/Unit/Services/ProviderSearch/ProviderSearchServiceTest.php && \
  git commit -m "feat(sprint-2): ProviderSearchService (Haversine SQL + filtres + tri + pagination)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10 : Public ProviderController (search, show) + tests

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Public/ProviderController.php`
- Create: `backend/app/Http/Requests/Api/V1/Public/SearchProvidersRequest.php`
- Modify: `backend/routes/api.php` (add 2 routes)
- Test: `backend/tests/Feature/Api/V1/Public/ProviderSearchTest.php`

- [ ] **Step 10.1 : Tests**

Create `backend/tests/Feature/Api/V1/Public/ProviderSearchTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Profile;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $phone, float $lat = 0.4, float $lng = 9.4, float $rating = 4.0, string $status = 'active'): User
    {
        $user = User::factory()->prestataire()->create(['phone' => $phone, 'status' => $status]);
        Profile::factory()->create([
            'user_id' => $user->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'average_rating' => $rating,
        ]);
        return $user;
    }

    public function test_search_requires_service_id(): void
    {
        $this->getJson('/api/v1/public/providers/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_search_validates_lat_requires_lng(): void
    {
        $service = Service::factory()->create();
        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&lat=0.4")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng']);
    }

    public function test_search_returns_paginated_results(): void
    {
        $service = Service::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $p = $this->makeProvider('+24107' . str_pad((string) (200 + $i), 3, '0', STR_PAD_LEFT));
            ProviderService::factory()->create(['provider_id' => $p->id, 'service_id' => $service->id]);
        }

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['pagination' => ['total']]])
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_search_with_geo_returns_distance_km(): void
    {
        $service = Service::factory()->create();
        $p = $this->makeProvider('+24107300', 0.42, 9.47);
        ProviderService::factory()->create(['provider_id' => $p->id, 'service_id' => $service->id]);

        $response = $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&lat=0.4162&lng=9.4673&radius_km=50");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.0.distance_km'));
    }

    public function test_search_filters_rating_min(): void
    {
        $service = Service::factory()->create();
        $high = $this->makeProvider('+24107301', rating: 4.7);
        $low = $this->makeProvider('+24107302', rating: 3.0);
        ProviderService::factory()->create(['provider_id' => $high->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $low->id, 'service_id' => $service->id]);

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&rating_min=4.0")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_search_filters_price_max(): void
    {
        $service = Service::factory()->create();
        $p1 = $this->makeProvider('+24107303');
        $p2 = $this->makeProvider('+24107304');
        ProviderService::factory()->create(['provider_id' => $p1->id, 'service_id' => $service->id, 'price_amount' => 10000]);
        ProviderService::factory()->create(['provider_id' => $p2->id, 'service_id' => $service->id, 'price_amount' => 50000]);

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&price_max=20000")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_search_excludes_pending_providers(): void
    {
        $service = Service::factory()->create();
        $pending = $this->makeProvider('+24107305', status: 'pending');
        ProviderService::factory()->create(['provider_id' => $pending->id, 'service_id' => $service->id]);

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_show_provider_returns_public_profile(): void
    {
        $user = User::factory()->prestataire()->create(['phone' => '+24107306']);
        Profile::factory()->create([
            'user_id' => $user->id,
            'firstname' => 'Sarah',
            'lastname' => 'Mbeng',
            'city' => 'Libreville',
            'latitude' => 0.4,
            'longitude' => 9.4,
        ]);

        $response = $this->getJson("/api/v1/public/providers/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.firstname', 'Sarah')
            ->assertJsonPath('data.city', 'Libreville');

        // anti-leak verifications
        $body = $response->json('data');
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('address', $body);
        $this->assertArrayNotHasKey('latitude', $body);
        $this->assertArrayNotHasKey('longitude', $body);
    }

    public function test_show_unknown_provider_returns_404(): void
    {
        $this->getJson('/api/v1/public/providers/019e6d9e-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }
}
```

- [ ] **Step 10.2 : Create SearchProvidersRequest**

Create `backend/app/Http/Requests/Api/V1/Public/SearchProvidersRequest.php` :

```php
<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;

class SearchProvidersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'rating_min' => ['nullable', 'numeric', 'between:0,5'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'in:distance,rating,price'],
        ];
    }
}
```

- [ ] **Step 10.3 : Create ProviderController**

Create `backend/app/Http/Controllers/Api/V1/Public/ProviderController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\SearchProvidersRequest;
use App\Http\Resources\Api\V1\ProviderSearchResultResource;
use App\Http\Resources\Api\V1\PublicProviderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ProviderSearch\ProviderSearchService;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function search(SearchProvidersRequest $request, ProviderSearchService $service)
    {
        $results = $service->search($request->validated());

        return ApiResponse::paginated(
            $results,
            fn ($u) => (new ProviderSearchResultResource($u))->toArray($request)
        );
    }

    public function show(string $id, Request $request)
    {
        $user = User::where('id', $id)
            ->where('type', 'prestataire')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with([
                'profile',
                'providerServices' => fn ($q) => $q->where('is_available', true)->with('service.category'),
            ])
            ->first();

        if (!$user) {
            abort(404, 'Prestataire introuvable.');
        }

        return ApiResponse::success((new PublicProviderResource($user))->toArray($request));
    }
}
```

- [ ] **Step 10.4 : Add routes**

In `backend/routes/api.php`, inside the `Route::prefix('public')` block:

```php
Route::get('providers/search', [\App\Http\Controllers\Api\V1\Public\ProviderController::class, 'search']);
Route::get('providers/{id}', [\App\Http\Controllers\Api\V1\Public\ProviderController::class, 'show']);
```

- [ ] **Step 10.5 : Run tests (pass) — 9 tests**

- [ ] **Step 10.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Controllers/Api/V1/Public/ProviderController.php backend/app/Http/Requests/Api/V1/Public/SearchProvidersRequest.php backend/routes/api.php backend/tests/Feature/Api/V1/Public/ProviderSearchTest.php && \
  git commit -m "feat(sprint-2): Public/ProviderController (search + show, anti-leak public profile)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 11 : Provider ServiceController (CRUD + IDOR protection)

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Provider/ServiceController.php`
- Create: `backend/app/Http/Requests/Api/V1/Provider/StoreProviderServiceRequest.php`
- Create: `backend/app/Http/Requests/Api/V1/Provider/UpdateProviderServiceRequest.php`
- Modify: `backend/routes/api.php` (add 4 routes)
- Test: `backend/tests/Feature/Api/V1/Provider/ServicesTest.php`

- [ ] **Step 11.1 : Tests**

Create `backend/tests/Feature/Api/V1/Provider/ServicesTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Provider;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth_sanctum(): void
    {
        $this->getJson('/api/v1/provider/services')->assertStatus(401);
    }

    public function test_index_rejects_client_with_AUTH_008(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/v1/provider/services')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_008');
    }

    public function test_index_returns_own_offers_only(): void
    {
        $provider1 = User::factory()->prestataire()->create();
        $provider2 = User::factory()->prestataire()->create();
        ProviderService::factory()->count(2)->create(['provider_id' => $provider1->id]);
        ProviderService::factory()->count(3)->create(['provider_id' => $provider2->id]);

        Sanctum::actingAs($provider1);
        $response = $this->getJson('/api/v1/provider/services');

        $response->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_store_creates_offer(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        Sanctum::actingAs($provider);

        $response = $this->postJson('/api/v1/provider/services', [
            'service_id' => $service->id,
            'price_model' => 'fixed',
            'price_amount' => 15000,
            'custom_description' => 'Travail soigné.',
            'is_available' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.price_amount', 15000)
            ->assertJsonPath('data.service.id', $service->id);

        $this->assertDatabaseHas('provider_services', [
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'price_amount' => 15000,
        ]);
    }

    public function test_store_rejects_duplicate_with_CATALOG_001(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        ProviderService::factory()->create(['provider_id' => $provider->id, 'service_id' => $service->id]);

        Sanctum::actingAs($provider);
        $this->postJson('/api/v1/provider/services', [
            'service_id' => $service->id,
            'price_model' => 'fixed',
            'price_amount' => 10000,
        ])->assertStatus(422)
          ->assertJsonPath('error.code', 'CATALOG_001');
    }

    public function test_store_requires_price_amount_unless_quote(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        Sanctum::actingAs($provider);

        // fixed without price_amount — must fail
        $this->postJson('/api/v1/provider/services', [
            'service_id' => $service->id,
            'price_model' => 'fixed',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['price_amount']);

        // quote without price_amount — must pass
        $service2 = Service::factory()->create();
        $this->postJson('/api/v1/provider/services', [
            'service_id' => $service2->id,
            'price_model' => 'quote',
        ])->assertStatus(201);
    }

    public function test_update_modifies_own_offer(): void
    {
        $provider = User::factory()->prestataire()->create();
        $offer = ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'price_amount' => 10000,
        ]);

        Sanctum::actingAs($provider);
        $this->putJson("/api/v1/provider/services/{$offer->id}", [
            'price_amount' => 20000,
            'is_available' => false,
        ])->assertStatus(200)
          ->assertJsonPath('data.price_amount', 20000)
          ->assertJsonPath('data.is_available', false);
    }

    public function test_update_returns_404_on_other_provider_offer(): void
    {
        $providerA = User::factory()->prestataire()->create();
        $providerB = User::factory()->prestataire()->create();
        $offerB = ProviderService::factory()->create(['provider_id' => $providerB->id]);

        Sanctum::actingAs($providerA);
        $this->putJson("/api/v1/provider/services/{$offerB->id}", ['price_amount' => 99999])
            ->assertStatus(404);

        $this->assertSame($offerB->price_amount, $offerB->fresh()->price_amount);
    }

    public function test_destroy_removes_own_offer(): void
    {
        $provider = User::factory()->prestataire()->create();
        $offer = ProviderService::factory()->create(['provider_id' => $provider->id]);

        Sanctum::actingAs($provider);
        $this->deleteJson("/api/v1/provider/services/{$offer->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('provider_services', ['id' => $offer->id]);
    }

    public function test_destroy_returns_404_on_other_provider_offer(): void
    {
        $providerA = User::factory()->prestataire()->create();
        $providerB = User::factory()->prestataire()->create();
        $offerB = ProviderService::factory()->create(['provider_id' => $providerB->id]);

        Sanctum::actingAs($providerA);
        $this->deleteJson("/api/v1/provider/services/{$offerB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('provider_services', ['id' => $offerB->id]);
    }
}
```

- [ ] **Step 11.2 : Create FormRequests**

Create `backend/app/Http/Requests/Api/V1/Provider/StoreProviderServiceRequest.php` :

```php
<?php

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'price_model' => ['required', 'in:fixed,hourly,quote'],
            'price_amount' => ['nullable', 'integer', 'min:0', 'required_unless:price_model,quote'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
```

Create `backend/app/Http/Requests/Api/V1/Provider/UpdateProviderServiceRequest.php` :

```php
<?php

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_model' => ['nullable', 'in:fixed,hourly,quote'],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 11.3 : Create ServiceController**

Create `backend/app/Http/Controllers/Api/V1/Provider/ServiceController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\StoreProviderServiceRequest;
use App\Http\Requests\Api\V1\Provider\UpdateProviderServiceRequest;
use App\Http\Resources\Api\V1\ProviderServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\ProviderService;
use App\Models\User;
use Illuminate\Http\Request;

final class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $provider = $this->ensureProvider($request);

        $offers = $provider->providerServices()
            ->with('service.category')
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated(
            $offers,
            fn ($ps) => (new ProviderServiceResource($ps))->toArray($request)
        );
    }

    public function store(StoreProviderServiceRequest $request)
    {
        $provider = $this->ensureProvider($request);
        $data = $request->validated();

        if ($provider->providerServices()->where('service_id', $data['service_id'])->exists()) {
            throw new ApiException(
                'CATALOG_001',
                422,
                'Tu proposes déjà ce service.',
                ['hint' => 'Utilise PUT /provider/services/{id} pour modifier.']
            );
        }

        $offer = $provider->providerServices()->create($data);
        $offer->load('service.category');

        return ApiResponse::success((new ProviderServiceResource($offer))->toArray($request), 201);
    }

    public function update(UpdateProviderServiceRequest $request, string $id)
    {
        $provider = $this->ensureProvider($request);
        $offer = $this->findOwnedOffer($provider, $id);

        $offer->update($request->validated());
        $offer->load('service.category');

        return ApiResponse::success((new ProviderServiceResource($offer))->toArray($request));
    }

    public function destroy(Request $request, string $id)
    {
        $provider = $this->ensureProvider($request);
        $offer = $this->findOwnedOffer($provider, $id);
        $offer->delete();

        return response()->noContent();
    }

    private function ensureProvider(Request $request): User
    {
        $user = $request->user();
        if ($user->type !== 'prestataire') {
            throw ApiException::accountUnauthorized();
        }
        return $user;
    }

    private function findOwnedOffer(User $provider, string $id): ProviderService
    {
        $offer = ProviderService::where('id', $id)
            ->where('provider_id', $provider->id)
            ->first();
        if (!$offer) {
            abort(404, 'Offre introuvable.');
        }
        return $offer;
    }
}
```

- [ ] **Step 11.4 : Add routes**

In `backend/routes/api.php`, inside `Route::middleware('auth:sanctum')` block (next to existing protected routes), add:

```php
Route::prefix('provider')->group(function () {
    Route::get('services', [\App\Http\Controllers\Api\V1\Provider\ServiceController::class, 'index']);
    Route::post('services', [\App\Http\Controllers\Api\V1\Provider\ServiceController::class, 'store']);
    Route::put('services/{id}', [\App\Http\Controllers\Api\V1\Provider\ServiceController::class, 'update']);
    Route::delete('services/{id}', [\App\Http\Controllers\Api\V1\Provider\ServiceController::class, 'destroy']);
});
```

- [ ] **Step 11.5 : Run (pass) — 10 tests**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Provider/ServicesTest.php 2>&1 | tail -15`

- [ ] **Step 11.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Controllers/Api/V1/Provider backend/app/Http/Requests/Api/V1/Provider backend/routes/api.php backend/tests/Feature/Api/V1/Provider && \
  git commit -m "feat(sprint-2): Provider/ServiceController CRUD (IDOR protected, AUTH_008, CATALOG_001)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 12 : CategoryObserver (cache invalidation)

**Files:**
- Create: `backend/app/Observers/CategoryObserver.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` (register observer)

- [ ] **Step 12.1 : Create CategoryObserver**

Create `backend/app/Observers/CategoryObserver.php` :

```php
<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        Cache::forget('categories:tree');
    }

    public function deleted(Category $category): void
    {
        Cache::forget('categories:tree');
    }
}
```

- [ ] **Step 12.2 : Register observer in AppServiceProvider**

Modify `backend/app/Providers/AppServiceProvider.php` — read it first to preserve existing content, then add to `boot()`:

```php
use App\Models\Category;
use App\Observers\CategoryObserver;
```

In `boot()` method (add line):

```php
public function boot(): void
{
    Category::observe(CategoryObserver::class);
}
```

- [ ] **Step 12.3 : Add test for invalidation**

Append to `backend/tests/Feature/Api/V1/Public/CategoriesTest.php` (inside the class) :

```php
public function test_cache_invalidated_when_category_saved(): void
{
    Category::factory()->create(['parent_id' => null]);
    $this->getJson('/api/v1/public/categories/tree'); // populates cache
    $this->assertTrue(Cache::has('categories:tree'));

    Category::factory()->create(['parent_id' => null]);
    $this->assertFalse(Cache::has('categories:tree'));
}
```

- [ ] **Step 12.4 : Run all CategoriesTest (must pass including new test)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Public/CategoriesTest.php 2>&1 | tail -10`
Expected: 7 tests pass (6 existing + 1 new).

- [ ] **Step 12.5 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Observers backend/app/Providers/AppServiceProvider.php backend/tests/Feature/Api/V1/Public/CategoriesTest.php && \
  git commit -m "feat(sprint-2): CategoryObserver invalidates categories:tree cache on save/delete

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 13 : Smoke test E2E + push

**Files:** (verification only)

- [ ] **Step 13.1 : Run full test suite**

Run: `cd backend && php artisan test 2>&1 | tail -10`
Expected: 102+ tests passing (70 Sprint 1 + 32 Sprint 2 + 6 new ApiException tests).

- [ ] **Step 13.2 : Verify route count**

Run: `cd backend && php artisan route:list --path=api/v1 2>&1 | grep -E "GET|POST|PUT|DELETE" | wc -l`
Expected: ≥ 21 routes (10 Sprint 1 + 11 Sprint 2).

- [ ] **Step 13.3 : Verify seeders idempotent on dev DB**

Run: `cd backend && php artisan db:seed 2>&1 | tail -5`
Expected: completes without error (updateOrCreate is idempotent).

Verify counts:
```bash
docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "SELECT count(*) FROM categories; SELECT count(*) FROM services;"
```
Expected: categories ≥ 25, services ≥ 40.

- [ ] **Step 13.4 : Verify no sensitive field leak in PublicProviderResource**

Run: `grep -rE "phone|email|password|address|latitude|longitude|otp_|two_factor|firebase" backend/app/Http/Resources/Api/V1/PublicProviderResource.php`
Expected: no match (the file should NOT reference any of those columns).

- [ ] **Step 13.5 : Smoke E2E via curl**

Start serve in background on port 8002:
```bash
cd backend && php artisan serve --port=8002 &
sleep 3
```

Test public endpoints :
```bash
echo "=== GET /categories/tree ==="
curl -s http://localhost:8002/api/v1/public/categories/tree | python3 -c "import sys, json; d=json.load(sys.stdin); print('root count:', len(d['data']))"

echo "=== GET /services (paginated) ==="
curl -s 'http://localhost:8002/api/v1/public/services?category_slug=plomberie-fuite' | python3 -c "import sys, json; d=json.load(sys.stdin); print('services in plomberie-fuite:', d['meta']['pagination']['total'])"

echo "=== Search providers (empty result expected) ==="
SERVICE_ID=$(curl -s 'http://localhost:8002/api/v1/public/services?q=fuite' | python3 -c "import sys, json; print(json.load(sys.stdin)['data'][0]['id'])")
curl -s "http://localhost:8002/api/v1/public/providers/search?service_id=$SERVICE_ID" | python3 -c "import sys, json; d=json.load(sys.stdin); print('total:', d['meta']['pagination']['total'])"
```

Stop the serve via TaskStop (note the background task id).

- [ ] **Step 13.6 : Push + tag**

```bash
cd /Applications/MAMP/htdocs/lukassa && git log --oneline | head -15 && git push origin main 2>&1 | tail -3 && git tag sprint-2-catalog-search && git push origin sprint-2-catalog-search 2>&1 | tail -2
```

---

## Definition of Done finale (Sprint 2)

- [ ] `php artisan test` : 102+ tests passent
- [ ] `php artisan route:list --path=api/v1` : ≥ 21 routes
- [ ] `php artisan db:seed --class=CategorySeeder` + `ServiceSeeder` → ~25 catégories + ~40 services
- [ ] `GET /api/v1/public/categories/tree` retourne 10 root avec children
- [ ] `GET /api/v1/public/services?category_slug=plomberie-fuite` retourne 3 services
- [ ] `GET /api/v1/public/providers/search?service_id=X` → 200 + structure paginated
- [ ] `POST /api/v1/provider/services` avec client → 403 AUTH_008
- [ ] `POST /api/v1/provider/services` avec prestataire + service déjà couvert → 422 CATALOG_001
- [ ] IDOR : prestataire A `PUT/DELETE /provider/services/{id de B}` → 404
- [ ] `Cache::has('categories:tree')` après premier appel tree
- [ ] `PublicProviderResource` ne contient ni phone, email, password, address, latitude, longitude, firebase_token, otp_*, two_factor_*
- [ ] Tag git `sprint-2-catalog-search` poussé sur GitHub
