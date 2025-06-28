<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;
use App\Http\Controllers\ClienteHistorialController;

Route::redirect('/', '/admin'); 

Route::get('/image/convert', [ImageConverter::class, 'convert']);

Route::get('clientes/{cliente}/historial/export-excel', [ClienteHistorialController::class, 'exportExcel'])
    ->name('clientes.historial.excel');

// Ruta para descargar PDF
Route::get('clientes/{cliente}/historial/export-pdf', [ClienteHistorialController::class, 'exportPdf'])
    ->name('clientes.historial.pdf');


    // --- Rutas de Historial de Usuarios ---
// ¡Estas nuevas rutas apuntan al UserHistorialController!
Route::get('users/{user}/historial/export-excel', [UserHistorialController::class, 'exportExcel'])
    ->name('users.historial.excel');

Route::get('users/{user}/historial/export-pdf', [UserHistorialController::class, 'exportPdf'])
    ->name('users.historial.pdf');