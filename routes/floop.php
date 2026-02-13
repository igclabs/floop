<?php

use IgcLabs\Floop\Http\Controllers\FloopController;
use Illuminate\Support\Facades\Route;

$prefix = config('floop.route_prefix', '_feedback');
$middleware = config('floop.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::post('/', [FloopController::class, 'store']);
        Route::get('/', [FloopController::class, 'index']);
        Route::get('/counts', [FloopController::class, 'counts']);
        Route::post('/action', [FloopController::class, 'action']);
    });
