<?php

namespace Daalvand\Safar724AutoTrack;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class Telegram
{
    private const BOT_TOKEN = '5487878955:AAHbqiHD5JBpXorVG62ZaSm-ARkS5jHw_ZM';
    private const VERIFY    = false;


    /**
     * @throws Exception
     */
    public function sendMessage(int|string $chatId, string $message, array $options): string {

        $query = $options + [
                'chat_id'              => $chatId,
                'text'                 => $message,
                'disable_notification' => false
            ];
        $res   = $this->request('sendMessage', 'GET', [
            'query' => $query
        ]);
        return $res->getBody()->getContents();
    }


    /**
     * @throws Exception
     */
    public function forwardMessage(int|string $chatId, int|string $fromChatId, int|string $messageId, bool $notification = true): string {
        $res = $this->request('forwardMessage', 'GET', [
            'json' => [
                'chat_id'              => $chatId,
                'from_chat_id'         => $fromChatId,
                'message_id'           => $messageId,
                'disable_notification' => !$notification

            ]
        ]);
        return $res->getBody()->getContents();
    }

    /**
     * @throws Exception
     */
    public function request(string $path, string $httpMethod = 'GET', array $options = []): Response {
        $verify     = self::VERIFY;
        $proxy      = $this->getProxies();
        $http_error = true;
        $options    = compact('proxy', 'verify', 'http_error') + $options;
        $botToken   = self::BOT_TOKEN;
        $url        = "https://api.telegram.org/bot$botToken/" . trim($path, '/ ');


        $client  = new Client();
        $request = new Request($httpMethod, $url);
        /** @var Response $response */
        $response = $client->sendAsync($request, $options)->wait();
        return $response;
    }

    /**
     * @throws Exception
     */
    public function getUpdates(): array {

        $response = json_decode($this->request('getUpdates')->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        return $response['result'] ?? [];
    }


    /**
     * @throws Exception
     */
    public function deleteWebhook(): string {
        return $this->request('deleteWebhook', 'DELETE')->getBody()->getContents();
    }


    private function getProxies(): array {
        $host = "127.0.0.1";
        return [
            'http'  => "http://$host:2081",
            'https' => "http://$host:2081",
        ];
    }
}
