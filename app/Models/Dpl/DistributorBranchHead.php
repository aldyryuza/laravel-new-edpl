<?php

namespace App\Models\Dpl;

use App\Models\Distributor;
use Database\Factories\Dpl\DistributorBranchHeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master Kacab distributor (redesign legacy dpl_exit_letter_master) dengan periode berlaku.
 */
#[Table('dpl.distributor_branch_heads')]
#[Fillable(['distributor_id', 'name', 'position', 'valid_from', 'valid_until', 'is_active'])]
class DistributorBranchHead extends Model
{
    /** @use HasFactory<DistributorBranchHeadFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }
}
