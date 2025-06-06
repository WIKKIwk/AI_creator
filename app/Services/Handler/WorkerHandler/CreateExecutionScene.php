<?php

namespace App\Services\Handler\WorkerHandler;

use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class CreateExecutionScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected const states = [
        'worker_selectMaterial' => 'worker_selectMaterial',
        'worker_inputUsedQty' => 'worker_inputUsedQty',
        'worker_inputOutputQty' => 'worker_inputOutputQty',
        'worker_inputNotes' => 'worker_inputNotes',
    ];

    protected ProdOrderService $prodOrderService;

    public function __construct(protected WorkerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleText(string $text): void
    {
        $activeState = $this->handler->getState();
        switch ($activeState) {
            case self::states['worker_inputUsedQty']:
                $this->inputUsedQty($text);
                return;
            case self::states['worker_inputOutputQty']:
                $this->inputOutputQty($text);
                return;
            case self::states['worker_inputNotes']:
                $this->inputNotes($text);
                return;
        }

        if ($activeState) {
            $this->tgBot->rmLastMsg();
            return;
        }
        $this->handler->sendMainMenu();
    }

    public function handleScene(): void
    {
        if (!$this->handler->user->workStation->prodOrder) {
            $this->tgBot->answerCbQuery(['text' => 'No production order assigned to your work station.']);
            return;
        }

        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->tgBot->answerCbQuery(['text' => 'Work is already completed.']);
            return;
        }

        $this->tgBot->answerCbQuery();
        $this->handler->setState(self::states['worker_selectMaterial']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getExecutionPrompt('Choose the material to execute:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']]
                ])
            )
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function selectMaterial($materialId): void
    {
        $this->tgBot->answerCbQuery();

        /** @var ProdOrderStepProduct $material */
        $material = $this->getStep()->materials()->find($materialId);

        $form = $this->handler->getCacheArray('executionForm');
        $materials = $form['materials'] ?? [];

        // Check if product already exists in the order
        foreach ($materials as $materialItem) {
            if ($materialItem['product_id'] == $material->product_id) {
                $this->tgBot->sendRequestAsync('editMessageText', [
                    'chat_id' => $this->tgBot->chatId,
                    'message_id' => $this->tgBot->getMessageId(),
                    'text' => $this->getExecutionPrompt(
                        'Choose the material to execute:',
                        '<i>❌ This material is already added.</i>'
                    ),
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard(
                        array_merge($this->getMaterialsKb(), [
                            [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']]
                        ])
                    )
                ]);
                return;
            }
        }

        // Add product to the order
        $materials[] = ['product_id' => $material->product_id, 'used_quantity' => 0];
        $form['materials'] = $materials;
        $this->handler->setCacheArray('executionForm', $form);

        $this->handler->setState(self::states['worker_inputUsedQty']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getExecutionPrompt("🔢 Enter quantity:"),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']]
            ])
        ]);
    }

    public function inputUsedQty($quantity): void
    {
        // check quantity is integer
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getExecutionPrompt("🔢 Enter quantity:", '<i>❌ Invalid quantity.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']],
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('executionForm');
        $materials = $form['materials'] ?? [];
        $lastMaterialIndex = count($materials) - 1;

        // Update last product's quantity
        if (isset($materials[$lastMaterialIndex])) {
            $materials[$lastMaterialIndex]['used_quantity'] = (float)$quantity;
            $form['materials'] = $materials;
            $this->handler->setCacheArray('executionForm', $form);
        }

        $this->handler->setState(self::states['worker_inputOutputQty']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getExecutionPrompt('✅ Material added! Choose next one or finish:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution'],
                        ['text' => '✅ Finish Materials', 'callback_data' => 'finishMaterials']
                    ]
                ])
            )
        ]);
    }

    public function finishMaterials(): void
    {
        $this->handler->setState(self::states['worker_inputOutputQty']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getExecutionPrompt('Enter output quantity:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']]
            ])
        ]);
    }

    public function inputOutputQty($quantity): void
    {
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getExecutionPrompt('Enter output quantity:', '<i>❌ Invalid quantity.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']],
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('executionForm');
        $form['output_quantity'] = $quantity;
        $this->handler->setCacheArray('executionForm', $form);

        $this->handler->setState(self::states['worker_inputNotes']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getExecutionPrompt('📄 Please enter any additional notes:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution']],
            ]),
        ]);
    }

    protected function inputNotes(string $text): void
    {
        $form = $this->handler->getCacheArray('executionForm');
        $form['notes'] = $text === '-' ? '' : $text;
        $this->handler->setCacheArray('executionForm', $form);

        // Save or forward to next logic
        $this->handler->forgetCache('state');

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getExecutionPrompt('✅ Execution form complete.'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution'],
                    ['text' => '✅ Save', 'callback_data' => 'saveExecution'],
                ]
            ]),
        ]);
    }

    public function saveExecution(): void
    {
        $form = $this->handler->getCacheArray('executionForm');

        try {
            $this->prodOrderService->createExecutionByForm($this->getStep(), $form);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getExecutionPrompt('✅ Execution saved successfully!'),
                'parse_mode' => 'HTML'
            ]);

            $this->cancelExecution(false);

            $this->tgBot->answerCbQuery(['text' => '✅ Execution saved successfully!']);

            $this->handler->sendMainMenu();
        } catch (Throwable $e) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getExecutionPrompt('', '<i>❌ ' . $e->getMessage() . '</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelExecution'],
                        ['text' => '✅ Save again', 'callback_data' => 'saveExecution'],
                    ]
                ]),
            ]);
        }
    }

    public function cancelExecution($withResponse = true): void
    {
        $this->handler->forgetCache('executionForm');
        $this->handler->forgetCache('state');
        $this->handler->forgetCache('edit_msg_id');

        if ($withResponse) {
            $this->tgBot->answerCbQuery(['text' => 'Operation cancelled.']);
            $this->tgBot->sendRequestAsync('deleteMessage', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
            ]);
            $this->handler->sendMainMenu();
        }
    }

    protected function getExecutionPrompt(string $prompt, $errorMsg = null): string
    {
        return strtr($this->handler::templates['createExecutionForm'], [
            '{errorMsg}' => $errorMsg ?? '',
            '{details}' => $this->getExecutionDetails(),
            '{prompt}' => $prompt,
        ]);
    }

    protected function getExecutionDetails(): string
    {
        $step = $this->getStep();
        $form = $this->handler->getCacheArray('executionForm');

        $materials = $form['materials'] ?? [];
        $outputQty = $form['output_quantity'] ?? 0;
        $notes = $form['notes'] ?? '-';

        $result = "<b>Execution details</b>\n\n";

        if (!empty($materials)) {
            $result .= "<b>Used materials:</b>\n";
            foreach ($materials as $index => $materialItem) {
                $index++;
                /** @var Product $product */
                $product = Product::query()->find($materialItem['product_id']);
                $result .= "$index) <b>$product->catName</b>: {$materialItem['used_quantity']} {$product->getMeasureUnit()->getLabel()}\n";
            }
            $result .= "\n";
        }
        $result .= "Output product: <b>{$step->outputProduct->catName}</b>\n";
        $result .= "Output quantity: <b>$outputQty {$step->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
        $result .= "Notes: <i>$notes</i>\n";

        return $result;
    }

    protected function getStep(): ?ProdOrderStep
    {
        $user = $this->handler->user;

        /** @var ProdOrderStep $step */
        $step = $user->workStation->prodOrder->steps()
            ->where('work_station_id', $user->work_station_id)
            ->first();

        return $step;
    }

    protected function getMaterialsKb(): array
    {
        /** @var Collection<ProdOrderStepProduct> $actualMaterials */
        $actualMaterials = $this->getStep()->materials()->get();
        $buttons = [];
        foreach ($actualMaterials as $actualMaterial) {
            $buttons[][] = [
                'text' => $actualMaterial->product->catName,
                'callback_data' => "selectMaterial:$actualMaterial->id",
            ];
        }
        return $buttons;
    }
}
