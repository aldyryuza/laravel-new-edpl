<?php

use App\Http\Controllers\Web\ActiveRoleController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CityController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DistributorController;
use App\Http\Controllers\Web\Dpl\ApprovalRuleController;
use App\Http\Controllers\Web\Dpl\ApproverAssignmentController;
use App\Http\Controllers\Web\Dpl\BonusSchemeController;
use App\Http\Controllers\Web\Dpl\CommercialParameterController;
use App\Http\Controllers\Web\Dpl\DistributorLogisticRateController;
use App\Http\Controllers\Web\Dpl\DistributorMarginOverrideController;
use App\Http\Controllers\Web\Dpl\DistributorMarginRuleController;
use App\Http\Controllers\Web\Dpl\DplProposalController;
use App\Http\Controllers\Web\Dpl\ProductMarginOverrideController;
use App\Http\Controllers\Web\Dpl\ProductPriceController;
use App\Http\Controllers\Web\Dpl\ProductPriceOverrideController;
use App\Http\Controllers\Web\ExampleController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\OnlineUserController;
use App\Http\Controllers\Web\OutletClassificationController;
use App\Http\Controllers\Web\OutletController;
use App\Http\Controllers\Web\OutletGroupController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ProvinceController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SalesAreaController;
use App\Http\Controllers\Web\SalesRegionController;
use App\Http\Controllers\Web\SalesZoneController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\UserOrgAssignmentController;
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
    Route::get('/teams', [TeamController::class, 'index'])->middleware('can:teams.read')->name('teams.index');
    Route::get('/user-assignments', [UserOrgAssignmentController::class, 'index'])->middleware('can:user-assignments.read')->name('user-assignments.index');

    // Wilayah administratif (schema public)
    Route::get('/provinces', [ProvinceController::class, 'index'])->middleware('can:provinces.read')->name('provinces.index');
    Route::get('/provinces/import-template', [ProvinceController::class, 'template'])
        ->middleware(['can:provinces.create', 'throttle:export'])
        ->name('provinces.import-template');
    Route::get('/cities', [CityController::class, 'index'])->middleware('can:cities.read')->name('cities.index');
    Route::get('/cities/import-template', [CityController::class, 'template'])
        ->middleware(['can:cities.create', 'throttle:export'])
        ->name('cities.import-template');
    Route::get('/cities/import-logistic-fee-template', [CityController::class, 'logisticFeeTemplate'])
        ->middleware(['can:cities.update', 'throttle:export'])
        ->name('cities.import-logistic-fee-template');

    // Master outlet (schema public)
    Route::get('/outlets', [OutletController::class, 'index'])->middleware('can:outlets.read')->name('outlets.index');
    Route::get('/outlets/import-template', [OutletController::class, 'template'])
        ->middleware(['can:outlets.create', 'throttle:export'])
        ->name('outlets.import-template');
    Route::get('/outlet-groups', [OutletGroupController::class, 'index'])->middleware('can:outlet-groups.read')->name('outlet-groups.index');
    Route::get('/outlet-classifications', [OutletClassificationController::class, 'index'])
        ->middleware('can:outlet-classifications.read')
        ->name('outlet-classifications.index');

    // Master product & distributor (schema public)
    Route::get('/products', [ProductController::class, 'index'])->middleware('can:products.read')->name('products.index');
    Route::get('/products/import-template', [ProductController::class, 'template'])
        ->middleware(['can:products.create', 'throttle:export'])
        ->name('products.import-template');
    Route::get('/distributors', [DistributorController::class, 'index'])->middleware('can:distributors.read')->name('distributors.index');
    Route::get('/distributors/import-template', [DistributorController::class, 'template'])
        ->middleware(['can:distributors.create', 'throttle:export'])
        ->name('distributors.import-template');
    Route::get('/distributors/import-margin-template', [DistributorController::class, 'marginTemplate'])
        ->middleware(['can:distributors.update', 'throttle:export'])
        ->name('distributors.import-margin-template');

    // E-DPL (schema dpl)
    Route::prefix('dpl')->name('dpl.')->group(function () {
        // Pengajuan DPL
        Route::get('/proposals', [DplProposalController::class, 'index'])->middleware('can:dpl-proposals.read')->name('proposals.index');
        Route::get('/proposals/create', [DplProposalController::class, 'create'])->middleware('can:dpl-proposals.create')->name('proposals.create');
        Route::get('/proposals/{proposal}', [DplProposalController::class, 'show'])->middleware('can:dpl-proposals.read')->name('proposals.show');
        Route::get('/proposals/{proposal}/export', [DplProposalController::class, 'export'])
            ->middleware(['can:dpl-proposals.print', 'throttle:export'])
            ->name('proposals.export');

        Route::get('/approval-rules', [ApprovalRuleController::class, 'index'])->middleware('can:approval-rules.read')->name('approval-rules.index');

        Route::get('/approver-assignments', [ApproverAssignmentController::class, 'index'])
            ->middleware('can:approver-assignments.read')
            ->name('approver-assignments.index');

        // Komersial DPL
        Route::get('/product-prices', [ProductPriceController::class, 'index'])->middleware('can:product-prices.read')->name('product-prices.index');
        Route::get('/product-prices/import-template', [ProductPriceController::class, 'template'])
            ->middleware(['can:product-prices.create', 'throttle:export'])
            ->name('product-prices.import-template');
        Route::get('/special-prices', [ProductPriceOverrideController::class, 'index'])->middleware('can:special-prices.read')->name('special-prices.index');
        Route::get('/special-prices/import-template', [ProductPriceOverrideController::class, 'template'])
            ->middleware(['can:special-prices.create', 'throttle:export'])
            ->name('special-prices.import-template');
        Route::get('/bonus-schemes', [BonusSchemeController::class, 'index'])->middleware('can:bonus-schemes.read')->name('bonus-schemes.index');
        Route::get('/commercial-parameters', [CommercialParameterController::class, 'index'])
            ->middleware('can:commercial-parameters.read')
            ->name('commercial-parameters.index');
        Route::get('/distributor-margin-rules', [DistributorMarginRuleController::class, 'index'])
            ->middleware('can:distributor-margin-rules.read')
            ->name('distributor-margin-rules.index');
        Route::get('/distributor-margin-rules/import-template', [DistributorMarginRuleController::class, 'template'])
            ->middleware(['can:distributor-margin-rules.create', 'throttle:export'])
            ->name('distributor-margin-rules.import-template');
        Route::get('/distributor-margin-overrides', [DistributorMarginOverrideController::class, 'index'])
            ->middleware('can:distributor-margin-overrides.read')
            ->name('distributor-margin-overrides.index');
        Route::get('/distributor-margin-overrides/import-template', [DistributorMarginOverrideController::class, 'template'])
            ->middleware(['can:distributor-margin-overrides.create', 'throttle:export'])
            ->name('distributor-margin-overrides.import-template');
        Route::get('/product-margin-overrides', [ProductMarginOverrideController::class, 'index'])
            ->middleware('can:product-margin-overrides.read')
            ->name('product-margin-overrides.index');
        Route::get('/distributor-logistic-rates', [DistributorLogisticRateController::class, 'index'])
            ->middleware('can:distributor-logistic-rates.read')
            ->name('distributor-logistic-rates.index');
    });
});
