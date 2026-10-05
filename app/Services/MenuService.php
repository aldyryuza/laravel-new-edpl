<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Menyusun data menu (tabel menus) menjadi pohon:
 * untuk sidebar, daftar management, dan halaman pengaturan hak akses.
 */
class MenuService
{
    /**
     * Semua menu terurut seperti pohon (parent lalu anak-anaknya), dengan atribut `depth`.
     *
     * @return Collection<int, Menu>
     */
    public function flatTree(): Collection
    {
        $flat = collect();

        $walk = function (Collection $byParent, ?int $parentId, int $depth) use (&$walk, $flat): void {
            foreach ($byParent->get($parentId ?? 0, []) as $menu) {
                $menu->setAttribute('depth', $depth);
                $flat->push($menu);
                $walk($byParent, $menu->id, $depth + 1);
            }
        };

        $walk($this->groupedByParent(), null, 0);

        return $flat;
    }

    /**
     * Item sidebar dalam format yang dipakai `layouts.partials.menu-items`.
     *
     * Menu dengan route tampil jika user punya akses `read`.
     * Menu grup (tanpa route) tampil jika minimal satu anaknya tampil.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sidebarFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $byParent = $this->groupedByParent()->map(
            fn (Collection $menus) => $menus->where('is_active', true)
        );

        return $this->sidebarItems($byParent, null, $user);
    }

    /**
     * @param  Collection<int, Collection<int, Menu>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function sidebarItems(Collection $byParent, ?int $parentId, User $user): array
    {
        $items = [];

        foreach ($byParent->get($parentId ?? 0, []) as $menu) {
            $children = $this->sidebarItems($byParent, $menu->id, $user);
            $canOpen = $menu->route && $user->hasMenuAccess($menu->code, 'read');

            if (! $canOpen && $children === []) {
                continue;
            }

            $items[] = [
                'title' => $menu->title,
                'icon' => $menu->icon,
                'route' => $canOpen ? $menu->route : null,
                // users.index -> users.* supaya halaman turunan (mis. users.edit) tetap menandai menu aktif.
                'active' => $menu->route && str_contains($menu->route, '.')
                    ? [Str::beforeLast($menu->route, '.').'.*']
                    : array_filter([$menu->route]),
                'children' => $children,
            ];
        }

        return $items;
    }

    /**
     * Menu dikelompokkan per parent_id (0 = root), sudah terurut.
     *
     * @return Collection<int, Collection<int, Menu>>
     */
    private function groupedByParent(): Collection
    {
        return Menu::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->groupBy(fn (Menu $menu) => $menu->parent_id ?? 0);
    }
}
