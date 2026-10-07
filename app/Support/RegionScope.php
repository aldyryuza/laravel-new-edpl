<?php

namespace App\Support;

use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wilayah yang boleh diakses user, dari penugasan aktif yang berlaku hari ini (user_org_assignments).
 *
 * - Ada penugasan Nasional  -> semua data.
 * - Penugasan zona/region/area -> outlet di area yang tercakup; distributor yang region-nya tercakup
 *   (untuk penugasan area, region dari area tersebut, karena distributor dipetakan per region).
 * - Tanpa penugasan -> tidak ada data.
 */
final class RegionScope
{
    /**
     * @param  array<int, int>  $areaIds  area yang tercakup (outlet)
     * @param  array<int, int>  $regionIds  region yang tercakup (distributor)
     */
    private function __construct(
        private bool $national,
        private array $areaIds,
        private array $regionIds,
    ) {}

    public static function forUser(User $user): self
    {
        $assignments = UserOrgAssignment::query()
            ->where('user_id', $user->getKey())
            ->effective()
            ->get(['zone_id', 'region_id', 'area_id']);

        if ($assignments->contains(fn (UserOrgAssignment $assignment) => $assignment->scopeType() === UserOrgAssignment::SCOPE_NATIONAL)) {
            return new self(true, [], []);
        }

        $zoneIds = $assignments->pluck('zone_id')->filter()->all();
        $assignedAreaIds = $assignments->pluck('area_id')->filter()->all();

        // Region yang tercakup penuh: ditugaskan langsung atau berada di zona yang ditugaskan.
        $fullRegionIds = array_unique([
            ...$assignments->pluck('region_id')->filter()->all(),
            ...($zoneIds ? SalesRegion::query()->whereIn('zone_id', $zoneIds)->pluck('id')->all() : []),
        ]);

        // Outlet: area yang ditugaskan + semua area di region yang tercakup penuh.
        $areaIds = array_unique([
            ...$assignedAreaIds,
            ...($fullRegionIds ? SalesArea::query()->whereIn('region_id', $fullRegionIds)->pluck('id')->all() : []),
        ]);

        // Distributor: region yang tercakup penuh + region dari area yang ditugaskan.
        $regionIds = array_unique([
            ...$fullRegionIds,
            ...($assignedAreaIds ? SalesArea::query()->whereIn('id', $assignedAreaIds)->pluck('region_id')->all() : []),
        ]);

        return new self(false, array_values(array_map('intval', $areaIds)), array_values(array_map('intval', $regionIds)));
    }

    public function isNational(): bool
    {
        return $this->national;
    }

    public function isEmpty(): bool
    {
        return ! $this->national && $this->areaIds === [] && $this->regionIds === [];
    }

    /**
     * @return array<int, int>
     */
    public function areaIds(): array
    {
        return $this->areaIds;
    }

    /**
     * @return array<int, int>
     */
    public function regionIds(): array
    {
        return $this->regionIds;
    }

    /**
     * Batasi query outlet (kolom area_id) ke wilayah user.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToOutlets(Builder $query): Builder
    {
        if ($this->national) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('area_id'), $this->areaIds ?: [0]);
    }

    /**
     * Batasi query distributor (lewat distributor_regions) ke wilayah user.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToDistributors(Builder $query): Builder
    {
        if ($this->national) {
            return $query;
        }

        return $query->whereHas('regions', fn (Builder $regions) => $regions->whereIn('sales_regions.id', $this->regionIds ?: [0]));
    }
}
