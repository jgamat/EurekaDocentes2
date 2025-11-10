<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Planilla Personal</title>
    <style>
    @page { margin: 20px 100px 40px 45px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .bg { position: fixed; top:0; left:0; right:0; bottom:0; z-index: -1; }
        .bg img { width: 100%; height: 100%; object-fit: cover; }
    .header { display:flex; justify-content: space-between; margin-bottom:4px; }
        .title { font-weight:bold; }
    .page { page-break-after: auto; position: relative; }
        table { width:100%; border-collapse: collapse; table-layout: auto; }
    th, td { border: 1px solid #000; padding: 2px; vertical-align: middle; }
        td { line-height: 1.1; white-space: normal; word-break: break-word; overflow-wrap: break-word; }
    /* Quitar evitación forzada de salto dentro de cada fila; permitimos que DOMPDF empaquete varias filas */
    /* tbody tr { page-break-inside: avoid; } */
    .tabla-detalle tbody tr { page-break-inside: auto; }
        th { background: #eee; }
    .firma { height: 28px; vertical-align: middle; position: relative; }
        .resumen-total { text-align: right; font-weight: bold; }
        .content { position: relative; }

    .tabla-detalle tbody td { border: 0 !important; vertical-align: middle; }
    .tabla-detalle thead th { border: 1px solid #000 !important; }
    /* No wrap + truncate (via PHP) sólo para columnas de Local y Cargo en detalle */
    .nowrap-truncate { white-space: nowrap; overflow: hidden; }
    /* Fila espaciadora al inicio del detalle de la primera planilla */
    .spacer-row td { height: 16px; }
    </style>
</head>
<body>
@foreach($pages as $p)
    <div class="page" style="@if(!$loop->last) page-break-after: always; @endif">
    <div class="header" style="align-items:flex-start; width:100%;">
            <!-- Línea 1: izquierda UNMSM, derecha info en una sola línea -->
        <div style="position:relative; width:100%;">
                <div style="font-weight:bold;">UNIVERSIDAD NACIONAL MAYOR DE SAN MARCOS</div>
                <div style="position:absolute; top:0; right:0; font-size: 11px;">
            Página: {{ $p['page_no'] ?? '' }} 
                    &nbsp; | &nbsp;
                    Fecha: {{ \Carbon\Carbon::parse($fecha_proceso)->format('d/m/Y') }}
                  <!--  &nbsp; | &nbsp; -->
          <!--  Fecha de impresión: {{ \Carbon\Carbon::parse($impresion_fecha)->format('d/m/Y H:i') }} -->
                </div>
            </div>
            <!-- Línea 2: izquierda OFICINA, centro PLANILLA N° (sin posiciones absolutas para evitar solapes) -->
            <div style="width:100%; margin-top:0;">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:33%; border:0; padding:0;">OFICINA CENTRAL DE ADMISIÓN</td>
                        <td style="width:34%; border:0; padding:0; text-align:center; font-weight:bold;">PLANILLA N° {{ sprintf('%03d', $p['planilla_numero'] ?? $numero_planilla) }}</td>
                        <td style="width:33%; border:0; padding:0;"></td>
                    </tr>
                </table>
            </div>
            <!-- Línea 3: título al centro -->
            <div style="width:100%; text-align:center; margin-top:0; font-weight:bold;">
                {{ $titulo_planilla }}
            </div>
            <!-- Línea 4: proceso al centro -->
            <div style="width:100%; text-align:center;">
                {{ $proceso_nombre }}
            </div>
        </div>
        @if($p['type'] === 'detail')
            @if(!empty($bg_detail_url))
                <div class="bg"><img src="{{ $bg_detail_url }}" alt="template"></div>
            @endif
            <div class="content">
                @php
                    // Helper truncado seguro con … (DOMPDF no soporta text-overflow: ellipsis)
                    $lim = function($s, $n){
                        $s = (string)($s ?? '');
                        return (mb_strlen($s, 'UTF-8') <= $n)
                            ? $s
                            : (mb_substr($s, 0, max(0, $n-1), 'UTF-8') . '…');
                    };
                    // Límites por tipo, coherentes con anchos de columnas
                    // Aumentados levemente para aprovechar mejor el espacio disponible en celdas
                    $localLimit = (!empty($es_tercero_cas) && $es_tercero_cas) ? 42 : ((!empty($es_alumno) && $es_alumno) ? 32 : 28);
                    $cargoLimit = (!empty($es_tercero_cas) && $es_tercero_cas) ? 38 : ((!empty($es_alumno) && $es_alumno) ? 28 : 26);
                @endphp
                <div style="margin-bottom:6px;">
                    <strong>Local:</strong> {{ $p['local_nombre'] }}
                    @if((empty($es_admin) || !$es_admin) && (empty($es_alumno) || !$es_alumno))
                        &nbsp; | &nbsp;
                        <strong>Cargo:</strong> {{ $p['cargo_nombre'] }}
                        @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                            &nbsp; | &nbsp;
                            <strong>Monto:</strong> {{ number_format($p['monto_cargo'],2) }}
                        @endunless
                    @endif
                </div>
                <table class="tabla-detalle">
                    <thead>
                        <tr>
                            <th style="width:2%">N°</th>
                            @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                <th style="width:5%">Código</th>
                            @endunless
                            <th style="width:{{ (!empty($es_alumno) && $es_alumno) ? '8%' : '6%' }}">Documento</th>
                            <th style="width:
                                {{ (!empty($es_tercero_cas) && $es_tercero_cas)
                                    ? '26%'
                                    : ((!empty($es_alumno) && $es_alumno) ? '34%' : '29%')
                                }}
                            ">Apellidos y Nombres</th>
                            <th style="width:
                                {{ (!empty($es_tercero_cas) && $es_tercero_cas)
                                    ? '26%'
                                    : ((!empty($es_alumno) && $es_alumno) ? '20%' : '16%')
                                }}
                            ">Local</th>
                            <th style="width:
                                {{ (!empty($es_tercero_cas) && $es_tercero_cas)
                                    ? '26%'
                                    : ((!empty($es_alumno) && $es_alumno) ? '20%' : '16%')
                                }}
                            ">Cargo</th>
                            @unless((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                                <th style="width:4%">Monto</th>
                            @endunless
                            <!-- Ajuste: se incrementa ancho de Firma de 8% a 12%; se reduce proporcionalmente Apellidos y Nombres -->
                            <th style="width:12%">Firma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="spacer-row">
                            <td style="text-align:center;">&nbsp;</td>
                            @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                <td>&nbsp;</td>
                            @endunless
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td class="nowrap-truncate">&nbsp;</td>
                            <td class="nowrap-truncate">&nbsp;</td>
                            @unless((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                                <td>&nbsp;</td>
                            @endunless
                            <td class="firma">&nbsp;</td>
                        </tr>
            @foreach($p['rows'] as $r)
                            <tr>
                <td style="text-align:center;">{{ $r['orden'] ?? $loop->iteration }}</td>
                                @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                    <td>{{ $r['codigo'] }}</td>
                                @endunless
                                <td>{{ $r['dni'] }}</td>
                                <td>{{ $r['nombres'] }}</td>
                                <td class="nowrap-truncate">{{ $lim($r['local_nombre'] ?? '', $localLimit) }}</td>
                                <td class="nowrap-truncate">{{ $lim($r['cargo_nombre'] ?? '', $cargoLimit) }}</td>
                                @unless((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                                    <td style="text-align:right;">{{ number_format($r['monto'],2) }}</td>
                                @endunless
                                <td class="firma" style="position:relative;">
                                    <div style="position:absolute; left:2%; right:2%; top:50%; transform: translateY(-50%); border-bottom:1px solid #000; height:0; line-height:0;">
                                        &nbsp;
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(!empty($p['is_last_detail']) && ((!empty($es_admin) && $es_admin) || (!empty($es_docente) && $es_docente)))
                    <div style="margin-top:4px; font-weight:bold; text-align:right;">Monto por local: {{ number_format($p['total_local'] ?? 0,2) }}</div>
                @endif

                <div style="position: fixed; bottom: 12px; left: 25px; right: 25px; font-size:11px;">
                    <table style="width:100%; border:0; border-collapse:collapse;">
                        <tr>
                            <td style="width:50%; text-align:center; height:60px; vertical-align:bottom; border:0;">
                                ___________________________________<br>
                                {{ $profec_vcFimaDirector ?? '_________________________' }}<br>
                                Director General Oficina Admisión
                            </td>
                            <td style="width:50%; text-align:center; height:60px; vertical-align:bottom; border:0;">
                                ________________________________<br>
                                {{ $profec_vcFimaJefe ?? '_________________________' }}<br>
                                Jefe Oficina de Economía
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        @else
            @if((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                {{-- Tercero/CAS y Alumnos no tienen página de resumen --}}
            @else
            @if(!empty($bg_summary_url))
                <div class="bg"><img src="{{ $bg_summary_url }}" alt="template resumen"></div>
            @endif
            <div class="content">
                <div style="margin-bottom:6px;"><strong>Local:</strong> {{ $p['local_nombre'] }}</div>
                <h3>Resumen por Cargo</h3>
                <table>
                    <thead>
                        <tr>
                            <th style="text-align:left;">Cargo</th>
                            <th style="text-align:center;">Cantidad</th>
                            <th style="text-align:center;">Asistentes</th>
                            <th style="text-align:center;">Inasistentes</th>
                            <th style="text-align:center;">Monto</th>
                            <th style="text-align:center;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($p['resumen'] as $item)
                            <tr>
                                <td>{{ $item['cargo_nombre'] }}</td>
                                <td style="text-align:center;">{{ $item['cantidad'] }}</td>
                                <td style="text-align:center;">{{ $item['asistentes'] ?? '' }}</td>
                                <td style="text-align:center;">{{ $item['inasistentes'] ?? '' }}</td>
                                <td style="text-align:center;">{{ number_format($item['monto'],2) }}</td>
                                <td style="text-align:center;">{{ number_format($item['subtotal'],2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="5" class="resumen-total" style="text-align:right;">Total por local</td>
                            <td style="text-align:center;">{{ number_format($p['gran_total'],2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Reubicado: bloque Jefe de Unidad y Observaciones se renderiza fijo cerca del footer -->
                <div style="position:fixed; left:25px; right:25px; bottom:140px; font-size:11px;"> <!-- bottom antes:110px -->
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <th colspan="3" style="text-align:left; background:#f5f5f5; border:1px solid #000; padding:6px 8px;">Jefe de Unidad</th>
                        </tr>
                        <tr>
                            <th style="width:60%; border:1px solid #000; padding:4px 6px;">Apellidos y Nombres</th>
                            <th style="width:20%; border:1px solid #000; padding:4px 6px;">Firma</th>
                            <th style="width:20%; border:1px solid #000; padding:4px 6px;">Fecha y Hora</th>
                        </tr>
                        <tr style="height:50px;">
                            <td style="border:1px solid #000; padding:10px 12px;">&nbsp;</td>
                            <td style="border:1px solid #000; position:relative; padding:10px 12px;">
                                <div style="position:absolute; left:8%; right:8%; bottom:6px; border-bottom:1px solid #000; height:0;">&nbsp;</div>
                            </td>
                            <td style="border:1px solid #000; position:relative; padding:10px 12px;">
                                <div style="position:absolute; left:8%; right:8%; bottom:6px; border-bottom:1px solid #000; height:0;">&nbsp;</div>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top:10px; font-weight:bold;">Observaciones:</div>
                    <div style="width:100%; border-bottom:1px solid #000; height:14px; margin-top:4px;"></div>
                    <div style="width:100%; border-bottom:1px solid #000; height:14px; margin-top:6px;"></div>
                </div>

                <div style="position: fixed; bottom: 12px; left: 25px; right: 25px; font-size:11px;">
                    <table style="width:100%; border:0; border-collapse:collapse;">
                        <tr>
                            <td style="width:50%; text-align:center; height:60px; vertical-align:bottom; border:0;">
                                ___________________________________<br>
                                {{ $profec_vcFimaDirector ?? '_________________________' }}<br>
                                Director General Oficina Admisión
                            </td>
                            <td style="width:50%; text-align:center; height:60px; vertical-align:bottom; border:0;">
                                ________________________________<br>
                                {{ $profec_vcFimaJefe ?? '_________________________' }}<br>
                                Jefe Oficina de Economía
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif
        @endif
    </div>
@endforeach
</body>
</html>
