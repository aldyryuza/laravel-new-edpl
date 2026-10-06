<?php

namespace App\Models\Dpl;

use Database\Factories\Dpl\ApprovalWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template workflow approval (pengganti legacy dpl_approval_line).
 */
#[Table('dpl.approval_workflows')]
#[Fillable(['code', 'name', 'description', 'is_active'])]
class ApprovalWorkflow extends Model
{
    /** @use HasFactory<ApprovalWorkflowFactory> */
    use HasFactory;

    /** Workflow standard RM -> NSM -> GM -> SMD -> DMD (di-seed ApprovalReferenceSeeder). */
    public const CODE_DPL_STANDARD = 'DPL_STANDARD';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ApprovalWorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('sequence');
    }
}
