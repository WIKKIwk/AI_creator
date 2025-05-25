<?php

namespace App\Events;

use App\Models\ProdOrderGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProdOrderChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ProdOrderGroup $prodOrderGroup, public bool $isNew = true)
    {
        //
    }
}
