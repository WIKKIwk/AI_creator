<?php

namespace App\Services\Handler\ProductionManager;

use App\Listeners\ProdOrderNotification;
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\Product;
use App\Models\WorkStation;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class CreateProdTemplateStepScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    public const states = [
        'step_selectWorkstation' => 'step_selectWorkstation',
        'step_selectOutput' => 'step_selectOutput',
        'step_selectUnit' => 'step_selectUnit',
        'step_inputQuantity' => 'step_inputQuantity',
        'step_addMaterial' => 'step_addMaterial',
        'step_inputMaterialQty' => 'step_inputMaterialQty',
        'step_confirmOptions' => 'step_confirmOptions',
    ];

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;
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

        match ($state) {
            self::states['step_selectUnit'] => $this->setUnit($text),
            self::states['step_inputQuantity'] => $this->setExpectedQuantity($text),
            self::states['step_inputMaterialQty'] => $this->setMaterialQuantity($text),
            default => null,
        };

        if (str_starts_with($text, '/select_workstation ')) {
            $workstationId = trim(str_replace('/select_workstation ', '', $text));
            $this->selectWorkstation($workstationId);
        } elseif (str_starts_with($text, '/select_output ')) {
            $outputId = trim(str_replace('/select_output ', '', $text));
            $this->selectOutputProduct($outputId);
        } elseif (str_starts_with($text, '/select_material ')) {
            $materialId = trim(str_replace('/select_material ', '', $text));
            $this->selectMaterial($materialId);
        } elseif ($state === self::states['step_selectWorkstation']) {
            $this->askWorkstation();
        } elseif ($state === self::states['step_addMaterial']) {
            $this->askMaterial();
        } else {
            $this->editForm('Invalid command or state.', []);
        }
    }

    public function skipOutput(): void
    {
        $this->selectWorkstation(null);
    }

    public function handleInlineQuery($query): void
    {
        $state = $this->handler->getState();
        $q = $query['query'] ?? '';
        dump($state, $q);
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

    protected function askWorkstation(): void
    {
        $this->editForm('Select workstation:', [
            [['text' => '🔍 Search station', 'switch_inline_query_current_chat' => '']],
        ]);
    }

    public function selectWorkstation($id): void
    {
        if ($id) {
            $step = $this->handler->getCacheArray('prodTemplateStep');
            $step['workstation_id'] = $id;
            $this->handler->setCacheArray('prodTemplateStep', $step);
        }

        $this->handler->setState(self::states['step_selectOutput']);
        $this->editForm('Select output product:', [
            [['text' => '🔍 Search product', 'switch_inline_query_current_chat' => '']],
            [['text' => 'Skip output', 'callback_data' => 'skipOutput']],
        ]);
    }

    public function selectOutputProduct($id): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $step['output_product_id'] = $id;
        $this->handler->setCacheArray('prodTemplateStep', $step);

        $this->handler->setState(self::states['step_selectUnit']);
        $this->editForm('Input measure unit (e.g., kg, pcs):');
    }

    protected function setUnit($unit): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $step['unit'] = $unit;
        $this->handler->setCacheArray('prodTemplateStep', $step);

        $this->handler->setState(self::states['step_inputQuantity']);
        $this->editForm('Expected output quantity:');
    }

    protected function setExpectedQuantity($qty): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $step['expected_quantity'] = (float)$qty;
        $this->handler->setCacheArray('prodTemplateStep', $step);

        $this->askMaterial();
    }

    protected function askMaterial(): void
    {
        $this->handler->setState(self::states['step_addMaterial']);
        $this->editForm('Select material to add:', [
            [['text' => '🔍 Search material', 'switch_inline_query_current_chat' => '']],
        ]);
    }

    public function selectMaterial($id): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $step['current_material'] = $id;
        $this->handler->setCacheArray('prodTemplateStep', $step);

        $this->handler->setState(self::states['step_inputMaterialQty']);
        $this->editForm('Input quantity for selected material:');
    }

    protected function setMaterialQuantity($qty): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $materials = $step['materials'] ?? [];

        $materials[] = ['material_id' => $step['current_material'], 'quantity' => (float)$qty];
        unset($step['current_material']);

        $step['materials'] = $materials;
        $this->handler->setCacheArray('prodTemplateStep', $step);

        $this->handler->setState(self::states['step_confirmOptions']);
        $this->confirmStep();
    }

    protected function confirmStep(): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');

        $this->editForm('Confirm step options:', [
            [['text' => ($step['is_last'] ?? false) ? '✅ Is Last' : '☐ Is Last', 'callback_data' => 'toggleIsLast']],
            [['text' => '➕ Add material', 'callback_data' => 'addAnotherMaterial']],
            [
                ['text' => '✅ Save Step', 'callback_data' => 'saveStep']
            ],
        ]);
    }

    protected function toggleIsLast(): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $step['is_last'] = !($step['is_last'] ?? false);
        $this->handler->setCacheArray('prodTemplateStep', $step);

        $this->confirmStep();
    }

    protected function saveStep(): void
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        $all = $this->handler->getCacheArray('prodTemplateSteps');
        $all[] = $step;
        $this->handler->setCacheArray('prodTemplateSteps', $all);
        $this->handler->forgetCache('prodTemplateStep');
        $this->handler->resetCache();

        $this->editForm('✅ Step saved.');
        $this->handler->sendMainMenu();
    }

    public function cancelStep(): void
    {
        $form = $this->handler->getCacheArray('prodTemplateSteps');
        $prodTmpId = $form['prodTemplateId'] ?? null;
        $prodTmp = ProdTemplate::query()->findOrFail($prodTmpId);

        $message = "<b>✅ ProdTemplate saved</b>\n\n";
        $message .= ProdOrderNotification::getProdTemplateMsg($prodTmp);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '➕ Create step', 'callback_data' => "createProdTemplateStep:$prodTmp->id"]]
            ]),
        ]);

        $this->handler->forgetCache('prodTemplateStep');
        $this->handler->resetCache();
        $this->editForm('❌ Step cancelled.');
    }

    protected function editForm(string $prompt, array $markup = []): void
    {
        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getStepPrompt($prompt),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(array_merge(
                $markup,
                [
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelStep']]
                ]
            )),
        ]);
    }

    protected function getStepPrompt(string $prompt, ?string $error = null): string
    {
        $step = $this->handler->getCacheArray('prodTemplateStep');
        return strtr($this->handler::templates['prodTemplateStep'], [
            '{errorMsg}' => $error ?? '',
            '{details}' => $this->getStepDetails($step),
            '{prompt}' => $prompt,
        ]);
    }

    protected function getStepDetails($step = []): string
    {
        $out = "<b>Step:</b>\n";
        $out .= "Workstation: " . ($step['workstation_id'] ?? '-') . "\n";
        $out .= "Output: " . ($step['output_product_id'] ?? '-') . "\n";
        $out .= "Unit: " . ($step['unit'] ?? '-') . "\n";
        $out .= "Expected Qty: " . ($step['expected_quantity'] ?? '-') . "\n";
        $out .= "Materials:\n";
        foreach ($step['materials'] ?? [] as $mat) {
            $out .= "- {$mat['material_id']}: {$mat['quantity']}\n";
        }
        $out .= "Is Last Step: " . (!empty($step['is_last']) ? '✅' : '❌') . "\n";
        return $out;
    }

    protected function answerInlineWorkstation(string $queryId, string $query): void
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

    protected function answerInlineProduct(string $queryId, string $query, string $command): void
    {
        $list = Product::query()->search($query)->limit(20)->get();
        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $queryId,
            'results' => TelegramService::inlineResults($list, 'id', 'catName', '/select_output '),
            'cache_time' => 0,
        ]);
    }
}
