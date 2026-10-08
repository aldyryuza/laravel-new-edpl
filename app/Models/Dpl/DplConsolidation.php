<?php

namespace App\Models\Dpl;

use App\Models\OutletGroup;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Database\Factories\Dpl\DplConsolidationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Konsolidasi / Group RS: satu DPL induk (tipe CONSOLIDATION, tanpa outlet) untuk banyak outlet.
 * Setelah semua item approved, dipecah menjadi satu DPL per outlet (status GENERATED).
 * Mode MANUAL: outlet dipilih dari wilayah pengaju, DPL anak milik pengaju.
 * Mode GROUP: outlet dari satu group RS, DPL anak milik PIC outlet (SPV, bila kosong AM), kecuali team pengaju
 * termasuk config('dpl.consolidation_creator_owned_teams').
 */
#[Table('dpl.dpl_consolidations')]
#[Fillable(['dpl_proposal_id', 'code', 'created_by_user_id', 'status', 'mode', 'outlet_group_id', 'region_id', 'zone_id'])]
class DplConsolidation extends Model
{
    /** @use HasFactory<DplConsolidationFactory> */
    use HasFactory;

    public const MODE_MANUAL = 'MANUAL';

    public const MODE_GROUP = 'GROUP';

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_GENERATED = 'GENERATED';

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'dpl_proposal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<OutletGroup, $this>
     */
    public function outletGroup(): BelongsTo
    {
        return $this->belongsTo(OutletGroup::class);
    }

    /**
     * @return BelongsTo<SalesRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(SalesRegion::class);
    }

    /**
     * @return BelongsTo<SalesZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SalesZone::class);
    }

    /**
     * @return HasMany<DplConsolidationOutlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(DplConsolidationOutlet::class, 'consolidation_id');
    }
}
