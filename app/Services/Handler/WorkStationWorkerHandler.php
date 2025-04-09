<?php

namespace App\Services\Handler;

use App\Enums\ProdOrderProductStatus;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TransactionService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

class WorkStationWorkerHandler extends BaseHandler
{
    protected User $user;
    protected array $promises = [];

    protected const states = [
        'main' => 'main',
        'completeMaterial' => 'completeMaterial',
        'completeWork' => 'completeWork',
    ];

    protected const templates = [
        'completeMaterial' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>

Required materials:
<b>{requiredMaterials}</b>

Expected materials:
<b>{expectedMaterials}</b>

Actual used materials:
<b>{actualUsedMaterials}</b>

Enter actual used quantity for <b>{actualMaterial}</b>:
HTML,

        'completeWork' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>

Expected materials:
<b>{expectedMaterials}</b>

Are you sure you want to complete the work?
HTML,

    ];

    protected TransactionService $transactionService;

    public function __construct(TgBot $tgBot, Cache $cache)
    {
        parent::__construct($tgBot, $cache);

        $this->transactionService = app(TransactionService::class);
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
        $this->tgBot->answerMsg([
            'text' => "Main menu for Work Station Worker",
            'reply_markup' => $this->getMainKb(),
        ]);
    }

    public function handleHelp(): void
    {
        $this->tgBot->answerMsg(['text' => "What do you need help with?"]);
    }

    /**
     * @throws GuzzleException
     */
    public function handleCbQuery($cbData): void
    {
        if (method_exists($this, $cbData)) {
            call_user_func([$this, $cbData]);
        } else {
            $this->tgBot->answerCbQuery(['text' => "Invalid callback data."]);
        }
    }

    public function sendMainMenu(): void
    {
        $this->tgBot->sendRequestAsync('sendMessage', [
            'chat_id' => $this->tgBot->chatId,
            'text' => "Main menu for Work Station Worker",
            'reply_markup' => $this->getMainKb(),
        ]);
    }

    public function handleText(string $text): void
    {
        $activeState = $this->cache->get($this->getCacheKey('state'));
        dump("Active state: $activeState");

        if ($activeState === self::states['completeMaterial']) {
            $this->completeMaterialText();
            return;
        }

        $this->sendMainMenu();
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function completeWork(): void
    {
        $prodOrder = $this->user->workStation->prodOrder;
        if (!$prodOrder) {
            $this->tgBot->answerCbQuery([
                'text' => "No production order assigned to your work station.",
                'reply_markup' => $this->getMainKb(),
            ]);
            $this->sendMainMenu();
            return;
        }

        $this->tgBot->answerCbQuery();
        $this->cache->put($this->getCacheKey('state'), self::states['completeWork']);

        $expectedMaterials = $this->getStep()->expectedItems
            ->map(function (ProdOrderStepProduct $item) {
                return "{$item->product->name} ({$item->quantity} {$item->product->measure_unit->getLabel()})";
            })
            ->implode("\n");

        $res = $this->tgBot->answerMsg([
            'text' => strtr(self::templates['completeWork'], [
                '{prodOrder}' => "ProdOrder-{$this->user->workStation->prodOrder->id}",
                '{product}' => $prodOrder->product->name . " ({$prodOrder->quantity} {$prodOrder->product->measure_unit->getLabel()})",
                '{expectedMaterials}' => $expectedMaterials,
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelCompleteWork'],
                    ['text' => '✅ Confirm', 'callback_data' => 'saveCompleteWork'],
                ]
            ])
        ]);
        $msgId = Arr::get($res, 'result.message_id');
        $this->cache->put($this->getCacheKey('edit_msg_id'), $msgId);
    }

    /**
     * @throws GuzzleException
     */
    public function cancelCompleteWork(): void
    {
        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerCbQuery([
            'text' => "Operation cancelled.",
            'reply_markup' => $this->getMainKb(),
        ]);

        $this->sendMainMenu();
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function saveCompleteWork(): void
    {
        $step = $this->getStep();
        $step->update(['status' => ProdOrderProductStatus::Completed]);

        foreach ($step->expectedItems as $expectedItem) {
            if ($expectedItem->status === ProdOrderProductStatus::Completed) {
                continue;
            }

            $this->transactionService->addMiniStock(
                $expectedItem->product_id,
                $expectedItem->quantity,
                workStationId: $step->work_station_id
            );
            $expectedItem->update(['status' => ProdOrderProductStatus::Completed]);
        }

        foreach ($step->actualItems as $actualItem) {
            if ($actualItem->status === ProdOrderProductStatus::Completed) {
                continue;
            }

            $this->transactionService->removeMiniStock(
                $actualItem->product_id,
                $actualItem->quantity,
                $step->work_station_id
            );
            $actualItem->update(['status' => ProdOrderProductStatus::Completed]);
        }

        $this->user->workStation->update(['prod_order_id' => null]);

        $this->tgBot->answerCbQuery([
            'text' => "Work completed successfully.",
            'show_alert' => true,
        ]);

        $this->sendMainMenu();
    }

    /**
     * @throws GuzzleException
     */
    public function completeMaterial(): void
    {
        $prodOrder = $this->user->workStation->prodOrder;
        if (!$prodOrder) {
            $this->tgBot->answerCbQuery([
                'text' => "No production order assigned to your work station.",
                'reply_markup' => $this->getMainKb(),
            ]);
            $this->sendMainMenu();
            return;
        }

        $this->tgBot->answerCbQuery();
        $this->cache->put($this->getCacheKey('state'), self::states['completeMaterial']);

        $res = $this->tgBot->answerMsg([
            'text' => strtr(
                self::templates['completeMaterial'],
                array_merge($this->getCompleteMaterialPlaceholders(), [
                    '{actualMaterialInput}' => '-',
                ])
            ),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelCompleteMaterial'],
                    ['text' => '✅ Save', 'callback_data' => 'saveCompleteMaterial'],
                ]
            ])
        ]);
        $msgId = Arr::get($res, 'result.message_id');
        $this->cache->put($this->getCacheKey('edit_msg_id'), $msgId);
    }

    public function completeMaterialText(): void
    {
        $input = $this->tgBot->getText();
        $input = trim($input);

        $actualMaterial = $this->getActualMaterial();

        $this->cache->put(
            $this->getCacheKey('completeMaterial'),
            json_encode(["quantity_$actualMaterial->id" => $input])
        );

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(
                self::templates['completeMaterial'],
                $this->getCompleteMaterialPlaceholders()
            ),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelCompleteMaterial'],
                    ['text' => '✅ Save', 'callback_data' => 'saveCompleteMaterial'],
                ]
            ])
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function cancelCompleteMaterial(): void
    {
        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('completeMaterial'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerCbQuery([
            'text' => "Operation cancelled.",
            'reply_markup' => $this->getMainKb(),
        ]);

        $this->sendMainMenu();
    }

    /**
     * @throws GuzzleException
     */
    public function saveCompleteMaterial(): void
    {
        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];
        $actualUsedMaterials = $this->getStep()
            ->actualItems()
            ->get();

        foreach ($actualUsedMaterials as $actualUsedMaterial) {
            $properQty = Arr::get($formData, "quantity_$actualUsedMaterial->id");
            if (empty($properQty)) {
                $properQty = $actualUsedMaterial->quantity;
            }
            $actualUsedMaterial->update([
                'status' => ProdOrderProductStatus::Completed,
                'quantity' => $properQty
            ]);
        }

        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('completeMaterial'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerCbQuery([
            'text' => "Actual materials saved successfully.",
            'reply_markup' => $this->getMainKb(),
        ]);

        $this->sendMainMenu();
    }

    protected function getStep(): ?ProdOrderStep
    {
        /** @var ProdOrderStep $step */
        $step = $this->user->workStation->prodOrder->steps()
            ->where('work_station_id', $this->user->work_station_id)
            ->first();

        return $step;
    }

    protected function getCompleteMaterialPlaceholders(): array
    {
        $prodOrder = $this->user->workStation->prodOrder;

        $step = $this->getStep();
        $expectedMaterials = $step->expectedItems
            ->map(function (ProdOrderStepProduct $item) {
                return "{$item->product->name} ({$item->quantity} {$item->product->measure_unit->getLabel()})";
            })
            ->implode("\n");

        $requiredMaterials = $step->requiredItems
            ->map(function (ProdOrderStepProduct $item) {
                return "{$item->product->name} ({$item->quantity} {$item->product->measure_unit->getLabel()})";
            })
            ->implode("\n");

        $actualUsedMaterials = $step->actualItems()->get();

        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];

        $actualUsedMaterialsStr = "";
        foreach ($actualUsedMaterials as $actualUsedMaterial) {
            $properQty = Arr::get($formData, "quantity_$actualUsedMaterial->id");
            if (empty($properQty)) {
                $properQty = $actualUsedMaterial->quantity;
            }
            $actualUsedMaterialsStr .= "{$actualUsedMaterial->product->name} ($properQty {$actualUsedMaterial->product->measure_unit->getLabel()})\n";
        }

        return [
            '{prodOrder}' => "ProdOrder-$prodOrder->id",
            '{product}' => $prodOrder->product->name . " ({$prodOrder->quantity} {$prodOrder->product->measure_unit->getLabel()})",
            '{expectedMaterials}' => $expectedMaterials,
            '{requiredMaterials}' => $requiredMaterials,
            '{actualUsedMaterials}' => $actualUsedMaterialsStr,
            '{actualMaterial}' => $this->getActualMaterial()?->product?->name ?? '-',
        ];
    }

    protected function getActualMaterial(): ?ProdOrderStepProduct
    {
        $prodOrder = $this->user->workStation->prodOrder;
        /** @var ProdOrderStep $step */
        $step = $prodOrder->steps()->where('work_station_id', $this->user->work_station_id)->first();

        /** @var ProdOrderStepProduct $actualMaterial */
        $actualMaterial = $step->actualItems()
            ->where(fn($q) => $q->where('status', '!=', ProdOrderProductStatus::Completed)->orWhereNull('status'))
            ->first();

        if (!$actualMaterial) {
            $actualMaterial = $step->actualItems()
                ->where('status', ProdOrderProductStatus::Completed)
                ->latest()
                ->first();
        }

        return $actualMaterial;
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => '🛠 Complete material', 'callback_data' => 'completeMaterial']
            ],
            [
                ['text' => '✅ Complete work', 'callback_data' => 'completeWork']
            ],
        ]);
    }
}
