<?php

namespace App\Console\Commands;

use App\Services\TgBot\TgBot;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;
use App\Models\User;
use App\Services\Handler\HandlerFactory;
use App\Services\TelegramService;
use backend\services\tg_bot\TgHelper;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class BotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public const ALLOWED_UPDATES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'poll',
        'poll_answer'
    ];

    protected TelegramService $telegramService;

    public function __construct()
    {
        parent::__construct();

        $this->telegramService = app(TelegramService::class);
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Long polling started');

        Redis::flushall();

        $offset = 0;
        while (true) {
            try {
                $update = $this->getUpdates($offset);
                if (empty($update)) {
                    continue;
                }

                if (Arr::get($update, 'update_id')) {
                    $offset = Arr::get($update, 'update_id') + 1;
                } else {
                    $lastUpdate = end($update);
                    $offset = Arr::get($lastUpdate, 'update_id', 0) + 1;
                }

                $this->info('New update:');

                $user = User::getFromChatId(TgBot::getChatIdByUpdate($update));
                $handlerByRole = HandlerFactory::make($user);
                $handlerByRole->handle($user, $update);
            } catch (Throwable $e) {
                $this->error($e->getMessage(), "Line: {$e->getLine()}, File: {$e->getFile()}");
            }
        }
    }

    /**
     * @throws GuzzleException
     */
    private function getUpdates(int $offset): array
    {
        $params = [
            'offset' => $offset,
            'timeout' => 60,
            'allowed_updates' => self::ALLOWED_UPDATES
        ];
        $response = $this->telegramService->sendRequest('getUpdates', $params);
        return Arr::get($response, 'result.0', []);
    }
}
