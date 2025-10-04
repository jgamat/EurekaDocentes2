<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class GenericSimpleArrayExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithStyles
{
    public function __construct(private Collection $rows, private string $title = 'Export') {}

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->rows->isEmpty()) return [];
        $keys = array_keys($this->rows->first());
        $titleRow = [$this->title];
        for ($i=1; $i < count($keys); $i++) { $titleRow[] = ''; }
        $blank = array_fill(0, count($keys), '');
        return [$titleRow, $blank, $keys];
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function($event){
                if ($this->rows->isEmpty()) return;
                $sheet = $event->sheet->getDelegate();
                $keys = array_keys($this->rows->first());
                $lastCol = Coordinate::stringFromColumnIndex(count($keys));
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true);
                $endRow = 3 + $this->rows->count();
                $sheet->getStyle("A3:{$lastCol}{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
        ];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        return [];
    }
}
