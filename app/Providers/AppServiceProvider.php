<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PersonalAccessToken;
use Laravel\Sactum\Sanctum;
use Laravel\Sanctum\Sanctum as SanctumSanctum;

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
        SanctumSanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
