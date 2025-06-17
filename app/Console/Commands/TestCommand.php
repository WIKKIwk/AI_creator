<?php

namespace App\Console\Commands;

use App\Events\StepExecutionCreated;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\User;
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
        /** @var User $me */
        $me = User::query()->find(8);
        Auth::login($me);

        /** @var User $stockManager */
        $stockManager = User::query()->find(14);

        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->find(44);

        $message = TgMessageService::getExecutionMsg($execution);

        TelegramService::sendMessage($stockManager, $message, [
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '❌ Decline', 'callback_data' => "declineExecution:$execution->id"],
                    ['text' => '✅ Approve', 'callback_data' => "approveExecution:$execution->id"]
                ]
            ]),
        ]);

//        $this->executionCreated();
//        $this->executionByWorker();
//        $this->executionByWorker();
    }

    protected function executionCreated(): void
    {
        /** @var User $me */
        $me = User::query()->find(5);
        Auth::login($me);

        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->find(41);

        StepExecutionCreated::dispatch($execution);

        $this->info('Success');
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

        TelegramService::sendMessage($execution->declinedBy, $message, [
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
