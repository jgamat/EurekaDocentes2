<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Credenciales</title>
<style>
@page { margin: 0; }
body { margin:0; font-family: DejaVu Sans, sans-serif; }
.page { position: relative; width: 210mm; height: 297mm; }
.page-break { page-break-after: always; }
.bg { position:absolute; top:0; left:0; width:210mm; height:297mm; }
/* Tamaño estimado del área de una credencial */
.cred { position:absolute; width:90mm; height:130mm; }
.cred.debug { outline:0.3mm dashed rgba(0,0,0,0.4); }
/* Posiciones base (sin offsets) */
.cred.pos1 { top:45mm; left:20mm; }
.cred.pos2 { top:45mm; left:110mm; }
.cred.pos3 { top:160mm; left:20mm; }
.cred.pos4 { top:160mm; left:110mm; }
.txt { position:absolute; font-size:8pt; line-height:1.10; font-weight:400; }
.num { font-weight:600; font-size:9pt; }
.foto { position:absolute; width:25mm; height:32mm; object-fit:cover; border:1px solid #000; }
</style>
</head>
<body>
@php
    $frontOffsetX = $offsets['front']['x'] ?? 0; 
    $frontOffsetY = $offsets['front']['y'] ?? 0;
    $backOffsetX  = $offsets['back']['x'] ?? 0;
    $backOffsetY  = $offsets['back']['y'] ?? 0;
    $slotPositions = [
        1 => ['top'=>45,'left'=>20],
        2 => ['top'=>45,'left'=>110],
        3 => ['top'=>160,'left'=>20],
        4 => ['top'=>160,'left'=>110],
    ];
@endphp
@foreach($pages as $pageIndex => $page)
    <div class="page page-break">
        @if($anverso)
            <img class="bg" src="data:image/jpeg;base64,{{ $anverso }}" alt="anverso" />
        @endif
        @foreach($page as $i => $p)
            @php
                $slot = $i + 1;
                $base = $slotPositions[$slot] ?? ['top'=>0,'left'=>0];
                $baseTop = $base['top'];
                $baseLeft = $base['left'];
            @endphp
            @if(!$p)
                <!-- Placeholder vacío para mantener posición -->
                <div class="cred" style="top:{{ $baseTop + $frontOffsetY }}mm; left:{{ $baseLeft + $frontOffsetX }}mm;"></div>
                @continue
            @endif
            @php
                // Coordenadas base (mm) con defaults
                $nameTop1 = 15; // primera línea de nombre
                $nameLineHeight = 4; // separación entre líneas de nombre
                $hasName2 = !empty($p['nombres_line2']);
                $nameTop2 = $hasName2 ? $nameTop1 + $nameLineHeight : null;
                $dniTop = $hasName2 ? ($nameTop1 + $nameLineHeight + 5) : 25; // ajuste si hay segunda línea
                $cargoTop1 = $dniTop + 7; // primera línea cargo
                $cargoTop2 = $cargoTop1 + 4; // segunda línea cargo
                // Local debajo de cargo antes de foto (bajarlo unos mm adicionales)
                $localTopCalculated = !empty($p['cargo_line2']) ? ($cargoTop2 + 19) : ($cargoTop1 + 23);
                $fotoTop = 43; $fotoLeft = 54.5; // posición foto
                if ($localTopCalculated + 3 > $fotoTop) {
                    $localTopCalculated = $fotoTop - 4; // evita solape
                }
            @endphp
            <div class="cred {{ $debug ? 'debug':'' }}" style="top:{{ $baseTop + $frontOffsetY }}mm; left:{{ $baseLeft + $frontOffsetX }}mm;">
                <!-- Nombre multi-línea -->
                <div class="txt" style="top:{{ $nameTop1 ?? 15 }}mm; left:1mm; width:55mm; font-weight:600; text-transform:uppercase;">{{ $p['nombres_line1'] ?? $p['nombres'] }}</div>
                @if($hasName2)
                    <div class="txt" style="top:{{ $nameTop2 ?? (($nameTop1 ?? 15)+4) }}mm; left:1mm; width:55mm; font-weight:600; text-transform:uppercase;">{{ $p['nombres_line2'] }}</div>
                @endif
                <!-- DNI -->
                <div class="txt" style="top:{{ $dniTop ?? 25 }}mm; left:1mm; width:55mm;">DNI: {{ $p['dni'] }}</div>
                <!-- Cargo multi-línea -->
                <div class="txt" style="top:{{ $cargoTop1 ?? 31 }}mm; left:1mm; width:55mm;">{{ $p['cargo_line1'] }}</div>
                @if(!empty($p['cargo_line2']))
                    <div class="txt" style="top:{{ $cargoTop2 ?? (($cargoTop1 ?? 31)+4) }}mm; left:1mm; width:55mm;">{{ $p['cargo_line2'] }}</div>
                @endif
                <!-- Local en celda vacía -->
                <div class="txt" style="top:{{ ($localTopCalculated ?? 39) + 2 }}mm; left:1mm; width:55mm;">{{ $p['local'] }}</div>
                <!-- Código y Número (parte inferior) -->
                <div class="txt" style="top:73mm; left:3mm;">{{ $p['codigo'] }}</div>
                <div class="txt" style="top:77mm; left:3mm;">{{ $p['credencial'] }}</div>
                <!-- Fecha/Hora impresión (esquina inferior derecha dentro de la credencial) -->
                <div class="txt" style="top:78mm; left:56mm; width:25mm; font-size:6pt; text-align:right;">{{ $generado->format('d/m H:i') }}</div>
                <!-- Foto -->
                @if(!empty($p['foto_path']))
                    @php
                        $rawFoto = null;
                        try { $rawFoto = @file_get_contents($p['foto_path']); } catch (\Exception $e) { $rawFoto = null; }
                    @endphp
                    @if($rawFoto)
                        <img class="foto" style="top:{{ $fotoTop ?? 43 }}mm; left:{{ $fotoLeft ?? 54.5 }}mm;" src="data:image/jpeg;base64,{{ base64_encode($rawFoto) }}" />
                    @endif
                @endif
            </div>
        @endforeach
    </div>
    <div class="page {{ !$loop->last ? 'page-break' : '' }}">
        @if($reverso)
            <img class="bg" src="data:image/jpeg;base64,{{ $reverso }}" alt="reverso" />
        @endif
        @foreach($page as $i => $p)
            @php
                $slot = $i + 1;
                $base = $slotPositions[$slot] ?? ['top'=>0,'left'=>0];
                $baseTop = $base['top'];
                $baseLeft = $base['left'];
            @endphp
            @if(!$p)
                <div class="cred" style="top:{{ $baseTop + $backOffsetY }}mm; left:{{ $baseLeft + $backOffsetX }}mm;"></div>
                @continue
            @endif
            <div class="cred {{ $debug ? 'debug':'' }}" style="top:{{ $baseTop + $backOffsetY }}mm; left:{{ $baseLeft + $backOffsetX }}mm;">
                <div class="txt num" style="top:15mm; left:10mm;">{{ $p['credencial'] }}</div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
