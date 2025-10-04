<?php

namespace App\Filament\Resources\DocenteResource\Pages;

use App\Filament\Resources\DocenteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\QueryException;
use Filament\Notifications\Notification;

class CreateDocente extends CreateRecord
{
    protected static string $resource = DocenteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['doc_vcDni'])) {
            $dni = preg_replace('/\D+/','', (string)$data['doc_vcDni']);
            $data['doc_vcDni'] = $dni;
        }
        return $data;
    }

    protected function handleRecordCreationException(\Throwable $e): void
    {
        if ($e instanceof QueryException) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(),'Duplicate entry') && str_contains($e->getMessage(),'docente')) {
                Notification::make()
                    ->title('El DNI ya está registrado')
                    ->body('No se pudo crear el docente porque el DNI ingresado ya existe.')
                    ->danger()
                    ->send();
                $this->halt();
                return;
            }
        }
        parent::handleRecordCreationException($e);
    }
}
