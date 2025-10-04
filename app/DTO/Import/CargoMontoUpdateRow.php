<?php

namespace App\DTO\Import;

class CargoMontoUpdateRow
{
    public int $rowNumber;
    public ?int $codigoCargo = null;
    public ?string $nombreExcel = null;
    public ?string $nombreBD = null;
    public ?float $montoActual = null;
    public ?float $montoNuevo = null;
    public array $errors = [];
    public array $warnings = [];
    public string $estado = 'pending'; // ok_cambiar | sin_cambio | error | duplicado
    public bool $valid = false;

    public function __construct(int $rowNumber)
    {
        $this->rowNumber = $rowNumber;
    }
}
