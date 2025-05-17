<?php

namespace App\Filament\Resources\RabItemResource\Pages;

use App\Filament\Resources\RabItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRabItem extends EditRecord
{
    protected static string $resource = RabItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
