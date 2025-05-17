<?php

namespace App\Filament\Resources\InstallationJobResource\Pages;

use App\Filament\Resources\InstallationJobResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallationJob extends EditRecord
{
    protected static string $resource = InstallationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
