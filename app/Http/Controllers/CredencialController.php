<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\ProcesoDocente;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use Barryvdh\DomPDF\Facade\Pdf;

class CredencialController extends Controller
{
    /**
     * Genera un PDF de prueba (placeholder) hasta integrar selección desde Livewire.
     */
    public function imprimirSeleccionados(Request $request)
    {
        // Placeholder simple: luego se integrará la recopilación de IDs desde la tabla Livewire
        $html = view('credenciales.plantilla-prueba', [
            'titulo' => 'Credenciales',
            'fecha' => now()->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        return $pdf->download('credenciales.pdf');
    }
}
