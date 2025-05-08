<?php

namespace App\Services\Handler;

use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class WorkStationWorkerHandler extends BaseHandler
{
    protected User $user;
    protected array $promises = [];

    protected const states = [
        'main' => 'main',
        'completeMaterial' => 'completeMaterial',
        'completeMaterialQty' => 'completeMaterialQty',
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

Actual materials:
<b>{actualUsedMaterials}</b>

Choose the actual material to complete:
HTML,

        'completeMaterialInput' => <<<HTML
{errorMsg}

Actual used materials:
<b>{actualUsedMaterials}</b>

Input actual used quantity for <b>{product}</b>. Or choose another material to complete.:
HTML,

        'completeWork' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>

Expected materials:
<b>{expectedMaterials}</b>

Actual used materials:
<b>{actualUsedMaterials}</b>

Are you sure you want to complete the work?
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
     * @throws Exception
     */
    public function handleCbQuery($cbData): void
    {
        if (preg_match('/^(.*)_(\d+)$/', $cbData, $matches)) {
            $base = $matches[1];   // e.g., 'completeMaterial'
            $id = (int)$matches[2]; // e.g., 98

            $callback = $base . 'Callback';
            if (method_exists($this, $callback)) {
                call_user_func([$this, $callback], $id);
            } else {
                throw new Exception("Method '$callback' does not exist.");
            }

            return;
        }


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

        if ($activeState === self::states['completeMaterialQty']) {
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
        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->tgBot->answerCbQuery([
                'text' => "Work is already completed.",
                'reply_markup' => $this->getMainKb(),
            ]);
            return;
        }

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
                return "{$item->product->name} ({$item->quantity} {$item->product->category?->measure_unit?->getLabel()})";
            })
            ->implode("\n");

        $res = $this->tgBot->answerMsg([
            'text' => strtr(self::templates['completeWork'], [
                '{prodOrder}' => "ProdOrder-{$this->user->workStation->prodOrder->id}",
                '{product}' => $prodOrder->product->name . " ({$prodOrder->quantity} {$prodOrder->product->category?->measure_unit?->getLabel()})",
                '{expectedMaterials}' => $expectedMaterials,
                '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
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

        $this->prodOrderService->completeWork($step);

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
        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->tgBot->answerCbQuery([
                'text' => "Work is already completed.",
                'reply_markup' => $this->getMainKb(),
            ]);
            return;
        }

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

        /** @var Collection<ProdOrderStepProduct> $actualMaterials */
        $actualMaterials = $this->getStep()->actualItems()->get();
        $buttons = [];
        foreach ($actualMaterials as $actualMaterial) {
            $buttons[][] = [
                'text' => $actualMaterial->product->name,
                'callback_data' => "completeMaterial_{$actualMaterial->id}",
            ];
        }

        $res = $this->tgBot->answerMsg([
            'text' => strtr(self::templates['completeMaterial'], $this->getCompleteMaterialPlaceholders()),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($buttons, [
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelCompleteMaterial']
                    ]
                ])
            )
        ]);
        $msgId = Arr::get($res, 'result.message_id');
        $this->cache->put($this->getCacheKey('edit_msg_id'), $msgId);
    }

    public function completeMaterialCallback($id): void
    {
        $this->cache->put($this->getCacheKey('actualMaterialId'), $id);
        $this->cache->put($this->getCacheKey('state'), 'completeMaterialQty');

        $actualMaterial = $this->getActualMaterial($id);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['completeMaterialInput'], [
                '{errorMsg}' => '',
                '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
                '{product}' => $actualMaterial->product->name,
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

    public function completeMaterialText(): void
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
                    '{product}' => $actualMaterial->product->name,
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

        if ($quantity > $actualMaterial->max_quantity) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['completeMaterialInput'], [
                    '{errorMsg}' => "<i>Quantity cannot be greater than {$actualMaterial->max_quantity} {$actualMaterial->product->category?->measure_unit?->getLabel()}</i>",
                    '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
                    '{product}' => $actualMaterial->product->name,
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

        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];
        $formData[$actualMaterial->id] = $quantity;
        $this->cache->put($this->getCacheKey('completeMaterial'), json_encode($formData));

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['completeMaterialInput'], [
                '{errorMsg}' => '',
                '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
                '{product}' => $actualMaterial->product->name,
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

    /**
     * @throws GuzzleException
     */
    public function cancelCompleteMaterial(): void
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

    protected function getCompleteMaterialPlaceholders(): array
    {
        $prodOrder = $this->user->workStation->prodOrder;

        $step = $this->getStep();
        $expectedMaterials = $step->expectedItems
            ->map(function (ProdOrderStepProduct $item) {
                return "{$item->product->name} ({$item->quantity} {$item->product->category?->measure_unit?->getLabel()})";
            })
            ->implode("\n");

        $requiredMaterials = $step->requiredItems
            ->map(function (ProdOrderStepProduct $item) {
                return "{$item->product->name} ({$item->quantity} {$item->product->category?->measure_unit?->getLabel()})";
            })
            ->implode("\n");

        return [
            '{prodOrder}' => "ProdOrder-$prodOrder->id",
            '{product}' => $prodOrder->product->name . " ({$prodOrder->quantity} {$prodOrder->product->category?->measure_unit?->getLabel()})",
            '{expectedMaterials}' => $expectedMaterials,
            '{requiredMaterials}' => $requiredMaterials,
            '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
        ];
    }

    protected function getActualMaterialsStr(): string
    {
        $step = $this->getStep();

        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];
        $actualMaterials = $step->actualItems()->get();
        $actualMaterialsStr = "";
        foreach ($actualMaterials as $actualMaterial) {
            $properQty = Arr::get($formData, $actualMaterial->id);
            if (empty($properQty)) {
                $properQty = $actualMaterial->quantity;
            }
            $actualMaterialsStr .= "{$actualMaterial->product->name}: $properQty {$actualMaterial->product->category?->measure_unit?->getLabel()}\n";
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
        $actualMaterial = $this->getStep()->actualItems()->find($id);
        return $actualMaterial;
    }

    protected function getActualMaterialFromCache(): ProdOrderStepProduct
    {
        $id = $this->cache->get($this->getCacheKey('actualMaterialId'));

        /** @var ProdOrderStepProduct $actualMaterial */
        $actualMaterial = $this->getStep()->actualItems()->find($id);
        return $actualMaterial;
    }

    protected function getActualMaterialButtons(): array
    {
        $actualMaterials = $this->getStep()->actualItems()->get();
        $buttons = [];
        foreach ($actualMaterials as $actualMaterial) {
            $buttons[][] = [
                'text' => $actualMaterial->product->name,
                'callback_data' => "completeMaterial_{$actualMaterial->id}",
            ];
        }
        return $buttons;
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
