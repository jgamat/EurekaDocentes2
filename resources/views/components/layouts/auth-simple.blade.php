@php($title = $title ?? ($pageTitle ?? 'Autenticación'))
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim($title) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
        body { font-family: inherit; }
        /* Fallback gradient & card tint if theme vars no-op */
        .fi-body {
            background: linear-gradient(135deg,#eef5ff 0%,#ffffff 45%,#f5f0ff 100%);
        }
        .primary-btn {
            /* Utility classes will still apply; this is only a hard fallback if utilities fail */
            background-color: #2563eb; /* primary-600 */
            color: #fff;
            border-radius: .5rem;
            font-weight: 600;
        }
    </style>
    {{-- Recursos del tema Filament + app --}}
    @vite(['resources/css/filament/admin/theme.css','resources/css/app.css','resources/js/app.js'])
    @if (file_exists(public_path('vendor/themes/nord.css')))
        <link rel="stylesheet" href="{{ asset('vendor/themes/nord.css') }}" />
    @endif
    {{-- Theme publicado (nord) si existe --}}
    @if (file_exists(public_path('vendor/themes/nord.css')))
        <link rel="stylesheet" href="{{ asset('vendor/themes/nord.css') }}" />
    @endif
</head>
<body class="fi-body fi-simple-layout font-sans antialiased text-gray-950 dark:text-gray-100" style="background:linear-gradient(135deg,#e5f0ff 0%,#ffffff 45%,#f3e8ff 100%) !important;">
    <div class="fi-layout flex min-h-screen flex-col items-center justify-center p-6">
        <div class="fi-auth-card w-full max-w-md">
            <div class="relative mb-6 flex flex-col items-center gap-2">
                {{-- Logo slot (optional) --}}
                @if (view()->exists('components.application-logo'))
                    <div>
                        @include('components.application-logo')
                    </div>
                @endif
                <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
                @isset($subtitle)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $subtitle }}</p>
                @endisset
            </div>
            <div class="fi-card rounded-xl border border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/80 shadow-lg p-6 space-y-5 backdrop-blur-sm">
                {{ $slot }}
            </div>
            @isset($below)
                <div class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                    {{ $below }}
                </div>
            @endisset
            <div class="mt-8 flex justify-center">
                <p class="text-[11px] uppercase tracking-wide text-gray-400">&copy; {{ date('Y') }} — Sistema</p>
            </div>
        </div>
    </div>
</body>
{{-- Sin scripts dinámicos para evitar fuga de código en front --}}
</html>
