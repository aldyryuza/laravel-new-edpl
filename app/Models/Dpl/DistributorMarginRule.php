<?php

namespace App\Models\Dpl;

use App\Models\City;
use App\Models\Product;
use App\Support\AuditLog;
use Database\Factories\Dpl\DistributorMarginRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Margin khusus product per grup distributor (legacy dpl_distributor_margin). city_id null = semua kota.
 */
#[Table('dpl.distributor_margin_rules')]
#[Fillable(['distributor_group_code', 'product_id', 'city_id', 'margin', 'note', 'is_active'])]
class DistributorMarginRule extends Model
{
    /** @use HasFactory<DistributorMarginRuleFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['distributor_group_code', 'product_id', 'city_id', 'margin', 'note', 'is_active'],
            fn () => "Margin khusus product {$this->distributor_group_code} - {$this->product?->code}",
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'margin' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
