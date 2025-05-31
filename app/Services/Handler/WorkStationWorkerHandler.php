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
        'inputOutputQty' => 'inputOutputQty',
        'inputNotes' => 'inputNotes',
    ];

    protected const templates = [
        'addExecution' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>
Progress: <b>{progress}</b>

Expected output product: <b>{expectedMaterial}</b>
Produced output product: <b>{producedMaterial}</b>

Using materials:
{usingMaterials}
Choose the material to execute:
HTML,

        'addExecutionForm' => <<<HTML
{errorMsg}

{executionDetails}
{prompt}
HTML,
    ];

    protected ProdOrderService $prodOrderService;

    public function __construct(TgBot $tgBot, Cache $cache)
    {
        parent::__construct($tgBot, $cache);

        $this->prodOrderService = app(ProdOrderService::class);
    }

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
        $activeState = $this->getState();;
        if ($activeState === self::states['addExecutionQty']) {
            $this->addExecutionQtyText($text);
            return;
        }

        if ($activeState === self::states['inputOutputQty']) {
            $this->inputOutputQtyText($text);
            return;
        }

        if ($activeState === self::states['inputNotes']) {
            $this->inputNotesText($text);
            return;
        }

        $this->sendMainMenu();
    }

    public function addExecution(): void
    {
        if (!$this->user->workStation->prodOrder) {
            $this->tgBot->answerCbQuery(['text' => 'No production order assigned to your work station.']);
            return;
        }

        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->tgBot->answerCbQuery(['text' => "Work is already completed."]);
            return;
        }

        $this->tgBot->answerCbQuery();
        $this->setState(self::states['addExecution']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => strtr(self::templates['addExecution'], $this->getAddExecutionPlaceholders()),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']]
                ])
            )
        ]);
    }

    public function addExecutionMaterialCallback($materialId): void
    {
        $material = $this->getActualMaterial($materialId);

        $this->cache->put($this->getCacheKey('executionMaterial'), $materialId);
        $this->setState(self::states['addExecutionQty']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => strtr(self::templates['addExecutionForm'], [
                '{errorMsg}' => '',
                '{executionDetails}' => $this->getExecutionDetails(),
                '{prompt}' => "🔢 Enter quantity for <b>{$material->product->catName}:</b>",
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']]
            ])
        ]);
        $this->cache->put($this->getCacheKey('edit_msg_id'), $this->tgBot->getMessageId());
    }

    public function addExecutionQtyText($quantity): void
    {
        $materialId = $this->cache->get($this->getCacheKey('executionMaterial'));
        $material = $this->getActualMaterial($materialId);

        // check quantity is integer
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['addExecutionForm'], [
                    '{errorMsg}' => '<i>❌ Invalid quantity. Please enter a valid number.</i>',
                    '{executionDetails}' => $this->getExecutionDetails(),
                    '{prompt}' => "🔢 Enter quantity for <b>{$material->product->catName}:</b>",
                ]),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']],
                ]),
            ]);
            return;
        }

        $form = json_decode($this->cache->get($this->getCacheKey('addExecutionForm')), true) ?? [];
        $form['materials'][$material->product_id] = (float)$quantity;

        $this->cache->put($this->getCacheKey('addExecutionForm'), json_encode($form));
        $this->cache->forget($this->getCacheKey('executionMaterial'));
        $this->setState(self::states['addExecution']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['addExecutionForm'], [
                '{errorMsg}' => '',
                '{executionDetails}' => $this->getExecutionDetails(),
                '{prompt}' => "✅ Material added! Choose next one or finish:",
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution'],
                        ['text' => '✅ Finish Materials', 'callback_data' => 'finishMaterials']
                    ]
                ])
            )
        ]);
    }

    public function finishMaterials(): void
    {
        $this->setState(self::states['inputOutputQty']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => strtr(self::templates['addExecutionForm'], [
                '{errorMsg}' => '',
                '{executionDetails}' => $this->getExecutionDetails(),
                '{prompt}' => "📦 Please enter the output quantity:",
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']]
            ])
        ]);
    }

    public function inputOutputQtyText($quantity): void
    {
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['addExecutionForm'], [
                    '{errorMsg}' => '<i>❌ Invalid quantity. Please enter a valid number.</i>',
                    '{executionDetails}' => $this->getExecutionDetails(),
                    '{prompt}' => "📦 Please enter the output quantity:",
                ]),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']],
                ]),
            ]);
            return;
        }

        $form = json_decode($this->cache->get($this->getCacheKey('addExecutionForm')), true) ?? [];
        $form['output_qty'] = (int)$quantity;
        $this->cache->put($this->getCacheKey('addExecutionForm'), json_encode($form));

        $this->setState(self::states['inputNotes']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['addExecutionForm'], [
                '{errorMsg}' => '',
                '{executionDetails}' => $this->getExecutionDetails(),
                '{prompt}' => "📄 Please enter any additional notes:",
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution']],
            ]),
        ]);
    }

    protected function inputNotesText(string $text): void
    {
        $form = json_decode($this->cache->get($this->getCacheKey('addExecutionForm')), true) ?? [];
        $form['notes'] = $text === '-' ? '' : $text;
        $this->cache->put($this->getCacheKey('addExecutionForm'), json_encode($form));

        // Save or forward to next logic
        $this->cache->forget($this->getCacheKey('state'));

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['addExecutionForm'], [
                '{errorMsg}' => '',
                '{executionDetails}' => $this->getExecutionDetails(),
                '{prompt}' => "✅ Execution form complete.",
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution'],
                    ['text' => '✅ Save', 'callback_data' => 'saveAddExecution'],
                ]
            ]),
        ]);
    }

    public function saveAddExecution(): void
    {
        $form = json_decode($this->cache->get($this->getCacheKey('addExecutionForm')), true) ?? [];
        $materials = $form['materials'] ?? [];
        $resultMaterials = [];
        foreach ($materials as $productId => $qty) {
            $resultMaterials[] = ['product_id' => $productId, 'used_quantity' => $qty];
        }

        $resultForm = [
            'materials' => $resultMaterials,
            'output_quantity' => $form['output_qty'] ?? 0,
            'notes' => $form['notes'] ?? '',
        ];

        try {
            $this->prodOrderService->createExecutionByForm($this->getStep(), $resultForm);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['addExecutionForm'], [
                    '{errorMsg}' => '',
                    '{executionDetails}' => $this->getExecutionDetails(),
                    '{prompt}' => "✅ Execution saved successfully!",
                ]),
                'parse_mode' => 'HTML'
            ]);

            $this->cancelAddExecution(false);

            $this->tgBot->answerCbQuery(['text' => '✅ Execution saved successfully!']);

            $this->sendMainMenu();

        } catch (Throwable $e) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
                'text' => strtr(self::templates['addExecutionForm'], [
                    '{errorMsg}' => '<i>❌ Error saving execution: ' . $e->getMessage() . '</i>',
                    '{executionDetails}' => $this->getExecutionDetails(),
                    '{prompt}' => '',
                ]),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelAddExecution'],
                        ['text' => '✅ Save again', 'callback_data' => 'saveAddExecution'],
                    ]
                ]),
            ]);
        }
    }

    public function cancelAddExecution($withResponse = true): void
    {
        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('executionMaterial'));
        $this->cache->forget($this->getCacheKey('addExecutionForm'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        if ($withResponse) {
            $this->tgBot->answerCbQuery([
                'text' => "Operation cancelled.",
                'reply_markup' => $this->getMainKb(),
            ]);
            $this->tgBot->sendRequestAsync('deleteMessage', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
            ]);
            $this->sendMainMenu();
        }
    }

    protected function getExecutionDetails(): string
    {
        $step = $this->getStep();
        $form = json_decode($this->cache->get($this->getCacheKey('addExecutionForm')), true) ?? [];

        $result = "<b>Execution details:</b>\n\n";
        $materials = $form['materials'] ?? [];
        $outputQty = $form['output_qty'] ?? 0;
        $notes = $form['notes'] ?? '-';

        if (!empty($materials)) {
            $result .= "<b>Used materials:</b>\n";
            foreach ($materials as $productId => $usedQty) {
                /** @var Product $product */
                $product = Product::query()->find($productId);
                $result .= "<b>$product->catName</b>: {$usedQty} {$product->getMeasureUnit()->getLabel()}\n";
            }
        }
        $result .= "\nOutput product: <b>{$step->outputProduct->catName}</b>\n";
        $result .= "Output quantity: <b>$outputQty {$step->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
        $result .= "Notes: <b>$notes</b>\n";

        return $result;
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

        $outputMeasureUnit =  $prodOrder->product->category?->measure_unit?->getLabel();

        return [
            '{prodOrder}' => $this->user->workStation->prodOrder->number,
            '{product}' => $prodOrder->product->catName . " ({$prodOrder->quantity} {$prodOrder->product->category?->measure_unit?->getLabel()})",
            '{progress}' => "{$prodOrder->getProgress()}%",
            '{expectedMaterial}' => "{$step->outputProduct->catName} ($step->expected_quantity $outputMeasureUnit)",
            '{producedMaterial}' => "{$step->outputProduct->catName} ($step->output_quantity $outputMeasureUnit)",
            '{usingMaterials}' => $usingMaterials,
        ];
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
                'callback_data' => "addExecutionMaterial:$actualMaterial->id",
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
