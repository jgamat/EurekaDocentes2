<?php

namespace App\Filament\Widgets;

use App\Models\AuthenticationLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AuthenticationLogStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Resumen de accesos';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $last24h = now()->subDay();

        $failed24h = AuthenticationLog::query()
            ->where('login_at', '>=', $last24h)
            ->where('login_successful', false)
            ->count();

        $success24h = AuthenticationLog::query()
            ->where('login_at', '>=', $last24h)
            ->where('login_successful', true)
            ->count();

        $lastSuccess = AuthenticationLog::query()
            ->where('login_successful', true)
            ->whereNotNull('login_at')
            ->latest('login_at')
            ->first();

        return [
            Stat::make('Logins fallidos (24h)', (string) $failed24h)
                ->description('Intentos no exitosos en las ultimas 24 horas')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Accesos exitosos (24h)', (string) $success24h)
                ->description('Inicios de sesion correctos en las ultimas 24 horas')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Ultimo acceso exitoso', $lastSuccess?->login_at?->format('d/m/Y H:i') ?? 'Sin registros')
                ->description('Fecha y hora del ultimo login exitoso')
                ->icon('heroicon-o-clock')
                ->color('primary'),
        ];
    }
}
