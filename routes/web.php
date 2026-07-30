<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/web/public.php';
require __DIR__.'/web/authenticated.php';
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Legacy URLs (Magazine + Product Analytics removed)
|--------------------------------------------------------------------------
|
| Explicit 404 so removed features do not fall through to generic handlers.
|
*/
Route::any('/magazine/{path?}', fn () => abort(404))->where('path', '.*');
Route::any('/admin/magazine/{path?}', fn () => abort(404))->where('path', '.*');
Route::any('/admin/product-analytics/{path?}', fn () => abort(404))->where('path', '.*');
Route::any('/product-analytics/{path?}', fn () => abort(404))->where('path', '.*');
