<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $message;

    public function __construct($chatId, $message)
    {
        $this->chatId = $chatId;
        $this->message = $message;
    }

    public function handle()
    {
        $token = config('services.telegram-bot-api.token');
        $baseUrl = config('services.telegram-bot-api.url', 'https://api.telegram.org/bot');
        $apiUrl = $baseUrl . $token . '/sendMessage';

        Log::debug('Sending Telegram notification', [
            'chat_id' => $this->chatId,
            'message' => $this->message,
            'api_url' => $apiUrl,
        ]);

        try {
            $response = Http::post($apiUrl, [
                'chat_id' => $this->chatId,
                'text' => $this->message,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to send Telegram notification', [
                    'chat_id' => $this->chatId,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram notification to chat_id ' . $this->chatId . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
