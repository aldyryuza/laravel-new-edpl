<?php

namespace App\Models;

use Database\Factories\DplItemTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commercial terms saat ini, satu baris per DPL item. Nilai NUMERIC di-cast ke string desimal
 * agar tidak kehilangan presisi (jangan dihitung dengan float).
 */
#[Table('dpl.dpl_item_terms')]
#[Fillable([
    'dpl_item_id', 'principle_discount_on', 'principle_discount_off', 'distributor_discount_on',
    'distributor_discount_off', 'negotiated_price', 'margin', 'logistic_fee', 'logistic_support',
    'base_price', 'final_price', 'effective_discount', 'calculator_percentage', 'price_type',
])]
class DplItemTerm extends Model
{
    /** @use HasFactory<DplItemTermFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principle_discount_on' => 'decimal:4',
            'principle_discount_off' => 'decimal:4',
            'distributor_discount_on' => 'decimal:4',
            'distributor_discount_off' => 'decimal:4',
            'negotiated_price' => 'decimal:4',
            'margin' => 'decimal:4',
            'logistic_fee' => 'decimal:4',
            'logistic_support' => 'decimal:4',
            'base_price' => 'decimal:4',
            'final_price' => 'decimal:4',
            'effective_discount' => 'decimal:4',
            'calculator_percentage' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<DplItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(DplItem::class, 'dpl_item_id');
    }
}
