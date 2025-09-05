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
                $updates = $this->getUpdates($offset);
                if (empty($updates)) {
                    continue;
                }

                if (!app()->isProduction()) {
                    dump($updates);
                }

                // Advance offset to the last processed update
                $lastUpdate = end($updates);
                $offset = Arr::get($lastUpdate, 'update_id', $offset) + 1;

                foreach ($updates as $update) {
                    $this->info('New update: ' . TgBot::getMessageIdUpdate($update));
                    $this->handleUpdate($update);
                }
            } catch (Throwable $e) {
                if (!app()->isProduction()) {
                    dump($e->getMessage(), $e->getLine(), $e->getFile());
                }
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
        if (!app()->isProduction()) {
            dump($chatId, $user?->id);
        }

        $loginKey = "login_$chatId";
        if ($this->cache->has($loginKey)) {
            /** @var User $userByAuthCode */
            $userByAuthCode = User::query()->where('auth_code', $text)->first();

            if (!$userByAuthCode) {
                $this->tgBot->answerMsg(['text' => 'Invalid auth code.']);
                // Keep the login window open; allow retry within TTL
                return null;
            }

            $user?->update(['chat_id' => null]);
            $userByAuthCode->update(['chat_id' => $chatId]);
//            $userByAuthCode->loadMissing(['organization', 'warehouse', 'workStation']);

            $user = $userByAuthCode;

            $this->cache->forget($loginKey);
            // Clean stale scene/state bound to this chat after re-login
            $this->cache->forget("$chatId:scene");
            $this->cache->forget("$chatId:state");
            $this->cache->forget("$chatId:edit_msg_id");

            // Immediately show main menu after successful login
            try {
                Auth::login($user);
                $handler = HandlerFactory::make($user);
                // Prepare handler context
                $handler->user = $user;
                $this->tgBot->setUpdate($update);
                $handler->handleStart();
                // Flush async messages
                $this->tgBot->settlePromises();
            } catch (\Throwable $e) {
                // Fallback to a basic success message
                $this->tgBot->answerMsg(['text' => 'Login successful. /start']);
            }
            return null;
        }

        if ($text == '/login') {
            // Open a 5-minute login window
            $this->cache->put($loginKey, 1, 300);
            $this->tgBot->answerMsg(['text' => 'Send auth code:']);
            return null;
        }

        if ($user) {
            Auth::login($user);
            $handlerByRole = HandlerFactory::make($user);
            $handlerByRole->handle($user, $update);
            return;
        }

        // Not logged in and not in login flow: prompt user
        if ($text && !$this->cache->has($loginKey)) {
            $this->tgBot->answerMsg(['text' => "Siz tizimga kirmagansiz. Avval /login yuboring."]);
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
        return Arr::get($response, 'result', []);
    }
}
