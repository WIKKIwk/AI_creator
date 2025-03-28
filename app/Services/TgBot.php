<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Exception\GuzzleException;

class TgBot
{
    protected Client $client;
    protected array $promises = [];

    public function __construct(
        protected TelegramService $telegramService
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function sendRequest(string $method, array $params): array
    {
        $res = $this->client->request('POST', $method, [
            'json' => $params
        ]);
        return json_decode($res->getBody()->getContents(), true);
    }

    public function sendRequestAsync(string $method, array $params): PromiseInterface
    {
        $promise = $this->client->requestAsync('POST', $method, [
            'json' => $params
        ]);
        $this->promises[] = $promise;
        return $promise;
    }

    public function settlePromises(): array
    {
        $responses = Utils::settle($this->promises)->wait();

        $result = [];
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $result[] = json_decode($response['value']->getBody()->getContents(), true);
            }
        }
        return $result;
    }

    /**
     * @throws GuzzleException
     */
    public function sendMsg(array $params): array
    {
        return $this->sendRequest('sendMessage', $params);
    }
}
