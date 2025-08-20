<?php

namespace App\Filament\Resources\AdministrativoResource\Pages;

use App\Filament\Resources\AdministrativoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdministrativo extends EditRecord
{
    protected static string $resource = AdministrativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
