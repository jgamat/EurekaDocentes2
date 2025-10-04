<?php

namespace App\DTO\Import;

/**
 * Representa una fila parseada del archivo de asignación de alumnos.
 * Se mantiene consistente con los DTO de Docentes y Administrativos.
 */
class AlumnoAssignmentRow
{
    public int $rowNumber;
    public ?string $codigo = null; // alu_vcCodigo
    public ?string $dni = null; // alu_vcDni (puede usarse para validaciones cruzadas si aplica)
    public ?string $paterno = null;
    public ?string $materno = null;
    public ?string $nombres = null; // alu_vcNombre (nombres de pila)
    public ?string $cargoNombre = null; // experiencia / función
    public ?string $localNombre = null; // nombre del local
    public ?string $fechaISO = null; // fecha asignación normalizada (Y-m-d)
    public ?int $cargoId = null; // expadm_iCodigo
    public ?int $localId = null; // loc_iCodigo
    public ?int $procesoFechaId = null; // profec_iCodigo
    public array $errors = [];
    public array $warnings = [];
    public bool $valid = true;
    public bool $willReactivate = false; // bandera para indicar reactivación

    public function __construct(int $rowNumber)
    {
        $this->rowNumber = $rowNumber;
    }

    public function addError(string $msg): void
    {
        $this->errors[] = $msg;
        $this->valid = false;
    }

    public function addWarning(string $msg): void
    {
        $this->warnings[] = $msg;
    }
}
