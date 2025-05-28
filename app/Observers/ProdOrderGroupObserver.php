<?php

namespace App\Observers;

use App\Models\ProdOrder\ProdOrderGroup;

class ProdOrderGroupObserver
{
    /**
     * Handle the ProdOrder "created" event.
     */
    public function created(ProdOrderGroup $prodOrderGroup): void
    {
        //
    }

    /**
     * Handle the ProdOrder "updated" event.
     */
    public function updated(ProdOrderGroup $prodOrderGroup): void
    {
        //
    }

    /**
     * Handle the ProdOrder "deleted" event.
     */
    public function deleted(ProdOrderGroup $prodOrderGroup): void
    {
        //
    }

    /**
     * Handle the ProdOrder "restored" event.
     */
    public function restored(ProdOrderGroup $prodOrderGroup): void
    {
        //
    }

    /**
     * Handle the ProdOrder "force deleted" event.
     */
    public function forceDeleted(ProdOrderGroup $prodOrderGroup): void
    {
        //
    }
}
