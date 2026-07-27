<?php

use App\Http\Controllers\Admin\ProductAnalyticsController;
use App\Http\Controllers\ProductAnalyticsIngestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'throttle:60,1'])
    ->post('/product-analytics/events', ProductAnalyticsIngestController::class)
    ->name('product-analytics.ingest');

Route::prefix('admin/product-analytics')
    ->name('admin.product-analytics.')
    ->middleware(['auth', 'verified', 'owner'])
    ->group(function () {
        Route::get('/', [ProductAnalyticsController::class, 'index'])->name('index');
    });
