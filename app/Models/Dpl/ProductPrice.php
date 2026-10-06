<?php

namespace App\Models\Dpl;

use App\Models\Product;
use App\Support\AuditLog;
use Database\Factories\Dpl\ProductPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Harga product per price region (legacy dpl_price_product).
 */
#[Table('dpl.product_prices')]
#[Fillable(['product_id', 'price_region_id', 'price', 'regular_price', 'valid_from', 'valid_until', 'is_active'])]
class ProductPrice extends Model
{
    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['product_id', 'price_region_id', 'price', 'regular_price', 'valid_from', 'valid_until', 'is_active'], fn () => "Harga product {$this->product?->code} region {$this->priceRegion?->code}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'regular_price' => 'decimal:4',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
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
     * @return BelongsTo<PriceRegion, $this>
     */
    public function priceRegion(): BelongsTo
    {
        return $this->belongsTo(PriceRegion::class);
    }
}
