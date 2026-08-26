<?php

namespace App\Providers;

use App\Services\Interfaces\ProcessorInterface;
use App\Services\Interfaces\Sqs\SqsServiceInterface;
use Aws\Sqs\SqsClient;
use GuzzleHttp\Client;
use App\Services\Processor;
use App\Services\Sqs\SqsService;
use Illuminate\Support\ServiceProvider;

class ConsumerProvider extends ServiceProvider
{
    #[\Override]
    public function register()
    {
        $this->app->singleton(SqsServiceInterface::class, function () {
            $sqsClient = new SqsClient([
                            'region' => env('AWS_SQS_REGION', 'eu-west-3'),
                            'version' => env('AWS_SQS_VERSION', '2012-11-05'),
                            'endpoint' => env('AWS_SQS_ENDPOINT', 'http://finance_task_sqs:4568'),
                            'credentials' => [
                                'key' => env('AWS_SQS_KEY', 'accesskeyid'),
                                'secret' => env('AWS_SQS_SECRET', 'secretkey'),
                            ],
                        ]);

            return new SqsService($sqsClient, env('AWS_SQS_QUEUE_URL'));
        });

        $this->app->singleton(ProcessorInterface::class, function ($app) {
            $externalApiClient = new Client([
                'base_uri' => env('EXTERNAL_API_BASE_URL', 'http://finance_task'),
                'connect_timeout' => 2.0,
                'timeout' => 5.0,
            ]);
            $internalApiClient = new Client([
                'base_uri' => env('INTERNAL_API_BASE_URL', 'http://finance_task'),
                'connect_timeout' => 2.0,
                'timeout' => 5.0,
            ]);

            return new Processor(
                $externalApiClient,
                $internalApiClient,
                $app->make(SqsServiceInterface::class)
            );
        });
    }
}
