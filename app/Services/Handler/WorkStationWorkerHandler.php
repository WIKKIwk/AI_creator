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
use Throwable;

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
<b>{expectedMaterial}</b>

Actual used materials:
<b>{actualUsedMaterials}</b>

Choose the actual material to complete:
HTML,

        'completeMaterialInput' => <<<HTML
{errorMsg}

Actual used materials:
<b>{actualUsedMaterials}</b>

Input actual used quantity for <b>{product}</b>. Or choose another material to complete:
HTML,

        'completeWork' => <<<HTML
{errorMsg}

Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>

Expected materials:
<b>{expectedMaterial}</b>

Output materials:
<b>{outputMaterial}</b>

Actual used materials:
<b>{actualUsedMaterials}</b>

Input output quantity for <b>{expectedMaterialName}</b>:
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

        if ($activeState === self::states['completeMaterialQty']) {
            $this->completeMaterialText();
            return;
        } elseif ($activeState === self::states['completeWork']) {
            $this->completeWorkText();
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
        if (!$this->user->workStation->prodOrder) {
            $this->tgBot->answerCbQuery(['text' => "No production order assigned to your work station."]);
            return;
        }

        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->tgBot->answerCbQuery(['text' => "Work is already completed."]);
            return;
        }

        $prodOrder = $this->user->workStation->prodOrder;

        $this->tgBot->answerCbQuery();
        $this->cache->put($this->getCacheKey('state'), self::states['completeWork']);

        $res = $this->tgBot->answerMsg([
            'text' => strtr(self::templates['completeWork'], [
                '{prodOrder}' => $this->user->workStation->prodOrder->number,
                '{product}' => $prodOrder->product->catName . " ({$prodOrder->quantity} {$prodOrder->product->category?->measure_unit?->getLabel()})",
                '{expectedMaterial}' => "{$step->outputProduct->catName} ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                '{outputMaterial}' => "{$step->outputProduct->catName} ({$step->output_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                '{expectedMaterialName}' => $step->outputProduct->catName,
                '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
                '{errorMsg}' => '',
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

    public function completeWorkText(): void
    {
        $step = $this->getStep();
        $quantity = $this->tgBot->getText();

        $formData = json_decode($this->cache->get($this->getCacheKey('completeWork')), true) ?? [];
        $outputQuantity = Arr::get($formData, 'output_quantity', $step->output_quantity ?? 0);

        // check quantity is integer
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['completeWork'], [
                    '{errorMsg}' => "<i>Invalid quantity. Please enter a valid number.</i>",
                    '{prodOrder}' => $this->user->workStation->prodOrder->number,
                    '{product}' => $step->outputProduct->catName . " ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                    '{expectedMaterial}' => "{$step->outputProduct->catName} ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                    '{outputMaterial}' => "{$step->outputProduct->catName} ({$outputQuantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                    '{expectedMaterialName}' => $step->outputProduct->catName,
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
            return;
        }

        $formData = json_decode($this->cache->get($this->getCacheKey('completeWork')), true) ?? [];
        $formData['output_quantity'] = $quantity;
        $this->cache->put($this->getCacheKey('completeWork'), json_encode($formData));

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['completeWork'], [
                '{errorMsg}' => '',
                '{prodOrder}' => $this->user->workStation->prodOrder->number,
                '{product}' => $step->outputProduct->catName . " ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                '{expectedMaterial}' => "{$step->outputProduct->catName} ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                '{outputMaterial}' => "{$step->outputProduct->catName} ({$quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
                '{expectedMaterialName}' => $step->outputProduct->catName,
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
        $formData = json_decode($this->cache->get($this->getCacheKey('completeWork')), true) ?? [];
        $outputQuantity = Arr::get($formData, 'output_quantity', $step->output_quantity ?? 0);

        try {
            $this->prodOrderService->completeWork($step, $outputQuantity);
        } catch (Throwable $e) {
            $this->tgBot->answerCbQuery([
                'text' => "Error: {$e->getMessage()}",
                'show_alert' => true,
            ]);
            return;
        }

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
        $this->cache->put($this->getCacheKey('state'), self::states['completeMaterial']);

        /** @var Collection<ProdOrderStepProduct> $actualMaterials */
        $actualMaterials = $this->getStep()->actualItems()->get();
        $buttons = [];
        foreach ($actualMaterials as $actualMaterial) {
            $buttons[][] = [
                'text' => $actualMaterial->product->catName,
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

        if ($quantity > $actualMaterial->available_quantity) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['completeMaterialInput'], [
                    '{errorMsg}' => "<i>Quantity cannot be greater than {$actualMaterial->available_quantity} {$actualMaterial->product->category?->measure_unit?->getLabel()}</i>",
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

        $formData = json_decode($this->cache->get($this->getCacheKey('completeMaterial')), true) ?? [];
        $formData[$actualMaterial->id] = $quantity;
        $this->cache->put($this->getCacheKey('completeMaterial'), json_encode($formData));

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
        $requiredMaterials = $step->requiredItems
            ->map(function (ProdOrderStepProduct $item) {
                return "{$item->product->catName} ({$item->required_quantity} {$item->product->category?->measure_unit?->getLabel()})";
            })
            ->implode("\n");

        return [
            '{prodOrder}' => $this->user->workStation->prodOrder->number,
            '{product}' => $prodOrder->product->catName . " ({$prodOrder->quantity} {$prodOrder->product->category?->measure_unit?->getLabel()})",
            '{expectedMaterial}' => "{$step->outputProduct->catName} ({$step->expected_quantity} {$step->outputProduct->category?->measure_unit?->getLabel()})",
            '{requiredMaterials}' => $requiredMaterials,
            '{actualUsedMaterials}' => $this->getActualMaterialsStr(),
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
        /** @var Collection<ProdOrderStepProduct> $actualMaterials */
        $actualMaterials = $this->getStep()->actualItems()->get();
        $buttons = [];
        foreach ($actualMaterials as $actualMaterial) {
            $buttons[][] = [
                'text' => $actualMaterial->product->catName,
                'callback_data' => "completeMaterial_{$actualMaterial->id}",
            ];
        }
        return $buttons;
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => '🛠 Use materials', 'callback_data' => 'completeMaterial']
            ],
            [
                ['text' => '✅ Complete work', 'callback_data' => 'completeWork']
            ],
        ]);
    }
}
