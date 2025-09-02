<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Planilla Docentes</title>
    <style>
    @page { margin: 40px 50px 40px 45px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .bg { position: fixed; top:0; left:0; right:0; bottom:0; z-index: -1; }
        .bg img { width: 100%; height: 100%; object-fit: cover; }
    .header { display:flex; justify-content: space-between; margin-bottom:4px; }
        .title { font-weight:bold; }
    .page { page-break-after: auto; position: relative; }
        table { width:100%; border-collapse: collapse; table-layout: auto; }
    th, td { border: 1px solid #000; padding: 2px; vertical-align: top; }
        td { line-height: 1.1; white-space: normal; word-break: break-word; overflow-wrap: break-word; }
    tbody tr { page-break-inside: avoid; }
        th { background: #eee; }
        .firma { height: 28px; }
        .resumen-total { text-align: right; font-weight: bold; }
        .content { position: relative; }
    </style>
</head>
<body>
@foreach($pages as $p)
    <div class="page" style="@if(!$loop->last) page-break-after: always; @endif">
    <div class="header" style="align-items:flex-start; width:100%;">
            <!-- Línea 1: izquierda UNMSM, derecha info en una sola línea -->
        <div style="position:relative; width:100%;">
                <div style="font-weight:bold;">UNIVERSIDAD NACIONAL MAYOR DE SAN MARCOS</div>
                <div style="position:absolute; top:0; right:0; font-size: 9px;">
            Página: {{ $p['page_no'] ?? '' }} 
                    &nbsp; | &nbsp;
                    Fecha: {{ \Carbon\Carbon::parse($fecha_proceso)->format('d/m/Y') }}
                    &nbsp; | &nbsp;
            Fecha de impresión: {{ \Carbon\Carbon::parse($impresion_fecha)->format('d/m/Y H:i') }}
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
                <div style="margin-bottom:6px;">
                    <strong>Local:</strong> {{ $p['local_nombre'] }}
                    &nbsp; | &nbsp;
                    <strong>Cargo:</strong> {{ $p['cargo_nombre'] }}
                    @unless((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                        &nbsp; | &nbsp;
                        <strong>Monto:</strong> {{ number_format($p['monto_cargo'],2) }}
                    @endunless
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:2%">N°</th>
                            @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                <th style="width:5%">Código</th>
                            @endunless
                            @unless(!empty($es_alumno) && $es_alumno)
                                <th style="width:6%">Documento</th>
                            @endunless
                            @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                <th style="width:6%">Credencial</th>
                            @endunless
                            <th style="width:
                                {{ (!empty($es_tercero_cas) && $es_tercero_cas)
                                    ? '24%'
                                    : ((!empty($es_alumno) && $es_alumno) ? '24%' : '23%')
                                }}
                            ">Apellidos y Nombres</th>
                            <th style="width:
                                {{ (!empty($es_tercero_cas) && $es_tercero_cas)
                                    ? '30%'
                                    : ((!empty($es_alumno) && $es_alumno) ? '30%' : '16%')
                                }}
                            ">Local</th>
                            <th style="width:
                                {{ (!empty($es_tercero_cas) && $es_tercero_cas)
                                    ? '30%'
                                    : ((!empty($es_alumno) && $es_alumno) ? '30%' : '16%')
                                }}
                            ">Cargo</th>
                            @unless((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                                <th style="width:4%">Monto</th>
                            @endunless
                            <th style="width:8%">Firma</th>
                        </tr>
                    </thead>
                    <tbody>
            @foreach($p['rows'] as $r)
                            <tr>
                <td style="text-align:center;">{{ $r['orden'] ?? $loop->iteration }}</td>
                                @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                    <td>{{ $r['codigo'] }}</td>
                                @endunless
                                @unless(!empty($es_alumno) && $es_alumno)
                                    <td>{{ $r['dni'] }}</td>
                                @endunless
                                @unless(!empty($es_tercero_cas) && $es_tercero_cas)
                                    <td>{{ $r['cred_numero'] }}</td>
                                @endunless
                                <td>{{ $r['nombres'] }}</td>
                                <td>{{ $r['local_nombre'] }}</td>
                                <td>{{ $r['cargo_nombre'] }}</td>
                                @unless((!empty($es_tercero_cas) && $es_tercero_cas) || (!empty($es_alumno) && $es_alumno))
                                    <td style="text-align:right;">{{ number_format($r['monto'],2) }}</td>
                                @endunless
                                <td class="firma"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="position: fixed; bottom: 10px; left: 25px; right: 25px;">
                    <table style="width:100%;">
                        <tr>
                            <td style="width:50%; height:60px; vertical-align:bottom; text-align:center;">
                                ________________________________<br>
                                Dr. Victor Ricardo Masuda Toyofuku<br>
                                Director General Oficina Admisión
                            </td>
                            <td style="width:50%; height:60px; vertical-align:bottom; text-align:center;">
                                ________________________________<br>
                                C.P. Diana Ines Cirineo Rosales<br>
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
                            <th>Cargo</th>
                            <th>Cantidad</th>
                            <th>Monto</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($p['resumen'] as $item)
                            <tr>
                                <td>{{ $item['cargo_nombre'] }}</td>
                                <td style="text-align:right;">{{ $item['cantidad'] }}</td>
                                <td style="text-align:right;">{{ number_format($item['monto'],2) }}</td>
                                <td style="text-align:right;">{{ number_format($item['subtotal'],2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="3" class="resumen-total">Total por local</td>
                            <td style="text-align:right;">{{ number_format($p['gran_total'],2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div style="position: fixed; bottom: 10px; left: 25px; right: 25px;">
                    <table style="width:100%;">
                        <tr>
                            <td style="width:50%; height:60px; vertical-align:bottom; text-align:center;">
                                ________________________________<br>
                                Dr. Victor Ricardo Masuda Toyofuku<br>
                                Director General Oficina Admisión
                            </td>
                            <td style="width:50%; height:60px; vertical-align:bottom; text-align:center;">
                                ________________________________<br>
                                C.P. Diana Ines Cirineo Rosales<br>
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
