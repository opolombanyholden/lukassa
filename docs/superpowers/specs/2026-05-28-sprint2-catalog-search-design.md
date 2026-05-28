# LUKASSA — Phase 2 / Sprint 2 — Catalogue & Recherche Prestataires — Design

**Date** : 2026-05-28
**Auteur** : Daisy + Claude (brainstorming session)
**Statut** : à valider
**Périmètre** : Sprint 2 backend uniquement — catalogue public (catégories + services), gestion d'offres prestataire, recherche géolocalisée Haversine. Pas de RFQ (Sprint 3), pas de paiements (Sprint 5), pas d'UI.
**Dépend de** : Phase 1 / Sprint 0 ✅, Phase 2 / Sprint 1 ✅ (auth Sanctum hybride + ApiException pattern + User/Profile models)

---

## 1. Contexte & motivation

Sprint 1 a livré l'authentification (register/login/OTP/profile). Pour que des prestataires puissent **proposer leurs services** et que des clients puissent **les découvrir et les rechercher**, il faut maintenant exposer le **catalogue public** (catégories+services) et un **moteur de recherche** avec géolocalisation.

**Choix utilisateur faits en brainstorming** :
1. **Seeder Laravel** avec catalogue Gabon (10 catégories root + 15 enfants + ~50 services).
2. **Haversine SQL natif PostgreSQL** pour Sprint 2 (PostGIS reporté au Sprint 3 / RFQ).
3. **Search complète** : `service_id` requis + `lat/lng/radius_km` optionnel + `rating_min` + `price_max` + `sort_by` configurable + pagination 15.
4. **Authorization provider** : check applicatif `$user->type === 'prestataire'` dans le controller (méthode `ensureProvider()`), pas de middleware dédié (refactor quand on aura plus de routes prestataire).
5. **Cache catégories tree** TTL 1h, invalidation via Observer.

---

## 2. Architecture cible

### 2.1 — Endpoints livrés (11 routes)

```
PUBLIC (Route::prefix('public'))
  GET  /api/v1/public/categories
  GET  /api/v1/public/categories/tree
  GET  /api/v1/public/categories/{slug}/services
  GET  /api/v1/public/services                            ?q=&category_slug=&page=
  GET  /api/v1/public/services/{slug}
  GET  /api/v1/public/providers/search                    ?service_id=&lat=&lng=&radius_km=&rating_min=&price_max=&sort_by=&page=
  GET  /api/v1/public/providers/{id}

PROTECTED (auth:sanctum + check user.type === 'prestataire' inline, Route::prefix('provider'))
  GET    /api/v1/provider/services
  POST   /api/v1/provider/services                        { service_id, price_model, price_amount?, custom_description?, is_available? }
  PUT    /api/v1/provider/services/{id}                   { price_model?, price_amount?, custom_description?, is_available? }
  DELETE /api/v1/provider/services/{id}                   → 204
```

### 2.2 — Structure fichiers (incrément Sprint 1)

```
backend/
├── app/
│   ├── Http/Controllers/Api/V1/
│   │   ├── Public/
│   │   │   ├── CategoryController.php           (index, tree, services)
│   │   │   ├── ServiceController.php            (index, show)
│   │   │   └── ProviderController.php           (search, show)
│   │   └── Provider/
│   │       └── ServiceController.php            (index, store, update, destroy)
│   ├── Http/Requests/Api/V1/
│   │   ├── Provider/
│   │   │   ├── StoreProviderServiceRequest.php
│   │   │   └── UpdateProviderServiceRequest.php
│   │   └── Public/
│   │       └── SearchProvidersRequest.php
│   ├── Http/Resources/Api/V1/
│   │   ├── CategoryResource.php
│   │   ├── CategoryWithChildrenResource.php     ← pour tree
│   │   ├── ServiceResource.php
│   │   ├── ProviderServiceResource.php          ← POV prestataire (mes offres)
│   │   ├── PublicProviderResource.php           ← profil public d'un prestataire
│   │   └── ProviderSearchResultResource.php     ← résultat recherche (distance_km, service inline)
│   ├── Models/
│   │   ├── Category.php                         ← HasUuids, parent/children, slug routing
│   │   ├── Service.php                          ← HasUuids, belongsTo Category
│   │   └── ProviderService.php                  ← HasUuids, belongsTo User + Service
│   ├── Observers/
│   │   └── CategoryObserver.php                 ← Cache::forget('categories:tree') on save/delete
│   ├── Services/
│   │   └── ProviderSearch/
│   │       └── ProviderSearchService.php        ← Haversine SQL + filtres + pagination
│   └── Exceptions/
│       └── ApiException.php                     ← +accountUnauthorized() AUTH_008 + CATALOG_001..003
└── database/
    ├── factories/
    │   ├── CategoryFactory.php
    │   ├── ServiceFactory.php
    │   └── ProviderServiceFactory.php
    └── seeders/
        ├── DatabaseSeeder.php                   ← orchestrate CategorySeeder + ServiceSeeder
        ├── CategorySeeder.php                   ← ~25 catégories Gabon
        └── ServiceSeeder.php                    ← ~50 services
```

### 2.3 — Convention de routing

- Public catalog par **slug** (SEO + URL parlantes) : `/public/categories/{slug}/services`, `/public/services/{slug}`.
- Provider routes par **UUID** : `/provider/services/{id}` (anti-IDOR — pas de slug deviné, l'utilisateur ne voit que ses propres IDs).
- Search par **UUID** dans `service_id` (paramètre query).

---

## 3. Décisions techniques

| Sujet | Décision | Justification |
|---|---|---|
| Géo search | Haversine SQL natif PG (`acos/cos/sin/radians`) | YAGNI : pas besoin de PostGIS pour quelques milliers de prestataires en Sprint 2. PostGIS arrive au Sprint 3 (RFQ — beaucoup plus de points à indexer + jobs queue PostGIS ST_DWithin). |
| Anti SQL injection sur coords | Cast `(float)` + interpolation directe dans `whereRaw` (PG ne supporte pas `?` dans `cos(radians(?))`) | Coords validées en amont par FormRequest (`between:-90,90`). Cast (float) garantit qu'aucune string arbitraire ne passe. |
| Cache categories tree | `Cache::remember('categories:tree', 1h, …)` + Observer | Catégories changent rarement. Tree calculation = 1 query + récursion PHP (~25 nodes). |
| Provider authorization | Méthode privée `ensureProvider()` dans `Provider\ServiceController` | DRY pour 4 méthodes du même controller. Refactor en middleware quand 2e+ controller en a besoin. |
| Anti-IDOR provider | Lookup `WHERE id=$id AND provider_id=$user->id` puis 404 si null | Pas de 403 (anti-leak — un prestataire ne sait pas qu'il existe d'autres offres). |
| Pagination | Laravel `paginate(15)` partout | Standard, le client peut surcharger `?per_page=` plus tard si besoin. |
| Sort par défaut | `distance` si géoloc, sinon `rating DESC` | Le UX attend la proximité quand on a la position, sinon la qualité. |
| Codes erreur métier | `AUTH_008` (provider only) + `CATALOG_001..003` | Conventions Sprint 1 étendues. |
| Models HasUuids | Tous (Category, Service, ProviderService) | Cohérence avec User/Profile. |
| Slug | `Str::slug()` Laravel, validé `unique:categories,slug` / `unique:services,slug` côté Seeder (les seeders fournissent les slugs manuellement, pas d'auto-génération en Sprint 2). |
| Resources | API Resources Laravel pour TOUS les retours | Évite le leak des colonnes (UserResource pattern de Sprint 1 réutilisé). |

---

## 4. Composants détaillés

### 4.1 — Models

**`App\Models\Category`** :

```php
class Category extends Model
{
    use HasFactory, HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['parent_id', 'name', 'slug', 'icon', 'description', 'order_position', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id'); }
    public function services(): HasMany { return $this->hasMany(Service::class); }

    public function getRouteKeyName(): string { return 'slug'; } // route model binding par slug
}
```

**`App\Models\Service`** :

```php
class Service extends Model
{
    use HasFactory, HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['category_id', 'name', 'slug', 'description', 'icon', 'cover_image',
                            'min_price_estimate', 'is_active', 'requires_quote'];
    protected $casts = ['is_active' => 'boolean', 'requires_quote' => 'boolean'];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function providerServices(): HasMany { return $this->hasMany(ProviderService::class); }

    public function getRouteKeyName(): string { return 'slug'; }
}
```

**`App\Models\ProviderService`** :

```php
class ProviderService extends Model
{
    use HasFactory, HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['provider_id', 'service_id', 'price_model', 'price_amount',
                            'custom_description', 'is_available'];
    protected $casts = ['is_available' => 'boolean', 'price_amount' => 'integer'];

    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
```

Update `App\Models\User` : ajouter relation `providerServices()` :

```php
public function providerServices(): HasMany
{
    return $this->hasMany(ProviderService::class, 'provider_id');
}
```

### 4.2 — Resources

**`CategoryResource`** (liste plate, sans children) :

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'slug' => $this->slug,
        'name' => $this->name,
        'icon' => $this->icon,
        'description' => $this->description,
        'parent_id' => $this->parent_id,
    ];
}
```

**`CategoryWithChildrenResource`** (utilisé par tree, transformation custom — pas une Resource Laravel pour éviter récursion infinie) :

```php
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
```

**`ServiceResource`** :

```php
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
        'requires_quote' => $this->requires_quote,
        'category' => $this->whenLoaded('category', fn () => [
            'id' => $this->category->id,
            'slug' => $this->category->slug,
            'name' => $this->category->name,
        ]),
    ];
}
```

**`ProviderServiceResource`** (POV prestataire — toutes les infos de son offre) :

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'service' => $this->whenLoaded('service', fn () => (new ServiceResource($this->service->load('category')))->toArray($request)),
        'price_model' => $this->price_model,
        'price_amount' => $this->price_amount,
        'custom_description' => $this->custom_description,
        'is_available' => $this->is_available,
        'created_at' => $this->created_at?->toIso8601String(),
        'updated_at' => $this->updated_at?->toIso8601String(),
    ];
}
```

**`PublicProviderResource`** (profil public — anti-leak des coordonnées personnelles) :

```php
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
        'average_rating' => (float) ($profile?->average_rating ?? 0),
        'total_reviews' => $profile?->total_reviews ?? 0,
        'intervention_radius_km' => $profile?->intervention_radius_km,
        'services' => $this->whenLoaded('providerServices', function () use ($request) {
            return $this->providerServices->where('is_available', true)
                ->map(fn ($ps) => (new ProviderServiceResource($ps->load('service.category')))->toArray($request))
                ->values()
                ->all();
        }),
    ];
}
```

**Champs NON exposés** : `phone`, `email`, `address`, `latitude`, `longitude`, `firebase_token`, `otp_*`, `two_factor_*`, `password`, `identity_verified_at`, `created_at`/`updated_at` (privés).

**`ProviderSearchResultResource`** (un par résultat de search, inclut `distance_km` calculée) :

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'firstname' => $this->profile->firstname,
        'lastname' => $this->profile->lastname,
        'city' => $this->profile->city,
        'average_rating' => (float) $this->profile->average_rating,
        'total_reviews' => $this->profile->total_reviews,
        'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
        'service' => [
            'id' => $this->providerServices->first()->service_id,
            'name' => $this->providerServices->first()->service->name,
            'price_amount' => $this->providerServices->first()->price_amount,
            'price_model' => $this->providerServices->first()->price_model,
        ],
    ];
}
```

### 4.3 — `ProviderSearchService` (cœur de la recherche)

```php
final class ProviderSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $hasGeo = isset($filters['lat'], $filters['lng']);

        $query = User::query()
            ->select([
                'users.*',
                DB::raw($hasGeo
                    ? $this->distanceExpr($filters['lat'], $filters['lng']).' AS distance_km'
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
                $this->distanceExpr($filters['lat'], $filters['lng']).' <= ?',
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
        // SQL injection safe : cast (float) appliqué après validation FormRequest.
        $lat = (float) $lat;
        $lng = (float) $lng;
        return "(6371 * acos(LEAST(1, "
             . "cos(radians($lat)) * cos(radians(profiles.latitude)) "
             . "* cos(radians(profiles.longitude) - radians($lng)) "
             . "+ sin(radians($lat)) * sin(radians(profiles.latitude))"
             . ")))";
    }
}
```

### 4.4 — FormRequests

**`SearchProvidersRequest`** :

```php
public function rules(): array
{
    return [
        'service_id' => ['required', 'uuid', 'exists:services,id'],
        'lat'        => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
        'lng'        => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        'radius_km'  => ['nullable', 'integer', 'min:1', 'max:200'],
        'rating_min' => ['nullable', 'numeric', 'between:0,5'],
        'price_max'  => ['nullable', 'integer', 'min:0'],
        'sort_by'    => ['nullable', 'in:distance,rating,price'],
    ];
}
```

**`StoreProviderServiceRequest`** :

```php
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
```

**`UpdateProviderServiceRequest`** : idem mais tout `nullable`, pas de `service_id` (immutable) :

```php
public function rules(): array
{
    return [
        'price_model' => ['nullable', 'in:fixed,hourly,quote'],
        'price_amount' => ['nullable', 'integer', 'min:0'],
        'custom_description' => ['nullable', 'string', 'max:2000'],
        'is_available' => ['nullable', 'boolean'],
    ];
}
```

### 4.5 — `Provider\ServiceController`

```php
final class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $provider = $this->ensureProvider($request);
        $offers = $provider->providerServices()
            ->with('service.category')
            ->orderByDesc('created_at')
            ->paginate(15);
        return ApiResponse::paginated($offers, fn ($ps) => (new ProviderServiceResource($ps))->toArray($request));
    }

    public function store(StoreProviderServiceRequest $request)
    {
        $provider = $this->ensureProvider($request);
        $data = $request->validated();

        if ($provider->providerServices()->where('service_id', $data['service_id'])->exists()) {
            throw new ApiException('CATALOG_001', 422, 'Tu proposes déjà ce service.',
                ['hint' => 'Utilise PUT /provider/services/{id} pour modifier.']);
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
        return response()->noContent(); // 204
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

### 4.6 — ApiResponse paginated helper

Extension de `ApiResponse` :

```php
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
```

### 4.7 — ApiException additions

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

(`CATALOG_001` est levé inline dans le controller pour pouvoir passer `hint` en details.)

### 4.8 — CategoryObserver

```php
class CategoryObserver
{
    public function saved(Category $category): void { Cache::forget('categories:tree'); }
    public function deleted(Category $category): void { Cache::forget('categories:tree'); }
}
```

Enregistrement dans `AppServiceProvider::boot()` :

```php
public function boot(): void
{
    Category::observe(CategoryObserver::class);
}
```

### 4.9 — Seeders

**`CategorySeeder`** : ~25 catégories (10 root + ~15 enfants).

```php
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
        ['name' => 'Dépannage', 'slug' => 'electricite-depannage'],
        ['name' => 'Installation prise', 'slug' => 'electricite-prise'],
    ],
    'coiffure-beaute' => [
        ['name' => 'Coiffure femme', 'slug' => 'coiffure-femme'],
        ['name' => 'Coiffure homme', 'slug' => 'coiffure-homme'],
        ['name' => 'Tressage', 'slug' => 'tressage'],
        ['name' => 'Manucure & Pédicure', 'slug' => 'manucure-pedicure'],
    ],
    'menage' => [
        ['name' => 'Ménage régulier', 'slug' => 'menage-regulier'],
        ['name' => 'Grand ménage', 'slug' => 'menage-grand'],
    ],
    'transport' => [
        ['name' => 'Déménagement', 'slug' => 'demenagement'],
        ['name' => 'Livraison course', 'slug' => 'livraison-course'],
    ],
    'mecanique-auto' => [
        ['name' => 'Vidange', 'slug' => 'vidange'],
        ['name' => 'Diagnostic électronique', 'slug' => 'diagnostic-auto'],
    ],
];
```

**`ServiceSeeder`** : ~50 services, exemples :

```php
$services = [
    'plomberie-fuite' => [
        ['name' => 'Réparation fuite robinet', 'slug' => 'fuite-robinet', 'min_price_estimate' => 8000],
        ['name' => 'Réparation fuite WC', 'slug' => 'fuite-wc', 'min_price_estimate' => 12000],
        ['name' => 'Détection fuite cachée', 'slug' => 'detection-fuite', 'min_price_estimate' => null, 'requires_quote' => true],
    ],
    'coiffure-femme' => [
        ['name' => 'Tissage', 'slug' => 'tissage', 'min_price_estimate' => 25000],
        ['name' => 'Coloration', 'slug' => 'coloration', 'min_price_estimate' => 30000],
        ['name' => 'Soin capillaire', 'slug' => 'soin-capillaire', 'min_price_estimate' => 15000],
    ],
    // ... etc pour les autres sous-catégories
];
```

Détail complet dans le plan d'implémentation. Total ~50 services.

`DatabaseSeeder` orchestre :

```php
public function run(): void
{
    $this->call([
        CategorySeeder::class,
        ServiceSeeder::class,
    ]);
}
```

---

## 5. Routes (additif à `routes/api.php`)

```php
Route::prefix('v1')->group(function () {
    // Sprint 1 routes existantes : auth/*, profile (skip)

    // === Sprint 2 PUBLIC ===
    Route::prefix('public')->group(function () {
        Route::get('categories', [PublicCategoryController::class, 'index']);
        Route::get('categories/tree', [PublicCategoryController::class, 'tree']);
        Route::get('categories/{category:slug}/services', [PublicCategoryController::class, 'services']);
        Route::get('services', [PublicServiceController::class, 'index']);
        Route::get('services/{service:slug}', [PublicServiceController::class, 'show']);
        Route::get('providers/search', [PublicProviderController::class, 'search']);
        Route::get('providers/{user}', [PublicProviderController::class, 'show']);
    });

    // === Sprint 2 PROTECTED (provider seulement) ===
    Route::middleware('auth:sanctum')->prefix('provider')->group(function () {
        Route::get('services', [ProviderServiceController::class, 'index']);
        Route::post('services', [ProviderServiceController::class, 'store']);
        Route::put('services/{id}', [ProviderServiceController::class, 'update']);
        Route::delete('services/{id}', [ProviderServiceController::class, 'destroy']);
    });
});
```

Note : `{category:slug}` et `{service:slug}` utilisent Laravel route-model binding par `getRouteKeyName()='slug'`.
`{user}` pour `providers/{user}` utilise binding par UUID (clé primaire) — donc `GET /public/providers/{uuid}`.

---

## 6. Gestion d'erreurs

| Scénario | Réponse |
|---|---|
| `search` sans `service_id` | 422 `{ errors: { service_id: [...] } }` (Laravel validation standard) |
| `search` avec lat sans lng | 422 `required_with` |
| `search` `service_id` inexistant | 422 `exists:services,id` |
| Provider authenticated route + user type=client | 403 `AUTH_008` |
| Provider PUT/DELETE offre d'un autre | 404 `Offre introuvable.` (anti-IDOR, pas 403) |
| Store offre duplicate | 422 `CATALOG_001` |
| Show service slug inexistant | 404 (route-model binding fail) → render as 422 `CATALOG_003` via custom handler ou 404 brut |
| Show category slug inexistant | 404 → 422 `CATALOG_002` ou 404 brut |

**Décision** : laisser Laravel renvoyer 404 par défaut quand route-model binding échoue. Si on veut le mapper en `CATALOG_002/003`, on le fera via `Model::resolveRouteBinding()` override. Pour Sprint 2 → on laisse 404 standard (Laravel renvoie déjà `{ message: "..." }` JSON).

---

## 7. Stratégie de tests (TDD)

**Total ~32 tests nouveaux** :

### 7.1 — Unit tests (~12)

- `tests/Unit/Services/ProviderSearch/ProviderSearchServiceTest.php` (9 tests) — filters, sort, geo, pagination
- `tests/Unit/Resources/CategoryWithChildrenTest.php` (3 tests) — buildTree algorithm

### 7.2 — Feature tests (~20)

- `tests/Feature/Api/V1/Public/CategoriesTest.php` (6 tests)
- `tests/Feature/Api/V1/Public/ServicesTest.php` (5 tests)
- `tests/Feature/Api/V1/Public/ProviderSearchTest.php` (9 tests, plus complexes)
- `tests/Feature/Api/V1/Provider/ServicesTest.php` (10 tests, incluant IDOR)

### 7.3 — Critères de succès (Definition of Done)

- [ ] **`php artisan test`** : 102+ tests passent (70 Sprint 1 + 32 Sprint 2)
- [ ] **`php artisan route:list --path=api/v1`** : ≥ 21 routes (10 Sprint 1 + 11 Sprint 2)
- [ ] **Seed reproductible** : `php artisan migrate:fresh --seed` crée ~25 catégories + ~50 services (vérifié via count tables)
- [ ] **Smoke test E2E** :
  - `GET /api/v1/public/categories/tree` → 10 catégories root avec children
  - `GET /api/v1/public/services?category_slug=plomberie-fuite` → 3 services
  - `GET /api/v1/public/providers/search?service_id=X` → 200 (vide si pas de provider seed)
  - Login client → `POST /api/v1/provider/services` → 403 `AUTH_008`
  - Login prestataire → `POST /api/v1/provider/services { service_id, price_model: 'fixed', price_amount: 10000 }` → 201
  - Same prestataire → `POST` même service → 422 `CATALOG_001`
- [ ] **IDOR test** : prestataire A ne peut PUT/DELETE l'offre du prestataire B → 404
- [ ] **Cache** : `Cache::has('categories:tree')` après premier `GET tree`
- [ ] **PublicProviderResource** ne contient pas `phone`, `email`, `address`, `latitude`, `longitude`, `firebase_token`, `otp_*`, `password`, `two_factor_*`
- [ ] Tag git `sprint-2-catalog-search` créé et poussé

---

## 8. Hors périmètre

- ❌ RFQ (création d'appel d'offres anonyme par client) → Sprint 3
- ❌ Bids (propositions prestataire sur un RFQ) → Sprint 4
- ❌ PostGIS / colonne `location` geography → Sprint 3 (migration dédiée + cast Spatial)
- ❌ Upload icon/cover_image pour catégories ou services → Sprint 7 (backoffice admin)
- ❌ CRUD admin sur catégories/services (création/édition/suppression) → Sprint 7
- ❌ Notifications push (FCM) au prestataire à la création d'une offre → Sprint 3
- ❌ Localisation i18n (catégories en/fr) → ultérieur (champs `name_en`, `name_fr` ou table `category_translations`)
- ❌ Tags / mots-clés sur services → ultérieur
- ❌ Variant pricing (selon zone géo, taille mission) → ultérieur

---

## 9. Dépendances vers les sprints suivants

À la fin de Sprint 2 :
- Catalogue services peuplé → Sprint 3 peut référencer `services.id` dans les RFQs
- `provider_services` table peuplée → Sprint 3 search PostGIS peut filtrer par offer dispo
- Provider profile structure stable → Sprint 4 (Bids) peut référencer le provider sans changements
- Pattern `ensureProvider()` posé → réutilisable pour /provider/rfqs/nearby (Sprint 3) et /provider/bids (Sprint 4)
