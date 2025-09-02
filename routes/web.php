<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CredencialController;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});


Route::middleware(['web','auth'])->group(function() {
    Route::get('/credenciales/imprimir', [CredencialController::class, 'imprimirSeleccionados'])
        ->name('credenciales.imprimir');
    Route::get('/planillas/{pla}/reimprimir', [\App\Http\Controllers\PlanillaPrintController::class, 'reimprimir'])
        ->name('planillas.reimprimir');
});
