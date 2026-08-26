<?php

namespace Tests\Unit\Services;

use App\Services\Interfaces\Sqs\SqsServiceInterface;
use App\Services\Processor;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessorTest extends TestCase
{
    private ClientInterface $externalApiClient;
    private ClientInterface $internalApiClient;
    private SqsServiceInterface $sqsService;
    private Processor $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->externalApiClient = Mockery::mock(ClientInterface::class);
        $this->internalApiClient = Mockery::mock(ClientInterface::class);
        $this->sqsService = Mockery::mock(SqsServiceInterface::class);

        $this->processor = new Processor(
            $this->externalApiClient,
            $this->internalApiClient,
            $this->sqsService
        );
    }

    public function testProcessSuccess(): void
    {
        $this->mockExternalSuccess();

        $this->internalApiClient
            ->shouldReceive('request')
            ->once()
            ->with(
                'POST',
                '/api/booking/1',
                [
                    'json' => [
                        'event' => 'booking.created',
                    ],
                ]
            )
            ->andReturn(new Response(201));

        $this->sqsService
            ->shouldReceive('deleteByHandle')
            ->once()
            ->with('receipt-1')
            ->andReturnTrue();

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testMissingReceiptHandle(): void
    {
        $this->externalApiClient->shouldNotReceive('request');
        $this->internalApiClient->shouldNotReceive('request');
        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process([
            'Body' => '{"booking_id":1,"event":"booking.created"}',
        ]);

        self::assertTrue(true);
    }

    public function testInvalidJson(): void
    {
        $this->externalApiClient->shouldNotReceive('request');
        $this->internalApiClient->shouldNotReceive('request');

        $this->expectDelete();

        $this->processor->process([
            'ReceiptHandle' => 'receipt-1',
            'Body' => 'invalid-json',
        ]);

        self::assertTrue(true);
    }

    public function testMalformedMessage(): void
    {
        $this->externalApiClient->shouldNotReceive('request');
        $this->internalApiClient->shouldNotReceive('request');

        $this->expectDelete();

        $this->processor->process([
            'ReceiptHandle' => 'receipt-1',
            'Body' => '{"booking_id":1}',
        ]);

        self::assertTrue(true);
    }

    public function testBookingNotFound(): void
    {
        $this->externalApiClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(
                $this->requestException(
                    'GET',
                    '/api/booking/999',
                    404
                )
            );

        $this->internalApiClient->shouldNotReceive('request');

        $this->expectDelete();

        $this->processor->process($this->createMessage(999));

        self::assertTrue(true);
    }

    public function testExternalApiFailure(): void
    {
        $this->externalApiClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(
                $this->requestException(
                    'GET',
                    '/api/booking/1',
                    500
                )
            );

        $this->internalApiClient->shouldNotReceive('request');
        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testExternalApiTimeout(): void
    {
        $this->externalApiClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(
                new ConnectException(
                    'Connection timeout',
                    new Request('GET', '/api/booking/1')
                )
            );

        $this->internalApiClient->shouldNotReceive('request');
        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testInvalidExternalResponse(): void
    {
        $this->externalApiClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], 'invalid-json'));

        $this->internalApiClient->shouldNotReceive('request');
        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testInternalApi404(): void
    {
        $this->assertInternalErrorDeletesMessage(404);
    }

    public function testInternalApi422(): void
    {
        $this->assertInternalErrorDeletesMessage(422);
    }

    public function testInternalApiFailure(): void
    {
        $this->mockExternalSuccess();

        $this->internalApiClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(
                $this->requestException(
                    'POST',
                    '/api/booking/1',
                    500
                )
            );

        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testInternalApiTimeout(): void
    {
        $this->mockExternalSuccess();

        $this->internalApiClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(
                new ConnectException(
                    'Connection timeout',
                    new Request('POST', '/api/booking/1')
                )
            );

        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testSqsDeleteFailure(): void
    {
        $this->mockExternalSuccess();

        $this->internalApiClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(201));

        $this->sqsService
            ->shouldReceive('deleteByHandle')
            ->once()
            ->with('receipt-1')
            ->andReturnFalse();

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    public function testUnexpectedException(): void
    {
        $this->externalApiClient
            ->shouldReceive('request')
            ->once()
            ->with('GET', '/api/booking/1')
            ->andThrow(new RuntimeException('Unexpected failure'));

        $this->internalApiClient->shouldNotReceive('request');
        $this->sqsService->shouldNotReceive('deleteByHandle');

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    private function assertInternalErrorDeletesMessage(int $statusCode): void
    {
        $this->mockExternalSuccess();

        $this->internalApiClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(
                $this->requestException(
                    'POST',
                    '/api/booking/1',
                    $statusCode
                )
            );

        $this->expectDelete();

        $this->processor->process($this->createMessage());

        self::assertTrue(true);
    }

    private function mockExternalSuccess(): void
    {
        $this->externalApiClient
            ->shouldReceive('request')
            ->once()
            ->with('GET', '/api/booking/1')
            ->andReturn(
                new Response(200, [], '{"id":1}')
            );
    }

    private function expectDelete(): void
    {
        $this->sqsService
            ->shouldReceive('deleteByHandle')
            ->once()
            ->with('receipt-1')
            ->andReturnTrue();
    }

    private function requestException(
        string $method,
        string $uri,
        int $statusCode
    ): RequestException {
        return new RequestException(
            'Request failed',
            new Request($method, $uri),
            new Response($statusCode)
        );
    }

    private function createMessage(int $bookingId = 1): array
    {
        return [
            'ReceiptHandle' => 'receipt-1',
            'Body' => json_encode([
                'booking_id' => $bookingId,
                'event' => 'booking.created',
            ]),
        ];
    }
}