<?php

namespace App\Providers;

use App\Listeners\RateNotFoundListener;
use App\Services\OurPureService;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    #[\Override]
    public function register()
    {
    }
}
