<?php

namespace App\Events;

use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStepExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepExecutionCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ProdOrderStepExecution $poStepExecution, public ?string $action = null)
    {
        //
    }
}
