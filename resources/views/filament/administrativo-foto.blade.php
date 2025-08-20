@php /** @var \App\Models\Administrativo $record */ @endphp
<div class="mb-4 flex items-start gap-4">
    <div class="w-32 shrink-0">
        @if($fotoPath)
            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <img src="{{ $fotoPath }}" alt="Foto administrativo DNI {{ $record->adm_vcDni }}" class="w-full h-auto object-cover">
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ basename($fotoPath) }}</div>
        @else
            <div class="flex flex-col items-center justify-center h-32 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/40 text-gray-400 dark:text-gray-500 text-[10px] p-2 text-center gap-1.5">
                <svg viewBox="0 0 128 128" class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="currentColor" role="img" aria-label="Silueta genérica">
                    <circle cx="64" cy="40" r="24" class="opacity-70" />
                    <path d="M16 112c0-22.09 21.49-40 48-40s48 17.91 48 40" class="opacity-50" />
                </svg>
                <span class="block font-medium">Sin foto</span>
                <span class="mt-0.5 opacity-70 leading-tight">Coloque archivo<br>en <code>storage/app/public/fotos/{DNI}.jpg</code></span>
            </div>
        @endif
    </div>
</div>
