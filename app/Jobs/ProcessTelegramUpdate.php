<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Handler\HandlerFactory;
use App\Services\TgBot\TgBot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public array $update,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var User|null $user */
        $user = User::query()->find($this->userId);
        if (!$user) {
            return;
        }

        // Ensure auth()-based scopes and services behave as in request lifecycle
        Auth::login($user);

        $handler = HandlerFactory::make($user);
        $handler->handle($user, $this->update);
    }
}

