@php($title = $title ?? ($pageTitle ?? 'Autenticación'))
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim($title) }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="antialiased min-h-full bg-gradient-to-br from-gray-50 via-white to-gray-100 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900 text-gray-900 dark:text-gray-100">
    <div class="flex min-h-screen flex-col items-center justify-center p-6">
        <div class="w-full max-w-md">
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
            <div class="rounded-xl border border-gray-200/60 dark:border-gray-700/60 bg-white dark:bg-gray-900/60 backdrop-blur supports-[backdrop-filter]:bg-white/70 dark:supports-[backdrop-filter]:bg-gray-900/70 shadow-sm ring-1 ring-black/5 dark:ring-white/10 p-6 space-y-5">
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
</html>
