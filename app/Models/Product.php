<?php

namespace App\Models;

use App\Models\Dpl\ProductPrice;
use App\Support\AuditLog;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Master product. Grouping 500/250/100/25 ml ditentukan dari volume.
 */
#[Fillable(['code', 'name', 'volume', 'is_branded', 'is_active', 'netsuite_id'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['code', 'name', 'volume', 'is_branded', 'is_active', 'netsuite_id'], fn () => "Product {$this->name} ({$this->code})");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'volume' => 'decimal:4',
            'is_branded' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
