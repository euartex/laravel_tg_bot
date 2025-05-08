<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramNotification;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyTasks extends Command
{
    protected $signature = 'tasks:notify';
    protected $description = 'Fetches incomplete tasks from API and notifies subscribed users via Telegram';

    protected Client $httpClient;

    public function __construct()
    {
        parent::__construct();
        $this->httpClient = new Client(['base_uri' => 'https://jsonplaceholder.typicode.com/']);
    }

    public function handle(): int
    {
        try {
            // Querying tasks from API
            $response = $this->httpClient->get('todos');
            $tasks = json_decode($response->getBody()->getContents(), true);

            // Filtering tasks: completed = false and userId <= 5
            $incompleteTasks = array_filter($tasks, fn($task) => !$task['completed'] && $task['userId'] <= 5);

            // Group tasks by userId
            $tasksByUser = [];
            foreach ($incompleteTasks as $task) {
                $tasksByUser[$task['userId']][] = $task['title'];
            }

            // Get active users
            $users = User::where('subscribed', true)->whereNotNull('telegram_chat_id')->get();

            if ($users->isEmpty()) {
                $this->info('No subscribed users found.');
                return 0;
            }

            // Sending notifications
            foreach ($users as $user) {
                $userId = $user->id; // Suppose, user id is userId from API
                if (isset($tasksByUser[$userId])) {
                    $message = "Your uncompleted tasks:\n" . implode("\n", array_map(
                            fn($task, $index) => ($index + 1) . ". $task",
                            $tasksByUser[$userId],
                            array_keys($tasksByUser[$userId])
                        ));

                    // dispatch task in queue
                    SendTelegramNotification::dispatch($user->telegram_chat_id, $message)
                        ->onQueue('telegram');
                    $this->info("Queued notification for user ID {$userId} (chat_id: {$user->telegram_chat_id})");
                } else {
                    $this->info("No tasks for user ID {$userId}");
                }
            }

            return 0;
        } catch (GuzzleException $e) {
            Log::error('Error fetching tasks: ' . $e->getMessage());
            $this->error('Failed to fetch tasks from API.');
            return 1;
        }
    }
}
