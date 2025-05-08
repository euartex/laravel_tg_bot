<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\SendTelegramNotification;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Telegram Webhook API",
 *     description="API for handling Telegram webhook updates and configuration in a Laravel application.",
 *     version="1.0.0"
 * )
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 *  )
 */
class TelegramController extends Controller
{
    protected TelegramBotService $telegram;

    public function __construct(TelegramBotService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * @OA\Post(
     *     path="/telegram/webhook",
     *     summary="Handle Telegram webhook updates",
     *     description="Processes incoming Telegram messages, such as /start, /stop, and other commands, and manages user subscriptions.",
     *     operationId="handleTelegramWebhook",
     *     tags={"Telegram Webhook"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Telegram webhook update data",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"message"},
     *             @OA\Property(
     *                 property="message",
     *                 type="object",
     *                 @OA\Property(
     *                     property="chat",
     *                     type="object",
     *                     required={"id"},
     *                     @OA\Property(
     *                         property="id",
     *                         type="string",
     *                         description="Unique identifier for the chat",
     *                         example="123456789"
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="text",
     *                     type="string",
     *                     description="The text of the message (e.g., a command)",
     *                     example="/start"
     *                 ),
     *                 @OA\Property(
     *                     property="from",
     *                     type="object",
     *                     required={"first_name"},
     *                     @OA\Property(
     *                         property="first_name",
     *                         type="string",
     *                         description="First name of the sender",
     *                         example="TestUser"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful processing of the webhook",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="OK"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request data",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Invalid webhook data"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Internal server error"
     *             )
     *         )
     *     )
     * )
     */
    public function webhook(Request $request): Response
    {
        Log::debug('Webhook received', ['request' => $request->all(), 'json' => $request->json()->all()]);
        $update = $this->telegram->getWebhookUpdate($request);

        Log::debug('Update received', ['update' => $update]);

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'] ?? '';
            $username = $update['message']['from']['first_name'] ?? 'Unknown';

            Log::info('New msg: ' . json_encode($username));
            Log::debug('Username extracted', ['username' => $username, 'from' => $update['message']['from'] ?? null]);

            switch ($text) {
                case '/start':
                    try {
                        Log::debug('Processing /start command', [
                            'chat_id' => $chatId,
                            'username' => $username,
                            'webhook_data' => $request->json()->all(),
                        ]);
                        $email = "telegram_{$chatId}_" . uniqid() . "@example.com";
                        Log::debug('Attempting to save user', [
                            'chat_id' => $chatId,
                            'data' => [
                                'name' => $username,
                                'email' => $email,
                                'subscribed' => true,
                            ]
                        ]);
                        $user = User::updateOrCreate(
                            ['telegram_chat_id' => $chatId],
                            [
                                'name' => $username,
                                'email' => $email,
                                'password' => bcrypt(Str::random(16)),
                                'subscribed' => true,
                            ]
                        );
                        Log::info("User saved or updated: chat_id={$chatId}, user_id={$user->id}");
                        SendTelegramNotification::dispatch($chatId, "Welcome, {$username}! You have subscribed to notifications. Use /stop to unsubscribe.")
                            ->onQueue('telegram');
                    } catch (\Exception $e) {
                        Log::error("Failed to save user: chat_id={$chatId}, error={$e->getMessage()}, trace={$e->getTraceAsString()}");
                        SendTelegramNotification::dispatch($chatId, "Error during registration. Try again later.")
                            ->onQueue('telegram');
                    }
                    break;

                case '/stop':
                    $user = User::where('telegram_chat_id', $chatId)->first();
                    if ($user) {
                        $user->update(['subscribed' => false]);
                        SendTelegramNotification::dispatch($chatId, "You have unsubscribed from notifications. Use /start to subscribe again.")
                            ->onQueue('telegram');
                        Log::info("User unsubscribed: chat_id={$chatId}, user_id={$user->id}");
                    } else {
                        SendTelegramNotification::dispatch($chatId, "You are not registered. Use /start for registration.")
                            ->onQueue('telegram');
                    }
                    break;

                default:
                    SendTelegramNotification::dispatch($chatId, "Commands list:\n/start — Subscribe to notifications\n/stop — Unsubscribe")
                        ->onQueue('telegram');
                    break;
            }
        } else {
            Log::debug('No message in update', ['update' => $update]);
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * @OA\Get(
     *     path="/telegram/set-webhook",
     *     summary="Set Telegram webhook URL",
     *     description="Configures the Telegram bot to send webhook updates to the specified URL.",
     *     operationId="setTelegramWebhook",
     *     tags={"Telegram Webhook"},
     *     @OA\Response(
     *         response=200,
     *         description="Webhook set successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Webhook set successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to set webhook",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Webhook setup failed"
     *             )
     *         )
     *     )
     * )
     */
    public function setWebhook(): JsonResponse
    {
        $url = 'https://' . request()->getHttpHost() . '/telegram/webhook';

        $result = $this->telegram->setWebhook($url);

        return response()->json([
            'success' => $result && isset($result['ok']) && $result['ok'],
            'message' => $result['description'] ?? 'Webhook setup failed',
        ]);
    }
}
