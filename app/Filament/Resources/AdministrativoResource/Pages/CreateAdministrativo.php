<?php

namespace App\Filament\Resources\AdministrativoResource\Pages;

use App\Filament\Resources\AdministrativoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\QueryException;
use Filament\Notifications\Notification;

class CreateAdministrativo extends CreateRecord
{
    protected static string $resource = AdministrativoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['adm_vcDni'])) {
            $dni = preg_replace('/\D+/','', (string)$data['adm_vcDni']);
            $data['adm_vcDni'] = $dni; // No forzamos padding arbitrario para no alterar PKs si existieran formateadas.
        }
        return $data;
    }

    protected function handleRecordCreationException(\Throwable $e): void
    {
        if ($e instanceof QueryException) {
            $sqlState = $e->getCode();
            // MySQL duplicate key error SQLSTATE 23000
            if ($sqlState == '23000' && str_contains($e->getMessage(),'Duplicate entry') && str_contains($e->getMessage(),'administrativo')) {
                Notification::make()
                    ->title('El DNI ya está registrado')
                    ->body('No se pudo crear el administrativo porque el DNI ingresado ya existe.')
                    ->danger()
                    ->send();
                $this->halt();
                return; }
        }
        parent::handleRecordCreationException($e);
    }
}
