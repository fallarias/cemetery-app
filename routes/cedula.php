<?php

use App\Http\Controllers\CedulaController;
use Illuminate\Support\Facades\Route;

// Add ->middleware('auth') to the group if only logged-in users should use it.


Route::middleware('auth')->group(function () {
    Route::get('/cedula', [CedulaController::class, 'cedula_display'])->name('display.cedula');
    Route::post('/cedula/print', [CedulaController::class, 'print']);

    Route::prefix('cedula/records')->group(function () {
        Route::get('/', [CedulaController::class, 'index']);
        Route::post('/', [CedulaController::class, 'store']);
        Route::get('/{cedula}', [CedulaController::class, 'show']);
        Route::delete('/{cedula}', [CedulaController::class, 'destroy']);

    });
});
