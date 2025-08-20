<?php

namespace App\Filament\Resources\AdministrativoResource\Pages;

use App\Filament\Resources\AdministrativoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdministrativos extends ListRecords
{
    protected static string $resource = AdministrativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
