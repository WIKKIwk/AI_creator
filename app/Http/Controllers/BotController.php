<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\Handler\HandlerFactory;
use App\Services\TgBot\TgBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BotController
{
    public function __construct(
        protected TgBot $tgBot,
        protected Cache $cache,
    ) {
    }

    public function handle(Request $request)
    {
        // Optional: verify Telegram secret token if configured
        $secret = config('services.telegram.webhook_secret');
        if (!empty($secret)) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if ($header !== $secret) {
                Log::warning('Invalid Telegram webhook secret token');
                return response()->json(['ok' => false], 403);
            }
        }

        $update = $request->all();
        if (empty($update)) {
            return response()->json(['ok' => true]);
        }

        // Mirror BotCommand::handleUpdate logic
        $this->tgBot->setUpdate($update);

        $chatId = $this->tgBot->getChatId();
        $text = $this->tgBot->getText();
        /** @var User|null $user */
        $user = $chatId ? User::findByChatId($chatId) : null;

        $loginKey = "login_$chatId";
        if ($chatId && $this->cache->has($loginKey)) {
            /** @var User|null $userByAuthCode */
            $userByAuthCode = $text ? User::query()->where('auth_code', $text)->first() : null;

            if (!$userByAuthCode) {
                $this->tgBot->answerMsg(['text' => 'Invalid auth code.']);
                $this->cache->forget($loginKey);
                return response()->json(['ok' => true]);
            }

            $user?->update(['chat_id' => null]);
            $userByAuthCode->update(['chat_id' => $chatId]);
            $user = $userByAuthCode;
            $this->cache->forget($loginKey);
        }

        if ($text === '/login' && $chatId) {
            $this->cache->put($loginKey, 0);
            $this->tgBot->answerMsg(['text' => 'Send auth code:']);
            return response()->json(['ok' => true]);
        }

        if ($user) {
            Auth::login($user);
            $handlerByRole = HandlerFactory::make($user);
            $handlerByRole->handle($user, $update);
        }

        return response()->json(['ok' => true]);
    }
}
