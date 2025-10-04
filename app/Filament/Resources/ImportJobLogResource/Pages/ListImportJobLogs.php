<?php

namespace App\Filament\Resources\ImportJobLogResource\Pages;

use App\Filament\Resources\ImportJobLogResource;
use Filament\Resources\Pages\ListRecords;

class ListImportJobLogs extends ListRecords
{
    protected static string $resource = ImportJobLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
