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
        'category_id', 'name', 'slug', 'description', 'icon', 'cover_image',
        'min_price_estimate', 'is_active', 'requires_quote',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_quote' => 'boolean',
        'min_price_estimate' => 'integer',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function providerServices(): HasMany { return $this->hasMany(ProviderService::class); }

    public function getRouteKeyName(): string { return 'slug'; }
}
