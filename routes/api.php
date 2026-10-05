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
*/

// Profil milik user yang login: tidak butuh hak akses menu.
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::put('profile/password', [ProfileController::class, 'updatePassword'])
    ->middleware('throttle:password')
    ->name('profile.password');

Route::apiResource('users', UserController::class);

Route::apiResource('roles', RoleController::class);
Route::get('roles/{role}/access', [RoleAccessController::class, 'show'])->name('roles.access.show');
Route::put('roles/{role}/access', [RoleAccessController::class, 'update'])->name('roles.access.update');

Route::apiResource('menus', MenuController::class);

Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
