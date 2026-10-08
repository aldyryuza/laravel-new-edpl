<?php

namespace App\Models\Dpl;

use App\Models\Product;
use App\Support\AuditLog;
use Database\Factories\Dpl\ProductMarginOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Override margin per product (legacy DPL_PRODUCT_MARGIN_OVERRIDE), hanya item non-Direct.
 * distributor_group_code / team_code null = semua.
 */
#[Table('dpl.product_margin_overrides')]
#[Fillable(['product_id', 'distributor_group_code', 'team_code', 'margin', 'note', 'is_active'])]
class ProductMarginOverride extends Model
{
    /** @use HasFactory<ProductMarginOverrideFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['product_id', 'distributor_group_code', 'team_code', 'margin', 'note', 'is_active'],
            fn () => "Override margin product {$this->product?->code}",
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
}
