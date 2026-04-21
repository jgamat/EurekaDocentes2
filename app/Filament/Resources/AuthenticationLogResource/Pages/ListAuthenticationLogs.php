<?php

namespace App\Filament\Resources\AuthenticationLogResource\Pages;

use App\Filament\Resources\AuthenticationLogResource;
use App\Filament\Widgets\AuthenticationLogStats;
use App\Filament\Widgets\RecentAuthenticationLogins;
use Filament\Resources\Pages\ListRecords;

class ListAuthenticationLogs extends ListRecords
{
    protected static string $resource = AuthenticationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AuthenticationLogStats::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RecentAuthenticationLogins::class,
        ];
    }
}
