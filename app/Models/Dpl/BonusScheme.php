<?php

namespace App\Models\Dpl;

use Database\Factories\Dpl\BonusSchemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Skema bonus (legacy dpl_bonus).
 */
#[Table('dpl.bonus_schemes')]
#[Fillable([
    'code', 'name', 'buy_quantity', 'bonus_quantity', 'discount_invoice', 'discount_bonus',
    'effective_discount', 'is_active',
])]
class BonusScheme extends Model
{
    /** @use HasFactory<BonusSchemeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buy_quantity' => 'decimal:4',
            'bonus_quantity' => 'decimal:4',
            'discount_invoice' => 'decimal:4',
            'discount_bonus' => 'decimal:4',
            'effective_discount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
