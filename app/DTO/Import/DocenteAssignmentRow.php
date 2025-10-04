<?php

namespace App\DTO\Import;

class DocenteAssignmentRow
{
    public function __construct(
        public int $rowNumber,
        public ?string $codigo = null,
        public ?string $dni = null,
        public ?string $nombres = null,
        public ?string $cargoNombre = null,
        public ?string $localNombre = null,
        public ?string $fechaOriginal = null,
        public ?string $fechaISO = null,
        public array $errors = [],
        public array $warnings = [],
        public bool $valid = false,
    // docentePk: se usa doc_vcCodigo (string) como identificador lógico en asignaciones
    public ?string $docentePk = null,
        public ?int $cargoId = null,
        public ?int $localId = null,
        public ?int $procesoFechaId = null,
    ) {}
}
