<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletGroupMember;
use Illuminate\Support\Facades\DB;

/**
 * Simpan outlet beserta keanggotaan group RS (GRUP3).
 *
 * Satu outlet maksimal satu keanggotaan aktif (valid_until kosong; dijaga partial unique index).
 * Pindah/lepas group menutup keanggotaan lama per hari ini (valid_until) dan membuka yang baru,
 * sehingga histori keanggotaan tetap tersimpan.
 */
class OutletService
{
    /**
     * @param  array<string, mixed>  $attributes  atribut kolom outlets
     * @param  int|null  $groupId  group aktif yang diinginkan; null = tanpa group
     */
    public function save(Outlet $outlet, array $attributes, ?int $groupId): Outlet
    {
        return DB::transaction(function () use ($outlet, $attributes, $groupId): Outlet {
            $outlet->fill($attributes)->save();
            $this->syncGroup($outlet, $groupId);

            return $outlet->load(['activeGroupMembership.outletGroup:id,code,name']);
        });
    }

    /**
     * Pastikan group aktif outlet = $groupId. Tidak melakukan apa pun bila sudah sama.
     */
    public function syncGroup(Outlet $outlet, ?int $groupId): void
    {
        $active = $outlet->activeGroupMembership()->first();

        if ($active?->outlet_group_id === $groupId) {
            return;
        }

        $today = now()->toDateString();

        $active?->update(['valid_until' => $today]);

        if ($groupId !== null) {
            OutletGroupMember::query()->create([
                'outlet_group_id' => $groupId,
                'outlet_id' => $outlet->id,
                'valid_from' => $today,
            ]);
        }

        $outlet->unsetRelation('activeGroupMembership');
    }
}
