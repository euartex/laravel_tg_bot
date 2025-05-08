<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected $token;
    protected $apiUrl;

    public function __construct()
    {
        Log::info('TEST: ' . config('services.telegram-bot-api.bot_token') . ' - ' . config('services.telegram-bot-api.url') );
        $this->token = config('services.telegram-bot-api.token');
        $this->apiUrl = config('services.telegram-bot-api.url') . $this->token;
    }

    public function getWebhookUpdate(Request $request)
    {
        return $request->json()->all();
    }

    public function sendMessage($chatId, $text)
    {
        return Http::post("{$this->apiUrl}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ])->json();
    }

    public function setWebhook($url)
    {
        Log::info('URL: ' . $this->apiUrl . ' Webhook URL: '. $url);
        return Http::post("{$this->apiUrl}/setWebhook", [
            'url' => $url,
        ])->json();
    }
}
