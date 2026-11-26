<?php

use App\Http\Controllers\Api\CarritoController;
use App\Http\Controllers\Api\EventoSolicitudController;
use App\Http\Controllers\Api\ExportacionCarritoController;
use App\Http\Controllers\Api\PublicacionController;
use App\Http\Controllers\Api\ResumenSolicitudController;
use App\Http\Middleware\PermisoExplicitoToken;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::get('/publicaciones', [PublicacionController::class, 'index'])->name('publicaciones.index');
Route::get('/publicaciones/{id}', [PublicacionController::class, 'show'])->whereNumber('id')->name('publicaciones.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/solicitudes/eventos', [EventoSolicitudController::class, 'store'])
        ->middleware(PermisoExplicitoToken::class.':solicitudes:eventos')->name('solicitudes.eventos.store');

    Route::get('/resumen-solicitud/{id}', [ResumenSolicitudController::class, 'show'])
        ->whereNumber('id')->middleware(PermisoExplicitoToken::class.':solicitudes:leer')->name('solicitudes.resumen');

    Route::post('/carrito/exportaciones/{modo}', [ExportacionCarritoController::class, 'store'])
        ->whereIn('modo', ['sincrona', 'asincrona'])
        ->middleware(PermisoExplicitoToken::class.':carrito:leer')->name('carrito.exportaciones.store');
    Route::get('/carrito/exportaciones/{id}', [ExportacionCarritoController::class, 'show'])
        ->whereUuid('id')->middleware(PermisoExplicitoToken::class.':carrito:leer')->name('carrito.exportaciones.show');

    Route::get('/carrito', [CarritoController::class, 'index'])
        ->middleware(CheckAbilities::class.':carrito:leer')->name('carrito.index');
    Route::get('/carrito/resumen', [CarritoController::class, 'resumen'])
        ->middleware(CheckAbilities::class.':carrito:leer')->name('carrito.resumen');
    Route::patch('/carrito/articulos/{producto}', [CarritoController::class, 'actualizarCantidad'])
        ->whereNumber('producto')->middleware(PermisoExplicitoToken::class.':carrito:escribir')->name('carrito.actualizar-cantidad');
});
