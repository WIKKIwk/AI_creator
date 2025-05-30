<?php

namespace App\Services\Handler;

use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Throwable;

class WorkStationWorkerHandler extends BaseHandler
{
    protected User $user;
    protected array $promises = [];

    protected const states = [
        'main' => 'main',
        'addExecution' => 'addExecution',
        'addExecutionQty' => 'addExecutionQty',
    ];

    protected const templates = [
        'addExecution' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>

Expected output product: <b>{expectedMaterial}</b>

Using materials:
{usingMaterials}

Choose the material to execute:
HTML,

        'addExecutionMaterialQty' => <<<HTML
{errorMsg}

{executionDetails}
Input used quantity for <b>{product}</b>:
HTML,
    ];

    protected ProdOrderService $prodOrderService;

    public function __construct(TgBot $tgBot, Cache $cache)
    {
        parent::__construct($tgBot, $cache);

        $this->prodOrderService = app(ProdOrderService::class);
    }

    /**
     * @throws GuzzleException
     */
    public function validateUser(User $user): bool
    {
        if (!$user->work_station_id) {
            $this->tgBot->sendMsg([
                'chat_id' => $user->chat_id,
                'text' => "You are not assigned to any work station. Please contact your manager.",
            ]);
            return false;
        }

        return true;
    }

    public function handleStart(): void
    {
        $this->sendMainMenu();
    }

    public function handleHelp(): void
    {
        $this->tgBot->answerMsg(['text' => "What do you need help with?"]);
    }

    public function sendMainMenu(): void
    {
        $msg = self::getUserDetailsMsg($this->user);

        $this->tgBot->sendRequestAsync('sendMessage', [
            'chat_id' => $this->tgBot->chatId,
            'text' => <<<HTML
<b>Your details:</b>

$msg
HTML,
            'reply_markup' => $this->getMainKb(),
            'parse_mode' => 'HTML',
        ]);
    }

    public function handleText(string $text): void
    {
        $activeState = $this->cache->get($this->getCacheKey('state'));

        if ($activeState === self::states['addExecutionQty']) {
            $this->addExecutionQtyText();
            return;
        }

        $this->sendMainMenu();
    }

    /**
     * @throws GuzzleException
     */
    public function addExecution(): void
    {
        if (!$this->user->workStation->prodOrder) {
            $this->tgBot->answerCbQuery(['text' => "No production order assigned to your work station."]);
            return;
        }

        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->tgBot->answerCbQuery(['text' => "Work is already completed."]);
            return;
        }

        $this->tgBot->answerCbQuery();
        $this->cache->put($this->getCacheKey('state'), self::states['addExecution']);

        $res = $this->tgBot->answerMsg([
            'text' => strtr(self::templates['addExecution'], $this->getAddExecutionPlaceholders()),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']
                    ]
                ])
            )
        ]);
        $msgId = Arr::get($res, 'result.message_id');
        $this->cache->put($this->getCacheKey('edit_msg_id'), $msgId);
    }

    public function addExecutionMaterialCallback($id): void
    {
        $currentMaterial = $this->getActualMaterial($id);
        $step = $this->getStep();
        $form = json_decode($this->cache->get($this->getCacheKey('addExecutionForm')), true) ?? [];

        $this->cache->put($this->getCacheKey('addExecutionForm'), '{}');
        $this->cache->put($this->getCacheKey('state'), 'addExecutionMaterialQty');

        $executionDetails = "";
        $materials = $form['materials'] ?? [];
        $outputQty = $form['output_qty'] ?? 0;

        if (!empty($materials)) {
            $executionDetails .= "<b>Used materials:</b>\n";
            foreach ($materials as $productId => $usedQty) {
                /** @var Product $product */
                $product = Product::query()->find($productId);
                $executionDetails .= "\n<b>$product->catName</b>: {$usedQty} {$product->getMeasureUnit()->getLabel()}\n";
            }
        }
        $executionDetails .= "Output product: <b>{$step->outputProduct->catName} $outputQty {$step->outputProduct->getMeasureUnit()->getLabel()}</b>\n";

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => strtr(self::templates['addExecutionMaterialQty'], [
                '{errorMsg}' => '',
                '{executionDetails}' => $executionDetails,
                '{product}' => $currentMaterial->product->catName,
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']
                    ]
                ])
            )
        ]);
    }

    public function addExecutionQtyText(): void
    {
        $actualMaterial = $this->getActualMaterialFromCache();
        $quantity = $this->tgBot->getText();

        // check quantity is integer
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['completeMaterialInput'], [
                    '{errorMsg}' => "<i>Invalid quantity. Please enter a valid number.</i>",
                    '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
                    '{product}' => $actualMaterial->product->catName,
                ]),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard(
                    array_merge($this->getActualMaterialButtons(), [
                        [
                            ['text' => '🚫 Cancel', 'callback_data' => 'cancelCompleteMaterial'],
                            ['text' => '✅ Save', 'callback_data' => 'saveCompleteMaterial'],
                        ]
                    ])
                )
            ]);
            return;
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['completeMaterialInput'], [
                '{errorMsg}' => '',
                '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
                '{product}' => $actualMaterial->product->catName,
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getActualMaterialButtons(), [
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelCompleteMaterial'],
                        ['text' => '✅ Save', 'callback_data' => 'saveCompleteMaterial'],
                    ]
                ])
            )
        ]);
    }

    public function cancelAddExecution(): void
    {
        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('completeMaterial'));
        $this->cache->forget($this->getCacheKey('completeMaterialQty'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerCbQuery([
            'text' => "Operation cancelled.",
            'reply_markup' => $this->getMainKb(),
        ]);

        $this->sendMainMenu();
    }

    public function saveCompleteMaterial(): void
    {
        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];
        $actualUsedMaterials = $this->getStep()
            ->actualItems()
            ->get();

        foreach ($actualUsedMaterials as $actualUsedMaterial) {
            $properQty = Arr::get($formData, $actualUsedMaterial->id);
            if (empty($properQty)) {
                $properQty = $actualUsedMaterial->quantity;
            }
            $actualUsedMaterial->update(['quantity' => $properQty]);
        }

        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('completeMaterial'));
        $this->cache->forget($this->getCacheKey('completeMaterialQty'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerCbQuery([
            'text' => "Actual materials saved successfully.",
            'reply_markup' => $this->getMainKb(),
        ]);

        $this->sendMainMenu();
    }

    protected function getAddExecutionPlaceholders(): array
    {
        $prodOrder = $this->user->workStation->prodOrder;

        $step = $this->getStep();
        $usingMaterials = "";
        foreach ($step->materials as $material) {
            $measureUnit = $material->product->category?->measure_unit?->getLabel();
            $usingMaterials .= "<b>{$material->product->catName}</b>\n";
            $usingMaterials .= "Required: <b>$material->required_quantity $measureUnit</b>\n";
            $usingMaterials .= "Available: <b>$material->available_quantity $measureUnit</b>\n";
            $usingMaterials .= "Used: <b>$material->used_quantity $measureUnit</b>\n";
        }

        return [
            '{prodOrder}' => $this->user->workStation->prodOrder->number,
            '{product}' => $prodOrder->product->catName . " ({$prodOrder->quantity} {$prodOrder->product->category?->measure_unit?->getLabel()})",
            '{expectedMaterial}' => "{$step->outputProduct->catName} ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
            '{usingMaterials}' => $usingMaterials,
        ];
    }

    protected function getActualMaterialsStr(): string
    {
        $step = $this->getStep();

        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];
        /** @var Collection<ProdOrderStepProduct> $actualMaterials */
        $actualMaterials = $step->actualItems()->get();
        $actualMaterialsStr = "";
        foreach ($actualMaterials as $actualMaterial) {
            $properQty = Arr::get($formData, $actualMaterial->id);
            if (empty($properQty)) {
                $properQty = $actualMaterial->required_quantity;
            }
            $measureUnit = $actualMaterial->product->category?->measure_unit?->getLabel();
            $actualMaterialsStr .= "{$actualMaterial->product->catName}: $properQty $measureUnit (available: $actualMaterial->available_quantity $measureUnit)\n";
        }

        return $actualMaterialsStr;
    }

    protected function getStep(): ?ProdOrderStep
    {
        /** @var ProdOrderStep $step */
        $step = $this->user->workStation->prodOrder->steps()
            ->where('work_station_id', $this->user->work_station_id)
            ->first();

        return $step;
    }

    protected function getActualMaterial($id): ProdOrderStepProduct
    {
        /** @var ProdOrderStepProduct $actualMaterial */
        $actualMaterial = $this->getStep()->materials()->find($id);
        return $actualMaterial;
    }

    protected function getMaterialsKb(): array
    {
        /** @var Collection<ProdOrderStepProduct> $actualMaterials */
        $actualMaterials = $this->getStep()->materials()->get();
        $buttons = [];
        foreach ($actualMaterials as $actualMaterial) {
            $buttons[][] = [
                'text' => $actualMaterial->product->catName,
                'callback_data' => "addExecutionMaterial_{$actualMaterial->id}",
            ];
        }
        return $buttons;
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => '🛠 Add execution', 'callback_data' => 'addExecution']
            ]
        ]);
    }
}
