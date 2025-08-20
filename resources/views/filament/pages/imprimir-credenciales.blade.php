<x-filament-panels::page>

    <!-- Barra de acciones superior -->
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Impresión de Credenciales</h2>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="clearPlantillaCache" wire:loading.attr="disabled" class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-medium px-3 py-1.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 disabled:opacity-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 012-2h2.586A2 2 0 0110 2.586L11.414 4H14a2 2 0 012 2v1H4V4z"/><path fill-rule="evenodd" d="M4 9v5a2 2 0 002 2h8a2 2 0 002-2V9H4zm3 2h6a1 1 0 010 2H7a1 1 0 010-2z" clip-rule="evenodd"/></svg>
                Limpiar caché fecha
            </button>
            <button type="button" wire:click="clearPlantillaCacheAllActive" wire:loading.attr="disabled" class="inline-flex items-center gap-1 rounded-md bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium px-3 py-1.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 disabled:opacity-60">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h3a1 1 0 011 1v1h2V3a1 1 0 011-1h3a1 1 0 011 1v1h1a1 1 0 011 1v3a1 1 0 01-1 1h-1v2h1a1 1 0 011 1v3a1 1 0 01-1 1h-1v1a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1h-2v1a1 1 0 01-1 1H4a1 1 0 01-1-1v-1H2a1 1 0 01-1-1v-3a1 1 0 011-1h1v-2H2a1 1 0 01-1-1V5a1 1 0 011-1h1V3zM8 6H6v2h2V6zm2 0h2v2h-2V6zm2 6h-2v2h2v-2zm-4 0H6v2h2v-2z" clip-rule="evenodd"/></svg>
                Limpiar caché todas fechas
            </button>
        </div>
    </div>

    {{-- Panel de Filtros --}}
    <div class="p-4 mb-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
        {{ $this->form }}
    </div>

    {{-- Panel de Plantillas separado --}}
    @php
        $fechaId = data_get($this->data,'proceso_fecha_id') ?? null;
        $fecha = $fechaId ? \App\Models\ProcesoFecha::find($fechaId) : null;
        $anv = $fecha?->profec_vcUrlAnverso ? asset('storage/'.$fecha->profec_vcUrlAnverso) : (file_exists(public_path('storage/templates/anverso.jpg')) ? asset('storage/templates/anverso.jpg') : null);
        $rev = $fecha?->profec_vcUrlReverso ? asset('storage/'.$fecha->profec_vcUrlReverso) : (file_exists(public_path('storage/templates/reverso.jpg')) ? asset('storage/templates/reverso.jpg') : null);
        $anvPath = $fecha?->profec_vcUrlAnverso ? public_path('storage/'.$fecha->profec_vcUrlAnverso) : (file_exists(public_path('storage/templates/anverso.jpg')) ? public_path('storage/templates/anverso.jpg') : null);
        $revPath = $fecha?->profec_vcUrlReverso ? public_path('storage/'.$fecha->profec_vcUrlReverso) : (file_exists(public_path('storage/templates/reverso.jpg')) ? public_path('storage/templates/reverso.jpg') : null);
        $anvInfo = $anvPath && file_exists($anvPath) ? @getimagesize($anvPath) : null;
        $revInfo = $revPath && file_exists($revPath) ? @getimagesize($revPath) : null;
        $minW = 2480; $minH = 3508;
    @endphp
    @php $hasTemplates = (bool) ($anv || $rev); @endphp
    <div class="mb-6 p-4 bg-gradient-to-b from-white to-slate-50 dark:from-gray-800 dark:to-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10" x-data="{ openFull:null, showPreviews:false }">
            <style>
                .cred-thumb {width:170px;max-width:170px;display:block;height:auto;object-fit:contain;background:#f8fafc;}
                .dark .cred-thumb {background:#1f2937;}
                .cred-thumb-wrapper {width:170px}
                @media (min-width:1400px){ .cred-thumb {width:190px;max-width:190px;} .cred-thumb-wrapper{width:190px;} }
                .cred-reso-badge {font-size:10px;padding:2px 6px;border-radius:4px;background:#f1f5f9;color:#334155;}
                .dark .cred-reso-badge {background:#374151;color:#e2e8f0;}
                .preview-toggle-btn {font-size:11px;display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-weight:500;}
                .preview-toggle-btn[data-state='on']{background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;}
                .dark .preview-toggle-btn[data-state='on']{background:#312e81;color:#e0e7ff;border-color:#4338ca;}
                .preview-toggle-btn[data-state='off']{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;}
                .dark .preview-toggle-btn[data-state='off']{background:#374151;color:#cbd5e1;border-color:#475569;}
                [x-cloak]{display:none !important;}
                .cred-modal img {max-width:100%;height:auto;}
                .spinner {width:28px;height:28px;border:3px solid #cbd5e1;border-top-color:#6366f1;border-radius:50%;animation:spin 0.9s linear infinite;}
                @keyframes spin {to {transform:rotate(360deg);}}
            </style>
            <div class="flex items-center gap-3 mb-3 flex-wrap">
                <h3 class="text-sm font-semibold flex items-center gap-2 text-gray-800 dark:text-gray-100">Plantillas de Credenciales
                    <template x-if="$store && $store.alpine">
                        <span></span>
                    </template>
                    <button type="button" @click="showPreviews=!showPreviews" class="preview-toggle-btn" :data-state="showPreviews ? 'on' : 'off'" x-text="showPreviews ? 'Ocultar previsualización' : 'Mostrar previsualización'"></button>
                </h3>
                @php
                    $pfid = data_get($this->data,'proceso_fecha_id') ?? null;
                    $hasAnvCache = $pfid ? session()->has('plantilla_anv_'.$pfid) : false;
                    $hasRevCache = $pfid ? session()->has('plantilla_rev_'.$pfid) : false;
                @endphp
                @if($pfid)
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium border {{ ($hasAnvCache||$hasRevCache) ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-600/10 dark:text-emerald-300 dark:border-emerald-400/30' : 'bg-yellow-50 text-yellow-700 border-yellow-300 dark:bg-yellow-600/10 dark:text-yellow-300 dark:border-yellow-400/30' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 5h2v6H9V5zm0 8h2v2H9v-2z"/></svg>
                        {{ ($hasAnvCache||$hasRevCache) ? 'Caché activa' : 'Sin caché' }}
                    </span>
                @endif
            </div>

            @if(!$hasTemplates)
                <div class="text-xs md:text-sm px-3 py-3 rounded-md border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-gray-700/40 text-slate-600 dark:text-slate-300">
                    No hay plantillas cargadas para la fecha seleccionada.
                    <span class="block mt-1">Suba Anverso y Reverso en: <strong>Procesos &gt; Editar &gt; Fechas</strong>. Si existen archivos globales por defecto (storage/templates) se usarán, de lo contrario el PDF se generará sin fondo.</span>
                </div>
            @endif

            @if($hasTemplates)
            <div x-show="showPreviews" x-collapse x-cloak class="relative">
                <div class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 dark:bg-gray-900/70" wire:loading.flex wire:target="data.proceso_fecha_id,clearPlantillaCache,clearPlantillaCacheAllActive">
                    <div class="flex flex-col items-center gap-2">
                        <div class="spinner"></div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Cargando plantillas...</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-6 items-start">
                    @if($anv)
                        <div class="space-y-1 cred-thumb-wrapper">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Anverso</span>
                                @if($anvInfo)
                                    <span class="cred-reso-badge">{{ $anvInfo[0] }}x{{ $anvInfo[1] }}</span>
                                @endif
                            </div>
                            <div class="group relative">
                                <img src="{{ $anv }}" alt="Anverso" class="cred-thumb border border-slate-200 dark:border-slate-600 rounded shadow-sm cursor-zoom-in transition group-hover:shadow-md" style="aspect-ratio:2480/3508;" loading="lazy" @click="openFull='anv'" />
                                @if($anvInfo && ($anvInfo[0] < $minW || $anvInfo[1] < $minH))
                                    <div class="absolute inset-0 bg-red-600/15 flex items-center justify-center text-[11px] font-semibold text-red-700 dark:text-red-300 text-center p-2 rounded">
                                        Resolución Baja<br>Mín: {{ $minW }}x{{ $minH }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="text-[11px] text-primary-600 hover:underline dark:text-primary-400" @click="openFull='anv'">Ver grande</button>
                                <a href="{{ $anv }}" target="_blank" class="text-[11px] text-gray-500 hover:underline dark:text-gray-400">Abrir pestaña</a>
                            </div>
                        </div>
                    @endif
                    @if($rev)
                        <div class="space-y-1 cred-thumb-wrapper">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Reverso</span>
                                @if($revInfo)
                                    <span class="cred-reso-badge">{{ $revInfo[0] }}x{{ $revInfo[1] }}</span>
                                @endif
                            </div>
                            <div class="group relative">
                                <img src="{{ $rev }}" alt="Reverso" class="cred-thumb border border-slate-200 dark:border-slate-600 rounded shadow-sm cursor-zoom-in transition group-hover:shadow-md" style="aspect-ratio:2480/3508;" loading="lazy" @click="openFull='rev'" />
                                @if($revInfo && ($revInfo[0] < $minW || $revInfo[1] < $minH))
                                    <div class="absolute inset-0 bg-red-600/15 flex items-center justify-center text-[11px] font-semibold text-red-700 dark:text-red-300 text-center p-2 rounded">
                                        Resolución Baja<br>Mín: {{ $minW }}x{{ $minH }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="text-[11px] text-primary-600 hover:underline dark:text-primary-400" @click="openFull='rev'">Ver grande</button>
                                <a href="{{ $rev }}" target="_blank" class="text-[11px] text-gray-500 hover:underline dark:text-gray-400">Abrir pestaña</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif
            <template x-if="openFull">
                <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="openFull=null" x-cloak>
                    <div class="cred-modal bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-[85vw] max-h-[85vh] overflow-auto relative">
                        <button type="button" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200" @click="openFull=null" aria-label="Cerrar">✕</button>
                        <div class="p-4 space-y-3">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="openFull==='anv' ? 'Anverso (Tamaño Completo)' : 'Reverso (Tamaño Completo)'"></h4>
                            <div class="border border-slate-200 dark:border-slate-600 rounded-md p-2 bg-gray-50 dark:bg-gray-900 flex justify-center">
                                <div class="w-full overflow-auto">
                                    <img :src="openFull==='anv' ? '{{ $anv }}' : '{{ $rev }}'" alt="Plantilla" style="max-width:100%;height:auto;" />
                                </div>
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">Clic en ✕ o presiona ESC para cerrar.</div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Tabla de Resultados --}}
    <div>
        {{ $this->table }}
    </div>

</x-filament-panels::page>
