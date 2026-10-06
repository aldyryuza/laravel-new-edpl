<?php

use App\Http\Controllers\Web\ActiveRoleController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Dpl\ApproverAssignmentController;
use App\Http\Controllers\Web\ExampleController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\OnlineUserController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SalesAreaController;
use App\Http\Controllers\Web\SalesRegionController;
use App\Http\Controllers\Web\SalesZoneController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
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
    Route::get('/users/export', [UserController::class, 'export'])->middleware(['can:users.print', 'throttle:export'])->name('users.export');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.read')->name('roles.index');
    Route::get('/roles/{role}/access', [RoleController::class, 'access'])->middleware('can:roles.read')->name('roles.access');

    Route::get('/menus', [MenuController::class, 'index'])->middleware('can:menus.read')->name('menus.index');

    Route::get('/online-users', [OnlineUserController::class, 'index'])->middleware('can:online-users.read')->name('online-users.index');

    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('can:audit-log.read')->name('audit-log.index');

    // Master organisasi (schema public)
    Route::get('/sales-zones', [SalesZoneController::class, 'index'])->middleware('can:sales-zones.read')->name('sales-zones.index');
    Route::get('/sales-zones/import-template', [SalesZoneController::class, 'template'])
        ->middleware(['can:sales-zones.create', 'throttle:export'])
        ->name('sales-zones.import-template');
    Route::get('/sales-regions', [SalesRegionController::class, 'index'])->middleware('can:sales-regions.read')->name('sales-regions.index');
    Route::get('/sales-regions/import-template', [SalesRegionController::class, 'template'])
        ->middleware(['can:sales-regions.create', 'throttle:export'])
        ->name('sales-regions.import-template');
    Route::get('/sales-areas', [SalesAreaController::class, 'index'])->middleware('can:sales-areas.read')->name('sales-areas.index');
    Route::get('/sales-areas/import-template', [SalesAreaController::class, 'template'])
        ->middleware(['can:sales-areas.create', 'throttle:export'])
        ->name('sales-areas.import-template');

    // E-DPL (schema dpl)
    Route::prefix('dpl')->name('dpl.')->group(function () {
        Route::get('/approver-assignments', [ApproverAssignmentController::class, 'index'])
            ->middleware('can:approver-assignments.read')
            ->name('approver-assignments.index');
    });
});
