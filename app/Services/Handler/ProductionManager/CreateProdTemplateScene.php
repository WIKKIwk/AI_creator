<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\ProductType;
use App\Listeners\ProdOrderNotification;
use App\Models\Product;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class CreateProdTemplateScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected ProdOrderService $prodOrderService;

    public const states = [
        'prodTemp_selectProduct' => 'prodTemp_selectProduct',
        'prodTemp_inputComment' => 'prodTemp_inputComment',
    ];

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleText($text): void
    {
        $activeState = $this->handler->getState();

        switch ($activeState) {
            case self::states['prodTemp_inputComment']:
                $this->inputComment($text);
                return;
        }

        if (str_starts_with($text, '/select_product')) {
            $productId = trim(str_replace('/select_product', '', $text));
            $this->selectProduct($productId);
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
        $this->handler->setState(self::states['prodTemp_selectProduct']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getProdTemplatePrompt('Select product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🔍 Select product', 'switch_inline_query_current_chat' => '']],
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdTemplate']]
            ]),
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function selectProduct($productId): void
    {
        dump("Product ID: $productId");

        $form = $this->handler->getCacheArray('prodTemplateForm');
        $form['product_id'] = $productId;
        $this->handler->setCacheArray('prodTemplateForm', $form);

        // Ask for quantity
        $this->handler->setState(self::states['prodTemp_inputComment']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getProdTemplatePrompt('Input comment:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdTemplate']]
            ]),
        ]);
    }

    public function inputComment($comment): void
    {
        $form = $this->handler->getCacheArray('prodTemplateForm');
        $form['comment'] = $comment;
        $this->handler->setCacheArray('prodTemplateForm', $form);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getProdTemplatePrompt('Input offer price for product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelProdTemplate'],
                    ['text' => '✅ Save', 'callback_data' => 'saveProdTemplate']
                ]
            ]),
        ]);
    }

    public function saveProdTemplate(): void
    {
        $form = $this->handler->getCacheArray('prodTemplateForm');
        dump($form);

        try {
            $prodTmp = $this->prodOrderService->createTemplateByForm($form);

            $message = "<b>✅ ProdTemplate saved</b>\n\n";
            $message .= TgMessageService::getProdTemplateMsg($prodTmp);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '➕ Create step', 'callback_data' => "createProdTemplateStep:$prodTmp->id"]],
                    [['text' => '🔙 Back', 'callback_data' => 'backMainMenu']],
                ]),
            ]);

            $this->handler->forgetCache('prodTemplateForm');
            $this->handler->forgetCache('state');
            $this->handler->forgetCache('scene');

            $this->tgBot->answerCbQuery(['text' => '✅ ProdTemplate saved successfully!'], true);
        } catch (Throwable $e) {
            $this->tgBot->answerCbQuery(['text' => '❌ Error saving ProdTemplate!'], true);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getProdTemplatePrompt('', "<i>❌ Error saving ProdTemplate: {$e->getMessage()}</i>"),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelProdTemplate'],
                        ['text' => '✅ Save again', 'callback_data' => 'saveProdTemplate']
                    ]
                ]),
            ]);
        }
    }

    public function cancelProdTemplate($withResponse = true): void
    {
        $this->handler->forgetCache('prodTemplateForm');
        $this->handler->resetCache();

        if ($withResponse) {
            $this->tgBot->answerCbQuery(['text' => 'Operation cancelled.']);
            $this->handler->sendMainMenu(true);
        }
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';

        /** @var Collection<Product> $products */
        $products = Product::query()
            ->search($search)
            ->where('type', ProductType::ReadyProduct->value)
            ->take(30)
            ->get();

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'type' => 'article',
                'id' => (string)$product->id,
                'title' => "$product->catName ($product->code)",
                'description' => $product->description ?: '',
                'input_message_content' => [
                    'message_text' => "/select_product $product->id",
                ],
            ];
        }

        dump($inlineQuery['id'], $results);

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results,
            'cache_time' => 0,
        ]);
    }

    protected function getProdTemplatePrompt(string $prompt, $errorMsg = null): string
    {
        return strtr($this->handler::templates['prodTemplate'], [
            '{errorMsg}' => $errorMsg ?? '',
            '{details}' => $this->getProdTemplateDetails(),
            '{prompt}' => $prompt,
        ]);
    }

    protected function getProdTemplateDetails(): string
    {
        $result = "<b>ProdTemplate details:</b>\n\n";

        $form = $this->handler->getCacheArray('prodTemplateForm');
        dump($form);
        $productId = $form['product_id'] ?? null;
        $product = Product::query()->find($productId);

        $productName = $product?->catName ?? '-';
        $comment = $form['comment'] ?? '-';

        $result .= "Product: <b>$productName</b>\n";
        $result .= "Comment: <i>$comment</i>\n";

        return $result;
    }
}
