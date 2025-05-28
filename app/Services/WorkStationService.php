<?php

namespace App\Services;

class WorkStationService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {
    }

}
