<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DistributorController;
use App\Http\Controllers\Api\Dpl\ApproverAssignmentController;
use App\Http\Controllers\Api\Dpl\BonusSchemeController;
use App\Http\Controllers\Api\Dpl\CommercialParameterController;
use App\Http\Controllers\Api\Dpl\DplProposalController;
use App\Http\Controllers\Api\Dpl\ProductPriceController;
use App\Http\Controllers\Api\Dpl\ProductPriceOverrideController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OutletCategoryController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\OutletGroupController;
use App\Http\Controllers\Api\OwnershipTypeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\RoleAccessController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalesAreaController;
use App\Http\Controllers\Api\SalesRegionController;
use App\Http\Controllers\Api\SalesZoneController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserOrgAssignmentController;
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

// Master organisasi (schema public)
Route::get('sales-zones/datatable', [SalesZoneController::class, 'datatable'])->name('sales-zones.datatable');
Route::post('sales-zones/import', [SalesZoneController::class, 'import'])->name('sales-zones.import');
Route::apiResource('sales-zones', SalesZoneController::class)->except('index');
Route::get('sales-regions/datatable', [SalesRegionController::class, 'datatable'])->name('sales-regions.datatable');
Route::post('sales-regions/import', [SalesRegionController::class, 'import'])->name('sales-regions.import');
Route::apiResource('sales-regions', SalesRegionController::class)->except('index');
Route::get('sales-areas/datatable', [SalesAreaController::class, 'datatable'])->name('sales-areas.datatable');
Route::post('sales-areas/import', [SalesAreaController::class, 'import'])->name('sales-areas.import');
Route::apiResource('sales-areas', SalesAreaController::class)->except('index');
Route::get('teams/datatable', [TeamController::class, 'datatable'])->name('teams.datatable');
Route::apiResource('teams', TeamController::class)->only(['store', 'update', 'destroy']);
Route::get('user-assignments/datatable', [UserOrgAssignmentController::class, 'datatable'])->name('user-assignments.datatable');
Route::apiResource('user-assignments', UserOrgAssignmentController::class)->only(['store', 'update', 'destroy']);

// Wilayah administratif (schema public)
Route::get('provinces/datatable', [ProvinceController::class, 'datatable'])->name('provinces.datatable');
Route::post('provinces/import', [ProvinceController::class, 'import'])->name('provinces.import');
Route::apiResource('provinces', ProvinceController::class)->only(['store', 'update', 'destroy']);
Route::get('cities/datatable', [CityController::class, 'datatable'])->name('cities.datatable');
Route::post('cities/import', [CityController::class, 'import'])->name('cities.import');
Route::apiResource('cities', CityController::class)->only(['store', 'update', 'destroy']);

// Master outlet (schema public)
Route::get('outlets/datatable', [OutletController::class, 'datatable'])->name('outlets.datatable');
Route::post('outlets/import', [OutletController::class, 'import'])->name('outlets.import');
Route::apiResource('outlets', OutletController::class)->except('index');
Route::get('outlet-groups/datatable', [OutletGroupController::class, 'datatable'])->name('outlet-groups.datatable');
Route::apiResource('outlet-groups', OutletGroupController::class)->only(['store', 'update', 'destroy']);
Route::get('outlet-categories/datatable', [OutletCategoryController::class, 'datatable'])->name('outlet-categories.datatable');
Route::apiResource('outlet-categories', OutletCategoryController::class)->only(['store', 'update', 'destroy']);
Route::get('ownership-types/datatable', [OwnershipTypeController::class, 'datatable'])->name('ownership-types.datatable');
Route::apiResource('ownership-types', OwnershipTypeController::class)->only(['store', 'update', 'destroy']);

// Master product & distributor (schema public)
Route::get('products/datatable', [ProductController::class, 'datatable'])->name('products.datatable');
Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
Route::apiResource('products', ProductController::class)->except('index');
Route::get('distributors/datatable', [DistributorController::class, 'datatable'])->name('distributors.datatable');
Route::get('distributors/options', [DistributorController::class, 'options'])->name('distributors.options');
Route::post('distributors/import', [DistributorController::class, 'import'])->name('distributors.import');
Route::apiResource('distributors', DistributorController::class)->except('index');

// E-DPL (schema dpl)
Route::prefix('dpl')->name('dpl.')->group(function () {
    // Pengajuan DPL
    Route::get('proposals/datatable', [DplProposalController::class, 'datatable'])->name('proposals.datatable');
    Route::get('proposals/outlet-options', [DplProposalController::class, 'outletOptions'])->name('proposals.outlet-options');
    Route::get('proposals/pricing', [DplProposalController::class, 'pricing'])->name('proposals.pricing');
    Route::post('proposals/calculate', [DplProposalController::class, 'calculate'])->name('proposals.calculate');
    Route::get('proposals/item-defaults', [DplProposalController::class, 'itemDefaults'])->name('proposals.item-defaults');
    Route::apiResource('proposals', DplProposalController::class)->except('index');

    Route::get('approver-assignments/datatable', [ApproverAssignmentController::class, 'datatable'])->name('approver-assignments.datatable');
    Route::apiResource('approver-assignments', ApproverAssignmentController::class)->except('index');

    // Komersial DPL
    Route::get('product-prices/datatable', [ProductPriceController::class, 'datatable'])->name('product-prices.datatable');
    Route::post('product-prices/import', [ProductPriceController::class, 'import'])->name('product-prices.import');
    Route::apiResource('product-prices', ProductPriceController::class)->only(['store', 'update', 'destroy']);
    Route::get('special-prices/datatable', [ProductPriceOverrideController::class, 'datatable'])->name('special-prices.datatable');
    Route::post('special-prices/import', [ProductPriceOverrideController::class, 'import'])->name('special-prices.import');
    Route::apiResource('special-prices', ProductPriceOverrideController::class)->only(['store', 'update', 'destroy']);
    Route::get('bonus-schemes/datatable', [BonusSchemeController::class, 'datatable'])->name('bonus-schemes.datatable');
    Route::apiResource('bonus-schemes', BonusSchemeController::class)->only(['store', 'update', 'destroy']);
    Route::get('commercial-parameters/datatable', [CommercialParameterController::class, 'datatable'])->name('commercial-parameters.datatable');
    Route::apiResource('commercial-parameters', CommercialParameterController::class)->only(['store', 'update', 'destroy']);
});
