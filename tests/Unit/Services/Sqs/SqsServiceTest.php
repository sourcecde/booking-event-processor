<?php

namespace Tests\Unit\Services\Sqs;

use App\Services\Sqs\SqsService;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Tests\TestCase;

class SqsServiceTest extends TestCase
{
    private const QUEUE_URL = 'http://finance_task_sqs:4568/finance-queue-production';

    public function testConsumeReturnsMessages(): void
    {
        $messages = [
            [
                'MessageId' => 'message-1',
                'ReceiptHandle' => 'receipt-1',
                'Body' => '{"booking_id":1,"event":"booking.created"}',
            ],
        ];

        $service = $this->createServiceWithResult([
            'Messages' => $messages,
        ]);

        self::assertSame($messages, $service->consume());
    }

    public function testConsumeReturnsEmptyArray(): void
    {
        $service = $this->createServiceWithResult([]);

        self::assertSame([], $service->consume());
    }

    public function testConsumeThrowsOnAwsError(): void
    {
        $service = $this->createServiceWithException(
            new AwsException(
                'Unable to receive messages',
                new Command('ReceiveMessage')
            )
        );

        $this->expectException(AwsException::class);

        $service->consume();
    }

    public function testDeleteReturnsTrue(): void
    {
        $service = $this->createServiceWithResult([
            '@metadata' => [
                'statusCode' => 200,
            ],
        ]);

        self::assertTrue(
            $service->deleteByHandle('receipt-1')
        );
    }

    public function testDeleteReturnsFalse(): void
    {
        $service = $this->createServiceWithResult([
            '@metadata' => [
                'statusCode' => 204,
            ],
        ]);

        self::assertFalse(
            $service->deleteByHandle('receipt-1')
        );
    }

    public function testDeleteReturnsFalseOnAwsError(): void
    {
        $service = $this->createServiceWithException(
            new AwsException(
                'Unable to delete message',
                new Command('DeleteMessage')
            )
        );

        self::assertFalse(
            $service->deleteByHandle('receipt-1')
        );
    }

    private function createServiceWithResult(array $result): SqsService
    {
        $handler = new MockHandler();
        $handler->append(new Result($result));

        return $this->createService($handler);
    }

    private function createServiceWithException(AwsException $exception): SqsService
    {
        $handler = new MockHandler();
        $handler->append($exception);

        return $this->createService($handler);
    }

    private function createService(MockHandler $handler): SqsService
    {
        $client = new SqsClient([
            'region' => 'eu-west-3',
            'version' => '2012-11-05',
            'credentials' => [
                'key' => 'accesskeyid',
                'secret' => 'secretkey',
            ],
            'handler' => $handler,
        ]);

        return new SqsService(
            $client,
            self::QUEUE_URL
        );
    }
}