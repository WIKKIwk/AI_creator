<?php

namespace App\Filament\Resources\WorkStationResource\Pages;

use App\Filament\Resources\WorkStationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkStations extends ListRecords
{
    protected static string $resource = WorkStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
