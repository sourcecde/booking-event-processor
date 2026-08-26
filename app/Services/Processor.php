<?php

namespace App\Services;

use App\Services\Interfaces\ProcessorInterface;
use App\Services\Interfaces\Sqs\SqsServiceInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Processor implements ProcessorInterface
{
    public function __construct(
        private readonly ClientInterface $externalApiClient,
        private readonly ClientInterface $internalApiClient,
        private readonly SqsServiceInterface $sqsService,
    ) {
    }

    #[\Override]
    public function process(array $message): void
    {
        try {
            $this->handle($message);
        } catch (Throwable $exception) {
            Log::error('Unexpected error while processing message; left for redelivery.', [
                'message' => $message,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function handle(array $message): void
    {
        $receiptHandle = $message['ReceiptHandle'] ?? null;

        if (null === $receiptHandle) {
            Log::error('Received a message with no receipt handle; skipping.', [
                'message' => $message,
            ]);

            return;
        }

        $decodedBody = json_decode($message['Body'] ?? '', true);

        if (!is_array($decodedBody)) {
            Log::error('Discarding message with invalid JSON body.', [
                'message' => $message,
            ]);

            $this->sqsService->deleteByHandle($receiptHandle);

            return;
        }

        $bookingId = $decodedBody['booking_id'] ?? null;
        $event = $decodedBody['event'] ?? null;

        if (null === $bookingId || null === $event) {
            Log::error('Discarding malformed message (missing booking_id/event).', [
                'message' => $message,
            ]);

            $this->sqsService->deleteByHandle($receiptHandle);

            return;
        }

        Log::info('Processing booking event.', [
            'booking_id' => $bookingId,
            'event' => $event,
        ]);

        $booking = $this->fetchBooking($bookingId, $receiptHandle);

        if (null === $booking) {
            return;
        }

        $this->registerEvent($bookingId, $event, $receiptHandle);
    }

    private function fetchBooking(int|string $bookingId, string $receiptHandle): ?array
    {
        try {
            $response = $this->externalApiClient->request(
                'GET',
                "/api/booking/{$bookingId}"
            );
        } catch (ConnectException $exception) {
            Log::warning('External booking API timed out; message left for redelivery.', [
                'booking_id' => $bookingId,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        } catch (RequestException $exception) {
            $statusCode = $exception->getResponse()?->getStatusCode();

            if (404 === $statusCode) {
                Log::error('Booking not found on external API; discarding message.', [
                    'booking_id' => $bookingId,
                ]);

                $this->sqsService->deleteByHandle($receiptHandle);

                return null;
            }

            Log::warning('External booking API call failed; message left for redelivery.', [
                'booking_id' => $bookingId,
                'status_code' => $statusCode,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (!is_array($decoded)) {
            Log::warning('External booking API returned an invalid response; message left for redelivery.', [
                'booking_id' => $bookingId,
            ]);

            return null;
        }

        Log::info('External booking API call succeeded.', [
            'booking_id' => $bookingId,
            'status_code' => $response->getStatusCode(),
        ]);

        return $decoded;
    }

    private function registerEvent(
        int|string $bookingId,
        string $event,
        string $receiptHandle
    ): void {
        try {
            $response = $this->internalApiClient->request(
                'POST',
                "/api/booking/{$bookingId}",
                [
                    'json' => [
                        'event' => $event,
                    ],
                ]
            );
        } catch (ConnectException $exception) {
            Log::warning('Internal booking API timed out; message left for redelivery.', [
                'booking_id' => $bookingId,
                'exception' => $exception->getMessage(),
            ]);

            return;
        } catch (RequestException $exception) {
            $statusCode = $exception->getResponse()?->getStatusCode();

            if (in_array($statusCode, [404, 422], true)) {
                Log::error('Internal booking API rejected the event; discarding message.', [
                    'booking_id' => $bookingId,
                    'status_code' => $statusCode,
                ]);

                $this->sqsService->deleteByHandle($receiptHandle);

                return;
            }

            Log::warning('Internal booking API call failed; message left for redelivery.', [
                'booking_id' => $bookingId,
                'status_code' => $statusCode,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        Log::info('Internal booking API call succeeded.', [
            'booking_id' => $bookingId,
            'status_code' => $response->getStatusCode(),
        ]);

        if (!$this->sqsService->deleteByHandle($receiptHandle)) {
            Log::error('Internal API call succeeded but failed to delete the SQS message.', [
                'booking_id' => $bookingId,
                'receipt_handle' => $receiptHandle,
            ]);

            return;
        }

        Log::info('SQS message deleted successfully.', [
            'booking_id' => $bookingId,
        ]);
    }
}
