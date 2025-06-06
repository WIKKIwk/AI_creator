<?php

namespace App\Services\Handler\WorkerHandler;

use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\TelegramService;

class WorkerHandler extends BaseHandler
{
    protected array $sceneHandlers = [
        'createExecution' => CreateExecutionScene::class,
    ];

    public const templates = [
        'createExecution' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>
Progress: <b>{progress}</b>

Expected output product: <b>{expectedMaterial}</b>
Produced output product: <b>{producedMaterial}</b>

Using materials:
{usingMaterials}
Choose the material to execute:
HTML,

        'createExecutionForm' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function validateUser(User $user): bool
    {
        if (!$user->work_station_id) {
            $this->tgBot->answerMsg(['text' => "You're not assigned to any WorkStation. Please contact your manager."]);
            return false;
        }

        return true;
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '🛠 Add execution', 'callback_data' => 'createExecution']]
        ]);
    }
}
