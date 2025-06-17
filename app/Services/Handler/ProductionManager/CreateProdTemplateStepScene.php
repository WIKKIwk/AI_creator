<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\MeasureUnit;
use App\Listeners\ProdOrderNotification;
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\Product;
use App\Models\WorkStation;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\ProductService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Throwable;

class CreateProdTemplateStepScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;

    protected ProdOrderService $prodOrderService;

    public const states = [
        'step_selectWorkstation' => 'step_selectWorkstation',
        'step_selectOutput' => 'step_selectOutput',
        'step_selectUnit' => 'step_selectUnit',
        'step_inputQuantity' => 'step_inputQuantity',
        'step_addMaterial' => 'step_addMaterial',
        'step_inputMaterialQty' => 'step_inputMaterialQty',
        'step_confirmOptions' => 'step_confirmOptions',
    ];

    public function __construct(public ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleScene(array $params = []): void
    {
        $this->handler->setState(self::states['step_selectWorkstation']);
        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());

        $this->handler->setCacheArray('prodTemplateStep', [
            'prodTemplateId' => $params[0] ?? null,
        ]);
        $this->askWorkstation();
    }

    public function handleText($text): void
    {
        $state = $this->handler->getState();
        dump("HandleText: $text, State: $state");
        switch ($state) {
            case self::states['step_inputQuantity']:
                $this->setExpectedQuantity($text);
                return;
            case self::states['step_inputMaterialQty']:
                $this->setMaterialQuantity($text);
                return;
        }

        if (str_starts_with($text, '/select_workstation ')) {
            $workstationId = trim(str_replace('/select_workstation ', '', $text));
            $this->selectWorkstation($workstationId);
            return;
        }

        if (str_starts_with($text, '/select_output ')) {
            $outputId = trim(str_replace('/select_output ', '', $text));
            $this->selectOutputProduct($outputId);
            return;
        }

        if (str_starts_with($text, '/select_material ')) {
            $materialId = trim(str_replace('/select_material ', '', $text));
            $this->selectMaterial($materialId);
            return;
        }

        $this->tgBot->rmLastMsg();
    }

    public function skipOutput(): void
    {
        $this->selectWorkstation(null);
    }

    public function handleInlineQuery($query): void
    {
        $state = $this->handler->getState();
        $q = $query['query'] ?? '';

        match ($state) {
            self::states['step_selectWorkstation'] => $this->answerInlineWorkstation($query['id'], $q),
            self::states['step_selectOutput'] => $this->answerInlineProduct($query['id'], $q, '/select_output '),
            self::states['step_addMaterial'] => $this->answerInlineProduct($query['id'], $q, '/select_material '),
            default => null,
        };
    }

    public function addAnotherMaterial(): void
    {
        $this->askMaterial();
    }

    public function askWorkstation(): void
    {
        $this->editForm(__('telegram.select_workstation'), [
            [['text' => __('telegram.search_workstation'), 'switch_inline_query_current_chat' => '']],
        ]);
    }

    public function selectWorkstation($id): void
    {
        if (!$id) {
            $this->askUnit();
            return;
        }

        $form = $this->handler->getCacheArray('prodTemplateStep');
        $form['work_station_id'] = $id;
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->askOutputProduct();
    }

    public function askOutputProduct(): void
    {
        $this->handler->setState(self::states['step_selectOutput']);
        $this->editForm(__('telegram.select_output_product'), [
            [['text' => __('telegram.search_product'), 'switch_inline_query_current_chat' => '']],
            [['text' => __('telegram.skip_output'), 'callback_data' => 'skipOutput']],
        ]);
    }

    public function selectOutputProduct($id): void
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        $form['output_product_id'] = $id;
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->askUnit();
    }

    public function askUnit(): void
    {
        $this->handler->setState(self::states['step_selectUnit']);
        $form = $this->handler->getCacheArray('prodTemplateStep');

        $workStationId = $form['work_station_id'] ?? null;
        /** @var WorkStation $workStation */
        $workStation = WorkStation::query()->findOrFail($workStationId);

        $buttons = [];
        /** @var MeasureUnit $unit */
        foreach ($workStation->getMeasureUnits() as $unit) {
            $buttons[] = [['text' => $unit->getLabel(), 'callback_data' => "setUnit:$unit->value"]];
        }

        $this->editForm(__('telegram.input_unit'), $buttons);
    }

    public function setUnit($unit): void
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        $form['unit'] = $unit;
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->askExpectedQuantity();
    }

    public function askExpectedQuantity(): void
    {
        $this->handler->setState(self::states['step_inputQuantity']);
        $this->editForm(__('telegram.expected_output_quantity'));
    }

    public function setExpectedQuantity($qty): void
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        $form['expected_quantity'] = (float)$qty;
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->askMaterial();
    }

    public function askMaterial(): void
    {
        $this->handler->setState(self::states['step_addMaterial']);
        $this->editForm(__('telegram.select_material'), [
            [['text' => __('telegram.search_material'), 'switch_inline_query_current_chat' => '']],
        ]);
    }

    public function selectMaterial($id): void
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        $form['current_material'] = $id;
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->askMaterialQuantity();
    }

    public function askMaterialQuantity(): void
    {
        $this->handler->setState(self::states['step_inputMaterialQty']);
        $this->editForm(__('telegram.input_material_quantity'));
    }

    public function setMaterialQuantity($qty): void
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        $materials = $form['materials'] ?? [];

        $materials[] = ['product_id' => $form['current_material'], 'required_quantity' => (float)$qty];
        unset($form['current_material']);

        $form['materials'] = $materials;
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->confirmStep();
    }

    public function confirmStep(): void
    {
        $this->handler->setState(self::states['step_confirmOptions']);

        $form = $this->handler->getCacheArray('prodTemplateStep');

        $this->editForm(__('telegram.confirm_step_options'), [
            [['text' => ($form['is_last'] ?? false) ? '✅ ' . __('telegram.is_last') : '☐ ' . __('telegram.is_last'), 'callback_data' => 'toggleIsLast']],
            [['text' => __('telegram.add_material'), 'callback_data' => 'addAnotherMaterial']],
            [
                ['text' => __('telegram.cancel'), 'callback_data' => 'cancelStep'],
                ['text' => __('telegram.save'), 'callback_data' => 'saveStep']
            ],
        ], true);
    }

    public function toggleIsLast(): void
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        $form['is_last'] = !($form['is_last'] ?? false);
        $this->handler->setCacheArray('prodTemplateStep', $form);

        $this->confirmStep();
    }

    public function saveStep(): void
    {
        try {
            $prodTemplate = $this->getProdTmp();
            $formUpdated = $this->getFormFields();
            dump($formUpdated);
            $this->prodOrderService->createTmpStepByForm($prodTemplate, $formUpdated);

            $this->handler->forgetCache('prodTemplateStep');
            $this->handler->resetCache();

            $this->tgBot->answerCbQuery(['text' => __('telegram.step_saved')], true);

            $message = "<b>" . __('telegram.step_saved') . "</b>";
            $message .= TgMessageService::getProdTemplateMsg($prodTemplate);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => __('telegram.create_step'), 'callback_data' => "createProdTemplateStep:$prodTemplate->id"]],
                    [['text' => __('telegram.back'), 'callback_data' => 'backMainMenu']],
                ]),
            ]);

        } catch (Throwable $th) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getStepPrompt(error: __('telegram.error_occurred') . ": {$th->getMessage()}"),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => ($form['is_last'] ?? false) ? '✅ ' . __('telegram.is_last') : '☐ ' . __('telegram.is_last'), 'callback_data' => 'toggleIsLast']],
                    [['text' => __('telegram.add_material'), 'callback_data' => 'addAnotherMaterial']],
                    [
                        ['text' => __('telegram.cancel'), 'callback_data' => 'cancelStep'],
                        ['text' => __('telegram.save_again'), 'callback_data' => 'saveStep']
                    ]
                ]),
            ]);
            return;
        }
    }

    public function cancelStep(): void
    {
        $this->tgBot->answerCbQuery(['text' => __('telegram.step_cancelled')], true);
        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getStepPrompt(error: "<i>" . __('telegram.step_cancelled') . "</i>"),
            'parse_mode' => 'HTML',
        ]);

        $this->handler->forgetCache('prodTemplateStep');
        $this->handler->resetCache();
    }

    public function getFormFields(): array
    {
        $prodTemplate = $this->getProdTmp();
        $form = $this->handler->getCacheArray('prodTemplateStep');

        $outputProductId = $form['output_product_id'] ?? null;
        if (!$outputProductId) {
            /** @var ProductService $productService */
            $productService = app(ProductService::class);
            $outputProduct = $productService->createOrGetSemiFinished(
                $prodTemplate,
                $form['work_station_id'] ?? null,
                $form['is_last'] ?? false,
            );
            $form['output_product_id'] = $outputProduct?->id;
        }

        $form['sequence'] = $prodTemplate->steps()->count() + 1;
        return $form;
    }

    public function getProdTmp(): ProdTemplate
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->findOrFail($form['prodTemplateId'] ?? null);
        return $prodTemplate;
    }

    public function editForm(string $prompt, array $markup = [], bool $overwriteMarkup = false): void
    {
        if ($overwriteMarkup) {
            $buttons = $markup;
        } else {
            $buttons = array_merge($markup, [
                [['text' => __('telegram.cancel'), 'callback_data' => 'cancelStep']]
            ]);
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id') ?? $this->tgBot->getMessageId(),
            'text' => $this->getStepPrompt($prompt),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($buttons),
        ]);
    }

    public function getStepPrompt(string $prompt = null, ?string $error = null): string
    {
        $form = $this->handler->getCacheArray('prodTemplateStep');
        return strtr($this->handler::templates['prodTemplateStep'], [
            '{errorMsg}' => $error ?? '',
            '{details}' => $this->getStepDetails($form),
            '{prompt}' => $prompt ?? '-',
        ]);
    }

    public function getStepDetails($form = []): string
    {
        /** @var WorkStation $workStation */
        $workStation = WorkStation::query()->find($form['work_station_id'] ?? null);
        $workStationName = $workStation?->name ?? '-';

        /** @var Product $outputProduct */
        $outputProduct = Product::query()->find($form['output_product_id'] ?? null);
        $outputProductName = $outputProduct?->catName ?? '-';

        $unit = $form['unit'] ?? null;
        $unitName = MeasureUnit::tryFrom($unit)?->getLabel() ?? '-';

        $expectedQuantity = $form['expected_quantity'] ?? '-';

        $result = "<b>" . __('telegram.step') . ":</b>\n";
        $result .= __('telegram.workstation') . ": <b>$workStationName</b>\n";
        $result .= __('telegram.output') . ": <b>$outputProductName</b>\n";
        $result .= __('telegram.unit') . ": <b>$unitName</b>\n";
        $result .= __('telegram.expected_quantity') . ": <b>$expectedQuantity</b>\n";
        $result .= __('telegram.last_step') . ": " . (!empty($form['is_last']) ? '✅' : '❌') . "\n";

        $result .= "\n" . __('telegram.materials') . ":\n";
        foreach ($form['materials'] ?? [] as $index => $material) {
            $index++;

            /** @var Product $product */
            $product = Product::query()->find($material['product_id'] ?? null);
            $productName = $product?->catName ?? '-';
            $unitLabel = $product?->getMeasureUnit()?->getLabel() ?? '';

            $result .= "$index) <b>$productName</b>: <b>{$material['required_quantity']} $unitLabel</b>\n";
        }

        return $result;
    }

    public function answerInlineWorkstation(string $queryId, string $query): void
    {
        $list = WorkStation::query()
            ->where('name', 'ILIKE', "%$query%")->limit(20)
            ->get();

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $queryId,
            'results' => TelegramService::inlineResults($list, 'id', 'name', '/select_workstation '),
            'cache_time' => 0,
        ]);
    }

    public function answerInlineProduct(string $queryId, string $query, string $command): void
    {
        $list = Product::query()->search($query)->limit(20)->get();
        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $queryId,
            'results' => TelegramService::inlineResults($list, 'id', 'catName', $command),
            'cache_time' => 0,
        ]);
    }
}
