# LUKASSA — Phase 2 / Sprint 3a — RFQ + PostGIS — Design

**Date** : 2026-05-30
**Auteur** : Daisy + Claude (brainstorming session)
**Statut** : à valider
**Périmètre** : Sprint 3a backend — migration PostGIS (profiles + rfqs avec GENERATED COLUMN), refactor `ProviderSearchService` Sprint 2 vers `ST_DWithin`, CRUD RFQ côté client, `GET /provider/rfqs/nearby` synchrone. Pas de queue, pas de notifications (Sprint 3b).
**Dépend de** : Phase 1 Sprint 0 ✅, Sprint 1 ✅, Sprint 2 ✅ (129 tests, 21 routes).

---

## 1. Contexte & motivation

Sprint 2 a livré le catalogue public + provider services + recherche géo Haversine. Sprint 3a pose la fondation **PostGIS** (longue dette technique du Sprint 0 où les migrations utilisent `decimal(lat,lng)`) et livre le **module RFQ côté client** + le **GET /provider/rfqs/nearby** synchrone.

**Choix utilisateur en brainstorming** :
1. **Splitter Sprint 3** en 3a (PostGIS + RFQ CRUD + nearby sync) puis 3b (job queue + notifications).
2. **PostgreSQL GENERATED COLUMN STORED** : `location geography(Point, 4326)` est auto-calculé depuis lat/lng à chaque INSERT/UPDATE par PG. Zero code Eloquent, zero observer, zero backfill manuel.
3. **Migrer le `ProviderSearchService` Sprint 2 vers `ST_DWithin`** : performance bien supérieure (index GIST). API publique inchangée — les 9 tests existants doivent passer sans modif.
4. **RFQ expiration optionnelle** : champ `expires_in_hours` accepté au POST (défaut 72h).
5. **Provider nearby logique** : inférée du profil (rayon = `profile.intervention_radius_km`, services = `provider_services WHERE is_available=true`).

---

## 2. Architecture cible

### 2.1 — Endpoints livrés (5 routes)

```
PROTECTED client (auth:sanctum + user.type === 'client')
  POST   /api/v1/client/rfqs            { service_id, description, address, latitude, longitude, preferred_date?, expires_in_hours? }
  GET    /api/v1/client/rfqs            ?status=&page=
  GET    /api/v1/client/rfqs/{id}
  DELETE /api/v1/client/rfqs/{id}       → status='cancelled', 204

PROTECTED prestataire (auth:sanctum + user.type === 'prestataire')
  GET    /api/v1/provider/rfqs/nearby   ?page=
```

### 2.2 — Logique métier `nearby` (inférée du profil)

Le prestataire ne voit QUE les RFQs :
1. **Géo** : `ST_DWithin(rfq.location, provider.profile.location, intervention_radius_km * 1000 m)`.
2. **Service** : `rfq.service_id` ∈ `provider_services` du prestataire (avec `is_available=true`).
3. **Statut** : `rfq.status = 'open'`.
4. **Non expirés** : `expires_at IS NULL OR expires_at > now()`.
5. **Géoloc requise** : provider DOIT avoir lat/lng (sinon liste vide).

Aucun paramètre query — tout est inféré depuis l'utilisateur courant. Pagination 15.

### 2.3 — Structure fichiers

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Client/
│   │   │   │   └── RfqController.php             (store, index, show, destroy)
│   │   │   └── Provider/
│   │   │       └── RfqController.php             (nearby)
│   │   ├── Requests/Api/V1/Client/
│   │   │   └── StoreRfqRequest.php
│   │   └── Resources/Api/V1/
│   │       ├── RfqResource.php                   (POV client)
│   │       └── ProviderRfqResource.php           (POV prestataire, anonymisé)
│   ├── Models/
│   │   ├── Rfq.php                               (nouveau, HasUuids)
│   │   └── User.php                              (ajouter `rfqs()` relation)
│   ├── Services/
│   │   ├── ProviderSearch/
│   │   │   └── ProviderSearchService.php         (REFACTOR Haversine → ST_DWithin)
│   │   └── Rfq/
│   │       └── RfqMatchingService.php            (nouveau, PostGIS query)
│   └── Exceptions/
│       └── ApiException.php                      (+AUTH_009 et RFQ_002 factories)
├── database/
│   ├── factories/
│   │   └── RfqFactory.php
│   └── migrations/
│       ├── 2026_05_30_190000_add_location_to_profiles.php
│       └── 2026_05_30_190001_add_location_to_rfqs.php
└── tests/
    ├── Unit/
    │   ├── Models/RfqTest.php
    │   └── Services/Rfq/RfqMatchingServiceTest.php
    └── Feature/
        ├── Migrations/PostGISLocationTest.php
        └── Api/V1/
            ├── Client/RfqsTest.php
            └── Provider/RfqsNearbyTest.php
```

---

## 3. Décisions techniques

| Sujet | Décision | Justification |
|---|---|---|
| PostGIS column type | `geography(Point, 4326)` | Distance en mètres directement, WGS84 sphérique standard mobile/web |
| Generated column | `GENERATED ALWAYS AS (...) STORED` | Auto-fill par PG depuis lat/lng. Zero code Eloquent, zero observer, zero backfill manuel. |
| Index spatial | `USING GIST (location)` | Permet `ST_DWithin` en O(log n) au lieu de FULL SCAN |
| `ST_MakePoint` ordre | `(longitude, latitude)` | Convention PostGIS X, Y — piège classique. Toujours longitude AVANT latitude. |
| Refactor Sprint 2 | Oui, PostGIS partout | Pas de coexistence de 2 systèmes, code consistant |
| Eloquent Spatial v4 | Non utilisé activement | On préfère SQL raw pour la clarté et le contrôle. Package reste installé pour usage futur Sprint 5+. |
| RFQ expiration | `expires_in_hours` optionnel, défaut 72h | Flexible côté client, sain par défaut |
| RFQ status fillable | NON (mass-assignment guard) | Transitions contrôlées par méthodes (forceFill internes) |
| Provider nearby logic | Inférée du profil, aucun query param | Plus simple, plus prévisible côté UX |
| Auth client/provider | Check inline `$user->type === 'X'` dans controller | Cohérent avec Sprint 2 (`ensureProvider()`). Refactor en middleware quand 3+ controllers en ont besoin. |
| Codes erreur | AUTH_009 (réservé client), RFQ_002 (transition status invalide) | Convention Sprint 1-2 étendue |

---

## 4. Composants détaillés

### 4.1 — Migration `add_location_to_profiles`

```php
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE profiles
            ADD COLUMN location geography(Point, 4326)
            GENERATED ALWAYS AS (
                CASE
                    WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                    THEN ST_SetSRID(
                        ST_MakePoint(longitude::double precision, latitude::double precision),
                        4326
                    )::geography
                    ELSE NULL
                END
            ) STORED
        ");

        DB::statement("CREATE INDEX profiles_location_idx ON profiles USING GIST (location)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS profiles_location_idx");
        DB::statement("ALTER TABLE profiles DROP COLUMN IF EXISTS location");
    }
};
```

### 4.2 — Migration `add_location_to_rfqs`

Strictement identique en structure, table = `rfqs`. Index nommé `rfqs_location_idx`.

### 4.3 — Model `Rfq`

```php
class Rfq extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id', 'service_id', 'description', 'address',
        'latitude', 'longitude', 'preferred_date', 'expires_at',
        'is_anonymous',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'preferred_date' => 'datetime',
        'expires_at' => 'datetime',
        'is_anonymous' => 'boolean',
    ];

    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }

    public function scopeOpen($q) { return $q->where('status', 'open'); }
    public function scopeNotExpired($q)
    {
        return $q->where(function ($w) {
            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
```

Update `User.php` : ajouter
```php
public function rfqs(): HasMany
{
    return $this->hasMany(Rfq::class, 'client_id');
}
```

### 4.4 — `RfqFactory`

```php
public function definition(): array
{
    return [
        'client_id' => User::factory()->client(),
        'service_id' => Service::factory(),
        'description' => fake()->paragraph(),
        'address' => fake()->streetAddress(),
        'latitude' => fake()->randomFloat(8, -1, 1),
        'longitude' => fake()->randomFloat(8, 8, 14),
        'preferred_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
        'status' => 'open',
        'is_anonymous' => true,
        'expires_at' => now()->addHours(72),
    ];
}

public function expired(): static
{
    return $this->state(fn () => ['expires_at' => now()->subHour()]);
}

public function cancelled(): static
{
    return $this->state(fn () => ['status' => 'cancelled']);
}
```

### 4.5 — `StoreRfqRequest`

```php
public function rules(): array
{
    return [
        'service_id' => ['required', 'uuid', 'exists:services,id'],
        'description' => ['required', 'string', 'min:10', 'max:2000'],
        'address' => ['required', 'string', 'max:255'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
        'preferred_date' => ['nullable', 'date', 'after:now'],
        'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:9999'],
    ];
}
```

### 4.6 — `Client\RfqController`

```php
final class RfqController extends Controller
{
    public function store(StoreRfqRequest $request)
    {
        $client = $this->ensureClient($request);
        $data = $request->validated();
        $hours = $data['expires_in_hours'] ?? 72;

        $rfq = $client->rfqs()->create([
            'service_id' => $data['service_id'],
            'description' => $data['description'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'preferred_date' => $data['preferred_date'] ?? null,
            'expires_at' => now()->addHours($hours),
            'is_anonymous' => true,
        ]);

        // Sprint 3b dispatchera ici MatchProvidersForRfqJob::dispatch($rfq);

        $rfq->load('service.category');
        return ApiResponse::success((new RfqResource($rfq))->toArray($request), 201);
    }

    public function index(Request $request)
    {
        $client = $this->ensureClient($request);
        $query = $client->rfqs()
            ->with('service.category')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rfqs = $query->paginate(15);
        return ApiResponse::paginated($rfqs, fn ($r) => (new RfqResource($r))->toArray($request));
    }

    public function show(string $id, Request $request)
    {
        $client = $this->ensureClient($request);
        $rfq = $client->rfqs()->where('id', $id)->with('service.category')->first();
        if (!$rfq) {
            abort(404, 'RFQ introuvable.');
        }
        return ApiResponse::success((new RfqResource($rfq))->toArray($request));
    }

    public function destroy(string $id, Request $request)
    {
        $client = $this->ensureClient($request);
        $rfq = $client->rfqs()->where('id', $id)->first();
        if (!$rfq) {
            abort(404, 'RFQ introuvable.');
        }
        if ($rfq->status !== 'open') {
            throw new ApiException('RFQ_002', 422, 'Seul un RFQ ouvert peut être annulé.');
        }
        $rfq->forceFill(['status' => 'cancelled'])->save();
        return response()->noContent();
    }

    private function ensureClient(Request $request): User
    {
        $user = $request->user();
        if ($user->type !== 'client') {
            throw new ApiException('AUTH_009', 403, 'Action réservée aux clients.');
        }
        return $user;
    }
}
```

### 4.7 — `Provider\RfqController`

```php
final class RfqController extends Controller
{
    public function nearby(Request $request, RfqMatchingService $matcher)
    {
        $user = $request->user();
        if ($user->type !== 'prestataire') {
            throw ApiException::accountUnauthorized(); // AUTH_008 Sprint 2
        }

        $rfqs = $matcher->findNearbyForProvider($user);

        return ApiResponse::paginated(
            $rfqs,
            fn ($r) => (new ProviderRfqResource($r))->toArray($request)
        );
    }
}
```

### 4.8 — `RfqMatchingService`

```php
final class RfqMatchingService
{
    public function findNearbyForProvider(User $provider): LengthAwarePaginator
    {
        $profile = $provider->profile;
        if (!$profile || $profile->latitude === null || $profile->longitude === null) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $offeredServiceIds = $provider->providerServices()
            ->where('is_available', true)
            ->pluck('service_id');

        if ($offeredServiceIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $radiusMeters = ($profile->intervention_radius_km ?? 10) * 1000;
        $providerLat = (float) $profile->latitude;
        $providerLng = (float) $profile->longitude;

        return Rfq::query()
            ->select([
                'rfqs.*',
                DB::raw($this->distanceExpr($providerLat, $providerLng).' AS distance_km'),
            ])
            ->whereIn('service_id', $offeredServiceIds)
            ->where('status', 'open')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('location')
            ->whereRaw(
                "ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$providerLng, $providerLat, $radiusMeters]
            )
            ->with('service.category')
            ->orderBy('distance_km')
            ->paginate(15);
    }

    private function distanceExpr(float $lat, float $lng): string
    {
        // Coords castées (float) — validées en amont par FormRequest pour ProviderSearchService.
        // Pour ce service, les coords viennent du profile DB, donc déjà float DB-side.
        return "(ST_Distance(location, ST_SetSRID(ST_MakePoint($lng, $lat), 4326)::geography) / 1000.0)";
    }
}
```

### 4.9 — Refactor `ProviderSearchService` (PostGIS)

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
                    ? "(ST_Distance(profiles.location, "
                      . $this->pointExpr((float) $filters['lat'], (float) $filters['lng'])
                      . ") / 1000.0) AS distance_km"
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
            $radiusMeters = ($filters['radius_km'] ?? 20) * 1000;
            $query->whereNotNull('profiles.location')
                  ->whereRaw(
                      "ST_DWithin(profiles.location, "
                      . $this->pointExpr((float) $filters['lat'], (float) $filters['lng'])
                      . ", ?)",
                      [$radiusMeters]
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

    private function pointExpr(float $lat, float $lng): string
    {
        return "ST_SetSRID(ST_MakePoint($lng, $lat), 4326)::geography";
    }
}
```

**Différences vs Sprint 2** :
- `acos/cos/sin/radians` → `ST_Distance` + `ST_DWithin`
- Distance en mètres natif PostGIS, divisée par 1000 pour km
- Index GIST utilisé automatiquement (`ST_DWithin` avant `ST_Distance`)
- API publique inchangée : `search(array): LengthAwarePaginator`. Les 9 unit tests + 9 feature tests Sprint 2 doivent passer sans modif.

### 4.10 — Resources

**`RfqResource`** (POV client — tout exposer) :

```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'description' => $this->description,
        'address' => $this->address,
        'latitude' => (float) $this->latitude,
        'longitude' => (float) $this->longitude,
        'preferred_date' => $this->preferred_date?->toIso8601String(),
        'status' => $this->status,
        'expires_at' => $this->expires_at?->toIso8601String(),
        'created_at' => $this->created_at?->toIso8601String(),
        'service' => $this->whenLoaded('service', fn () => [
            'id' => $this->service->id,
            'name' => $this->service->name,
            'category' => $this->service?->category ? [
                'name' => $this->service->category->name,
            ] : null,
        ]),
        'bids_count' => 0, // placeholder Sprint 4
    ];
}
```

**`ProviderRfqResource`** (POV prestataire — anonymisé, pas de `client_id` ni nom client) :

```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'description' => $this->description,
        'address' => $this->address,
        'latitude' => (float) $this->latitude,
        'longitude' => (float) $this->longitude,
        'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
        'preferred_date' => $this->preferred_date?->toIso8601String(),
        'expires_at' => $this->expires_at?->toIso8601String(),
        'service' => $this->whenLoaded('service', fn () => [
            'id' => $this->service->id,
            'name' => $this->service->name,
        ]),
        // PAS de : client_id, client.firstname, status (toujours 'open')
    ];
}
```

### 4.11 — ApiException additions

```php
public static function actionReservedForClients(): self
{
    return new self('AUTH_009', 403, 'Action réservée aux clients.');
}

// RFQ_001 réservé pour usage futur (not found métier). Pour l'instant, on utilise abort(404) standard.
// RFQ_002 levé inline dans le controller (transition status invalide).
```

---

## 5. Routes (additions dans `routes/api.php`)

À ajouter **INSIDE** le block `Route::middleware('auth:sanctum')->group(...)` existant :

```php
Route::prefix('client')->group(function () {
    Route::post('rfqs',          [\App\Http\Controllers\Api\V1\Client\RfqController::class, 'store']);
    Route::get('rfqs',           [\App\Http\Controllers\Api\V1\Client\RfqController::class, 'index']);
    Route::get('rfqs/{id}',      [\App\Http\Controllers\Api\V1\Client\RfqController::class, 'show']);
    Route::delete('rfqs/{id}',   [\App\Http\Controllers\Api\V1\Client\RfqController::class, 'destroy']);
});

// existing provider/services routes from Sprint 2 — ADD inside the same provider prefix block:
Route::get('rfqs/nearby', [\App\Http\Controllers\Api\V1\Provider\RfqController::class, 'nearby']);
```

**Note** : `rfqs/nearby` doit venir AVANT toute future route `rfqs/{id}` côté provider (anti-glouton matching).

---

## 6. Tests (TDD strict)

### 6.1 — Inventaire (~30 nouveaux tests)

**Unit** (~10 nouveaux) :
- `tests/Unit/Models/RfqTest.php` — 5 tests
- `tests/Unit/Services/Rfq/RfqMatchingServiceTest.php` — 5 tests

**Migrations** (4 tests) :
- `tests/Feature/Migrations/PostGISLocationTest.php`

**Feature** (~16 nouveaux) :
- `tests/Feature/Api/V1/Client/RfqsTest.php` — 10 tests
- `tests/Feature/Api/V1/Provider/RfqsNearbyTest.php` — 8 tests

**Regression** (18 existants Sprint 2 — doivent rester verts) :
- `tests/Unit/Services/ProviderSearch/ProviderSearchServiceTest.php` (9)
- `tests/Feature/Api/V1/Public/ProviderSearchTest.php` (9)

Cible cumulative : **159+ tests** (129 actuels + 30 nouveaux). Zero regression.

### 6.2 — Critères de succès (Definition of Done)

- [ ] `php artisan test` : 159+ tests passent
- [ ] 2 migrations PostGIS exécutées sur lukassa + lukassa_test
- [ ] `\d profiles` montre `location | geography(Point,4326)` GENERATED ALWAYS AS STORED
- [ ] `pg_indexes` montre `profiles_location_idx` et `rfqs_location_idx` USING GIST
- [ ] `php artisan route:list --path=api/v1` : ≥ 26 routes (21 Sprints 1-2 + 5 Sprint 3a)
- [ ] Smoke E2E :
  - Login client → `POST /client/rfqs` → 201
  - Login client → `GET /client/rfqs` → liste paginée
  - Login client → `DELETE /client/rfqs/{id}` → 204 + status='cancelled'
  - Login prestataire → `POST /client/rfqs` → 403 AUTH_009
  - Login client → `GET /provider/rfqs/nearby` → 403 AUTH_008
- [ ] `ProviderRfqResource` ne contient ni `client_id` ni `client_name`/`client_phone`/`client_email`
- [ ] Tag git `sprint-3a-rfq-postgis` poussé sur GitHub

---

## 7. Hors périmètre Sprint 3a

- ❌ Queue job `MatchProvidersForRfqJob` → Sprint 3b
- ❌ Notifications persistées + 4 endpoints CRUD lecture → Sprint 3b
- ❌ Mock push (NotificationSenderInterface, LogPushNotifier, FakePushNotifier) → Sprint 3b
- ❌ Auto-expire RFQs (cron) → Sprint 8
- ❌ Bids/sélection → Sprint 4
- ❌ Reverse geocoding adresse ↔ coords → ultérieur
- ❌ RFQ avec photos → ultérieur
- ❌ Chat client/prestataire sur RFQ → Sprint 6
- ❌ Statistiques (taux conversion RFQ → Order) → Sprint 7 admin

---

## 8. Dépendances vers les sprints suivants

À la fin de Sprint 3a :
- `rfqs` table peuplée + index GIST → Sprint 3b dispatche le job sur `MatchProvidersForRfqJob`
- `RfqMatchingService::findNearbyForProvider()` réutilisé en Sprint 3b par le Job pour trouver les candidats à notifier
- `Client\RfqController::store()` aura juste à ajouter `MatchProvidersForRfqJob::dispatch($rfq)` après le `$rfq = create()` — 1 ligne en Sprint 3b
- `ProviderSearchService` migré vers PostGIS → Sprint 4 (Bids) bénéficie du pattern ST_DWithin si recherche géo nécessaire
- `RfqResource.bids_count` placeholder (=0) → Sprint 4 le remplit avec `$this->bids()->count()`
