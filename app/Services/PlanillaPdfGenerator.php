<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class PlanillaPdfGenerator
{
    /**
     * Genera PDF en landscape usando plantillas PDF (detalle y resumen).
     * @param array $pages Array de páginas con structure type=detail|summary
     * @param array $header ['numero_planilla','proceso_nombre','fecha_proceso','impresion_fecha']
    * @param ?string $tplDetalle Ruta absoluta a docentes.pdf (nullable)
    * @param ?string $tplResumen Ruta absoluta a resumen_doc.pdf (nullable)
     * @return string PDF binario
     */
    public function buildDocentesPdf(array $pages, array $header, ?string $tplDetalle, ?string $tplResumen): string
    {
        $pdf = new Fpdi('L', 'mm', 'A4');
        $totalPages = count($pages);

        // Pre-cargar templates
        $srcDetalle = null;
        $srcResumen = null;
    if ($tplDetalle && is_file($tplDetalle)) {
            $srcDetalle = $pdf->setSourceFile($tplDetalle);
        }
    if ($tplResumen && is_file($tplResumen)) {
            $srcResumen = $pdf->setSourceFile($tplResumen);
        }

        $pageNo = 0;
        foreach ($pages as $p) {
            $pageNo++;
            $pdf->AddPage('L', 'A4');
            
            // Fondo
            if ($p['type'] === 'detail' && $srcDetalle) {
                $tplIdx = $pdf->importPage(1);
                $pdf->useTemplate($tplIdx, 0, 0, 297, 210, true);
            } elseif ($p['type'] === 'summary' && $srcResumen) {
                $tplIdx = $pdf->importPage(1);
                $pdf->useTemplate($tplIdx, 0, 0, 297, 210, true);
            }

            // Encabezado requerido
            // Línea 1: izquierda UNMSM, derecha (misma línea) Página | Fecha | Fecha de impresión
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetXY(10, 8);
            $pdf->Cell(120, 6, utf8_decode('UNIVERSIDAD NACIONAL MAYOR DE SAN MARCOS'), 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $rightW = 150; $rightX = 297 - 10 - $rightW; // margen derecho
            $fechaProc = $header['fecha_proceso'] ?? '';
            $fechaProcFmt = $fechaProc ? date('d/m/Y', strtotime($fechaProc)) : '';
            $fechaImp = $header['impresion_fecha'] ?? date('Y-m-d H:i');
            $pPageNo = $pages[$pageNo-1]['page_no'] ?? $pageNo;
            $topRight = 'Página: '.$pPageNo.'   |   Fecha: '.$fechaProcFmt.'   |   Fecha de impresión: '.date('d/m/Y H:i', strtotime($fechaImp));
            $pdf->SetXY($rightX, 8);
            $pdf->Cell($rightW, 6, utf8_decode($topRight), 0, 1, 'R');

            // Línea 2: izquierda OFICINA..., centro PLANILLA N° (misma línea)
            $pdf->SetFont('Arial', 'B', 11);
            $yLinea2 = 14; // línea base visual
            // Misma coordenada X (10mm) que OFICINA para alinear el inicio de la celda base
            $pdf->SetXY(10, $yLinea2);
            $pdf->Cell(120, 6, utf8_decode('OFICINA CENTRAL DE ADMISIÓN'), 0, 0, 'L');
            // PLANILLA centrado, alineado exactamente con la línea de OFICINA
                $yPlanilla = $yLinea2 - 1.0; // corrección visual previa
            $pdf->SetXY(10, $yPlanilla);
            $numPlano = '';
            if (isset($pages[$pageNo-1]['planilla_numero'])) {
                $numPlano = sprintf('%03d', (int)$pages[$pageNo-1]['planilla_numero']);
            } elseif (!empty($header['numero_planilla'])) {
                $numPlano = sprintf('%03d', (int)$header['numero_planilla']);
            }
            $pdf->Cell(277, 6, utf8_decode('PLANILLA N° ').$numPlano, 0, 0, 'C');
            // Forzar salto manual controlado a la siguiente sección
            $pdf->Ln(6);

            // Línea 3: título centrado (bajado más para evitar cualquier solape visual)
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetXY(10, 21);
            $titulo = $header['titulo_planilla'] ?? 'PLANILLA';
            $pdf->Cell(277, 6, utf8_decode((string)$titulo), 0, 1, 'C');
            // Línea 4: nombre del proceso centrado (acompaña el ajuste)
            $pdf->SetFont('Arial', '', 9);
                $pdf->SetXY(10, 27);
            $pdf->Cell(277, 5, ($header['proceso_nombre'] ?? ''), 0, 1, 'C');

            if ($p['type'] === 'detail') {
                // Sub-encabezado de detalle
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetXY(10, 32);
                $sub = utf8_decode('Local: ').$p['local_nombre'].'   |   '.utf8_decode('Cargo: ').$p['cargo_nombre'].'   |   '.utf8_decode('Monto: ').number_format((float)$p['monto_cargo'],2);
                $pdf->Cell(277, 6, $sub, 0, 0, 'L');

                // Tabla (15 filas aprox) con columnas: Codigo, Documento, # Credencial, Local, Cargo, Monto, Nombres, Firma
                    $y = 40; $rowH = 10;
                // Definir anchos (suma 277)
                $wCodigo = 25; $wDoc = 28; $wCred = 28; $wLocal = 45; $wCargo = 45; $wMonto = 20; $wNombres = 66; $wFirma = 20;
                // Cabecera tabla
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetXY(10, $y);
                    $pdf->Cell($wCodigo, $rowH, 'Codigo', 1, 0, 'C');
                    $pdf->Cell($wDoc, $rowH, 'Documento', 1, 0, 'C');
                    $pdf->Cell($wCred, $rowH, utf8_decode('# Credencial'), 1, 0, 'C');
                    $pdf->Cell($wLocal, $rowH, 'Local', 1, 0, 'C');
                    $pdf->Cell($wCargo, $rowH, 'Cargo', 1, 0, 'C');
                    $pdf->Cell($wMonto, $rowH, 'Monto', 1, 0, 'C');
                    $pdf->Cell($wNombres, $rowH, 'Apellidos y Nombres', 1, 0, 'C');
                    $pdf->Cell($wFirma, $rowH, 'Firma', 1, 1, 'C');

                $pdf->SetFont('Arial', '', 9);
                    $y += $rowH;
                foreach ($p['rows'] as $r) {
                    $pdf->SetXY(10, $y);
                    $pdf->Cell($wCodigo, $rowH, (string)$r['codigo'], 1, 0, 'L');
                    $pdf->Cell($wDoc, $rowH, (string)$r['dni'], 1, 0, 'L');
                    $pdf->Cell($wCred, $rowH, (string)$r['cred_numero'], 1, 0, 'L');
                    $pdf->Cell($wLocal, $rowH, utf8_decode((string)$r['local_nombre']), 1, 0, 'L');
                    $pdf->Cell($wCargo, $rowH, utf8_decode((string)$r['cargo_nombre']), 1, 0, 'L');
                    $pdf->Cell($wMonto, $rowH, number_format((float)$r['monto'],2), 1, 0, 'R');
                    $pdf->Cell($wNombres, $rowH, utf8_decode((string)$r['nombres']), 1, 0, 'L');
                    $pdf->Cell($wFirma, $rowH, '', 1, 1, 'L');
                    $y += $rowH;
                }

                // Pie con firmas
                // Footer con firmas y cargos
                // Firmas al pie de página (Y=200mm)
                $y = 200;
                $pdf->SetXY(30, $y);
                $pdf->Cell(100, 0, '________________________________________', 0, 0, 'C');
                $pdf->Cell(120, 0, '________________________________________', 0, 1, 'C');
                $pdf->SetXY(30, $y+6);
                $pdf->Cell(100, 6, utf8_decode('Dr. Victor Ricardo Masuda Toyofuku'), 0, 0, 'C');
                $pdf->Cell(120, 6, utf8_decode('C.P. Diana Ines Cirineo Rosales'), 0, 1, 'C');
                $pdf->SetXY(30, $y+12);
                $pdf->Cell(100, 6, utf8_decode('Director General Oficina Admisión'), 0, 0, 'C');
                $pdf->Cell(120, 6, utf8_decode('Jefe Oficina de Economía'), 0, 1, 'C');
            } else {
                // Resumen por local
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetXY(10, 36);
                $pdf->Cell(200, 6, 'Local: '.$p['local_nombre'], 0, 1, 'L');

                $y = 44; $rowH = 9;
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetXY(10, $y);
                $pdf->Cell(140, $rowH, 'Cargo', 1, 0, 'C');
                $pdf->Cell(30, $rowH, 'Cantidad', 1, 0, 'C');
                $pdf->Cell(40, $rowH, 'Monto', 1, 0, 'C');
                $pdf->Cell(40, $rowH, 'Subtotal', 1, 1, 'C');
                $pdf->SetFont('Arial', '', 9);
                $y += $rowH;
                foreach ($p['resumen'] as $item) {
                    $pdf->SetXY(10, $y);
                    $pdf->Cell(140, $rowH, (string)$item['cargo_nombre'], 1, 0, 'L');
                    $pdf->Cell(30, $rowH, (string)$item['cantidad'], 1, 0, 'R');
                    $pdf->Cell(40, $rowH, number_format((float)$item['monto'],2), 1, 0, 'R');
                    $pdf->Cell(40, $rowH, number_format((float)$item['subtotal'],2), 1, 1, 'R');
                    $y += $rowH;
                }
                // Total
                $pdf->SetXY(10, $y);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(210, $rowH, 'Total por local', 1, 0, 'R');
                $pdf->Cell(40, $rowH, number_format((float)$p['gran_total'],2), 1, 1, 'R');

                // Footer con firmas y cargos (resumen)
                // Firmas al pie de página (Y=200mm)
                $y = 200;
                $pdf->SetXY(30, $y);
                $pdf->Cell(100, 0, '________________________________________', 0, 0, 'C');
                $pdf->Cell(120, 0, '________________________________________', 0, 1, 'C');
                $pdf->SetXY(30, $y+6);
                $pdf->Cell(100, 6, utf8_decode('Dr. Victor Ricardo Masuda Toyofuku'), 0, 0, 'C');
                $pdf->Cell(120, 6, utf8_decode('C.P. Diana Ines Cirineo Rosales'), 0, 1, 'C');
                $pdf->SetXY(30, $y+12);
                $pdf->Cell(100, 6, utf8_decode('Director General Oficina Admisión'), 0, 0, 'C');
                $pdf->Cell(120, 6, utf8_decode('Jefe Oficina de Economía'), 0, 1, 'C');
            }
        }

        return $pdf->Output('S'); // return as string
    }
}
