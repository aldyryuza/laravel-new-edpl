<?php

namespace App\Models;

use App\Support\AuditLog;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['parent_id', 'code', 'title', 'icon', 'route', 'sort_order', 'is_active'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['parent_id', 'code', 'title', 'icon', 'route', 'sort_order', 'is_active'],
            fn () => "Menu {$this->title} ({$this->code})",
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    /**
     * @return HasMany<RoleMenuAccess, $this>
     */
    public function roleAccesses(): HasMany
    {
        return $this->hasMany(RoleMenuAccess::class);
    }

    /**
     * Apakah $menuId adalah menu ini sendiri atau salah satu turunannya.
     * Dipakai untuk mencegah parent melingkar.
     */
    public function isSelfOrDescendant(int $menuId): bool
    {
        $current = Menu::query()->find($menuId);

        while ($current !== null) {
            if ($current->id === $this->id) {
                return true;
            }

            $current = $current->parent_id ? Menu::query()->find($current->parent_id) : null;
        }

        return false;
    }
}
