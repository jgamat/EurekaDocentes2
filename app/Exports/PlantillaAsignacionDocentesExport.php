<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlantillaAsignacionDocentesExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
    return ['codigo','dni','nombres','cargo','local','fecha'];
    }

    public function array(): array
    {
        return [
            // Para docentes normalmente se usa solo nombres completos; se dejan paterno/materno vacíos opcionales.
            ['DOC001','44556677','PEREZ LOPEZ JUAN','COORDINADOR','LOCAL PRINCIPAL','2025-10-01'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        return [];
    }
}
