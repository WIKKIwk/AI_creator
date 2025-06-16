<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryTransaction;
use App\Models\Inventory\MiniInventory;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkStation;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class TestCommand extends Command
{
    protected $signature = 'app:test';
    protected $description = 'Empty orders command';

    public function handle(): void
    {
        $this->executionByWorker();
    }

    protected function executionByPM(): void
    {
        /** @var User $me */
        $me = User::query()->find(14);
        Auth::login($me);

        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->find(41);

        app(ProdOrderService::class)->declineExecutionProdManager($execution, 'Bad work test.');

        $this->info('Success');
    }

    protected function executionByWorker(): void
    {
        /** @var User $me */
        $me = User::query()->find(14);
        Auth::login($me);

        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->find(41);

        $message = "<b>Execution approved by Worker</b>\n\n";
        $message .= TgMessageService::getExecutionMsg($execution);

        TelegramService::sendMessage($execution->declinedBy->chat_id, $message, [
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '❌ Decline', 'callback_data' => "declineExecution:$execution->id"],
                    ['text' => '✅ Approve', 'callback_data' => "approveExecution:$execution->id"]
                ]
            ])
        ]);

        $this->info('Success');
    }
}
