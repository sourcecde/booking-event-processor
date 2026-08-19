<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ConsumerCommand;
use App\Services\Interfaces\ProcessorInterface;
use App\Services\Interfaces\Sqs\SqsServiceInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ConsumerCommandTest extends \Tests\TestCase
{
    private SqsServiceInterface $sqsService;
    private ProcessorInterface $processor;
    private CommandTester $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->sqsService = \Mockery::mock(SqsServiceInterface::class);
        $this->processor = \Mockery::mock(ProcessorInterface::class);

        $runner = new ConsumerCommand($this->sqsService, $this->processor);
        $runner->setLaravel($this->app);

        $this->command = new CommandTester($runner);
    }

    public function testHandle_NoMessages()
    {
        $this->sqsService->shouldReceive('consume')
            ->times(10);

        $this->command->execute([]);
    }

    public function testHandle_ReceivedMessages()
    {
        $this->sqsService->shouldReceive('consume')
            ->once()
        ->andReturn([
            ['message'],
        ]);

        $this->sqsService->shouldReceive('consume')
            ->times(10)
        ->andReturn([]);

        $this->processor->shouldReceive('process')->once();

        $this->command->execute([]);
    }
}
