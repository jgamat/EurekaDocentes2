<?php

namespace App\DTO\Import;

/**
 * DTO para representar una fila de importación de asignación de administrativos.
 * Inspirado en DocenteAssignmentRow pero desacoplado para permitir reglas específicas
 * futuras sin afectar docentes.
 */
class AdministrativoAssignmentRow
{
    public int $rowNumber; // número de fila (hoja) iniciando en 2 (después de encabezado)

    public ?string $codigo = null; // código interno administrativo (si aplica)
    public ?string $dni = null;
    public ?string $nombres = null; // texto crudo recibido para validación parcial
    public ?string $cargoNombre = null; // nombre del cargo en maestro
    public ?string $localNombre = null; // nombre del local maestro
    public ?string $fechaOriginal = null; // fecha como viene en el archivo
    public ?string $fechaISO = null; // fecha normalizada YYYY-mm-dd

    // IDs resueltos
    public ?int $cargoId = null; // instancia ExperienciaAdmision (o equivalente para administrativos si difiere después)
    public ?int $localId = null; // instancia Locales (loc_iCodigo). Nunca debe contener locma_iCodigo.
    public ?int $localMaestroId = null; // locma_iCodigo cuando se reconoció el maestro pero falta crear instancia
    public ?int $procesoFechaId = null;

    // Clave lógica de administrativo (código) para insertar relación proceso
    public ?string $administrativoPk = null;

    // Estado
    public array $errors = [];
    public array $warnings = [];
    public bool $valid = false;

    public function __construct(int $rowNumber)
    {
        $this->rowNumber = $rowNumber;
    }
}
