<?php

namespace App\Models;

use App\Support\AuditLog;
use Database\Factories\OutletSalesAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * PIC outlet: AM dan SPV penanggung jawab outlet (legacy dpl_map_outlet_users). Minimal salah satu terisi.
 */
#[Fillable(['outlet_id', 'am_user_id', 'spv_user_id'])]
class OutletSalesAssignment extends Model
{
    /** @use HasFactory<OutletSalesAssignmentFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['outlet_id', 'am_user_id', 'spv_user_id'], fn () => "PIC outlet {$this->outlet?->code}");
    }

    /**
     * Pemilik DPL anak konsolidasi: SPV, bila kosong AM.
     */
    public function ownerUserId(): ?int
    {
        return $this->spv_user_id ?? $this->am_user_id;
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function am(): BelongsTo
    {
        return $this->belongsTo(User::class, 'am_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function spv(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spv_user_id');
    }
}
