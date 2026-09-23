<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Telegram\TelegramExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Integração Socialite · provider Telegram (AgendaFácil).
        // O listener handle() registra o Provider correto (que estende AbstractProvider).
        Event::listen(SocialiteWasCalled::class, TelegramExtendSocialite::class.'@handle');

        // Set the default string length for the database schema
        // Schema::defaultStringLength(191);

        // Force HTTPS in production
        // if ($this->app->environment(['staging', 'production', 'test'])) {
        //     URL::forceScheme('https');
        // }
    }
}
