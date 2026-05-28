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
        'parent_id', 'name', 'slug', 'icon', 'description', 'order_position', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_position' => 'integer',
    ];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function services(): HasMany { return $this->hasMany(Service::class); }

    public function getRouteKeyName(): string { return 'slug'; }
}
