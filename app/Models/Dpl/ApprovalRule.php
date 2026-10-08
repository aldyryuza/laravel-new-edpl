<?php

namespace App\Models\Dpl;

use App\Models\Province;
use App\Support\AuditLog;
use Database\Factories\Dpl\ApprovalRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Aturan jalur approval item (legacy DPL_ITEM_APPROVAL_RULE): rentang Total Calculator (bawah eksklusif,
 * atas inklusif, null = tanpa batas) + provinsi outlet (kosong = semua) -> step workflow yang wajib.
 */
#[Table('dpl.approval_rules')]
#[Fillable(['name', 'discount_lower', 'discount_upper', 'sort_order', 'note', 'is_active'])]
class ApprovalRule extends Model
{
    /** @use HasFactory<ApprovalRuleFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['name', 'discount_lower', 'discount_upper', 'sort_order', 'note', 'is_active'], fn () => "Aturan approval {$this->name}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_lower' => 'decimal:4',
            'discount_upper' => 'decimal:4',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Apakah Total Calculator (string desimal) masuk rentang aturan.
     */
    public function coversTotal(string $total): bool
    {
        return ($this->discount_lower === null || (float) $total > (float) $this->discount_lower)
            && ($this->discount_upper === null || (float) $total <= (float) $this->discount_upper);
    }

    /**
     * @return BelongsToMany<ApprovalWorkflowStep, $this>
     */
    public function steps(): BelongsToMany
    {
        return $this->belongsToMany(ApprovalWorkflowStep::class, 'dpl.approval_rule_steps', 'approval_rule_id', 'workflow_step_id');
    }

    /**
     * Kosong = berlaku semua provinsi.
     *
     * @return BelongsToMany<Province, $this>
     */
    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(Province::class, 'dpl.approval_rule_provinces', 'approval_rule_id', 'province_id');
    }
}
