<?php

namespace App\Tests\Integration\Scheduler;

use App\Scheduler\RunScheduledCommand;
use App\Scheduler\Schedule;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class ScheduleTest extends KernelTestCase
{
    public function testScheduleMatchesTheFormerCrontab(): void
    {
        self::assertSame([
            'app:sync:tus' => '0-59/2 1-5 * * *',
            'app:sync:lidl' => '1-59/2 1 * * *',
            'app:sync:mercator' => '1-59/2 2 * * *',
            'app:sync:dm' => '1-59/2 3 * * *',
            'app:sync:hofer' => '1-59/2 4 * * *',
            'app:handle:promotions' => '1 3 * * *',
            'app:sitemap' => '5 6 * * *',
        ], $this->scheduledCommands());
    }

    public function testEveryScheduledCommandExists(): void
    {
        $application = new Application(self::bootKernel());

        foreach (array_keys($this->scheduledCommands()) as $command) {
            self::assertTrue($application->has($command), $command);
        }
    }

    /**
     * @return array<string, string> command => cron expression
     */
    private function scheduledCommands(): array
    {
        $schedule = self::getContainer()->get(Schedule::class)->getSchedule();

        $commands = [];
        foreach ($schedule->getRecurringMessages() as $recurringMessage) {
            $context = new MessageContext('default', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            foreach ($recurringMessage->getMessages($context) as $message) {
                self::assertInstanceOf(RunScheduledCommand::class, $message);
                $commands[$message->command] = (string) $recurringMessage->getTrigger();
            }
        }

        return $commands;
    }
}
