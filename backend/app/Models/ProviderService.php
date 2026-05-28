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
        'provider_id', 'service_id', 'price_model', 'price_amount',
        'custom_description', 'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price_amount' => 'integer',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
