<?php

namespace App\Providers;

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Modules\ModuleRegistry;
use App\Services\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(ClaudeDocumentClient::class, fn () => ClaudeDocumentClient::fromConfig());
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
    }
}
