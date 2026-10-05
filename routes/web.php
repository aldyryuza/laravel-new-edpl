<?php

use App\Http\Controllers\Web\ActiveRoleController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ExampleController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/active-role', [ActiveRoleController::class, 'create'])->name('active-role.create');
    Route::post('/active-role', [ActiveRoleController::class, 'store'])->name('active-role.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/example', [ExampleController::class, 'index'])->middleware('can:example.read')->name('example.index');

    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.read')->name('users.index');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.read')->name('roles.index');
    Route::get('/roles/{role}/access', [RoleController::class, 'access'])->middleware('can:roles.read')->name('roles.access');

    Route::get('/menus', [MenuController::class, 'index'])->middleware('can:menus.read')->name('menus.index');
});
