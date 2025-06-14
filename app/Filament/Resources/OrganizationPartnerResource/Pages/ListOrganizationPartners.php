<?php

namespace App\Filament\Resources\OrganizationPartnerResource\Pages;

use App\Filament\Resources\OrganizationPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationPartners extends ListRecords
{
    protected static string $resource = OrganizationPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add Partner'),
        ];
    }
}
