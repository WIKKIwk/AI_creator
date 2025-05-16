<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()->organization_id) {
            $data['organization_id'] = auth()->user()->organization_id;
        }

        $data['password'] = Hash::make($data['password']);

        return parent::mutateFormDataBeforeCreate($data);
    }
}
