<?php

namespace App\Filament\Resources\OdpAssetResource\Pages;

use App\Filament\Resources\OdpAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOdpAssets extends ListRecords
{
    protected static string $resource = OdpAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
