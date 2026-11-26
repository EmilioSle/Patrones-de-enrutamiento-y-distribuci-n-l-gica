<?php

use App\Http\Controllers\Api\CarritoController;
use App\Http\Controllers\Api\PublicacionController;
use App\Http\Controllers\Api\ResumenSolicitudController;
use App\Http\Middleware\PermisoExplicitoToken;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::get('/publicaciones', [PublicacionController::class, 'index'])->name('publicaciones.index');
Route::get('/publicaciones/{id}', [PublicacionController::class, 'show'])->whereNumber('id')->name('publicaciones.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/resumen-solicitud/{id}', [ResumenSolicitudController::class, 'show'])
        ->whereNumber('id')->middleware(PermisoExplicitoToken::class.':solicitudes:leer')->name('solicitudes.resumen');

    Route::get('/carrito', [CarritoController::class, 'index'])
        ->middleware(CheckAbilities::class.':carrito:leer')->name('carrito.index');
    Route::get('/carrito/resumen', [CarritoController::class, 'resumen'])
        ->middleware(CheckAbilities::class.':carrito:leer')->name('carrito.resumen');
    Route::patch('/carrito/articulos/{producto}', [CarritoController::class, 'actualizarCantidad'])
        ->whereNumber('producto')->middleware(PermisoExplicitoToken::class.':carrito:escribir')->name('carrito.actualizar-cantidad');
});
