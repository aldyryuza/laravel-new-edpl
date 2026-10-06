<?php

namespace App\Models\Dpl;

use App\Models\Distributor;
use App\Models\Product;
use Database\Factories\Dpl\DplItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item DPL (product + distributor). Commercial terms ada di DplItemTerm.
 */
#[Table('dpl.dpl_items')]
#[Fillable([
    'dpl_proposal_id', 'product_id', 'distributor_id', 'minimum_order', 'bonus_scheme_id',
    'is_direct_delivery', 'is_buyer_reward', 'is_bonus_conversion', 'is_support_satoria', 'is_returnable',
    'remark', 'netsuite_id',
])]
class DplItem extends Model
{
    /** @use HasFactory<DplItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_order' => 'integer',
            'is_direct_delivery' => 'boolean',
            'is_buyer_reward' => 'boolean',
            'is_bonus_conversion' => 'boolean',
            'is_support_satoria' => 'boolean',
            'is_returnable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'dpl_proposal_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<BonusScheme, $this>
     */
    public function bonusScheme(): BelongsTo
    {
        return $this->belongsTo(BonusScheme::class);
    }

    /**
     * @return HasOne<DplItemTerm, $this>
     */
    public function terms(): HasOne
    {
        return $this->hasOne(DplItemTerm::class);
    }

    /**
     * State approval item ini di setiap step.
     *
     * @return HasMany<ApprovalItemState, $this>
     */
    public function approvalStates(): HasMany
    {
        return $this->hasMany(ApprovalItemState::class);
    }
}
