<?php

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Replaces the production crontab. Run by `messenger:consume scheduler_default` (see deploy/cene-zivil-scheduler.service).
 *
 * Times are in PHP's default timezone, the same one AbstractSyncCommand uses to decide which day a run belongs to.
 * One worker runs the commands one after another; the sync commands lock themselves (AbstractCommand::MODE_SINGULAR).
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            // after a restart, run each task once if its time passed while the worker was down
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                // Tuš on even minutes 1:00-5:58; the others on odd minutes, one hour each
                RecurringMessage::cron('0-59/2 1-5 * * *', new RunScheduledCommand('app:sync:tus')),
                RecurringMessage::cron('1-59/2 1 * * *', new RunScheduledCommand('app:sync:lidl')),
                RecurringMessage::cron('1-59/2 2 * * *', new RunScheduledCommand('app:sync:mercator')),
                RecurringMessage::cron('1-59/2 3 * * *', new RunScheduledCommand('app:sync:dm')),
                // Spar is disabled: RecurringMessage::cron('1-59/2 4 * * *', new RunScheduledCommand('app:sync:spar')),
                RecurringMessage::cron('1-59/2 4 * * *', new RunScheduledCommand('app:sync:hofer')),
                RecurringMessage::cron('1 3 * * *', new RunScheduledCommand('app:handle:promotions')),
                RecurringMessage::cron('5 6 * * *', new RunScheduledCommand('app:sitemap')),
            );
    }
}
