<?php

namespace Database\Factories\ProdOrder;

use App\Enums\ProdOrderGroupType;
use App\Models\Organization;
use App\Models\OrganizationPartner;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdOrderGroup>
 */
class ProdOrderGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ProdOrderGroupType::ByOrder,
            'warehouse_id' => Warehouse::query()->first()->id,
            'agent_id' => OrganizationPartner::query()->latest()->first()->id,
            'created_by' => User::query()->first()->id,
        ];
    }
}
