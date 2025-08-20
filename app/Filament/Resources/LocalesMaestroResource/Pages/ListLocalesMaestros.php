<?php

namespace App\Filament\Resources\LocalesMaestroResource\Pages;

use App\Filament\Resources\LocalesMaestroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocalesMaestros extends ListRecords
{
    protected static string $resource = LocalesMaestroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Crear Local'),
        ];
    }
}
