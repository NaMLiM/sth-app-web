<?php

namespace App\Filament\Resources\OdpAssetResource\Pages;

use App\Filament\Resources\OdpAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOdpAsset extends EditRecord
{
    protected static string $resource = OdpAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
