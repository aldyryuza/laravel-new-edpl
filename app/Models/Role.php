<?php

namespace App\Models;

use App\Support\AuditLog;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, LogsActivity;

    public const ADMINISTRATOR = 'Administrator';

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['name', 'description'], fn () => "Role {$this->name}");
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_roles');
    }

    /**
     * @return HasMany<RoleMenuAccess, $this>
     */
    public function menuAccesses(): HasMany
    {
        return $this->hasMany(RoleMenuAccess::class);
    }
}
