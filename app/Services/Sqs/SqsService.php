<?php

namespace App\Services\Sqs;

use App\Services\Interfaces\Sqs\SqsServiceInterface;
use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Illuminate\Support\Facades\Log;

class SqsService implements SqsServiceInterface
{
    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly string $queueUrl
    ) {
    }

    #[\Override]
    public function consume(): array
    {
        try {
            $result = $this->sqsClient->receiveMessage([
                'QueueUrl' => $this->queueUrl,
                'MaxNumberOfMessages' => 10,
                'WaitTimeSeconds' => 20,
            ]);

            return $result->get('Messages') ?? [];
        } catch (AwsException $exception) {
            Log::error('Failed to consume SQS messages', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    #[\Override]
    public function deleteByHandle(string $receiptHandle): bool
    {
        try {
            $result = $this->sqsClient->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);

            return ($result->get('@metadata')['statusCode'] ?? null) === 200;
        } catch (AwsException $exception) {
            Log::error('Failed to delete SQS message', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
