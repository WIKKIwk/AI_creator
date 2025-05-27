<?php

namespace App\Events;

use App\Models\ProdOrderGroup;
use App\Models\SupplyOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplyOrderClosed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public SupplyOrder $supplyOrder)
    {
        //
    }
}
