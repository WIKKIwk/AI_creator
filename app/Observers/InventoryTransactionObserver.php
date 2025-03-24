<?php

namespace App\Observers;

use App\Enums\TransactionType;
use App\Models\InventoryTransaction;
use App\Services\TransactionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryTransactionObserver
{
    public $after_commit = true;

    public function __construct(
        protected TransactionService $transactionService
    ) {
    }

    /**
     * @throws Throwable
     */
    public function creating(InventoryTransaction $transaction): void
    {
        try {
            DB::beginTransaction();

            if ($transaction->type === TransactionType::In) {
                $this->transactionService->addStockByTransaction($transaction);
            } elseif ($transaction->type === TransactionType::Out) {
                $this->transactionService->removeStockByTransaction($transaction);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
