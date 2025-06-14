<?php

namespace App\Filament\Resources\OrganizationPartnerResource\Pages;

use App\Filament\Resources\OrganizationPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizationPartner extends EditRecord
{
    protected static string $resource = OrganizationPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
