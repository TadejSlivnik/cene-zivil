<?php

namespace App\Scheduler;

/**
 * Runs a console command in its own process, see RunScheduledCommandHandler.
 */
final readonly class RunScheduledCommand implements \Stringable
{
    public function __construct(
        public string $command,
        /** Seconds before the process is killed, so a hung run cannot block the scheduler. */
        public int $timeout = 1800,
    ) {
    }

    /** Shown by `debug:scheduler`. */
    public function __toString(): string
    {
        return $this->command;
    }
}
