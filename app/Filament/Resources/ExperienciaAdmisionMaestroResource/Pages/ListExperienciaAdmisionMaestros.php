<?php

namespace App\Filament\Resources\ExperienciaAdmisionMaestroResource\Pages;

use App\Filament\Resources\ExperienciaAdmisionMaestroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExperienciaAdmisionMaestros extends ListRecords
{
    protected static string $resource = ExperienciaAdmisionMaestroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
