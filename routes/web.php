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

// Debug route to inspect runtime mail configuration in local environment
if (app()->environment('local')) {
    Route::get('/_maildebug', function () {
        return response()->json([
            'env' => app()->environment(),
            'app.url' => config('app.url'),
            'mail.default' => config('mail.default'),
            'mail.from' => config('mail.from'),
            'queue.default' => config('queue.default'),
        ]);
    });

    Route::get('/_mailtest', function () {
        try {
            $to = request('to', 'jtellog@unmsm.edu.pe');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['sent' => false, 'error' => 'Invalid email'], 422);
            }
            \Mail::raw('Test SMTP from Eureka', function ($message) use ($to) {
                $message->to($to)->subject('SMTP Test');
            });
            return response()->json(['sent' => true, 'to' => $to]);
        } catch (\Throwable $e) {
            return response()->json(['sent' => false, 'error' => $e->getMessage()], 500);
        }
    });
}
