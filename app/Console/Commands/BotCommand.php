<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\Handler\BaseHandler;
use App\Services\Handler\HandlerFactory;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Throwable;

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

    public function __construct(protected TgBot $tgBot, protected Cache $cache)
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

//        Redis::flushall();

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

                $this->info('New update: ' . TgBot::getMessageIdUpdate($update));

                $this->handleUpdate($update);
            } catch (Throwable $e) {
                dump($e->getMessage(), $e->getLine(), $e->getFile());
                $this->error($e->getMessage() . " Line: {$e->getLine()}, File: {$e->getFile()}");
            }
        }
    }

    public function handleUpdate(array $update)
    {
        $this->tgBot->setUpdate($update);

        $chatId = $this->tgBot->getChatId();
        $text = $this->tgBot->getText();
        $user = User::findByChatId($chatId);

        $loginKey = "login_$chatId";
        if ($this->cache->has($loginKey)) {
            /** @var User $userByAuthCode */
            $userByAuthCode = User::query()->where('auth_code', $text)->first();

            if (!$userByAuthCode) {
                $this->tgBot->answerMsg(['text' => 'Invalid auth code.']);
                $this->cache->forget($loginKey);
                return null;
            }

            $user?->update(['chat_id' => null]);
            $userByAuthCode->update(['chat_id' => $chatId]);

            Auth::login($userByAuthCode);
            $userByAuthCode->loadMissing(['organization', 'warehouse', 'workStation']);

            $message = "<b>You logged in.</b>\n\n";
            $message .= BaseHandler::getUserDetailsMsg($userByAuthCode);

            $this->tgBot->answerMsg(['text' => $message, 'parse_mode' => 'HTML']);
            $this->cache->forget($loginKey);
            return null;
        }

        if ($text == '/login') {
            $this->cache->put($loginKey, 0);
            $this->tgBot->answerMsg(['text' => 'Send auth code:']);
            return null;
        }

        if ($user) {
            Auth::login($user);
            $handlerByRole = HandlerFactory::make($user);
            $handlerByRole->handle($user, $update);
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
