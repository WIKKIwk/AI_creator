<?php

namespace App\Services;

use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrderStep;
use Exception;

class WorkStationService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {
    }

}
