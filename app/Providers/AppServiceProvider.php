<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Volt::mount([
            resource_path('views'),
        ]);

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        view()->composer('partials.inactivity-modal', function ($view) {
            $view->with('session_remaining', config('session.lifetime') * 60);
        });
    }
}
