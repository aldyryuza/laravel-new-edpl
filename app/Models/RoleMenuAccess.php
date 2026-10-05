<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['role_id', 'menu_id', 'can_create', 'can_read', 'can_update', 'can_delete', 'can_print', 'can_approve'])]
class RoleMenuAccess extends Model
{
    /**
     * Action yang bisa diatur per menu. Kolom tabel: can_{action}.
     * Dipakai sebagai ability "{kode_menu}.{action}", contoh: users.create.
     */
    public const ACTIONS = ['create', 'read', 'update', 'delete', 'print', 'approve'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_fill_keys(array_map(fn (string $action) => "can_{$action}", self::ACTIONS), 'boolean');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
