<?php

namespace App\Tests\Unit\Scheduler;

use App\Scheduler\RunScheduledCommand;
use App\Scheduler\RunScheduledCommandHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;

final class RunScheduledCommandHandlerTest extends TestCase
{
    private TestHandler $logs;
    private RunScheduledCommandHandler $handler;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $logger = new Logger('test', [$this->logs], [new PsrLogMessageProcessor()]);

        $this->handler = new RunScheduledCommandHandler($logger, \dirname(__DIR__, 2).'/Fixtures/console', 'prod');
    }

    public function testSuccessfulCommandLogsNothing(): void
    {
        ($this->handler)(new RunScheduledCommand('exit:0'));

        self::assertSame([], $this->logs->getRecords());
    }

    public function testFailedCommandLogsErrorWithExitCodeAndOutput(): void
    {
        ($this->handler)(new RunScheduledCommand('exit:3'));

        self::assertTrue($this->logs->hasErrorThatContains('Scheduled command "exit:3" failed with exit code 3'));
        // the command gets the kernel environment and never waits for input
        self::assertTrue($this->logs->hasErrorThatContains('args: exit:3 --env=prod --no-interaction'));
    }

    public function testFailedCommandPrefersErrorOutput(): void
    {
        ($this->handler)(new RunScheduledCommand('stderr:1'));

        self::assertTrue($this->logs->hasErrorThatContains("exit code 1\nPHP Fatal error: boom"));
    }

    public function testHungCommandIsKilledAndLogged(): void
    {
        ($this->handler)(new RunScheduledCommand('sleep:5', timeout: 1));

        self::assertTrue($this->logs->hasErrorThatContains('Scheduled command "sleep:5" was killed after 1 seconds'));
    }
}
