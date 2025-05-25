<?php

namespace App\Http\Controllers;

use App\Services\Handler\HandlerFactory;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Request;

class BotController
{
    public function __construct(
        protected TelegramService $telegramService
    ) {
    }

    public function handle(Request $request): void
    {
        $user = $request->user();
        $handlerByRole = HandlerFactory::make($request->user());
    }
}
