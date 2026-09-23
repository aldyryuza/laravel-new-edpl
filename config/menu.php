<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu
|--------------------------------------------------------------------------
|
| Struktur menu didefinisikan di sini supaya Blade tetap bersih dan
| penambahan menu tidak perlu mengubah layout.
|
| Bentuk item:
|
|   ['header' => 'Master Data']                      -> judul section
|
|   [
|       'title'  => 'Users',
|       'icon'   => 'bx bx-user',                    -> class boxicons (opsional untuk child)
|       'route'  => 'users.index',                   -> nama route
|       'params' => [],                              -> parameter route (opsional)
|       'url'    => '/users',                        -> alternatif jika bukan named route
|       'active' => ['users.*'],                     -> pattern route untuk state aktif (opsional)
|       'can'    => 'users.view',                    -> permission/ability (opsional)
|       'badge'  => ['text' => '3', 'class' => 'bg-danger'],
|       'target' => '_blank',
|       'children' => [ ... ],                       -> submenu (boleh bertingkat)
|   ]
|
| Menu ini ikut ter-cache oleh `php artisan config:cache`.
|
*/

return [

    ['header' => 'Menu Utama'],

    [
        'title' => 'Dashboard',
        'icon' => 'bx bx-home-smile',
        'route' => 'dashboard',
    ],

    ['header' => 'Contoh Struktur'],

    [
        'title' => 'Master Data',
        'icon' => 'bx bx-data',
        'children' => [
            [
                'title' => 'Contoh Halaman',
                'route' => 'example.index',
            ],
            [
                'title' => 'Submenu Bertingkat',
                'children' => [
                    ['title' => 'Level 3', 'url' => 'javascript:void(0);'],
                ],
            ],
        ],
    ],
];
