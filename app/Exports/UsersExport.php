<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Ekspor daftar user, memakai filter yang sama dengan tabel (scope User::search).
 *
 * StringValueBinder menyimpan semua nilai sebagai teks: mencegah formula injection
 * (mis. nama "=HYPERLINK(...)") dan menjaga nol di depan NIK.
 */
class UsersExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private ?string $search = null) {}

    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        return User::query()
            ->select(['users.id', 'users.nik', 'users.name', 'users.email', 'users.is_active', 'users.last_login_at', 'users.created_at'])
            ->with('roles:id,name')
            ->search($this->search)
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['NIK', 'Nama', 'Email', 'Role', 'Dibuat', 'Status', 'Login Terakhir'];
    }

    /**
     * @param  User  $row
     * @return array<int, string|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->nik,
            $row->name,
            $row->email,
            $row->roles->pluck('name')->sort()->implode(', '),
            $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            $row->is_active ? 'Aktif' : 'Nonaktif',
            $row->last_login_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    public function title(): string
    {
        return 'User';
    }
}
