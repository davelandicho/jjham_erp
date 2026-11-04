<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\URL;

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
        CreateAction::configureUsing(
            fn (CreateAction $action) => $action->createAnother(false)
        );

        if (str_contains(request()->getHost(), 'trycloudflare.com')) {
            URL::forceScheme('https');
        };
    }
}
