<?php

namespace App\Events;

use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\SupplyOrder\SupplyOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplyOrderChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public SupplyOrder $supplyOrder, public bool $isNew = true)
    {
        //
    }
}
