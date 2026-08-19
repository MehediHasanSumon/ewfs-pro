<?php

namespace App\Providers;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('pdf.*', function ($view): void {
            if (! array_key_exists('companySetting', $view->getData()) || $view->getData()['companySetting'] === null) {
                static $cachedCompanySetting = null;
                if ($cachedCompanySetting === null) {
                    $cachedCompanySetting = CompanySetting::query()->first() ?: false;
                }
                $view->with('companySetting', $cachedCompanySetting ?: null);
            }
        });
    }
}
