<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlantillaAsignacionAdministrativosExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
    return ['codigo','dni','nombres','cargo','local','fecha'];
    }

    public function array(): array
    {
        return [
            ['ADM001','12345678','GARCIA DIAZ LUIS','CARGO X','LOCAL Y','2025-01-01'],
        ];
    }
}
