<?php

namespace App\Console\Commands;

use App\Services\Interfaces\ProcessorInterface;
use App\Services\Interfaces\Sqs\SqsServiceInterface;

class ConsumerCommand extends \Illuminate\Console\Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the messages from the SQS queue';

    private int $wastedIterations = 0;
    private int $wastedThreshold = 10;

    public function __construct(private SqsServiceInterface $sqsService, private ProcessorInterface $processor)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        while (true) {
            $messages = $this->sqsService->consume();

            $messagesCount = 0;
            foreach ($messages as $message) {
                $this->processor->process($message);
                $messagesCount++;
            }

            if (0 === $messagesCount) {
                $this->wastedIterations++;
            }

            if ($this->checkWastedIterationsInThreshold()) {
                break;
            }
        }
    }

    private function checkWastedIterationsInThreshold(): bool
    {
        $result = ($this->wastedIterations >= $this->wastedThreshold);

        return $result;
    }
}
