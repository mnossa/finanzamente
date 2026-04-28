<?php

use App\Http\Controllers\Admin\MagazineAdminController;
use App\Http\Controllers\MagazineController;
use Illuminate\Support\Facades\Route;

// ── Magazine (pubblico) ────────────────────────────────────────────────────
// throttle:60,1 → max 60 req/min per IP (limita scraping e write spam sul view counter)
Route::prefix('magazine')->name('magazine.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [MagazineController::class, 'index'])->name('index');
    Route::get('/categoria/{categorySlug}', [MagazineController::class, 'category'])->name('category');
    Route::get('/{slug}', [MagazineController::class, 'show'])->name('show');
});

// ── Magazine Admin (solo proprietario) ────────────────────────────────────
Route::prefix('admin/magazine')->name('admin.magazine.')->middleware(['auth', 'verified', 'owner'])->group(function () {
    Route::get('/', [MagazineAdminController::class, 'index'])->name('index');
    Route::get('/crea', [MagazineAdminController::class, 'create'])->name('create');
    Route::post('/', [MagazineAdminController::class, 'store'])->name('store');
    Route::get('/unsplash-search', [MagazineAdminController::class, 'unsplashSearch'])->name('unsplash-search');
    Route::get('/{article}/modifica', [MagazineAdminController::class, 'edit'])->name('edit');
    Route::get('/{article}/anteprima', [MagazineAdminController::class, 'preview'])->name('preview');
    Route::put('/{article}', [MagazineAdminController::class, 'update'])->name('update');
    Route::delete('/{article}', [MagazineAdminController::class, 'destroy'])->name('destroy');
});
