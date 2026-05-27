# LUKASSA — Phase 2 / Sprint 1 — Auth & Profils — Design

**Date** : 2026-05-27
**Auteur** : Daisy + Claude (brainstorming session)
**Statut** : à valider
**Périmètre** : Sprint 1 backend uniquement (API d'authentification phone+OTP, gestion profils, double stratégie Sanctum stateful/Bearer). Aucun front, aucune feature au-delà.
**Dépend de** : Phase 1 / Sprint 0 (Docker + Laravel 12 + migrations PG) ✅

---

## 1. Contexte & motivation

Phase 1 a livré la fondation infrastructure. Phase 2 démarre l'**API métier** sur 4 sprints. Sprint 1 livre l'authentification : sans elle, aucune route métier des sprints suivants ne peut être protégée.

**Choix utilisateur faits en brainstorming** :
1. SMS OTP en **mode simulation** (LogOtpSender + FakeOtpSender) — pas de fournisseur réel en Sprint 1.
2. **Hybride Sanctum** : tokens Bearer pour Flutter, cookies stateful pour Nuxt.
3. **OTP stocké en colonnes `users`** (otp_code_hash, otp_expires_at, otp_attempts, otp_type), hashé en bcrypt.
4. **OTP suffit pour passer `pending` → `active`** (KYC reporté Sprint 7).
5. **Avatar skip** dans Sprint 1.
6. **Rate limiting** : Laravel `throttle:` + lockout après 10 échecs login → status='suspended'.

---

## 2. Architecture cible

### 2.1 — Endpoints livrés

```
PUBLIC (prefix /api/v1)
  POST  /auth/register              { phone, password, password_confirmation, type }
  POST  /auth/verify-otp            { phone, otp }
  POST  /auth/resend-otp            { phone }                        throttle:3,60
  POST  /auth/login                 { phone, password, device_name? } throttle:5,1
  POST  /auth/forgot-password       { phone }                         throttle:3,60
  POST  /auth/reset-password        { phone, otp, password, password_confirmation }
  GET   /sanctum/csrf-cookie        (Nuxt only, fourni par Sanctum)

PROTECTED (auth:sanctum, prefix /api/v1)
  GET   /auth/user                  → UserResource (résumé : id, phone, email, type, status, profile.firstname/lastname)
  POST  /auth/logout                → 204
  GET   /profile                    → ProfileResource (user + profile complet)
  PUT   /profile                    { firstname, lastname, bio, address, city, country?,
                                      latitude?, longitude?, intervention_radius_km?, language? }
```

### 2.2 — Stratégie auth duale

- **Mobile (Flutter)** : `POST /auth/login` avec `device_name` → réponse `{ token: "..." }`. Envoie `Authorization: Bearer xxx`.
- **Web (Nuxt)** : `GET /sanctum/csrf-cookie` puis `POST /auth/login` sans `device_name`. Cookie `laravel_session` posé. Pas de token retourné.
- Sanctum détecte automatiquement le guard via présence du header Bearer vs cookie.

### 2.3 — Structure fichiers

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── AuthController.php
│   │   │   └── ProfileController.php
│   │   ├── Requests/Api/V1/
│   │   │   ├── RegisterRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   ├── VerifyOtpRequest.php
│   │   │   ├── ResendOtpRequest.php
│   │   │   ├── ForgotPasswordRequest.php
│   │   │   ├── ResetPasswordRequest.php
│   │   │   └── UpdateProfileRequest.php
│   │   ├── Resources/Api/V1/
│   │   │   ├── UserResource.php
│   │   │   └── ProfileResource.php
│   │   └── Responses/
│   │       └── ApiResponse.php        ← helper réponse uniforme success/error
│   ├── Services/
│   │   ├── AuthService.php            ← register/login/lockout orchestration
│   │   └── Otp/
│   │       ├── OtpService.php
│   │       ├── OtpSenderInterface.php
│   │       ├── LogOtpSender.php
│   │       └── FakeOtpSender.php
│   ├── Models/
│   │   ├── User.php                   ← HasApiTokens, HasUuids, casts, hidden, fillable
│   │   └── Profile.php                ← relations, casts
│   ├── Exceptions/
│   │   └── ApiException.php           ← code métier + HTTP code + details
│   └── Providers/
│       └── AppServiceProvider.php     ← bind OtpSenderInterface
├── bootstrap/
│   └── app.php                        ← activer routes/api.php + exception render
├── config/
│   └── otp.php                        ← nouveau
├── database/migrations/
│   └── 2026_05_27_180000_add_otp_columns_to_users_table.php   ← nouvelle migration
└── routes/
    └── api.php                        ← refait
```

---

## 3. Décisions techniques

| Sujet | Décision | Justification |
|---|---|---|
| Token strategy | Sanctum (Bearer + stateful) | Hybride mobile/web sans complexité JWT |
| OTP storage | Colonnes `users` (hashé bcrypt) | Pas de table supplémentaire, lookup par phone direct |
| OTP TTL | 10 min, max 5 tentatives | Configurable via `.env` (`config/otp.php`) |
| OTP code | 6 chiffres, hashé bcrypt | Standard SMS, résistant DB leak |
| Login throttle | 5/min par IP (Laravel `throttle:5,1`) | Anti brute-force court terme |
| Login lockout | 10 fails sur 1h → `status='suspended'` | Anti brute-force long terme |
| Password rules | min 8 chars, confirmed | Laravel default Password rule |
| UUID model trait | `HasUuids` (Laravel 11+ natif) | Auto-génère UUID sur create() |
| Réponse uniforme | `{ success, data\|error, meta }` | Conformité conception §10 |
| Codes erreur métier | `AUTH_001` … `AUTH_007` | Init nomenclature pour sprints suivants |
| Validation | FormRequest classes | Découple controller du validate() inline |
| Format API | API Resources Laravel | Évite leak de colonnes (otp_code_hash, etc.) |
| Mass-assignment | `$fillable` strict (pas `$guarded=[]`) | Anti-élévation `type='admin'` ou `status='active'` |

---

## 4. Composants détaillés

### 4.1 — Migration `add_otp_columns_to_users_table`

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('otp_code_hash', 255)->nullable();
    $table->timestamp('otp_expires_at')->nullable();
    $table->unsignedTinyInteger('otp_attempts')->default(0);
    $table->string('otp_type', 30)->nullable(); // 'verify_account' | 'reset_password'
    $table->index('otp_expires_at');
});
```

### 4.2 — `config/otp.php`

```php
return [
    'sender' => env('OTP_SENDER', 'log'),
    'code_length' => env('OTP_CODE_LENGTH', 6),
    'expiration_minutes' => env('OTP_EXPIRATION_MINUTES', 10),
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
];
```

### 4.3 — `.env` additions

```dotenv
OTP_SENDER=fake               # 'fake' renvoie l'OTP dans la réponse JSON (dev)
                              # 'log' écrit dans storage/logs/laravel.log seulement
OTP_CODE_LENGTH=6
OTP_EXPIRATION_MINUTES=10
OTP_MAX_ATTEMPTS=5

LOGIN_MAX_FAILS_PER_HOUR=10
```

### 4.4 — `bootstrap/app.php` activation API routes

Laravel 12 n'active pas `routes/api.php` par défaut. À ajouter dans `withRouting()` :

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',         // ← AJOUT
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();                // ← AJOUT pour Sanctum stateful
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\ApiException $e, $request) {
            return $e->toJsonResponse();
        });
    })->create();
```

### 4.5 — `routes/api.php` (V1)

```php
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;

Route::prefix('v1')->group(function () {
    Route::post('auth/register',        [AuthController::class, 'register']);
    Route::post('auth/verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('auth/resend-otp',      [AuthController::class, 'resendOtp'])->middleware('throttle:3,60');
    Route::post('auth/login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
    Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user',    [AuthController::class, 'user']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('profile',      [ProfileController::class, 'show']);
        Route::put('profile',      [ProfileController::class, 'update']);
    });
});
```

### 4.6 — Model `User`

```php
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['phone', 'email', 'password', 'type'];
    // status, identity_verified_at, otp_*, firebase_token, 2fa_* sont contrôlés en interne

    protected $hidden = [
        'password', 'remember_token',
        'otp_code_hash', 'otp_expires_at', 'otp_attempts', 'otp_type',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'identity_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function profile(): HasOne { return $this->hasOne(Profile::class); }
}
```

### 4.7 — Model `Profile`

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Profile extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'firstname', 'lastname', 'bio',
        'address', 'city', 'country',
        'latitude', 'longitude', 'intervention_radius_km',
        'language',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'average_rating' => 'decimal:1',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

### 4.8 — `App\Services\Otp\OtpService`

```php
class OtpService
{
    public function __construct(private OtpSenderInterface $sender) {}

    public function generateFor(User $user, string $type): string
    {
        $code = (string) random_int(100000, 999999); // 6 digits
        $user->update([
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(config('otp.expiration_minutes')),
            'otp_attempts' => 0,
            'otp_type' => $type,
        ]);
        $this->sender->send($user->phone, $code);
        return $code; // retourné UNIQUEMENT au caller pour mode fake
    }

    public function verify(User $user, string $otp, string $expectedType): void
    {
        if ($user->otp_type !== $expectedType) {
            throw ApiException::otpInvalid();
        }
        if (!$user->otp_expires_at || $user->otp_expires_at->isPast()) {
            throw ApiException::otpExpired();
        }
        if ($user->otp_attempts >= config('otp.max_attempts')) {
            throw ApiException::otpTooManyAttempts();
        }
        if (!Hash::check($otp, $user->otp_code_hash)) {
            $user->increment('otp_attempts');
            throw ApiException::otpInvalid(['attempts_remaining' => config('otp.max_attempts') - $user->otp_attempts]);
        }
        $user->update([
            'otp_code_hash' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_type' => null,
        ]);
    }
}
```

### 4.9 — `FakeOtpSender` vs `LogOtpSender`

```php
class FakeOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $code): void { /* no-op */ }
    public static function lastSent(): ?array { /* in-memory pour tests */ }
}

class LogOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $code): void
    {
        Log::info("[OTP] phone={$phone} code={$code}");
    }
}
```

Le `_dev_otp` dans la réponse JSON n'est ajouté QUE si `OTP_SENDER=fake`. Implémenté côté `AuthController::register` :

```php
$code = $this->otp->generateFor($user, 'verify_account');
$payload = ['message' => 'OTP envoyé', 'user_id' => $user->id];
if (config('otp.sender') === 'fake') $payload['_dev_otp'] = $code;
return ApiResponse::success($payload, 201);
```

### 4.10 — `ApiException` (codes erreur métier)

```php
class ApiException extends \Exception
{
    public function __construct(
        public readonly string $code,
        public readonly int $httpStatus,
        string $message,
        public readonly array $details = [],
    ) { parent::__construct($message); }

    public function toJsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $this->code, 'message' => $this->getMessage(), 'details' => $this->details],
            'meta' => ['timestamp' => now()->toIso8601String(), 'version' => 'v1'],
        ], $this->httpStatus);
    }

    public static function otpInvalid(array $details = []): self
    { return new self('AUTH_001', 422, 'Code OTP invalide.', $details); }

    public static function otpExpired(): self
    { return new self('AUTH_002', 422, 'Code OTP expiré, demande un nouveau code.'); }

    public static function otpTooManyAttempts(): self
    { return new self('AUTH_003', 429, 'Trop de tentatives OTP, demande un nouveau code.'); }

    public static function invalidCredentials(): self
    { return new self('AUTH_004', 401, 'Identifiants invalides.'); }

    public static function accountNotVerified(): self
    { return new self('AUTH_005', 403, 'Compte non vérifié. Confirme ton numéro via OTP.'); }

    public static function accountSuspended(): self
    { return new self('AUTH_006', 403, 'Compte suspendu. Contacte le support.'); }

    public static function invalidAccountType(): self
    { return new self('AUTH_007', 422, "Type de compte invalide."); }
}
```

### 4.11 — `ApiResponse` helper

```php
class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => ['timestamp' => now()->toIso8601String(), 'version' => 'v1'],
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
            'meta' => ['timestamp' => now()->toIso8601String(), 'version' => 'v1'],
        ], $status);
    }
}
```

---

## 5. Flux fonctionnels (récap)

| Flux | Étapes |
|---|---|
| **Register** | `User::create(status=pending)` → `Profile::create()` lié → `OtpService::generateFor($user, 'verify_account')` → réponse 201 |
| **Verify OTP** | `OtpService::verify($user, $otp, 'verify_account')` → `status='active'` → 200 avec UserResource |
| **Resend OTP** | `OtpService::generateFor($user, $existingType)` → 200 |
| **Login Bearer** | `AuthService::attemptLogin()` → check status → `$user->createToken($device_name)` → 200 + token |
| **Login Stateful** | `AuthService::attemptLogin()` → `Auth::login($user)` → 200 (cookie posé par framework) |
| **Forgot password** | `OtpService::generateFor($user, 'reset_password')` → toujours 200 (anti-leak) |
| **Reset password** | `OtpService::verify($user, $otp, 'reset_password')` → `update password` → `tokens()->delete()` → 200 |
| **Logout Bearer** | `$request->user()->currentAccessToken()->delete()` → 204 |
| **Logout stateful** | `Auth::guard('web')->logout()` + `session()->invalidate()` → 204 |
| **Profile show** | `ProfileResource::make($user->load('profile'))` → 200 |
| **Profile update** | `Profile::update($validated)` → 200 |

---

## 6. Stratégie de tests (TDD)

**Pyramide** :
- 23+ feature tests HTTP end-to-end (RefreshDatabase, PG via phpunit.xml)
- 8+ unit tests OtpService (FakeOtpSender stub)

**Inventaire feature tests** dans `tests/Feature/Api/V1/Auth/` :

```
RegisterTest                   – 7 scénarios (nominal + 6 rejets validation/security)
VerifyOtpTest                  – 5 scénarios (active, expiré, mauvais code, 5 tentatives, resend)
LoginTest                      – 7 scénarios (Bearer, stateful, pending, suspended, deleted, throttle, lockout)
LogoutTest                     – 2 scénarios (Bearer, stateful)
ForgotResetPasswordTest        – 3 scénarios (forgot, reset OK, reset avec mauvais type)
ProfileTest                    – 4 scénarios (show, update, validation lat, 401)
```

**Unit tests** dans `tests/Unit/Services/Otp/OtpServiceTest.php` :
- generate stores hashed code with TTL
- verify rejects expired
- verify rejects wrong type
- verify rejects after max_attempts
- verify increments attempts on wrong code
- verify clears columns on success

**Setup PHPUnit** : `phpunit.xml` doit pointer vers la même DB PG (ou créer une DB de test `lukassa_test`). Décision : créer `lukassa_test` séparée pour ne pas polluer dev. À gérer dans le plan d'implémentation.

---

## 7. Critères de succès Sprint 1

- [ ] 30+ tests feature/unit passent : `php artisan test`
- [ ] OTP visible dans `storage/logs/laravel.log` (mode `log`) OU dans réponse JSON `_dev_otp` (mode `fake`)
- [ ] Postman flow complet : register → verify-otp → login (Bearer) → GET /auth/user OK
- [ ] Postman flow stateful : csrf-cookie → login → GET /auth/user OK via cookie
- [ ] Logout Bearer ne révoque QUE le token courant (test : 2 devices, logout 1, l'autre marche encore)
- [ ] Après 10 fails login : `users.status='suspended'`, login renvoie 403 `AUTH_006`
- [ ] Tentative `register` avec `type='admin'` : 422 `AUTH_007`
- [ ] Tentative `register` avec `status` dans payload : ignoré (mass-assignment)
- [ ] Réponse `success: false` uniforme sur toutes les erreurs métier
- [ ] Pas de leak des colonnes `otp_*`, `password`, `two_factor_*` dans `UserResource`

---

## 8. Hors périmètre

- ❌ Vrai fournisseur SMS (Twilio, Africa's Talking) → Phase 3 ou plus tard
- ❌ Vérification d'identité (KYC) prestataire → Sprint 7 (backoffice admin)
- ❌ Upload avatar → Sprint ultérieur
- ❌ Recovery codes / 2FA app (Jetstream les a en base mais pas exploités API) → futur
- ❌ Passkeys / WebAuthn → futur
- ❌ Email-based recovery → on est phone-first
- ❌ Onboarding wizard (multi-étapes) → reste à 1 étape register + 1 étape verify
- ❌ Profil prestataire spécifique (services proposés) → Sprint 2
- ❌ Notifications push FCM → Sprint 3

---

## 9. Dépendances vers les sprints suivants

À la fin de Sprint 1, le Sprint 2 peut démarrer avec :
- `middleware('auth:sanctum')` opérationnel sur toutes les routes protégées
- `$request->user()` accessible dans tous les contrôleurs protégés
- `UserResource` / `ProfileResource` réutilisables
- Convention codes erreur `XXX_NNN` et format réponse uniforme établis
- Pattern Adapter (`OtpSenderInterface`) servira de modèle pour `PaymentAdapter` Sprint 5
