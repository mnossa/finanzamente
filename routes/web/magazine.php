<?php

use Illuminate\Support\Facades\Route;

// ── Magazine (pubblico) ────────────────────────────────────────────────────
// throttle:60,1 → max 60 req/min per IP (limita scraping e write spam sul view counter)
Route::prefix('magazine')->name('magazine.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [\App\Http\Controllers\MagazineController::class, 'index'])->name('index');
    Route::get('/categoria/{categorySlug}', [\App\Http\Controllers\MagazineController::class, 'category'])->name('category');
    Route::get('/{slug}', [\App\Http\Controllers\MagazineController::class, 'show'])->name('show');
});

// ── Magazine Admin (solo proprietario) ────────────────────────────────────
Route::prefix('admin/magazine')->name('admin.magazine.')->middleware(['auth', 'verified', 'owner'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'index'])->name('index');
    Route::get('/crea', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'store'])->name('store');
    Route::get('/unsplash-search', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'unsplashSearch'])->name('unsplash-search');
    Route::get('/{article}/modifica', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'edit'])->name('edit');
    Route::get('/{article}/anteprima', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'preview'])->name('preview');
    Route::put('/{article}', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'update'])->name('update');
    Route::delete('/{article}', [\App\Http\Controllers\Admin\MagazineAdminController::class, 'destroy'])->name('destroy');
});
