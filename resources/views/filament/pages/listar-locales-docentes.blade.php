@php /** @var \\App\\Filament\\Pages\\ListarLocalesDocentes $this */ @endphp
<x-filament::page>
    @php
        $dashboard = $this->getDashboardData();
        $kpis = $dashboard['kpis'] ?? [];
        $bars = $dashboard['local_bars'] ?? [];
        $topSaturados = $dashboard['top_saturados'] ?? [];
        $cargoBreakdown = $dashboard['cargo_breakdown'] ?? [];
        $totalCargo = collect($cargoBreakdown)->sum('value');

        $colors = ['#3b82f6', '#f59e0b', '#ef4444'];
        $segments = [];
        $cursor = 0;
        foreach ($cargoBreakdown as $index => $item) {
            $value = (int) ($item['value'] ?? 0);
            $percent = $totalCargo > 0 ? ($value / $totalCargo) * 100 : 0;
            $start = $cursor;
            $end = $cursor + $percent;
            $segments[] = [
                'label' => $item['label'] ?? '-',
                'value' => $value,
                'percent' => round($percent, 1),
                'color' => $colors[$index] ?? '#6b7280',
                'start' => round($start, 2),
                'end' => round($end, 2),
            ];
            $cursor = $end;
        }

        $conic = empty($segments)
            ? '#e5e7eb 0 100%'
            : collect($segments)
                ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
                ->implode(', ');
    @endphp
    <style>
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position:absolute; inset:0; width:100%; }
        }

        .capacity-bar-track {
            display: flex;
            width: 100%;
            height: 0.875rem;
            border-radius: 9999px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .capacity-vac {
            background: #93c5fd;
        }

        .capacity-ocu {
            background: #2563eb;
        }

        .occupancy-ring {
            width: 13rem;
            height: 13rem;
            border-radius: 9999px;
            background: conic-gradient({{ $conic }});
            position: relative;
            margin-inline: auto;
        }

        .occupancy-ring::after {
            content: '';
            position: absolute;
            inset: 1.7rem;
            border-radius: 9999px;
            background: #ffffff;
        }

        .dark .occupancy-ring::after {
            background: rgb(31 41 55);
        }
    </style>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <form wire:submit.prevent="noop" class="grid gap-4 md:grid-cols-3">
                {{ $this->form }}
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-gray-500">Locales con cargos</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($kpis['locales'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-gray-500">Capacidad total</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($kpis['capacidad'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-gray-500">Vacantes</p>
                <p class="mt-2 text-2xl font-semibold text-sky-600 dark:text-sky-400">{{ number_format($kpis['vacantes'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-gray-500">Ocupados</p>
                <p class="mt-2 text-2xl font-semibold text-blue-700 dark:text-blue-300">{{ number_format($kpis['ocupados'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-gray-500">Locales saturados (>=90%)</p>
                <p class="mt-2 text-2xl font-semibold text-red-600 dark:text-red-400">{{ number_format($kpis['saturados'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-gray-500">Ocupacion global: {{ number_format((float) ($kpis['ocupacion_pct'] ?? 0), 1) }}%</p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <header class="mb-4 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Capacidad vs ocupacion por local</h3>
                    <p class="text-xs text-gray-500">Top {{ count($bars) }} locales</p>
                </header>

                <div class="space-y-3">
                    @forelse($bars as $bar)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
                                <span class="truncate pr-3">{{ $bar['local_nombre'] }}</span>
                                <span>{{ number_format((float) $bar['pct'], 1) }}%</span>
                            </div>
                            <div class="capacity-bar-track">
                                <div class="capacity-vac" style="width: {{ $bar['disp_pct'] }}%" title="Disponibles: {{ $bar['disponibles'] }}"></div>
                                <div class="capacity-ocu" style="width: {{ $bar['ocu_pct'] }}%" title="Ocupados: {{ $bar['ocupados'] }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No hay datos para la fecha seleccionada.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <header class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Top 10 locales con mayor ocupacion</h3>
                </header>

                <div class="space-y-2">
                    @forelse($topSaturados as $index => $item)
                        <div class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2 text-sm dark:border-gray-700">
                            <span class="w-6 text-center font-semibold text-gray-500">{{ $index + 1 }}</span>
                            <span class="flex-1 truncate text-gray-800 dark:text-gray-200">{{ $item['local_nombre'] }}</span>
                            <span class="font-medium {{ $item['pct'] >= 100 ? 'text-red-600 dark:text-red-400' : ($item['pct'] >= 90 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">
                                {{ number_format((float) $item['pct'], 1) }}%
                            </span>
                            <span class="text-gray-500">({{ $item['ocupados'] }}/{{ $item['capacidad'] }})</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Sin registros para mostrar ranking.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Distribucion de ocupados por cargo</h3>
                <div class="mt-4">
                    <div class="occupancy-ring"></div>
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach($segments as $segment)
                        <li class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $segment['color'] }}"></span>
                                <span class="text-gray-700 dark:text-gray-200">{{ $segment['label'] }}</span>
                            </div>
                            <span class="text-gray-600 dark:text-gray-300">{{ number_format($segment['value']) }} ({{ number_format((float) $segment['percent'], 1) }}%)</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <div id="print-area">
            {{ $this->table }}
        </div>
    </div>
</x-filament::page>
