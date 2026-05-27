# LUKASSA Sprint 1 — Auth & Profils Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer l'API d'authentification phone+OTP de LUKASSA (register, verify-otp, login Bearer/stateful, forgot/reset password, profile show/update) avec rate-limiting, lockout, et codes d'erreur métier uniformes.

**Architecture:** Laravel 12 + Sanctum hybride (Bearer pour Flutter, stateful cookies pour Nuxt). OTP simulé via `OtpSenderInterface` (pattern Adapter) avec `LogOtpSender` et `FakeOtpSender`. OTP hashé bcrypt sur colonnes `users`. TDD strict avec ~30+ tests feature + unit.

**Tech Stack:** Laravel 12, PHP 8.3, Sanctum 4, PostgreSQL 16+PostGIS, Redis 7 (sessions/queue/cache), PHPUnit 11, predis, matanyadaev/laravel-eloquent-spatial.

**Référence spec :** `docs/superpowers/specs/2026-05-27-sprint1-auth-profiles-design.md`
**Prérequis :** Phase 1 Sprint 0 ✅ (Docker up, Laravel 12 installé, 23 migrations passées, repo GitHub configuré).

---

## Structure de fichiers cible

```
backend/
├── app/
│   ├── Exceptions/
│   │   └── ApiException.php                    ← Task 5
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── AuthController.php              ← Tasks 13-19
│   │   │   └── ProfileController.php           ← Tasks 20-21
│   │   ├── Requests/Api/V1/
│   │   │   ├── RegisterRequest.php             ← Task 13
│   │   │   ├── VerifyOtpRequest.php            ← Task 14
│   │   │   ├── ResendOtpRequest.php            ← Task 15
│   │   │   ├── LoginRequest.php                ← Task 16
│   │   │   ├── ForgotPasswordRequest.php       ← Task 18
│   │   │   ├── ResetPasswordRequest.php        ← Task 19
│   │   │   └── UpdateProfileRequest.php        ← Task 21
│   │   ├── Resources/Api/V1/
│   │   │   ├── UserResource.php                ← Task 12
│   │   │   └── ProfileResource.php             ← Task 12
│   │   └── Responses/
│   │       └── ApiResponse.php                 ← Task 5
│   ├── Models/
│   │   ├── User.php                            ← Task 6 (modify default)
│   │   └── Profile.php                         ← Task 7
│   ├── Providers/
│   │   └── AppServiceProvider.php              ← Task 8 (bind OtpSenderInterface)
│   └── Services/
│       ├── AuthService.php                     ← Task 10
│       └── Otp/
│           ├── OtpSenderInterface.php          ← Task 8
│           ├── LogOtpSender.php                ← Task 8
│           ├── FakeOtpSender.php               ← Task 8
│           └── OtpService.php                  ← Task 9
├── bootstrap/
│   └── app.php                                 ← Task 4 (modify)
├── config/
│   └── otp.php                                 ← Task 3 (new)
├── database/
│   ├── factories/
│   │   ├── UserFactory.php                     ← Task 6
│   │   └── ProfileFactory.php                  ← Task 7
│   └── migrations/
│       └── 2026_05_27_180000_add_otp_columns_to_users_table.php  ← Task 2
├── routes/
│   └── api.php                                 ← Task 11
└── tests/
    ├── Feature/Api/V1/
    │   ├── Auth/
    │   │   ├── RegisterTest.php                ← Task 13
    │   │   ├── VerifyOtpTest.php               ← Task 14
    │   │   ├── ResendOtpTest.php               ← Task 15
    │   │   ├── LoginTest.php                   ← Task 16
    │   │   ├── LogoutTest.php                  ← Task 17
    │   │   ├── ForgotPasswordTest.php          ← Task 18
    │   │   └── ResetPasswordTest.php           ← Task 19
    │   └── ProfileTest.php                     ← Tasks 20-21
    └── Unit/Services/
        ├── Otp/OtpServiceTest.php              ← Task 9
        └── AuthServiceTest.php                 ← Task 10
```

---

## Task 1 : Setup PHPUnit + DB de test `lukassa_test`

**Files:**
- Modify: `backend/phpunit.xml`
- Modify: `backend/.env.testing` (new)
- Run: SQL `CREATE DATABASE lukassa_test`

- [ ] **Step 1.1 : Créer la DB de test dans PostgreSQL**

Run: `docker exec -i lukassa_postgres psql -U postgres -c "CREATE DATABASE lukassa_test;"`
Expected: `CREATE DATABASE`

Si déjà existante : `ERROR: database "lukassa_test" already exists` — pas grave, idempotent.

- [ ] **Step 1.2 : Activer PostGIS sur la DB de test**

Run: `docker exec -i lukassa_postgres psql -U postgres -d lukassa_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"`
Expected: `CREATE EXTENSION`

- [ ] **Step 1.3 : Créer `backend/.env.testing`**

Create file `backend/.env.testing` :

```dotenv
APP_NAME="LUKASSA API (test)"
APP_ENV=testing
APP_KEY=base64:BlRlP0ce+1PY7PkhHshPuZiFI3Dypy0xasyLlMHiHiQ=
APP_DEBUG=true
APP_URL=http://localhost:8001

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lukassa_test
DB_USERNAME=postgres
DB_PASSWORD=postgres

CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
BCRYPT_ROUNDS=4

OTP_SENDER=fake
OTP_CODE_LENGTH=6
OTP_EXPIRATION_MINUTES=10
OTP_MAX_ATTEMPTS=5
LOGIN_MAX_FAILS_PER_HOUR=10
```

Notes : `BCRYPT_ROUNDS=4` accélère les tests (Hash::make passe de ~200ms à <10ms). `CACHE_STORE=array` et `SESSION_DRIVER=array` évitent de dépendre de Redis en tests.

- [ ] **Step 1.4 : Modifier `backend/phpunit.xml`**

Lire le `phpunit.xml` existant puis remplacer les variables `<env>` par :

```xml
<env name="APP_ENV" value="testing"/>
<env name="APP_MAINTENANCE_DRIVER" value="file"/>
<env name="BCRYPT_ROUNDS" value="4"/>
<env name="CACHE_STORE" value="array"/>
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="lukassa_test"/>
<env name="MAIL_MAILER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="TELESCOPE_ENABLED" value="false"/>
```

- [ ] **Step 1.5 : Vérifier que les tests existants par défaut passent toujours**

Run: `cd /Applications/MAMP/htdocs/lukassa/backend && php artisan test 2>&1 | tail -10`
Expected: tous les tests existants passent (probablement 2 tests par défaut de Laravel 12).

Si erreur "database lukassa_test does not exist" : revérifier 1.1.

- [ ] **Step 1.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/phpunit.xml backend/.env.testing && \
  git commit -m "$(cat <<'EOF'
chore(sprint-1): setup PHPUnit avec DB de test lukassa_test

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Note : le `.gitignore` actuel a `backend/.env` (match exact, pas glob), donc `.env.testing` n'est PAS ignoré. Vérifier que `git status` montre bien `.env.testing` en untracked avant le commit.

---

## Task 2 : Migration `add_otp_columns_to_users_table`

**Files:**
- Create: `backend/database/migrations/2026_05_27_180000_add_otp_columns_to_users_table.php`

- [ ] **Step 2.1 : Créer la migration**

Create file `backend/database/migrations/2026_05_27_180000_add_otp_columns_to_users_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code_hash', 255)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->string('otp_type', 30)->nullable();

            $table->index('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['otp_expires_at']);
            $table->dropColumn(['otp_code_hash', 'otp_expires_at', 'otp_attempts', 'otp_type']);
        });
    }
};
```

- [ ] **Step 2.2 : Lancer migrate sur DB dev**

Run: `cd backend && php artisan migrate 2>&1 | tail -3`
Expected: `2026_05_27_180000_add_otp_columns_to_users_table ........ DONE`

- [ ] **Step 2.3 : Vérifier en DB que les colonnes existent**

Run: `docker exec -i lukassa_postgres psql -U postgres -d lukassa -c "\d users" 2>&1 | grep otp`
Expected: 4 lignes : `otp_code_hash`, `otp_expires_at`, `otp_attempts`, `otp_type`.

- [ ] **Step 2.4 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/database/migrations/ && \
  git commit -m "feat(sprint-1): add otp columns to users (hash, expires_at, attempts, type)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3 : `config/otp.php` + `.env` additions

**Files:**
- Create: `backend/config/otp.php`
- Modify: `backend/.env`

- [ ] **Step 3.1 : Créer `backend/config/otp.php`**

```php
<?php

return [
    'sender' => env('OTP_SENDER', 'log'),
    'code_length' => env('OTP_CODE_LENGTH', 6),
    'expiration_minutes' => env('OTP_EXPIRATION_MINUTES', 10),
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
];
```

- [ ] **Step 3.2 : Ajouter les variables OTP au `.env`**

Lire `backend/.env`, puis ajouter en fin de fichier :

```dotenv

OTP_SENDER=fake
OTP_CODE_LENGTH=6
OTP_EXPIRATION_MINUTES=10
OTP_MAX_ATTEMPTS=5

LOGIN_MAX_FAILS_PER_HOUR=10
```

- [ ] **Step 3.3 : Vérifier que la config est lue**

Run: `cd backend && php artisan config:clear && php artisan tinker --execute='echo config("otp.code_length"), PHP_EOL;'`
Expected: `6`

- [ ] **Step 3.4 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/config/otp.php && \
  git commit -m "feat(sprint-1): add OTP config (sender, length, expiration, max_attempts)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

Note : `backend/.env` est gitignoré, ce qui est voulu (variables locales).

---

## Task 4 : Activer `routes/api.php` + Sanctum stateful + exception handler

**Files:**
- Modify: `backend/bootstrap/app.php`

- [ ] **Step 4.1 : Lire le `bootstrap/app.php` actuel**

Run: `cat backend/bootstrap/app.php`
Expected: voir l'appel `Application::configure()->withRouting(...)->withMiddleware(...)->withExceptions(...)->create()`.

- [ ] **Step 4.2 : Remplacer `bootstrap/app.php`**

Écrire `backend/bootstrap/app.php` :

```php
<?php

use App\Exceptions\ApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ApiException $e, $request) {
            if ($request->is('api/*')) {
                return $e->toJsonResponse();
            }
        });
    })
    ->create();
```

- [ ] **Step 4.3 : Créer `backend/routes/api.php` minimal (sera enrichi en Task 11)**

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/ping', fn () => ['ok' => true]);
```

- [ ] **Step 4.4 : Vérifier que la route /api/v1/ping répond**

Lancer le serveur en arrière-plan (run_in_background=true) puis tester :
```
php artisan serve --port=8001  # background
curl -s http://localhost:8001/api/v1/ping
```
Expected: `{"ok":true}`

Penser à TaskStop sur le shell de serve après le test.

- [ ] **Step 4.5 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/bootstrap/app.php backend/routes/api.php && \
  git commit -m "feat(sprint-1): activate api routes + sanctum stateful in bootstrap

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5 : Helpers `ApiResponse` + `ApiException`

**Files:**
- Create: `backend/app/Http/Responses/ApiResponse.php`
- Create: `backend/app/Exceptions/ApiException.php`
- Test: `backend/tests/Unit/Exceptions/ApiExceptionTest.php`

- [ ] **Step 5.1 : Écrire le test unit pour `ApiException`**

Create file `backend/tests/Unit/Exceptions/ApiExceptionTest.php` :

```php
<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiException;
use Tests\TestCase;

class ApiExceptionTest extends TestCase
{
    public function test_otp_invalid_factory_sets_code_and_status(): void
    {
        $e = ApiException::otpInvalid(['attempts_remaining' => 3]);
        $this->assertSame('AUTH_001', $e->code);
        $this->assertSame(422, $e->httpStatus);
        $this->assertSame(['attempts_remaining' => 3], $e->details);
    }

    public function test_to_json_response_returns_uniform_error_envelope(): void
    {
        $e = ApiException::accountSuspended();
        $response = $e->toJsonResponse();
        $body = $response->getData(true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('AUTH_006', $body['error']['code']);
        $this->assertArrayHasKey('timestamp', $body['meta']);
        $this->assertSame('v1', $body['meta']['version']);
    }

    public function test_all_7_factories_produce_distinct_codes(): void
    {
        $codes = [
            ApiException::otpInvalid()->code,
            ApiException::otpExpired()->code,
            ApiException::otpTooManyAttempts()->code,
            ApiException::invalidCredentials()->code,
            ApiException::accountNotVerified()->code,
            ApiException::accountSuspended()->code,
            ApiException::invalidAccountType()->code,
        ];
        $this->assertSame(
            ['AUTH_001', 'AUTH_002', 'AUTH_003', 'AUTH_004', 'AUTH_005', 'AUTH_006', 'AUTH_007'],
            $codes
        );
    }
}
```

- [ ] **Step 5.2 : Run le test (doit fail : ApiException n'existe pas)**

Run: `cd backend && php artisan test tests/Unit/Exceptions/ApiExceptionTest.php 2>&1 | tail -5`
Expected: erreurs `Class "App\Exceptions\ApiException" not found`.

- [ ] **Step 5.3 : Créer `ApiException`**

Create file `backend/app/Exceptions/ApiException.php` :

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ApiException extends \Exception
{
    public function __construct(
        public readonly string $code,
        public readonly int $httpStatus,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function toJsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->code,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ], $this->httpStatus);
    }

    public static function otpInvalid(array $details = []): self
    {
        return new self('AUTH_001', 422, 'Code OTP invalide.', $details);
    }

    public static function otpExpired(): self
    {
        return new self('AUTH_002', 422, 'Code OTP expiré, demande un nouveau code.');
    }

    public static function otpTooManyAttempts(): self
    {
        return new self('AUTH_003', 429, 'Trop de tentatives OTP, demande un nouveau code.');
    }

    public static function invalidCredentials(): self
    {
        return new self('AUTH_004', 401, 'Identifiants invalides.');
    }

    public static function accountNotVerified(): self
    {
        return new self('AUTH_005', 403, 'Compte non vérifié. Confirme ton numéro via OTP.');
    }

    public static function accountSuspended(): self
    {
        return new self('AUTH_006', 403, 'Compte suspendu. Contacte le support.');
    }

    public static function invalidAccountType(): self
    {
        return new self('AUTH_007', 422, 'Type de compte invalide.');
    }
}
```

- [ ] **Step 5.4 : Run le test (doit pass)**

Run: `cd backend && php artisan test tests/Unit/Exceptions/ApiExceptionTest.php 2>&1 | tail -10`
Expected: 3 tests pass.

- [ ] **Step 5.5 : Créer `ApiResponse`**

Create file `backend/app/Http/Responses/ApiResponse.php` :

```php
<?php

namespace App\Http\Responses;

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
}
```

- [ ] **Step 5.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Exceptions backend/app/Http/Responses backend/tests/Unit/Exceptions && \
  git commit -m "feat(sprint-1): ApiException factories + ApiResponse helper

Codes AUTH_001..007 + format uniforme { success, data|error, meta }.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6 : Model `User` + factory

**Files:**
- Modify: `backend/app/Models/User.php`
- Modify: `backend/database/factories/UserFactory.php`
- Test: `backend/tests/Unit/Models/UserTest.php`

- [ ] **Step 6.1 : Écrire les tests `UserTest`**

Create file `backend/tests/Unit/Models/UserTest.php` :

```php
<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_is_auto_generated_on_create(): void
    {
        $user = User::factory()->create();
        $this->assertIsString($user->id);
        $this->assertSame(36, strlen($user->id)); // UUID v4 length
    }

    public function test_has_api_tokens_trait(): void
    {
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    public function test_password_is_hashed_on_assignment(): void
    {
        $user = User::factory()->create(['password' => 'plaintext-secret']);
        $this->assertNotSame('plaintext-secret', $user->password);
        $this->assertTrue(\Hash::check('plaintext-secret', $user->password));
    }

    public function test_otp_columns_are_hidden_from_array(): void
    {
        $user = User::factory()->create([
            'otp_code_hash' => 'hash',
            'otp_attempts' => 3,
            'otp_type' => 'verify_account',
        ]);
        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('otp_code_hash', $array);
        $this->assertArrayNotHasKey('otp_expires_at', $array);
        $this->assertArrayNotHasKey('otp_attempts', $array);
        $this->assertArrayNotHasKey('otp_type', $array);
        $this->assertArrayNotHasKey('two_factor_secret', $array);
    }

    public function test_mass_assignment_does_not_allow_status_or_type_admin(): void
    {
        // fillable est strict : status n'est PAS dedans
        $user = User::factory()->make();
        $user->fill(['status' => 'active', 'type' => 'admin']);
        // type EST dans fillable, donc passe
        $this->assertSame('admin', $user->type);
        // status n'est PAS dans fillable, donc reste à la valeur d'origine
        $this->assertNotSame('active', $user->status);
    }

    public function test_soft_deletes_works(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $this->assertNotNull($user->fresh()->deleted_at);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }
}
```

- [ ] **Step 6.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Unit/Models/UserTest.php 2>&1 | tail -15`
Expected: erreurs (par exemple `UserFactory` n'a pas les bons champs ou User model pas configuré pour UUID).

- [ ] **Step 6.3 : Modifier `backend/app/Models/User.php`**

Remplacer le contenu par :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'phone',
        'email',
        'password',
        'type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code_hash',
        'otp_expires_at',
        'otp_attempts',
        'otp_type',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}
```

**Important** : l'import `HasFactory` est `use Illuminate\Database\Eloquent\Factories\HasFactory;` — l'ajouter.

Corriger l'import en ajoutant en haut : `use Illuminate\Database\Eloquent\Factories\HasFactory;`

- [ ] **Step 6.4 : Modifier `backend/database/factories/UserFactory.php`**

Remplacer le contenu par :

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'phone' => '+241' . fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'type' => fake()->randomElement(['client', 'prestataire']),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function client(): static
    {
        return $this->state(fn () => ['type' => 'client']);
    }

    public function prestataire(): static
    {
        return $this->state(fn () => ['type' => 'prestataire']);
    }

    public function withOtp(string $type = 'verify_account', string $code = '123456'): static
    {
        return $this->state(fn () => [
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
            'otp_type' => $type,
        ]);
    }
}
```

**Note critique** : la factory utilise `'status' => 'active'` qui n'est PAS dans le `$fillable`. Pour bypass, Laravel Factory utilise `forceFill()` par défaut sur les attributes. C'est OK pour les factories.

- [ ] **Step 6.5 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Unit/Models/UserTest.php 2>&1 | tail -10`
Expected: 6 tests pass.

- [ ] **Step 6.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Models/User.php backend/database/factories/UserFactory.php backend/tests/Unit/Models && \
  git commit -m "feat(sprint-1): User model UUID + HasApiTokens + factory states

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7 : Model `Profile` + factory

**Files:**
- Create: `backend/app/Models/Profile.php`
- Create: `backend/database/factories/ProfileFactory.php`
- Test: `backend/tests/Unit/Models/ProfileTest.php`

- [ ] **Step 7.1 : Écrire le test `ProfileTest`**

Create file `backend/tests/Unit/Models/ProfileTest.php` :

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $profile = Profile::factory()->create();
        $this->assertSame(36, strlen($profile->id));
    }

    public function test_user_relation_returns_user(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);
        $this->assertSame($user->id, $profile->user->id);
    }

    public function test_default_country_is_gabon(): void
    {
        $profile = Profile::factory()->create();
        $this->assertSame('Gabon', $profile->country);
    }

    public function test_latitude_longitude_cast_to_decimal(): void
    {
        $profile = Profile::factory()->create([
            'latitude' => 0.41622,
            'longitude' => 9.46728,
        ]);
        $fresh = $profile->fresh();
        $this->assertSame('0.41622000', (string) $fresh->latitude);
        $this->assertSame('9.46728000', (string) $fresh->longitude);
    }
}
```

- [ ] **Step 7.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Unit/Models/ProfileTest.php 2>&1 | tail -5`
Expected: erreurs Class Profile not found ou ProfileFactory not found.

- [ ] **Step 7.3 : Créer `backend/app/Models/Profile.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'firstname',
        'lastname',
        'bio',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'intervention_radius_km',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'average_rating' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 7.4 : Créer `backend/database/factories/ProfileFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'bio' => fake()->sentence(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Gabon',
            'latitude' => fake()->randomFloat(8, -1, 1),
            'longitude' => fake()->randomFloat(8, 8, 14),
            'intervention_radius_km' => 10,
            'language' => 'fr',
        ];
    }
}
```

- [ ] **Step 7.5 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Unit/Models 2>&1 | tail -10`
Expected: tous les tests Unit/Models passent (User + Profile).

- [ ] **Step 7.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Models/Profile.php backend/database/factories/ProfileFactory.php backend/tests/Unit/Models && \
  git commit -m "feat(sprint-1): Profile model + factory + relation user

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8 : OTP senders (interface + Log + Fake) + binding

**Files:**
- Create: `backend/app/Services/Otp/OtpSenderInterface.php`
- Create: `backend/app/Services/Otp/LogOtpSender.php`
- Create: `backend/app/Services/Otp/FakeOtpSender.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Unit/Services/Otp/OtpSenderTest.php`

- [ ] **Step 8.1 : Écrire les tests des senders**

Create file `backend/tests/Unit/Services/Otp/OtpSenderTest.php` :

```php
<?php

namespace Tests\Unit\Services\Otp;

use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSenderInterface;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OtpSenderTest extends TestCase
{
    public function test_log_sender_implements_interface(): void
    {
        $this->assertInstanceOf(OtpSenderInterface::class, new LogOtpSender());
    }

    public function test_fake_sender_implements_interface(): void
    {
        $this->assertInstanceOf(OtpSenderInterface::class, new FakeOtpSender());
    }

    public function test_log_sender_writes_to_log_channel(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('[OTP] phone=+24107000000 code=123456');

        (new LogOtpSender())->send('+24107000000', '123456');
    }

    public function test_fake_sender_records_last_sent_for_assertions(): void
    {
        FakeOtpSender::reset();
        $sender = new FakeOtpSender();
        $sender->send('+24107000000', '654321');

        $last = FakeOtpSender::lastSent();
        $this->assertSame('+24107000000', $last['phone']);
        $this->assertSame('654321', $last['code']);
    }

    public function test_service_provider_binds_correct_implementation_for_fake(): void
    {
        config(['otp.sender' => 'fake']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $sender = $this->app->make(OtpSenderInterface::class);
        $this->assertInstanceOf(FakeOtpSender::class, $sender);
    }

    public function test_service_provider_binds_log_for_log_config(): void
    {
        config(['otp.sender' => 'log']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $sender = $this->app->make(OtpSenderInterface::class);
        $this->assertInstanceOf(LogOtpSender::class, $sender);
    }
}
```

- [ ] **Step 8.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Unit/Services/Otp 2>&1 | tail -5`
Expected: classes non trouvées.

- [ ] **Step 8.3 : Créer l'interface**

Create file `backend/app/Services/Otp/OtpSenderInterface.php` :

```php
<?php

namespace App\Services\Otp;

interface OtpSenderInterface
{
    public function send(string $phone, string $code): void;
}
```

- [ ] **Step 8.4 : Créer `LogOtpSender`**

Create file `backend/app/Services/Otp/LogOtpSender.php` :

```php
<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

class LogOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $code): void
    {
        Log::info("[OTP] phone={$phone} code={$code}");
    }
}
```

- [ ] **Step 8.5 : Créer `FakeOtpSender`**

Create file `backend/app/Services/Otp/FakeOtpSender.php` :

```php
<?php

namespace App\Services\Otp;

class FakeOtpSender implements OtpSenderInterface
{
    private static array $sent = [];

    public function send(string $phone, string $code): void
    {
        self::$sent[] = ['phone' => $phone, 'code' => $code, 'at' => now()];
    }

    public static function lastSent(): ?array
    {
        return self::$sent ? self::$sent[array_key_last(self::$sent)] : null;
    }

    public static function all(): array
    {
        return self::$sent;
    }

    public static function reset(): void
    {
        self::$sent = [];
    }
}
```

- [ ] **Step 8.6 : Modifier `AppServiceProvider`**

Lire `backend/app/Providers/AppServiceProvider.php` actuel puis modifier la méthode `register()` :

```php
use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSenderInterface;

public function register(): void
{
    $this->app->bind(OtpSenderInterface::class, function ($app) {
        return match (config('otp.sender')) {
            'fake' => new FakeOtpSender(),
            'log'  => new LogOtpSender(),
            default => throw new \RuntimeException(
                'Unknown OTP_SENDER value: '.config('otp.sender')
            ),
        };
    });
}
```

- [ ] **Step 8.7 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Unit/Services/Otp 2>&1 | tail -10`
Expected: 6 tests pass.

- [ ] **Step 8.8 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Services/Otp backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Services && \
  git commit -m "feat(sprint-1): OtpSenderInterface + LogOtpSender + FakeOtpSender + DI binding

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9 : `OtpService`

**Files:**
- Create: `backend/app/Services/Otp/OtpService.php`
- Test: `backend/tests/Unit/Services/Otp/OtpServiceTest.php`

- [ ] **Step 9.1 : Écrire les tests `OtpServiceTest`**

Create file `backend/tests/Unit/Services/Otp/OtpServiceTest.php` :

```php
<?php

namespace Tests\Unit\Services\Otp;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    private function service(): OtpService
    {
        return new OtpService(new FakeOtpSender());
    }

    public function test_generate_for_stores_hashed_code_with_ttl_and_returns_plain(): void
    {
        $user = User::factory()->create();
        $code = $this->service()->generateFor($user, 'verify_account');

        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));

        $user->refresh();
        $this->assertNotNull($user->otp_code_hash);
        $this->assertTrue(Hash::check($code, $user->otp_code_hash));
        $this->assertSame(0, $user->otp_attempts);
        $this->assertSame('verify_account', $user->otp_type);
        $this->assertTrue($user->otp_expires_at->isFuture());
    }

    public function test_verify_success_clears_otp_columns(): void
    {
        $user = User::factory()->create();
        $code = $this->service()->generateFor($user, 'verify_account');

        $this->service()->verify($user->fresh(), $code, 'verify_account');

        $user->refresh();
        $this->assertNull($user->otp_code_hash);
        $this->assertNull($user->otp_expires_at);
        $this->assertSame(0, $user->otp_attempts);
        $this->assertNull($user->otp_type);
    }

    public function test_verify_rejects_when_no_otp_active(): void
    {
        $user = User::factory()->create();
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Code OTP invalide.');
        $this->service()->verify($user, '123456', 'verify_account');
    }

    public function test_verify_rejects_when_expired(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create([
            'otp_expires_at' => now()->subMinute(),
        ]);
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Code OTP expiré, demande un nouveau code.');
        $this->service()->verify($user, '123456', 'verify_account');
    }

    public function test_verify_rejects_wrong_type(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create();
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Code OTP invalide.');
        $this->service()->verify($user, '123456', 'reset_password');
    }

    public function test_verify_increments_attempts_on_wrong_code(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create();
        try {
            $this->service()->verify($user, '999999', 'verify_account');
            $this->fail('Should have thrown');
        } catch (ApiException $e) {
            $this->assertSame('AUTH_001', $e->code);
        }
        $this->assertSame(1, $user->fresh()->otp_attempts);
    }

    public function test_verify_blocks_after_max_attempts(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create([
            'otp_attempts' => 5,
        ]);
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Trop de tentatives OTP, demande un nouveau code.');
        $this->service()->verify($user, '123456', 'verify_account');
    }

    public function test_generate_calls_sender(): void
    {
        $user = User::factory()->create(['phone' => '+24107111111']);
        $code = $this->service()->generateFor($user, 'verify_account');

        $last = FakeOtpSender::lastSent();
        $this->assertSame('+24107111111', $last['phone']);
        $this->assertSame($code, $last['code']);
    }
}
```

- [ ] **Step 9.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Unit/Services/Otp/OtpServiceTest.php 2>&1 | tail -5`
Expected: erreurs Class OtpService not found.

- [ ] **Step 9.3 : Créer `OtpService`**

Create file `backend/app/Services/Otp/OtpService.php` :

```php
<?php

namespace App\Services\Otp;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private OtpSenderInterface $sender)
    {
    }

    public function generateFor(User $user, string $type): string
    {
        $length = (int) config('otp.code_length', 6);
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_pad('9', $length, '9');
        $code = (string) random_int($min, $max);

        $user->forceFill([
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes((int) config('otp.expiration_minutes', 10)),
            'otp_attempts' => 0,
            'otp_type' => $type,
        ])->save();

        $this->sender->send($user->phone, $code);

        return $code;
    }

    public function verify(User $user, string $otp, string $expectedType): void
    {
        if ($user->otp_type !== $expectedType || !$user->otp_code_hash) {
            throw ApiException::otpInvalid();
        }

        if (!$user->otp_expires_at || $user->otp_expires_at->isPast()) {
            throw ApiException::otpExpired();
        }

        $maxAttempts = (int) config('otp.max_attempts', 5);
        if ($user->otp_attempts >= $maxAttempts) {
            throw ApiException::otpTooManyAttempts();
        }

        if (!Hash::check($otp, $user->otp_code_hash)) {
            $user->increment('otp_attempts');
            $remaining = $maxAttempts - $user->otp_attempts;
            throw ApiException::otpInvalid(['attempts_remaining' => max(0, $remaining)]);
        }

        $user->forceFill([
            'otp_code_hash' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_type' => null,
        ])->save();
    }
}
```

- [ ] **Step 9.4 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Unit/Services/Otp 2>&1 | tail -10`
Expected: 8 tests pass (6 sender + 2 sont déjà couverts par OtpServiceTest).

Note : si `withOtp()` factory state n'existe pas, c'est Task 6.4 qui devait l'ajouter. Vérifier.

- [ ] **Step 9.5 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Services/Otp/OtpService.php backend/tests/Unit/Services/Otp/OtpServiceTest.php && \
  git commit -m "feat(sprint-1): OtpService (generate hashed code, verify with TTL+attempts)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10 : `AuthService` (login attempts + lockout)

**Files:**
- Create: `backend/app/Services/AuthService.php`
- Test: `backend/tests/Unit/Services/AuthServiceTest.php`

- [ ] **Step 10.1 : Écrire les tests**

Create file `backend/tests/Unit/Services/AuthServiceTest.php` :

```php
<?php

namespace Tests\Unit\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_attempt_login_returns_user_on_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107222222',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $result = (new AuthService())->attemptLogin('+24107222222', 'secret123');
        $this->assertSame($user->id, $result->id);
    }

    public function test_attempt_login_throws_on_unknown_phone(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Identifiants invalides.');
        (new AuthService())->attemptLogin('+24107000000', 'secret123');
    }

    public function test_attempt_login_throws_on_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+24107333333',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Identifiants invalides.');
        (new AuthService())->attemptLogin('+24107333333', 'wrong-password');
    }

    public function test_attempt_login_rejects_pending_user(): void
    {
        User::factory()->pending()->create([
            'phone' => '+24107444444',
            'password' => 'secret123',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Compte non vérifié. Confirme ton numéro via OTP.');
        (new AuthService())->attemptLogin('+24107444444', 'secret123');
    }

    public function test_attempt_login_rejects_suspended_user(): void
    {
        User::factory()->suspended()->create([
            'phone' => '+24107555555',
            'password' => 'secret123',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Compte suspendu. Contacte le support.');
        (new AuthService())->attemptLogin('+24107555555', 'secret123');
    }

    public function test_lockout_after_10_failed_attempts_suspends_user(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107666666',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $service = new AuthService();
        for ($i = 1; $i <= 10; $i++) {
            try {
                $service->attemptLogin('+24107666666', 'wrong');
            } catch (ApiException $e) {
                // expected
            }
        }

        $this->assertSame('suspended', $user->fresh()->status);
    }

    public function test_successful_login_resets_fail_counter(): void
    {
        User::factory()->create([
            'phone' => '+24107777777',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $service = new AuthService();
        // 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            try { $service->attemptLogin('+24107777777', 'wrong'); } catch (ApiException $e) {}
        }
        // success
        $service->attemptLogin('+24107777777', 'secret123');

        // counter should be 0 ; another wrong attempt counter back to 1
        $this->assertSame(1, Cache::get('login_fails:+24107777777') ?? 0);
        try { $service->attemptLogin('+24107777777', 'wrong'); } catch (ApiException $e) {}
        $this->assertSame(1, Cache::get('login_fails:+24107777777'));
    }
}
```

- [ ] **Step 10.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Unit/Services/AuthServiceTest.php 2>&1 | tail -5`
Expected: AuthService class not found.

- [ ] **Step 10.3 : Créer `AuthService`**

Create file `backend/app/Services/AuthService.php` :

```php
<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    private const FAIL_KEY_PREFIX = 'login_fails:';

    public function attemptLogin(string $phone, string $password): User
    {
        $user = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->recordFailedAttempt($phone, $user);
            throw ApiException::invalidCredentials();
        }

        if ($user->status === 'pending') {
            throw ApiException::accountNotVerified();
        }

        if ($user->status === 'suspended') {
            throw ApiException::accountSuspended();
        }

        if ($user->status === 'deleted') {
            // Anti-leak : même message qu'identifiants invalides
            throw ApiException::invalidCredentials();
        }

        Cache::forget(self::FAIL_KEY_PREFIX . $phone);
        return $user;
    }

    private function recordFailedAttempt(string $phone, ?User $user): void
    {
        $key = self::FAIL_KEY_PREFIX . $phone;
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addHour());

        $max = (int) config('app.login_max_fails_per_hour', env('LOGIN_MAX_FAILS_PER_HOUR', 10));
        if ($user && $count >= $max && $user->status === 'active') {
            $user->forceFill(['status' => 'suspended'])->save();
        }
    }
}
```

- [ ] **Step 10.4 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Unit/Services/AuthServiceTest.php 2>&1 | tail -10`
Expected: 7 tests pass.

- [ ] **Step 10.5 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Services/AuthService.php backend/tests/Unit/Services/AuthServiceTest.php && \
  git commit -m "feat(sprint-1): AuthService (login + cache-based lockout after 10 fails)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 11 : Routes `routes/api.php` (squelette V1 complet)

**Files:**
- Modify: `backend/routes/api.php`

- [ ] **Step 11.1 : Écrire `backend/routes/api.php`**

Remplacer le contenu par :

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public auth endpoints
    Route::post('auth/register',        [AuthController::class, 'register']);
    Route::post('auth/verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('auth/resend-otp',      [AuthController::class, 'resendOtp'])->middleware('throttle:3,60');
    Route::post('auth/login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
    Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);

    // Protected endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user',    [AuthController::class, 'user']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('profile',      [ProfileController::class, 'show']);
        Route::put('profile',      [ProfileController::class, 'update']);
    });
});
```

- [ ] **Step 11.2 : Créer les contrôleurs stubs (vides)**

Create file `backend/app/Http/Controllers/Api/V1/AuthController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request) { abort(501); }
    public function verifyOtp(Request $request) { abort(501); }
    public function resendOtp(Request $request) { abort(501); }
    public function login(Request $request) { abort(501); }
    public function forgotPassword(Request $request) { abort(501); }
    public function resetPassword(Request $request) { abort(501); }
    public function user(Request $request) { abort(501); }
    public function logout(Request $request) { abort(501); }
}
```

Create file `backend/app/Http/Controllers/Api/V1/ProfileController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request) { abort(501); }
    public function update(Request $request) { abort(501); }
}
```

- [ ] **Step 11.3 : Vérifier que les routes sont listées**

Run: `cd backend && php artisan route:list --path=api | head -20`
Expected: voir les 10 routes (6 public + 4 protégées).

- [ ] **Step 11.4 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/routes/api.php backend/app/Http/Controllers/Api && \
  git commit -m "feat(sprint-1): routes /api/v1 + stubs AuthController/ProfileController

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 12 : Resources `UserResource` + `ProfileResource`

**Files:**
- Create: `backend/app/Http/Resources/Api/V1/UserResource.php`
- Create: `backend/app/Http/Resources/Api/V1/ProfileResource.php`
- Test: `backend/tests/Unit/Resources/Api/V1/UserResourceTest.php`

- [ ] **Step 12.1 : Écrire les tests Resource**

Create file `backend/tests/Unit/Resources/Api/V1/UserResourceTest.php` :

```php
<?php

namespace Tests\Unit\Resources\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_exposes_safe_fields(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107888888',
            'email' => 'sarah@example.com',
            'type' => 'client',
            'status' => 'active',
        ]);
        Profile::factory()->create(['user_id' => $user->id, 'firstname' => 'Sarah', 'lastname' => 'Doe']);

        $arr = (new UserResource($user->load('profile')))->toArray(new Request());

        $this->assertSame($user->id, $arr['id']);
        $this->assertSame('+24107888888', $arr['phone']);
        $this->assertSame('sarah@example.com', $arr['email']);
        $this->assertSame('client', $arr['type']);
        $this->assertSame('active', $arr['status']);
        $this->assertSame('Sarah', $arr['profile']['firstname']);
        $this->assertSame('Doe', $arr['profile']['lastname']);

        // Sensitive fields must not leak
        $this->assertArrayNotHasKey('password', $arr);
        $this->assertArrayNotHasKey('otp_code_hash', $arr);
        $this->assertArrayNotHasKey('two_factor_secret', $arr);
    }
}
```

- [ ] **Step 12.2 : Run le test (doit fail)**

Run: `cd backend && php artisan test tests/Unit/Resources 2>&1 | tail -5`
Expected: UserResource not found.

- [ ] **Step 12.3 : Créer `UserResource`**

Create file `backend/app/Http/Resources/Api/V1/UserResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'email' => $this->email,
            'type' => $this->type,
            'status' => $this->status,
            'identity_verified_at' => $this->identity_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', function () {
                return [
                    'firstname' => $this->profile->firstname,
                    'lastname' => $this->profile->lastname,
                ];
            }),
        ];
    }
}
```

- [ ] **Step 12.4 : Créer `ProfileResource`**

Create file `backend/app/Http/Resources/Api/V1/ProfileResource.php` :

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'bio' => $this->bio,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'intervention_radius_km' => $this->intervention_radius_km,
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'language' => $this->language,
        ];
    }
}
```

- [ ] **Step 12.5 : Run le test (doit pass)**

Run: `cd backend && php artisan test tests/Unit/Resources 2>&1 | tail -5`
Expected: 1 test pass.

- [ ] **Step 12.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Resources backend/tests/Unit/Resources && \
  git commit -m "feat(sprint-1): UserResource + ProfileResource (no sensitive field leak)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 13 : Endpoint `POST /auth/register`

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/RegisterRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php` (méthode `register`)
- Test: `backend/tests/Feature/Api/V1/Auth/RegisterTest.php`

- [ ] **Step 13.1 : Écrire les tests feature**

Create file `backend/tests/Feature/Api/V1/Auth/RegisterTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    public function test_register_creates_user_in_pending_status_and_sends_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107123456',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'client',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['user_id', '_dev_otp', 'message']]);

        $user = User::where('phone', '+24107123456')->first();
        $this->assertNotNull($user);
        $this->assertSame('pending', $user->status);
        $this->assertSame('client', $user->type);
        $this->assertNotNull($user->otp_code_hash);
        $this->assertSame('verify_account', $user->otp_type);

        // Profile linked
        $this->assertNotNull($user->profile);
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+24107000111']);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107000111',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => 'not-a-phone',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_password_too_short(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222333',
            'password' => 'short',
            'password_confirmation' => 'short',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_password_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222444',
            'password' => 'secret123',
            'password_confirmation' => 'different',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_admin_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222555',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_register_accepts_prestataire_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222666',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'prestataire',
        ]);

        $response->assertStatus(201);
        $this->assertSame('prestataire', User::where('phone', '+24107222666')->first()->type);
    }
}
```

- [ ] **Step 13.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/RegisterTest.php 2>&1 | tail -5`
Expected: 501 ou Class RegisterRequest not found.

- [ ] **Step 13.3 : Créer `RegisterRequest`**

Create file `backend/app/Http/Requests/Api/V1/RegisterRequest.php` :

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'type' => ['required', 'string', 'in:client,prestataire'],
            'email' => ['nullable', 'email', 'unique:users,email'],
        ];
    }
}
```

- [ ] **Step 13.4 : Implémenter `AuthController::register`**

Remplacer la méthode `register` dans `backend/app/Http/Controllers/Api/V1/AuthController.php` :

Ajouter en haut :

```php
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Profile;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\DB;
```

Méthode :

```php
public function register(RegisterRequest $request, OtpService $otp)
{
    $data = $request->validated();

    $user = DB::transaction(function () use ($data) {
        $user = User::create([
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'type' => $data['type'],
        ]);
        // status n'est pas dans fillable — déjà 'pending' par DB default
        Profile::create(['user_id' => $user->id]);
        return $user;
    });

    $code = $otp->generateFor($user, 'verify_account');

    $payload = [
        'user_id' => $user->id,
        'message' => 'OTP envoyé à votre numéro.',
    ];
    if (config('otp.sender') === 'fake') {
        $payload['_dev_otp'] = $code;
    }

    return ApiResponse::success($payload, 201);
}
```

- [ ] **Step 13.5 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/RegisterTest.php 2>&1 | tail -15`
Expected: 7 tests pass.

- [ ] **Step 13.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/RegisterTest.php && \
  git commit -m "feat(sprint-1): POST /auth/register + OTP send

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 14 : Endpoint `POST /auth/verify-otp`

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/VerifyOtpRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php` (méthode `verifyOtp`)
- Test: `backend/tests/Feature/Api/V1/Auth/VerifyOtpTest.php`

- [ ] **Step 14.1 : Écrire les tests**

Create file `backend/tests/Feature/Api/V1/Auth/VerifyOtpTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_otp_activates_user_with_correct_code(): void
    {
        $user = User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900001',
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900001',
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.status', 'active');

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->otp_code_hash);
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900002',
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900002',
            'otp' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_001')
            ->assertJsonPath('error.details.attempts_remaining', 4);
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900003',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900003',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_002');
    }

    public function test_verify_otp_blocks_after_5_attempts(): void
    {
        User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900004',
            'otp_attempts' => 5,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900004',
            'otp' => '123456',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_003');
    }

    public function test_verify_otp_rejects_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900005',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_001');
    }
}
```

- [ ] **Step 14.2 : Run les tests (doivent fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/VerifyOtpTest.php 2>&1 | tail -5`

- [ ] **Step 14.3 : Créer `VerifyOtpRequest`**

Create file `backend/app/Http/Requests/Api/V1/VerifyOtpRequest.php` :

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 14.4 : Implémenter `AuthController::verifyOtp`**

Ajouter imports en haut si manquants :

```php
use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
```

Remplacer la méthode `verifyOtp` :

```php
public function verifyOtp(VerifyOtpRequest $request, OtpService $otp)
{
    $data = $request->validated();
    $user = User::where('phone', $data['phone'])->first();

    if (!$user) {
        throw ApiException::otpInvalid();
    }

    $otp->verify($user, $data['otp'], 'verify_account');

    $user->forceFill(['status' => 'active'])->save();
    $user->load('profile');

    return ApiResponse::success([
        'user' => new UserResource($user),
        'message' => 'Compte activé.',
    ]);
}
```

- [ ] **Step 14.5 : Run les tests (doivent pass)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/VerifyOtpTest.php 2>&1 | tail -10`
Expected: 5 tests pass.

- [ ] **Step 14.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests/Api/V1/VerifyOtpRequest.php backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/VerifyOtpTest.php && \
  git commit -m "feat(sprint-1): POST /auth/verify-otp (activate user on valid code)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 15 : Endpoint `POST /auth/resend-otp`

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/ResendOtpRequest.php`
- Modify: AuthController (méthode `resendOtp`)
- Test: `backend/tests/Feature/Api/V1/Auth/ResendOtpTest.php`

- [ ] **Step 15.1 : Écrire le test**

Create file `backend/tests/Feature/Api/V1/Auth/ResendOtpTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResendOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    public function test_resend_otp_generates_new_code_and_resets_attempts(): void
    {
        $user = User::factory()->pending()->withOtp('verify_account', '111111')->create([
            'phone' => '+24107910001',
            'otp_attempts' => 4,
        ]);
        $oldHash = $user->otp_code_hash;

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '+24107910001',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $user->refresh();
        $this->assertNotSame($oldHash, $user->otp_code_hash);
        $this->assertSame(0, $user->otp_attempts);
    }

    public function test_resend_otp_returns_200_even_for_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '+24107910099',
        ]);
        $response->assertStatus(200);
    }
}
```

- [ ] **Step 15.2 : Run le test (fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/ResendOtpTest.php 2>&1 | tail -5`

- [ ] **Step 15.3 : Créer `ResendOtpRequest`**

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ResendOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['phone' => ['required', 'string']];
    }
}
```

- [ ] **Step 15.4 : Implémenter `AuthController::resendOtp`**

```php
public function resendOtp(\App\Http\Requests\Api\V1\ResendOtpRequest $request, OtpService $otp)
{
    $user = User::where('phone', $request->validated()['phone'])->first();

    if ($user && $user->otp_type) {
        $code = $otp->generateFor($user, $user->otp_type);
        $payload = ['message' => 'OTP renvoyé.'];
        if (config('otp.sender') === 'fake') {
            $payload['_dev_otp'] = $code;
        }
        return ApiResponse::success($payload);
    }

    // Anti-leak : on retourne 200 même si user n'existe pas
    return ApiResponse::success(['message' => 'OTP renvoyé.']);
}
```

- [ ] **Step 15.5 : Run les tests (pass)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/ResendOtpTest.php 2>&1 | tail -10`

- [ ] **Step 15.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests/Api/V1/ResendOtpRequest.php backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/ResendOtpTest.php && \
  git commit -m "feat(sprint-1): POST /auth/resend-otp (anti-leak constant response)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 16 : Endpoint `POST /auth/login` (Bearer + stateful)

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/LoginRequest.php`
- Modify: AuthController (méthode `login`)
- Test: `backend/tests/Feature/Api/V1/Auth/LoginTest.php`

- [ ] **Step 16.1 : Écrire les tests**

Create file `backend/tests/Feature/Api/V1/Auth/LoginTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_login_with_device_name_returns_bearer_token(): void
    {
        User::factory()->create([
            'phone' => '+24107920001',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920001',
            'password' => 'secret123',
            'device_name' => 'iPhone Sarah',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'token']])
            ->assertJsonPath('data.user.phone', '+24107920001');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_without_device_name_uses_stateful(): void
    {
        User::factory()->create([
            'phone' => '+24107920002',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920002',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user']])
            ->assertJsonMissingPath('data.token');
    }

    public function test_login_rejects_pending_user(): void
    {
        User::factory()->pending()->create([
            'phone' => '+24107920003',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920003',
            'password' => 'secret123',
            'device_name' => 'x',
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'AUTH_005');
    }

    public function test_login_rejects_suspended_user(): void
    {
        User::factory()->suspended()->create([
            'phone' => '+24107920004',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920004',
            'password' => 'secret123',
            'device_name' => 'x',
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'AUTH_006');
    }

    public function test_login_returns_401_on_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+24107920005',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920005',
            'password' => 'wrong-password',
            'device_name' => 'x',
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'AUTH_004');
    }

    public function test_login_returns_401_on_unknown_phone_without_leak(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107999999',
            'password' => 'whatever',
            'device_name' => 'x',
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'AUTH_004');
    }

    public function test_login_after_10_fails_suspends_account(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107920006',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => '+24107920006',
                'password' => 'wrong',
                'device_name' => 'x',
            ]);
        }

        $this->assertSame('suspended', $user->fresh()->status);
    }
}
```

- [ ] **Step 16.2 : Run les tests (fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/LoginTest.php 2>&1 | tail -5`

- [ ] **Step 16.3 : Créer `LoginRequest`**

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

- [ ] **Step 16.4 : Implémenter `AuthController::login`**

Ajouter import :
```php
use App\Http\Requests\Api\V1\LoginRequest;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
```

Méthode :
```php
public function login(LoginRequest $request, AuthService $auth)
{
    $data = $request->validated();
    $user = $auth->attemptLogin($data['phone'], $data['password']);
    $user->load('profile');

    $deviceName = $data['device_name'] ?? null;

    if ($deviceName) {
        $token = $user->createToken($deviceName)->plainTextToken;
        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    // Stateful (web) : pose le cookie de session
    Auth::login($user);
    $request->session()->regenerate();
    return ApiResponse::success(['user' => new UserResource($user)]);
}
```

- [ ] **Step 16.5 : Run les tests (pass)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/LoginTest.php 2>&1 | tail -15`
Expected: 7 tests pass.

Note : `throttle:5,1` est sur la route, donc en testing les tests "10 fails" peuvent être limités par throttle. Solution : désactiver throttle en testing OU faire que ResetAttempts entre essais. Si erreur 429 imprévu, ajouter dans `setUp()` :
```php
$this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
```

- [ ] **Step 16.6 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests/Api/V1/LoginRequest.php backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/LoginTest.php && \
  git commit -m "feat(sprint-1): POST /auth/login (hybride Bearer/stateful + lockout)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 17 : Endpoints `GET /auth/user` + `POST /auth/logout`

**Files:**
- Modify: AuthController (méthodes `user`, `logout`)
- Test: `backend/tests/Feature/Api/V1/Auth/LogoutTest.php`

- [ ] **Step 17.1 : Écrire les tests `LogoutTest`**

Create file `backend/tests/Feature/Api/V1/Auth/LogoutTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_user_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/user')->assertStatus(401);
    }

    public function test_get_user_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/user')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_bearer_revokes_current_token_only(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => 'secret123']);
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Token 1 must be revoked
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/v1/auth/user')
            ->assertStatus(401);

        // Token 2 still works
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/v1/auth/user')
            ->assertStatus(200);
    }
}
```

- [ ] **Step 17.2 : Run (fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/LogoutTest.php 2>&1 | tail -5`

- [ ] **Step 17.3 : Implémenter `user()` et `logout()`**

Remplacer dans AuthController :

```php
public function user(\Illuminate\Http\Request $request)
{
    $user = $request->user()->load('profile');
    return ApiResponse::success(['id' => $user->id] + (new UserResource($user))->toArray($request));
}

public function logout(\Illuminate\Http\Request $request)
{
    $token = $request->user()->currentAccessToken();
    if ($token && method_exists($token, 'delete')) {
        $token->delete();
    } else {
        // stateful (web) : invalidate session
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
    return ApiResponse::success(['message' => 'Déconnecté.']);
}
```

Note : `user()` ajoute `'id'` explicitement parce que UserResource::toArray() inclut déjà `id` mais on veut s'assurer qu'il est en racine de `data`. Simplification : utiliser `(new UserResource(...))->resolve()`. Réécrire plus proprement :

```php
public function user(\Illuminate\Http\Request $request)
{
    $user = $request->user()->load('profile');
    return ApiResponse::success((new UserResource($user))->toArray($request));
}
```

- [ ] **Step 17.4 : Run (pass)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/Auth/LogoutTest.php 2>&1 | tail -10`
Expected: 3 tests pass.

- [ ] **Step 17.5 : Commit**

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/LogoutTest.php && \
  git commit -m "feat(sprint-1): GET /auth/user + POST /auth/logout (Bearer + stateful)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 18 : Endpoint `POST /auth/forgot-password`

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/ForgotPasswordRequest.php`
- Modify: AuthController (méthode `forgotPassword`)
- Test: `backend/tests/Feature/Api/V1/Auth/ForgotPasswordTest.php`

- [ ] **Step 18.1 : Test**

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    public function test_forgot_password_sends_otp_with_reset_type(): void
    {
        $user = User::factory()->create(['phone' => '+24107930001', 'status' => 'active']);

        $this->postJson('/api/v1/auth/forgot-password', ['phone' => '+24107930001'])
            ->assertStatus(200);

        $user->refresh();
        $this->assertSame('reset_password', $user->otp_type);
        $this->assertNotNull($user->otp_code_hash);
    }

    public function test_forgot_password_returns_200_even_for_unknown_phone(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['phone' => '+24107930099'])
            ->assertStatus(200);
    }
}
```

Create file `backend/tests/Feature/Api/V1/Auth/ForgotPasswordTest.php` avec le code ci-dessus.

- [ ] **Step 18.2 : Run (fail)**

- [ ] **Step 18.3 : `ForgotPasswordRequest`**

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['phone' => ['required', 'string']];
    }
}
```

- [ ] **Step 18.4 : Implémentation `forgotPassword`**

```php
public function forgotPassword(\App\Http\Requests\Api\V1\ForgotPasswordRequest $request, OtpService $otp)
{
    $user = User::where('phone', $request->validated()['phone'])->first();

    if ($user) {
        $code = $otp->generateFor($user, 'reset_password');
        $payload = ['message' => 'OTP envoyé pour réinitialisation.'];
        if (config('otp.sender') === 'fake') {
            $payload['_dev_otp'] = $code;
        }
        return ApiResponse::success($payload);
    }

    return ApiResponse::success(['message' => 'OTP envoyé pour réinitialisation.']);
}
```

- [ ] **Step 18.5 : Run (pass) + commit**

```bash
cd backend && php artisan test tests/Feature/Api/V1/Auth/ForgotPasswordTest.php 2>&1 | tail -10
```

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests/Api/V1/ForgotPasswordRequest.php backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/ForgotPasswordTest.php && \
  git commit -m "feat(sprint-1): POST /auth/forgot-password

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 19 : Endpoint `POST /auth/reset-password`

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/ResetPasswordRequest.php`
- Modify: AuthController (méthode `resetPassword`)
- Test: `backend/tests/Feature/Api/V1/Auth/ResetPasswordTest.php`

- [ ] **Step 19.1 : Test**

Create file `backend/tests/Feature/Api/V1/Auth/ResetPasswordTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_with_valid_otp_updates_hash_and_revokes_tokens(): void
    {
        $user = User::factory()->withOtp('reset_password', '654321')->create([
            'phone' => '+24107940001',
            'password' => 'old-password',
            'status' => 'active',
        ]);
        $user->createToken('device-1');
        $user->createToken('device-2');

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '+24107940001',
            'otp' => '654321',
            'password' => 'new-password-2026',
            'password_confirmation' => 'new-password-2026',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-2026', $user->password));
        $this->assertNull($user->otp_code_hash);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_wrong_otp_type(): void
    {
        User::factory()->withOtp('verify_account', '111111')->create([
            'phone' => '+24107940002',
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '+24107940002',
            'otp' => '111111',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422)->assertJsonPath('error.code', 'AUTH_001');
    }
}
```

- [ ] **Step 19.2 : Run (fail)**

- [ ] **Step 19.3 : `ResetPasswordRequest`**

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

- [ ] **Step 19.4 : Implémentation `resetPassword`**

```php
public function resetPassword(\App\Http\Requests\Api\V1\ResetPasswordRequest $request, OtpService $otp)
{
    $data = $request->validated();
    $user = User::where('phone', $data['phone'])->first();

    if (!$user) {
        throw ApiException::otpInvalid();
    }

    $otp->verify($user, $data['otp'], 'reset_password');

    $user->forceFill(['password' => $data['password']])->save();
    $user->tokens()->delete();

    return ApiResponse::success(['message' => 'Mot de passe réinitialisé.']);
}
```

- [ ] **Step 19.5 : Run + commit**

```bash
cd backend && php artisan test tests/Feature/Api/V1/Auth/ResetPasswordTest.php 2>&1 | tail -10
```

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests/Api/V1/ResetPasswordRequest.php backend/app/Http/Controllers/Api/V1/AuthController.php backend/tests/Feature/Api/V1/Auth/ResetPasswordTest.php && \
  git commit -m "feat(sprint-1): POST /auth/reset-password (revoke all tokens on reset)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 20 : Endpoint `GET /profile`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/ProfileController.php` (méthode `show`)
- Test: `backend/tests/Feature/Api/V1/ProfileTest.php` (partiel)

- [ ] **Step 20.1 : Test**

Create file `backend/tests/Feature/Api/V1/ProfileTest.php` :

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_auth(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_show_returns_user_profile(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create([
            'user_id' => $user->id,
            'firstname' => 'Sarah',
            'city' => 'Libreville',
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.firstname', 'Sarah')
            ->assertJsonPath('data.city', 'Libreville')
            ->assertJsonPath('data.country', 'Gabon');
    }
}
```

- [ ] **Step 20.2 : Run (fail)**

Run: `cd backend && php artisan test tests/Feature/Api/V1/ProfileTest.php 2>&1 | tail -5`

- [ ] **Step 20.3 : Implémentation `show`**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->profile;
        if (!$profile) {
            $profile = $request->user()->profile()->create([]);
        }
        return ApiResponse::success((new ProfileResource($profile))->toArray($request));
    }

    public function update(Request $request) { abort(501); }
}
```

- [ ] **Step 20.4 : Run (pass) + commit**

```bash
cd backend && php artisan test tests/Feature/Api/V1/ProfileTest.php 2>&1 | tail -10
```

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Controllers/Api/V1/ProfileController.php backend/tests/Feature/Api/V1/ProfileTest.php && \
  git commit -m "feat(sprint-1): GET /profile

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 21 : Endpoint `PUT /profile`

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/UpdateProfileRequest.php`
- Modify: `ProfileController::update`
- Modify: `backend/tests/Feature/Api/V1/ProfileTest.php` (ajouter tests)

- [ ] **Step 21.1 : Ajouter tests dans `ProfileTest`**

Ajouter à la classe `ProfileTest` :

```php
public function test_update_modifies_profile_fields(): void
{
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile', [
        'firstname' => 'Sarah',
        'lastname' => 'Mbeng',
        'bio' => 'Plombière expérimentée',
        'address' => '123 rue Foch',
        'city' => 'Port-Gentil',
        'latitude' => -0.7193,
        'longitude' => 8.7815,
        'intervention_radius_km' => 25,
        'language' => 'fr',
    ])->assertStatus(200)
        ->assertJsonPath('data.firstname', 'Sarah')
        ->assertJsonPath('data.city', 'Port-Gentil')
        ->assertJsonPath('data.intervention_radius_km', 25);
}

public function test_update_rejects_invalid_latitude(): void
{
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile', [
        'latitude' => 999,  // out of valid range
    ])->assertStatus(422)->assertJsonValidationErrors(['latitude']);
}

public function test_update_rejects_invalid_radius(): void
{
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/profile', [
        'intervention_radius_km' => -5,
    ])->assertStatus(422)->assertJsonValidationErrors(['intervention_radius_km']);
}
```

- [ ] **Step 21.2 : Run (fail)**

- [ ] **Step 21.3 : `UpdateProfileRequest`**

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'firstname' => ['nullable', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'intervention_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'language' => ['nullable', 'string', 'in:fr,en'],
        ];
    }
}
```

- [ ] **Step 21.4 : Implémentation `update`**

```php
public function update(\App\Http\Requests\Api\V1\UpdateProfileRequest $request)
{
    $profile = $request->user()->profile ?? $request->user()->profile()->create([]);
    $profile->fill($request->validated())->save();
    return ApiResponse::success((new ProfileResource($profile->fresh()))->toArray($request));
}
```

- [ ] **Step 21.5 : Run (pass) + commit**

```bash
cd backend && php artisan test tests/Feature/Api/V1/ProfileTest.php 2>&1 | tail -10
```

```bash
cd /Applications/MAMP/htdocs/lukassa && git add backend/app/Http/Requests/Api/V1/UpdateProfileRequest.php backend/app/Http/Controllers/Api/V1/ProfileController.php backend/tests/Feature/Api/V1/ProfileTest.php && \
  git commit -m "feat(sprint-1): PUT /profile (validation lat/lng/radius)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 22 : Vérification finale Definition of Done + push

**Files:** (vérifications only)

- [ ] **Step 22.1 : Lancer toute la suite de tests**

Run: `cd backend && php artisan test 2>&1 | tail -15`
Expected: tous les tests passent (≥ 35 tests : 25+ feature + 10+ unit).

- [ ] **Step 22.2 : Smoke test manuel Postman/curl**

Lancer Laravel : `php artisan serve --port=8001` (run_in_background=true).

Tester le flow complet :
```bash
# Register
curl -s -X POST http://localhost:8001/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"phone":"+24107555000","password":"secret123","password_confirmation":"secret123","type":"client"}' | jq .

# Récupérer _dev_otp depuis la réponse, puis :
curl -s -X POST http://localhost:8001/api/v1/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"phone":"+24107555000","otp":"<DEV_OTP>"}' | jq .

# Login bearer
curl -s -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"+24107555000","password":"secret123","device_name":"curl-test"}' | jq .

# Use token
TOKEN="<paste>"
curl -s http://localhost:8001/api/v1/auth/user -H "Authorization: Bearer $TOKEN" | jq .
```

Expected: chaque étape réussit avec un body conforme.

Arrêter le serveur avec TaskStop sur le shell ID.

- [ ] **Step 22.3 : Verify Definition of Done criteria**

Vérifier chaque critère du spec section 7 :

```bash
echo "=== Test count ==="
cd backend && php artisan test 2>&1 | grep -E "Tests:.*passed"

echo "=== OTP dans laravel.log ==="
grep "\[OTP\]" backend/storage/logs/laravel.log | head -3 || echo "(pas trouvé, peut être mode fake)"

echo "=== Routes API ==="
cd backend && php artisan route:list --path=api/v1 | wc -l

echo "=== UserResource ne leak pas otp_code_hash ==="
grep -r "otp_code_hash" backend/app/Http/Resources/ && echo "FAIL" || echo "OK"
```

Expected:
- Tests : 35+ passed
- Routes : 10+
- Pas de `otp_code_hash` dans Resources

- [ ] **Step 22.4 : Push final**

```bash
cd /Applications/MAMP/htdocs/lukassa && git log --oneline | head -25 && git push origin main 2>&1 | tail -3
```

Expected: push OK avec tous les commits du sprint visibles.

- [ ] **Step 22.5 : Commit final tag**

```bash
cd /Applications/MAMP/htdocs/lukassa && git tag sprint-1-auth-profiles && git push origin sprint-1-auth-profiles 2>&1 | tail -2
```

Expected: tag créé et poussé.

---

## Definition of Done finale (Sprint 1)

- [ ] `php artisan test` : 35+ tests passent
- [ ] `php artisan route:list --path=api/v1` : 10 routes (6 public + 4 protégées)
- [ ] Flow Postman complet : register → verify-otp → login → /user OK
- [ ] OTP visible dans `_dev_otp` (mode fake) ou `laravel.log` (mode log)
- [ ] Login Bearer ET stateful fonctionnent
- [ ] Logout Bearer ne révoque QUE le token courant (vérifié dans LogoutTest)
- [ ] Après 10 fails login → `users.status='suspended'`
- [ ] `register` avec `type=admin` rejeté (AUTH_007)
- [ ] `register` avec `status` dans payload → ignoré
- [ ] Tous les Resources ne leak aucun champ sensible
- [ ] Format réponse uniforme `{ success, data|error, meta }`
- [ ] Tag git `sprint-1-auth-profiles` créé et poussé
