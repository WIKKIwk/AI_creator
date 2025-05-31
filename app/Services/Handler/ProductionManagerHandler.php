<?php

namespace App\Services\Handler;

use App\Listeners\ProdOrderNotification;
use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class ProductionManagerHandler extends BaseHandler
{
    protected User $user;
    protected array $promises = [];
    protected ProdOrderService $prodOrderService;

    protected const states = [
        'prodOrderGroup_selectType' => 'prodOrderGroup_selectType',
        'prodOrderGroup_inputWarehouse' => 'prodOrderGroup_inputWarehouse',
        'prodOrderGroup_inputAgent' => 'prodOrderGroup_inputAgent',
        'prodOrderGroup_inputDeadline' => 'prodOrderGroup_inputDeadline',
        'prodOrderGroup_addProduct' => 'prodOrderGroup_addProduct',
        'prodOrderGroup_inputQuantity' => 'prodOrderGroup_inputQuantity',
        'prodOrderGroup_inputOfferPrice' => 'prodOrderGroup_inputOfferPrice',
        'prodOrderGroup_confirm' => 'prodOrderGroup_confirm',
    ];

    protected const templates = [
        'prodOrderGroup' => <<<HTML
{errorMsg}
<b>Production Order Group</b>
{prompt}
HTML,
    ];

    public function __construct(TgBot $tgBot, Cache $cache)
    {
        parent::__construct($tgBot, $cache);

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function validateUser(User $user): bool
    {
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

    public function confirmOrderCallback($id): void
    {
        $prodOrderGroup = $this->prodOrderService->getOrderGroupById($id);
        $this->prodOrderService->confirmOrderGroup($prodOrderGroup);

        $message = "<b>✅ Order confirmed!</b>\n\n";;
        $message .= ProdOrderNotification::getNotificationMsg($prodOrderGroup);

        $this->tgBot->answerCbQuery(['text' => "Order confirmed!"], true);
        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }

    public function createProdOrder(): void
    {
        $this->cache->put($this->getCacheKey('state'), 'createProdOrderGroup_type');
        $this->tgBot->answerMsg([
            'chat_id' => $this->tgBot->chatId,
            'text' => "Select the type of ProdOrderGroup:",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'ByOrder', 'callback_data' => 'type_ByOrder'],
                        ['text' => 'ByCatalog', 'callback_data' => 'type_ByCatalog']
                    ],
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrderGroup']
                    ]
                ]
            ]
        ]);
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

        $this->sendMainMenu();
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => '🛠 Create ProdOrder', 'callback_data' => 'createProdOrder'],
                ['text' => '📋 ProdOrders List', 'callback_data' => 'prodOrdersList'],
            ]
        ]);
    }
}
