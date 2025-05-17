<?php

namespace App\Filament\Resources\RabItemResource\Pages;

use App\Filament\Resources\RabItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRabItems extends ListRecords
{
    protected static string $resource = RabItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
