<?php

namespace App\Services;

use App\Models\Distributor;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Simpan distributor beserta region (distributor_regions) dan jaga hierarki induk -> cabang tidak melingkar.
 */
class DistributorService
{
    /** Batas kedalaman penelusuran induk, pengaman bila data lama sudah melingkar. */
    private const MAX_DEPTH = 50;

    /**
     * @param  array<string, mixed>  $attributes  atribut kolom distributors
     * @param  array<int, int>|null  $regionIds  null = region tidak diubah
     */
    public function save(Distributor $distributor, array $attributes, ?array $regionIds): Distributor
    {
        return DB::transaction(function () use ($distributor, $attributes, $regionIds): Distributor {
            $distributor->fill($attributes)->save();

            if ($regionIds !== null) {
                $this->syncRegions($distributor, $regionIds);
            }

            return $distributor;
        });
    }

    /**
     * Apakah menjadikan $parentId sebagai induk $distributor membuat hierarki melingkar
     * (induk adalah distributor itu sendiri atau salah satu cabangnya).
     */
    public function createsCycle(?Distributor $distributor, ?int $parentId): bool
    {
        if ($parentId === null || $distributor === null || ! $distributor->exists) {
            return false;
        }

        $currentId = $parentId;

        for ($depth = 0; $currentId !== null && $depth < self::MAX_DEPTH; $depth++) {
            if ($currentId === $distributor->id) {
                return true;
            }

            $currentId = Distributor::query()->whereKey($currentId)->value('parent_distributor_id');
        }

        return $currentId !== null;
    }

    /**
     * Sinkronkan region distributor. Pivot tidak memicu model event, jadi perubahan dicatat manual
     * ke audit log (hanya bila ada perubahan).
     *
     * @param  array<int, int>  $regionIds
     * @return bool apakah ada perubahan
     */
    public function syncRegions(Distributor $distributor, array $regionIds): bool
    {
        $before = $distributor->regions()->orderBy('code')->pluck('code')->all();
        $changes = $distributor->regions()->sync($regionIds);

        if ($changes['attached'] === [] && $changes['detached'] === []) {
            return false;
        }

        activity(AuditLog::DATA)
            ->event('regions_updated')
            ->performedOn($distributor)
            ->withChanges([
                'old' => ['regions' => $before],
                'attributes' => ['regions' => $distributor->regions()->orderBy('code')->pluck('code')->all()],
            ])
            ->log("Region distributor {$distributor->name} ({$distributor->code}) diubah");

        return true;
    }
}
