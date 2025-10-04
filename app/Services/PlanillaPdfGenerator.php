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

                // Reubicación: colocar Jefe de Unidad y Observaciones cerca del footer
                $sectionBottomBase = 195; // footer base Y
                $blockHeight = 42; // altura aproximada Jefe + Observaciones
                $startY = $sectionBottomBase - $blockHeight - 12; // margen de separación respecto a firmas
                if ($startY < $y + 12) { $startY = $y + 12; }

                $pdf->SetFont('Arial', 'B', 9);
                // Título con borde (colspan)
                $pdf->SetXY(10, $startY);
                $pdf->Cell(277, 7, utf8_decode('Jefe de Unidad'), 1, 1, 'L');
                $wTotal = 277; $wNom = 0.55 * $wTotal; $wFirma = 0.20 * $wTotal; $wFecha = $wTotal - $wNom - $wFirma;
                $pdf->SetXY(10, $startY + 7);
                $pdf->Cell($wNom, 6, utf8_decode('Apellidos y Nombres'), 1, 0, 'L');
                $pdf->Cell($wFirma, 6, 'Firma', 1, 0, 'L');
                $pdf->Cell($wFecha, 6, utf8_decode('Fecha y Hora'), 1, 1, 'L');
                $pdf->SetXY(10, $startY + 13);
                $rowJefeH = 12;
                $pdf->Cell($wNom, $rowJefeH, '', 1, 0, 'L');
                $pdf->Cell($wFirma, $rowJefeH, '', 1, 0, 'L');
                $pdf->Cell($wFecha, $rowJefeH, '', 1, 1, 'L');
                // Línea interna firma y fecha
                $pdf->SetDrawColor(0,0,0);
                $lineYInner = $startY + 13 + $rowJefeH - 4;
                $pdf->Line(10 + $wNom + 2, $lineYInner, 10 + $wNom + $wFirma - 2, $lineYInner);
                $pdf->Line(10 + $wNom + $wFirma + 2, $lineYInner, 10 + $wNom + $wFirma + $wFecha - 2, $lineYInner);

                // Observaciones
                $obsTitleY = $startY + 13 + $rowJefeH + 4;
                $pdf->SetXY(10, $obsTitleY);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(100, 5, utf8_decode('Observaciones:'), 0, 1, 'L');
                $line1Y = $obsTitleY + 7;
                $line2Y = $line1Y + 8;
                $pdf->Line(10, $line1Y, 10 + 277, $line1Y);
                $pdf->Line(10, $line2Y, 10 + 277, $line2Y);
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

                // Tabla ajustada a 15 filas visibles: reducimos altura de fila a 9 y adelantamos y inicial
                    $y = 38; $rowH = 9;
                // Definir anchos (suma 277) - redistribución del espacio del # Credencial
                // Ajuste: ampliamos columna Firma (20->30) reduciendo Nombres (82->72) mantiene 277mm total
                $wCodigo = 25; $wDoc = 30; $wLocal = 50; $wCargo = 50; $wMonto = 20; $wNombres = 72; $wFirma = 30; // 25+30+50+50+20+72+30 = 277
                // Cabecera tabla
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetXY(10, $y);
                    $pdf->Cell($wCodigo, $rowH, 'Codigo', 1, 0, 'C');
                    $pdf->Cell($wDoc, $rowH, 'Documento', 1, 0, 'C');
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
                    $pdf->Cell($wLocal, $rowH, utf8_decode((string)$r['local_nombre']), 1, 0, 'L');
                    $pdf->Cell($wCargo, $rowH, utf8_decode((string)$r['cargo_nombre']), 1, 0, 'L');
                    $pdf->Cell($wMonto, $rowH, number_format((float)$r['monto'],2), 1, 0, 'R');
                    $pdf->Cell($wNombres, $rowH, utf8_decode((string)$r['nombres']), 1, 0, 'L');
                    // Firma: celda vacía con línea horizontal inferior interior
                    $pdf->Cell($wFirma, $rowH, '', 1, 1, 'L');
                    // Dibujar línea interna (ligeramente arriba del borde inferior para que sea visible si se firma)
                    $lineY = $y + $rowH - 3; // 3mm sobre borde inferior
                    $pdf->SetDrawColor(0,0,0);
                    $pdf->Line(10 + $wCodigo + $wDoc + $wLocal + $wCargo + $wMonto + $wNombres, $lineY, 10 + $wCodigo + $wDoc + $wLocal + $wCargo + $wMonto + $wNombres + $wFirma - 1, $lineY);
                    $y += $rowH;
                }

                // Línea de Monto por local en la última página de detalle del local
                if (!empty($p['is_last_detail']) && isset($p['total_local'])) {
                    $pdf->SetFont('Arial', 'B', 10);
                    // Asegurar que no se sobreponga con firmas: si queda poco espacio subimos la línea
                    if ($y > 180) { $y = 180; }
                    $pdf->SetXY(10, $y + 2);
                    $pdf->Cell(277, 6, utf8_decode('Monto por local: ').number_format((float)$p['total_local'], 2), 0, 1, 'R');
                }

                // Pie con firmas
                // Footer con firmas y cargos
                // Firmas al pie de página (subimos ligeramente para acomodar más filas)
                $y = 195;
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

                // Bloques Jefe de Unidad y Observaciones (reubicados y elevados)
                $sectionBottomBase = 195; // posición base de footer
                $blockHeight = 58; // ajustado por mayor altura fila vacía
                $startY = $sectionBottomBase - $blockHeight - 24; // separación incrementada a 24mm respecto a footer (antes 20mm)
                if ($startY < $y + 8) { $startY = $y + 8; }
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetXY(10, $startY);
                $pdf->Cell(277, 8, utf8_decode('Jefe de Unidad'), 1, 1, 'L');
                $wTotal = 277; $wNom = 0.55 * $wTotal; $wFirma = 0.20 * $wTotal; $wFecha = $wTotal - $wNom - $wFirma;
                $pdf->SetXY(10, $startY + 8);
                $pdf->Cell($wNom, 7, utf8_decode('Apellidos y Nombres'), 1, 0, 'L');
                $pdf->Cell($wFirma, 7, 'Firma', 1, 0, 'L');
                $pdf->Cell($wFecha, 7, utf8_decode('Fecha y Hora'), 1, 1, 'L');
                $rowJefeH = 22; // altura incrementada
                $pdf->SetXY(10, $startY + 15);
                $pdf->Cell($wNom, $rowJefeH, '', 1, 0, 'L');
                $pdf->Cell($wFirma, $rowJefeH, '', 1, 0, 'L');
                $pdf->Cell($wFecha, $rowJefeH, '', 1, 1, 'L');
                // Líneas internas firma/fecha
                $pdf->SetDrawColor(0,0,0);
                $lineYInner = $startY + 15 + $rowJefeH - 6; // ajuste línea proporcional
                $pdf->Line(10 + $wNom + 4, $lineYInner, 10 + $wNom + $wFirma - 4, $lineYInner);
                $pdf->Line(10 + $wNom + $wFirma + 4, $lineYInner, 10 + $wNom + $wFirma + $wFecha - 4, $lineYInner);
                // Observaciones elevadas
                $obsTitleY = $startY + 15 + $rowJefeH + 6;
                $pdf->SetXY(10, $obsTitleY);
                $pdf->Cell(100, 6, utf8_decode('Observaciones:'), 0, 1, 'L');
                $line1Y = $obsTitleY + 9;
                $line2Y = $line1Y + 9;
                $pdf->Line(10, $line1Y, 10 + 277, $line1Y);
                $pdf->Line(10, $line2Y, 10 + 277, $line2Y);

                // Footer con firmas y cargos (resumen)
                $y = 195;
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
