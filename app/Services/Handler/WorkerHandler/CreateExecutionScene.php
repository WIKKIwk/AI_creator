<?php

namespace App\Services\Handler\WorkerHandler;

use App\Enums\ProdOrderStepStatus;
use App\Listeners\StepExecutionNotification;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class CreateExecutionScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;

    public const states = [
        'worker_selectMaterial' => 'worker_selectMaterial',
        'worker_inputUsedQty' => 'worker_inputUsedQty',
        'worker_inputOutputQty' => 'worker_inputOutputQty',
        'worker_inputNotes' => 'worker_inputNotes',
    ];

    public ProdOrderService $prodOrderService;

    public function __construct(public WorkerHandler $handler)
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

    public function handleScene($params = []): void
    {
        if (!$this->handler->user->workStation->prodOrder) {
            $this->handler->resetCache();
            $this->tgBot->answerCbQuery(['text' => __('telegram.no_prodorder_assigned')]);
            return;
        }

        $step = $this->getStep();
        if ($step->status == ProdOrderStepStatus::Completed) {
            $this->handler->resetCache();
            $this->tgBot->answerCbQuery(['text' => __('telegram.work_already_completed')]);
            return;
        }

        $this->tgBot->answerCbQuery();
        $this->handler->setState(self::states['worker_selectMaterial']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getExecutionPrompt(__('telegram.choose_material_to_execute')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']]
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
                        __('telegram.choose_material_to_execute'),
                        '<i>❌ ' . __('telegram.material_already_added') . '</i>'
                    ),
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard(
                        array_merge($this->getMaterialsKb(), [
                            [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']]
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
            'text' => $this->getExecutionPrompt(__('telegram.enter_quantity')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']]
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
                'text' => $this->getExecutionPrompt(
                    __('telegram.enter_quantity'),
                    "<i>" . __('telegram.invalid_quantity') . "</i>"
                ),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']],
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
            'text' => $this->getExecutionPrompt(__('telegram.material_added_next_or_finish')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($this->getMaterialsKb(), [
                    [
                        ['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution'],
                        ['text' => __('telegram.finish_materials'), 'callback_data' => 'finishMaterials']
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
            'text' => $this->getExecutionPrompt(__('telegram.enter_output_quantity')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']]
            ])
        ]);
    }

    public function inputOutputQty($quantity): void
    {
        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getExecutionPrompt(
                    __('telegram.enter_output_quantity'),
                    "<i>" . __('telegram.invalid_quantity') . "</i>"
                ),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']],
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
            'text' => $this->getExecutionPrompt(__('telegram.input_additional_notes')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution']],
            ]),
        ]);
    }

    public function inputNotes(string $text): void
    {
        $form = $this->handler->getCacheArray('executionForm');
        $form['notes'] = $text === '-' ? '' : $text;
        $this->handler->setCacheArray('executionForm', $form);

        // Save or forward to next logic
        $this->handler->forgetCache('state');

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getExecutionPrompt(__('telegram.execution_form_completed')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution'],
                    ['text' => __('telegram.save'), 'callback_data' => 'saveExecution'],
                ]
            ]),
        ]);
    }

    public function saveExecution(): void
    {
        $form = $this->handler->getCacheArray('executionForm');

        try {
            dump($form);
            $execution = $this->prodOrderService->createExecutionByForm($this->getStep(), $form);

            $this->tgBot->answerCbQuery(['text' => __('telegram.execution_saved_success')]);

            $message = "<b>" . __('telegram.execution_saved_success') . "</b>\n\n";
            $message .= TgMessageService::getExecutionMsg($execution);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            $this->cancelExecution(false);

            $this->handler->sendMainMenu();
        } catch (Throwable $e) {
            dump($e->getMessage(), $e->getLine(), $e->getFile());
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getExecutionPrompt('', '<i>❌ ' . $e->getMessage() . '</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => __('telegram.cancel'), 'callback_data' => 'cancelExecution'],
                        ['text' => __('telegram.save_again'), 'callback_data' => 'saveExecution'],
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
            $this->tgBot->answerCbQuery(['text' => __('telegram.operation_cancelled')]);
            $this->handler->sendMainMenu(true);
        }
    }

    public function getExecutionPrompt(string $prompt, $errorMsg = null): string
    {
        return strtr($this->handler::templates['createExecutionForm'], [
            '{errorMsg}' => $errorMsg ?? '',
            '{details}' => $this->getExecutionDetails(),
            '{prompt}' => $prompt,
        ]);
    }

    public function getExecutionDetails(): string
    {
        $step = $this->getStep();
        $form = $this->handler->getCacheArray('executionForm');

        $materials = $form['materials'] ?? [];
        $outputQty = $form['output_quantity'] ?? 0;
        $notes = $form['notes'] ?? '-';

        $result = "<b>" . __('telegram.execution_details') . "</b>\n\n";

        if (!empty($materials)) {
            $result .= "<b>" . __('telegram.used_materials') . "</b>\n";
            foreach ($materials as $index => $materialItem) {
                $index++;
                /** @var Product $product */
                $product = Product::query()->find($materialItem['product_id']);
                $result .= "$index) <b>$product->catName</b>: {$materialItem['used_quantity']} {$product->getMeasureUnit()->getLabel()}\n";
            }
            $result .= "\n";
        }

        $result .= __('telegram.output_product') . ": <b>{$step->outputProduct->catName}</b>\n";
        $result .= __('telegram.output_quantity') . ": <b>$outputQty {$step->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
        $result .= __('telegram.notes') . ": <i>$notes</i>\n";

        return $result;
    }

    public function getStep(): ?ProdOrderStep
    {
        $user = $this->handler->user;

        /** @var ProdOrderStep $step */
        $step = $user->workStation->prodOrder->steps()
            ->where('work_station_id', $user->work_station_id)
            ->first();

        return $step;
    }

    public function getMaterialsKb(): array
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
