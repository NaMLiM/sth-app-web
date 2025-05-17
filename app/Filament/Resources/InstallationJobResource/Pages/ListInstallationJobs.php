<?php

namespace App\Filament\Resources\InstallationJobResource\Pages;

use App\Filament\Resources\InstallationJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstallationJobs extends ListRecords
{
    protected static string $resource = InstallationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
