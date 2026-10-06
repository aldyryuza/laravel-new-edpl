<?php

namespace App\Models\Dpl;

use Database\Factories\Dpl\CommercialParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Parameter bisnis configurable (mis. MAX_DPL_DISCOUNT). value disimpan sebagai jsonb.
 */
#[Table('dpl.commercial_parameters')]
#[Fillable(['code', 'value', 'description', 'is_active'])]
class CommercialParameter extends Model
{
    /** @use HasFactory<CommercialParameterFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_active' => 'boolean',
        ];
    }
}
