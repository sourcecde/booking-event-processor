<?php

namespace App\Services\Interfaces\Sqs;

interface SqsServiceInterface
{
    public function consume(): array;
    public function deleteByHandle(string $receiptHandle): bool;
}
