<?php

namespace App\Services\Import\Concerns;

use Carbon\Carbon;

/**
 * Trait con utilidades compartidas para servicios de importación de asignaciones
 * (docentes y administrativos). Contiene funciones puramente determinísticas
 * y sin dependencias de instancia para normalizar y parsear valores.
 */
trait SharedAssignmentImport
{
    /**
     * Lista canónica unificada de columnas para las tres importaciones:
     * codigo,dni,paterno,materno,nombres,cargo,local,fecha
     * - Para Docentes y Administrativos normalmente solo se completa 'nombres'.
     * - Para Alumnos se pueden usar los tres componentes (paterno,materno,nombres) o un nombre completo en 'nombres'.
     */
    public const UNIFIED_HEADERS = [
        // Formato oficial (6 columnas):
        // codigo, dni, nombres, cargo, local, fecha
        // Columnas adicionales como paterno/materno se aceptan sólo si vienen y se usarán para validación de nombres.
        'codigo','dni','nombres','cargo','local','fecha'
    ];

    protected function parseFecha(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);
        $fmts = ['Y-m-d','d/m/Y','d-m-Y'];
        foreach ($fmts as $f) {
            try { $c = Carbon::createFromFormat($f, $raw); if ($c) return $c->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        if (is_numeric($raw)) { // Excel serial date
            try { return Carbon::createFromTimestamp(((int)$raw - 25569) * 86400)->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        return null;
    }

    // Normalización genérica avanzada (espacios + tildes) usada en comparación de nombres.
    protected function normExtended(?string $s): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        if ($s === '') return null;
        $s = strtr($s,[ 'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U' ]);
        $s = preg_replace('/\s+/',' ', $s);
        return mb_strtoupper($s);
    }

    /**
     * Compara nombre importado contra nombre base (ambos en una sola cadena o partes) y decide si hay discrepancia.
     * Estrategia:
     * 1. Normalizar (quitar tildes, mayúsculas, compactar espacios).
     * 2. Si la cadena importada está contenida como substring de la base -> OK.
     * 3. Calcular distancia Levenshtein sobre versiones sin espacios; si <= umbral dinámico (ceil(len/6)) -> OK.
     * 4. Si porcentaje de coincidencia similar_text >= 70% -> OK.
     * Caso contrario marcamos discrepancia.
     */
    protected function nombresDiscrepan(?string $nombreBase, ?string $importado): bool
    {
        if (!$nombreBase || !$importado) return false; // sin suficiente data no marcamos
    $base = $this->normExtended($nombreBase);
    $imp = $this->normExtended($importado);
        if (!$base || !$imp) return false;
        if (str_contains($base, $imp)) return false; // substring directa

        $baseFlat = str_replace(' ','', $base);
        $impFlat = str_replace(' ','', $imp);
        $len = max(mb_strlen($baseFlat), mb_strlen($impFlat));
        if ($len === 0) return false;
        $dist = levenshtein($baseFlat, $impFlat);
        $threshold = (int) ceil($len / 6); // tolerancia proporcional al largo
        if ($dist <= $threshold) return false;

        similar_text($baseFlat, $impFlat, $percent);
        if ($percent >= 70) return false;
        return true; // discrepancia
    }

    // Versión simple anterior (se mantiene por retrocompatibilidad en servicios existentes)
    protected function norm(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim($v);
        return $v === '' ? null : mb_strtoupper($v);
    }

    protected function normalizeCargoName(?string $s): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        if ($s === '') return null;
        return $this->stripAccentsAndSpaces($s);
    }

    protected function normalizeLocalName(?string $s): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        if ($s === '') return null;
        return $this->stripAccentsAndSpaces($s);
    }

    private function stripAccentsAndSpaces(string $s): string
    {
        $s = mb_strtoupper($s);
        $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']);
        $s = preg_replace('/\s+/',' ',trim($s));
        return $s;
    }

    /**
     * Convierte las claves de una fila asociativa a las claves canónicas usando
     * un conjunto de alias tolerantes (ignora acentos, espacios, guiones y underscores).
     *
     * Alias soportados (ejemplos):
     *  - codigo: code, código, cod
     *  - dni: documento, doc
     *  - paterno: apellido paterno, apepaterno, ap_paterno
     *  - materno: apellido materno, apematerno, ap_materno
     *  - nombres: nombre, nombre completo, nombrecompleto, nombreyapellidos
     *  - cargo: puesto, funcion, función, experiencia
     *  - local: sede, lugar
     *  - fecha: fechaasignacion, fecha_asignacion, fecha-asignacion
     */
    protected function canonicalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (is_int($k)) { // dejamos numéricos como están para fallback en servicios antiguos
                $out[$k] = $v; continue;
            }
            $norm = $this->normalizeHeaderKey($k);
            switch ($norm) {
                case 'CODIGO':
                case 'CODE':
                case 'COD':
                case 'CODO': // tolerancia typo menor
                    $out['codigo'] = $v; break;
                case 'DNI':
                case 'DOCUMENTO':
                case 'DOC':
                    $out['dni'] = $v; break;
                case 'PATERNO':
                case 'APELLIDOPATERNO':
                case 'APEPATERNO':
                case 'APELLIDOPAT':
                    $out['paterno'] = $v; break;
                case 'MATERNO':
                case 'APELLIDOMATERNO':
                case 'APEMATERNO':
                case 'APELLIDOMAT':
                    $out['materno'] = $v; break;
                case 'NOMBRES':
                case 'NOMBRE':
                case 'NOMBRECOMPLETO':
                case 'NOMBREYAPELLIDOS':
                case 'NOMBRE_APELLIDOS':
                    $out['nombres'] = $v; break;
                case 'CARGO':
                case 'PUESTO':
                case 'FUNCION':
                case 'FUNCIONCARGO':
                case 'EXPERIENCIA':
                    $out['cargo'] = $v; break;
                case 'LOCAL':
                case 'SEDE':
                case 'LUGAR':
                    $out['local'] = $v; break;
                case 'FECHA':
                case 'FECHAASIGNACION':
                case 'FECHAASIG':
                case 'FECHA_ASIGNACION':
                case 'FECHA-ASIGNACION':
                    $out['fecha'] = $v; break;
                default:
                    // ignoramos columnas desconocidas sin romper
                    $out[$k] = $v;
            }
        }
        return $out;
    }

    protected function normalizeHeaderKey(string $k): string
    {
        $k = strtr(mb_strtoupper($k), ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']);
        $k = preg_replace('/[^A-Z0-9]+/','', $k) ?? $k; // quitar espacios, guiones, underscores, etc.
        return $k;
    }
}
