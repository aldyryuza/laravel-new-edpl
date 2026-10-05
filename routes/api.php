<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleAccessController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| Semua route di sini otomatis: prefix /api, name api.*, middleware web + auth
| (lihat bootstrap/app.php). Hak akses per action diatur di masing-masing controller.
|
| Konvensi: data untuk tabel diambil dari `GET {resource}/datatable` (Yajra,
| protokol DataTables). Endpoint JSON biasa hanya untuk kebutuhan non-tabel.
*/

// Profil milik user yang login: tidak butuh hak akses menu.
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::put('profile/password', [ProfileController::class, 'updatePassword'])
    ->middleware('throttle:password')
    ->name('profile.password');

Route::get('users/datatable', [UserController::class, 'datatable'])->name('users.datatable');
Route::apiResource('users', UserController::class)->except('index');

Route::get('roles/datatable', [RoleController::class, 'datatable'])->name('roles.datatable');
Route::apiResource('roles', RoleController::class)->except('index');
Route::get('roles/{role}/access', [RoleAccessController::class, 'show'])->name('roles.access.show');
Route::put('roles/{role}/access', [RoleAccessController::class, 'update'])->name('roles.access.update');

Route::get('menus/datatable', [MenuController::class, 'datatable'])->name('menus.datatable');
Route::apiResource('menus', MenuController::class);

Route::get('activity-logs/datatable', [ActivityLogController::class, 'datatable'])->name('activity-logs.datatable');
