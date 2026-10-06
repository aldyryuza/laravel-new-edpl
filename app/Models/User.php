<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AuditLog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nik', 'name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, LogsActivity, Notifiable;

    /**
     * Samakan dengan default kolom DB agar instance baru langsung bernilai benar.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Hak akses role aktif, di-cache per instance (per request).
     *
     * @var array<string, array<int, string>>|null
     */
    protected ?array $menuAccessCache = null;

    /**
     * Role yang sedang dipakai. Diisi tiap request oleh middleware ResolveActiveRole
     * dari session; null = belum memilih / tidak punya role = tanpa hak akses.
     */
    protected ?Role $activeRole = null;

    /**
     * Password/remember_token sengaja tidak dicatat; ganti password dicatat terpisah.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['nik', 'name', 'email', 'is_active'], fn () => "User {$this->name} ({$this->nik})");
    }

    /**
     * uuid diisi otomatis saat create; PK tetap `id` (bigint) untuk FK internal.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * UUID v4 acak: identifier publik/JWT `sub` yang opaque (tidak memuat waktu pembuatan).
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Data member untuk presence channel `online` (daftar user online).
     *
     * Halaman dikirim browser saat join (sekali per page load), bukan lewat
     * pesan antar-browser, supaya beban tetap linear terhadap jumlah user.
     *
     * @return array{id: int, nik: string, name: string, role: string|null, page: string|null, title: string|null}
     */
    public function toOnlinePresence(?string $page = null, ?string $title = null): array
    {
        $page = is_string($page) && str_starts_with($page, '/') ? Str::limit($page, 200, '') : null;
        $title = is_string($title) && $title !== '' ? Str::limit(strip_tags($title), 100) : null;

        return [
            'id' => $this->id,
            'nik' => $this->nik,
            'name' => $this->name,
            'role' => $this->activeRole()?->name,
            'page' => $page,
            'title' => $title,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_has_roles');
    }

    /**
     * Cari berdasarkan NIK, nama, atau email (case-insensitive).
     * Dipakai tabel user dan ekspor Excel agar hasilnya selalu sama.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereLike('nik', "%{$term}%")
            ->orWhereLike('name', "%{$term}%")
            ->orWhereLike('email', "%{$term}%"));
    }

    public function activeRole(): ?Role
    {
        return $this->activeRole;
    }

    public function setActiveRole(?Role $role): void
    {
        $this->activeRole = $role;
        $this->flushMenuAccess();
    }

    /**
     * Action yang diizinkan per kode menu untuk role aktif.
     *
     * @return array<string, array<int, string>> contoh: ['users' => ['read', 'update']]
     */
    public function menuAccess(): array
    {
        if ($this->menuAccessCache !== null) {
            return $this->menuAccessCache;
        }

        if ($this->activeRole === null) {
            return $this->menuAccessCache = [];
        }

        $rows = RoleMenuAccess::query()
            ->join('menus', 'menus.id', '=', 'role_menu_accesses.menu_id')
            ->where('role_menu_accesses.role_id', $this->activeRole->id)
            ->get(['menus.code', ...array_map(fn (string $action) => "role_menu_accesses.can_{$action}", RoleMenuAccess::ACTIONS)]);

        $access = [];

        foreach ($rows as $row) {
            foreach (RoleMenuAccess::ACTIONS as $action) {
                if ($row->{"can_{$action}"}) {
                    $access[$row->code][$action] = $action;
                }
            }
        }

        return $this->menuAccessCache = array_map('array_values', $access);
    }

    public function hasMenuAccess(string $menuCode, string $action): bool
    {
        return in_array($action, $this->menuAccess()[$menuCode] ?? [], true);
    }

    /**
     * Kosongkan cache hak akses, misalnya setelah hak akses role diubah.
     */
    public function flushMenuAccess(): void
    {
        $this->menuAccessCache = null;
    }
}
