@php($title = 'Iniciar sesión')
<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="authenticate" class="space-y-6">
        {{ $this->form }}
        <div class="flex items-center justify-start gap-4 text-sm">
            <label class="flex items-center gap-2">
                <x-filament::input.checkbox wire:model="remember" />
                <span>Recuérdame</span>
            </label>
        </div>
        <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
        @if (session('status'))
            <p class="text-xs text-success-600">{{ session('status') }}</p>
        @endif
    </x-filament-panels::form>
</x-filament-panels::page.simple>
