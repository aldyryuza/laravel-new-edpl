<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ExampleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/example', [ExampleController::class, 'index'])->name('example.index');
