<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Use the Sanctum guard for broadcast channel authentication so that
     * token-based API clients (SPA, Electron) can authenticate private and
     * presence channels.
     *
     * Validates: Requirements 11.4
     */
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
