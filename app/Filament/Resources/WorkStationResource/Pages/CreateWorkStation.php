<?php

namespace App\Filament\Resources\WorkStationResource\Pages;

use App\Filament\Resources\WorkStationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkStation extends CreateRecord
{
    protected static string $resource = WorkStationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = auth()->user()->organization_id;

        return parent::mutateFormDataBeforeCreate($data);
    }
}
