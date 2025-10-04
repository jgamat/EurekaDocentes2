<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseConsolidadoSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected int $rowCounter = 0;

    public function __construct(
        protected int $procesoFechaId,
        protected string $fechaLabel,
        protected string $generatedAt
    ) {}

    public function collection(): Collection
    {
        $rows = $this->queryRows();
        $out = [];
        $n = 1;
        foreach ($rows as $r) {
            $out[] = [
                $n++,
                $r['tipo'] ?? '',
                $r['codigo'] ?? '',
                $r['dni'] ?? '',
                $r['nombres'] ?? '',
                $r['local'] ?? '',
                $r['cargo'] ?? '',
                $r['monto'] ?? 0,
                $r['fecha_asignacion'] ?? '',
                $r['credencial'] ?? '',
                $r['numero_planilla'] ?? '',
            ];
        }
        return collect($out);
    }

    public function headings(): array
    {
        return [
            [strtoupper($this->titleFull())],
            ["FECHA: {$this->fechaLabel}", '', '', '', '', '', '', '', 'GENERADO: '.$this->generatedAt],
            ['N°','Tipo de personal','Código','DNI','Apellidos y Nombres','Local','Cargo','Monto','Fecha Asignación','N° Credencial','N° Planilla'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title row
        $sheet->mergeCells('A1:K1');
        // Bold title & headings
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3:K3')->getFont()->setBold(true);
        // Column widths: A (N°) fixed narrow, others auto
        $sheet->getColumnDimension('A')->setWidth(5);
        foreach (range('B','K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Apply thin borders to all populated cells (after collection loaded). We assume max rows = collection count + 3 header rows
        $dataLastRow = $sheet->getHighestDataRow();
        $rowCount = max($dataLastRow, 3); // ensure at least header rows
        $sheet->getStyle("A1:K{$rowCount}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Number format with two decimals for Monto column (H), starting row 4 (row 3 is headings)
        if ($rowCount > 3) {
            $sheet->getStyle("H4:H{$rowCount}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
        return [
            'A1' => ['alignment' => ['horizontal' => 'center']],
            'A3:K3' => ['alignment' => ['horizontal' => 'center']],
        ];
    }

    abstract protected function queryRows(): array;
    abstract protected function titleFull(): string;
}
