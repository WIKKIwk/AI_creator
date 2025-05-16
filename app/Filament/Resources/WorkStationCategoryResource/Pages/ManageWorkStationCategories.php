<?php

namespace App\Filament\Resources\WorkStationCategoryResource\Pages;

use App\Filament\Resources\WorkStationCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkStationCategories extends ManageRecords
{
    protected static string $resource = WorkStationCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function($data) {
                    $data['organization_id'] = auth()->user()->organization_id;
                    return $data;
                }),
        ];
    }
}
