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
        dump("Inline state: $state, query: $q");
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
        $this->editForm('Select workstation:', [
            [['text' => '🔍 Search station', 'switch_inline_query_current_chat' => '']],
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
        $this->editForm('Select output product:', [
            [['text' => '🔍 Search product', 'switch_inline_query_current_chat' => '']],
            [['text' => 'Skip output', 'callback_data' => 'skipOutput']],
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

        $this->editForm('Input measure unit (e.g., kg, pcs):', $buttons);
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
        $this->editForm('Expected output quantity:');
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
        $this->editForm('Select material to add:', [
            [['text' => '🔍 Search material', 'switch_inline_query_current_chat' => '']],
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
        $this->editForm('Input quantity for selected material:');
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

        $this->editForm('Confirm step options:', [
            [['text' => ($form['is_last'] ?? false) ? '✅ Is Last' : '☐ Is Last', 'callback_data' => 'toggleIsLast']],
            [['text' => '➕ Add material', 'callback_data' => 'addAnotherMaterial']],
            [
                ['text' => '🚫 Cancel', 'callback_data' => 'cancelStep'],
                ['text' => '✅ Save', 'callback_data' => 'saveStep']
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

            $this->tgBot->answerCbQuery(['text' => '✅ Step saved successfully.'], true);

            $message = "<b>✅ Step saved successfully.</b>\n\n";
            $message .= TgMessageService::getProdTemplateMsg($prodTemplate);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '➕ Create step', 'callback_data' => "createProdTemplateStep:$prodTemplate->id"]],
                    [['text' => '🔙 Back', 'callback_data' => 'backMainMenu']],
                ]),
            ]);

        } catch (\Throwable $th) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getStepPrompt(error: "❌ Error saving step. {$th->getMessage()}"),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => ($form['is_last'] ?? false) ? '✅ Is Last' : '☐ Is Last', 'callback_data' => 'toggleIsLast']],
                    [['text' => '➕ Add material', 'callback_data' => 'addAnotherMaterial']],
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelStep'],
                        ['text' => '✅ Save again', 'callback_data' => 'saveStep']
                    ]
                ]),
            ]);
            return;
        }
    }

    public function cancelStep(): void
    {
        $this->tgBot->answerCbQuery(['text' => '❌ Step cancelled.'], true);
        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getStepPrompt(error: "<i>❌ Step cancelled.</i>"),
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
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelStep']]
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

        $result = "<b>Step:</b>\n";
        $result .= "Workstation: <b>$workStationName</b>\n";
        $result .= "Output: <b>$outputProductName</b>\n";
        $result .= "Unit: <b>$unitName</b>\n";
        $result .= "Expected quantity: <b>$expectedQuantity</b>\n";
        $result .= "Last step: " . (!empty($form['is_last']) ? '✅' : '❌') . "\n";

        $result .= "\nMaterials:\n";
        foreach ($form['materials'] ?? [] as $index => $material) {
            $index++;

            /** @var Product $product */
            $product = Product::query()->find($material['product_id'] ?? null);
            $productName = $product?->catName ?? '-';

            $result .= "$index) <b>$productName</b>: <b>{$material['required_quantity']} {$product->getMeasureUnit()->getLabel()}</b>\n";
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
