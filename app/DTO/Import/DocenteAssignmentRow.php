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
        // localId: SIEMPRE debe ser loc_iCodigo (instancia por fecha). Si aún no existe se deja null hasta import().
        public ?int $localId = null,
        // localMaestroId: locma_iCodigo referencial cuando se resolvió el maestro pero no la instancia
        public ?int $localMaestroId = null,
        public ?int $procesoFechaId = null,
    ) {}
}
