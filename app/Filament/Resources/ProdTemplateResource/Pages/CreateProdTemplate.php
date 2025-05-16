<?php

namespace App\Filament\Resources\ProdTemplateResource\Pages;

use App\Filament\Resources\ProdTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProdTemplate extends CreateRecord
{
    protected static string $resource = ProdTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = auth()->user()->organization_id;

        return parent::mutateFormDataBeforeCreate($data);
    }
}
