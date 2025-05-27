<?php

namespace Telegram;

use App\Console\Commands\BotCommand;
use App\Services\TgBot\TgBot;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TgLoginTest extends TestCase
{
    public function test_login_command(): void
    {
        $update = [
            "update_id" => 309975560,
            "message" => [
                "message_id" => 11511,
                "from" => [
                    "id" => 355919981,
                    "is_bot" => false,
                    "first_name" => "Ravshan",
                    "last_name" => "Davlatov",
                    "username" => "ravshan014",
                    "language_code" => "en",
                ],
                "chat" => [
                    "id" => 355919981,
                    "first_name" => "Ravshan",
                    "last_name" => "Davlatov",
                    "username" => "ravshan014",
                    "type" => "private",
                ],
                "date" => 1748282188,
                "text" => "/login",
                "entities" => [
                    0 => [
                        "offset" => 0,
                        "length" => 6,
                        "type" => "bot_command",
                    ]
                ]
            ]
        ];

        Cache::shouldReceive('put')->once()->with('login_355919981')->andReturn(true);

        $mock = $this->partialMock(TgBot::class, function (Mockery\MockInterface $mock) {
            $mock->expects('answerMsg')->once()->with(355919981, 'Send auth code:');
        });

        app(BotCommand::class)->handleUpdate($update);
    }
}
