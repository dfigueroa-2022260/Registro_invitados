<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\PaginaController;
use Illuminate\Support\Facades\Route;

// ----- Páginas -----
Route::get('/', [PaginaController::class, 'login'])->name('login');
Route::get('/firma', [PaginaController::class, 'firma']);
Route::get('/asiento', [PaginaController::class, 'asiento']);
Route::get('/admin', [PaginaController::class, 'admin']);

// ----- API JSON (misma sesión y protección CSRF que las páginas) -----
Route::prefix('api')->group(function () {
    Route::post('invitados/verificar', [InvitadoController::class, 'verificar']);
    Route::post('salir', [InvitadoController::class, 'salir']);
    Route::post('firmas', [FirmaController::class, 'guardar']);

    Route::middleware('api.invitado')->prefix('consulta')->group(function () {
        Route::get('yo', [ConsultaController::class, 'yo']);
        Route::get('mapa', [ConsultaController::class, 'mapa']);
        Route::get('buscar', [ConsultaController::class, 'buscar']);
    });

    Route::post('admin/login', [AdminController::class, 'login']);

    Route::middleware('api.admin')->prefix('admin')->group(function () {
        Route::post('logout', [AdminController::class, 'logout']);
        Route::get('sala', [AdminController::class, 'sala']);
        Route::post('asientos', [AdminController::class, 'agregar']);
        Route::put('asientos/{id}', [AdminController::class, 'editar'])->whereNumber('id');
        Route::delete('asientos/{id}', [AdminController::class, 'eliminar'])->whereNumber('id');
        Route::put('lados/{lado}', [AdminController::class, 'cambiarLetra']);
        Route::get('exportar', [AdminController::class, 'exportar']);
        Route::get('firmas/exportar', [AdminController::class, 'exportarFirmas']);
        Route::post('importar', [AdminController::class, 'importar']);
        Route::get('plantilla', [AdminController::class, 'plantilla']);
    });
});
