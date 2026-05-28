<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    public function providerServices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProviderService::class, 'provider_id');
    }
}
