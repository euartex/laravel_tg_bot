<?php

use App\Http\Controllers\TelegramController;
use App\Models\User;
use App\Jobs\SendTelegramNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('handles /start command and saves user with password', function () {
    // Проверка, что миграции применены
    $this->artisan('migrate', ['--env' => 'testing'])->assertExitCode(0);

    // Проверка существования таблицы users
    $hasUsersTable = Schema::hasTable('users');
    dump('Users table exists: ' . ($hasUsersTable ? 'Yes' : 'No'));

    // Подготовка
    $chatId = '123456789';
    $username = 'TestUser';
    $webhookData = [
        'message' => [
            'chat' => ['id' => $chatId],
            'text' => '/start',
            'from' => ['username' => $username],
        ],
    ];

    // Фейковая очередь
    Queue::fake();

    // Фейковый ответ Telegram API (для SendTelegramNotification)
    Http::fake([
        'https://api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
    ]);

    // Выполнение
    $response = $this->postJson('/telegram/webhook', $webhookData);

    // Проверки
    $response->assertStatus(200)->assertContent('OK');
    $user = User::where('telegram_chat_id', $chatId)->first();
    if (!$user) {
        dump('User not found in database. Check migrations and TelegramController.');
        dump('Database users count: ' . User::count());
        dump('Webhook data sent: ', $webhookData);
    } else {
        dump('User found', ['name' => $user->name, 'email' => $user->email, 'telegram_chat_id' => $user->telegram_chat_id, 'subscribed' => $user->subscribed]);
    }
    expect($user)->not->toBeNull();
    expect($user->name)->toBe($username);
    expect($user->email)->toMatch('/telegram_' . preg_quote($chatId, '/') . '_\w+@example\.com/');
    expect($user->subscribed)->toBeTruthy();
    expect($user->password)->not->toBeEmpty();

    // Проверка очереди
    Queue::assertPushedOn('telegram', SendTelegramNotification::class);
    Queue::assertPushed(SendTelegramNotification::class, function ($job) use ($chatId, $username) {
        $reflection = new ReflectionClass($job);
        $chatIdProperty = $reflection->getProperty('chatId');
        $chatIdProperty->setAccessible(true);
        $messageProperty = $reflection->getProperty('message');
        $messageProperty->setAccessible(true);

        $jobChatId = $chatIdProperty->getValue($job);
        $message = $messageProperty->getValue($job);

        return $jobChatId === $chatId && str_contains($message, "Добро пожаловать, {$username}!");
    });
});

it('runs NotifyTasks command and queues notifications', function () {
    // Проверка, что миграции применены
    $this->artisan('migrate', ['--env' => 'testing'])->assertExitCode(0);

    // Подготовка
    Queue::fake();
    Http::fake([
        'https://jsonplaceholder.typicode.com/todos' => Http::response([
            [
                'userId' => 1,
                'id' => 1,
                'title' => 'delectus aut autem',
                'completed' => false,
            ],
        ], 200),
    ]);

    $user = User::create([
        'id' => 1, // Соответствует userId из API
        'name' => 'Test User',
        'email' => 'test@example.com',
        'telegram_chat_id' => '123456789',
        'subscribed' => true,
        'subscribed_at' => now(),
        'password' => bcrypt('password'),
    ]);

    // Выполнение
    $this->artisan('tasks:notify')->assertExitCode(0);

    // Проверки
    Queue::assertPushed(SendTelegramNotification::class, function ($job) use ($user) {
        $reflection = new ReflectionClass($job);
        $chatIdProperty = $reflection->getProperty('chatId');
        $chatIdProperty->setAccessible(true);
        $messageProperty = $reflection->getProperty('message');
        $messageProperty->setAccessible(true);

        $chatId = $chatIdProperty->getValue($job);
        $message = $messageProperty->getValue($job);

        if ($chatId !== $user->telegram_chat_id || !str_contains($message, 'delectus aut autem')) {
            dump("Queue check failed: chatId=$chatId, expected={$user->telegram_chat_id}, message=$message");
        }

        return $chatId === $user->telegram_chat_id && str_contains($message, 'delectus aut autem');
    });
});
