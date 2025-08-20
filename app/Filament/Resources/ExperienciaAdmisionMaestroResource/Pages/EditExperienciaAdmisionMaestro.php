<?php

namespace App\Filament\Resources\ExperienciaAdmisionMaestroResource\Pages;

use App\Filament\Resources\ExperienciaAdmisionMaestroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExperienciaAdmisionMaestro extends EditRecord
{
    protected static string $resource = ExperienciaAdmisionMaestroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
