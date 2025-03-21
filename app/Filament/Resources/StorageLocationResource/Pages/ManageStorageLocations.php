<?php

namespace App\Filament\Resources\StorageLocationResource\Pages;

use App\Filament\Resources\StorageLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStorageLocations extends ManageRecords
{
    protected static string $resource = StorageLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
