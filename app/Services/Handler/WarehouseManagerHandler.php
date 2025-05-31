<?php

namespace App\Services\Handler;

use App\Models\User;
use App\Services\TelegramService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

class WarehouseManagerHandler extends BaseHandler
{
    protected User $user;
    protected array $promises = [];

    protected const states = [
        'main' => 'main',
        'addRawMaterial' => 'addRawMaterial',
        'remove_rm' => 'remove_rm',
        'complete_product' => 'complete_product',
        'view_inventory' => 'view_inventory',
        'view_transactions' => 'view_transactions',
    ];

    protected const templates = [
        'addRawMaterial' => <<<HTML
<b>Form inputs</b>

<b>1) Name</b>: {name}
<b>2) Quantity</b>: {quantity}
<b>3) Price</b>: {price}
HTML,

    ];

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
            'text' => "Main menu for Warehouse Manager",
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
        $this->tgBot->sendRequest('answerCallbackQuery', [
            'callback_query_id' => Arr::get($this->tgBot->update, 'callback_query.id')
        ]);

        if (method_exists($this, $cbData)) {
            call_user_func([$this, $cbData]);
        } else {
            $this->tgBot->answerMsg(['text' => "Invalid callback data."]);
        }
    }

    public function handleText(string $text): void
    {
        $chatId = $this->tgBot->chatId;
        $activeState = $this->getState();;
        dump("Active state: $activeState");

        if ($activeState === self::states['addRawMaterial']) {
            $this->addRawMaterialText();
            return;
        }

        $this->tgBot->sendRequestAsync('sendMessage', [
            'chat_id' => $chatId,
            'text' => "Main menu for Warehouse Manager",
            'reply_markup' => $this->getMainKb(),
        ]);
    }

    public function addRawMaterial(): void
    {
        $this->setState(self::states['addRawMaterial']);

        $res = $this->tgBot->answerMsg([
            'text' => strtr(self::templates['addRawMaterial'], [
                '{name}' => '',
                '{quantity}' => '',
                '{price}' => '',
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => 'Cancel', 'callback_data' => 'cancelAddRm'],
                    ['text' => 'Save', 'callback_data' => 'saveAddRm'],
                ]
            ])
        ]);
        $msgId = Arr::get($res, 'result.message_id');
        $this->cache->put($this->getCacheKey('edit_msg_id'), $msgId);
    }

    public function addRawMaterialText(): void
    {
        $formData = json_decode($this->cache->get($this->getCacheKey('addRawMaterial')), true) ?? [];

        $name = Arr::get($formData, 'name');
        $quantity = Arr::get($formData, 'quantity');
        $price = Arr::get($formData, 'price');

        $inputs = $this->parseUserInput($this->tgBot->input);
        if (!$inputs) {
            return;
        }

        if ($inputs['index'] == 1) {
            $name = Arr::get($inputs, 'value');
        } elseif ($inputs['index'] == 2) {
            $quantity = Arr::get($inputs, 'value');
        } elseif ($inputs['index'] == 3) {
            $price = Arr::get($inputs, 'value');
        }

        $this->cache->put(
            $this->getCacheKey('addRawMaterial'),
            json_encode([
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price,
            ])
        );

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->cache->get($this->getCacheKey('edit_msg_id')),
            'text' => strtr(self::templates['addRawMaterial'], [
                '{name}' => $name,
                '{quantity}' => $quantity,
                '{price}' => $price,
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => 'Cancel', 'callback_data' => 'cancelAddRm'],
                    ['text' => 'Save', 'callback_data' => 'saveAddRm'],
                ]
            ])
        ]);
    }

    public function cancelAddRm(): void
    {
        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('addRawMaterial'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerMsg([
            'text' => "Operation cancelled.",
            'reply_markup' => $this->getMainKb(),
        ]);
    }

    public function saveAddRm(): void
    {
        $formData = json_decode($this->cache->get($this->getCacheKey('addRawMaterial')), true) ?? [];
        $name = Arr::get($formData, 'name');
        $quantity = Arr::get($formData, 'quantity');
        $price = Arr::get($formData, 'price');

        dump("Name: $name, Quantity: $quantity, Price: $price");
//                    $this->transactionService->addMiniStock($this->user->work_station_id, $name, $quantity, $price);

        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('addRawMaterial'));
        $this->cache->forget($this->getCacheKey('edit_msg_id'));

        $this->tgBot->answerMsg([
            'text' => "Product added successfully.",
            'reply_markup' => $this->getMainKb(),
        ]);
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => '+ Add RM', 'callback_data' => 'addRawMaterial']
            ],
            [
                ['text' => '- Remove RM', 'callback_data' => 'remove_rm']
            ]
        ]);
    }
}
