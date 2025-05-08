<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TelegramBotService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramBotService::class, fn () => new TelegramBotService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
