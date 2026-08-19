<?php

namespace App\Providers;

use App\Services\Interfaces\ProcessorInterface;
use App\Services\Interfaces\Sqs\SqsServiceInterface;
use Illuminate\Support\ServiceProvider;

class ConsumerProvider extends ServiceProvider
{
    #[\Override]
    public function register()
    {
        $this->app->singleton(SqsServiceInterface::class, function () {
            //@todo: return the proper service
        });

        $this->app->singleton(ProcessorInterface::class, function () {
            //@todo: return the proper service
        });
    }
}
