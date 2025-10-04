<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlantillaAsignacionAlumnosExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            // Formato oficial reducido (6 columnas)
            'codigo','dni','nombres','cargo','local','fecha'
        ];
    }

    public function array(): array
    {
        return [
            ['A001','71234567','PEREZ LOPEZ JUAN','ASISTENTE','LOCAL PRINCIPAL', now()->format('Y-m-d')],
        ];
    }
}
