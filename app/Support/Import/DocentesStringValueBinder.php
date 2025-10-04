<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * ValueBinder que fuerza a tratar ciertos encabezados (codigo, dni) como texto
 * para preservar ceros a la izquierda y evitar conversión a número.
 */
class DocentesStringValueBinder extends StringValueBinder
{
    /** @var array<string,bool> */
    protected array $forceColumns = [
        'codigo' => true,
        'dni' => true,
    ];

    public function bindValue(Cell $cell, $value): bool
    {
        $col = strtolower($cell->getWorksheet()->getCell(''. $cell->getColumn() .'1')->getValue() ?? '');
        if (isset($this->forceColumns[$col])) {
            // Cast directo a string conservando formato original
            $cell->setValueExplicit((string)$value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
