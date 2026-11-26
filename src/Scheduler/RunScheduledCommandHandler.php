<?php

namespace App\Scheduler;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs the command as `php bin/console <command>` in a new process, like cron did, instead of inside the long-running
 * scheduler worker: commands then start with a fresh container, Doctrine connection and memory, and a command that
 * crashes or keeps its lock after an exception cannot take the worker down with it.
 *
 * A failure is logged (and so reported to Discord in prod) but not thrown: the command logs its own exception, and
 * Messenger would otherwise log the failure again with the whole command output.
 */
#[AsMessageHandler]
final readonly class RunScheduledCommandHandler
{
    private const OUTPUT_EXCERPT_LENGTH = 400;

    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public function __invoke(RunScheduledCommand $message): void
    {
        $process = new Process(
            [\PHP_BINARY, $this->projectDir.'/bin/console', $message->command, '--env='.$this->environment, '--no-interaction'],
            $this->projectDir,
            timeout: $message->timeout,
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->logger->error('Scheduled command "{command}" was killed after {timeout} seconds', [
                'command' => $message->command,
                'timeout' => $message->timeout,
            ]);

            return;
        }

        if (!$process->isSuccessful()) {
            // Deduplication compares only the first line, so the output goes on the next lines.
            $this->logger->error("Scheduled command \"{command}\" failed with exit code {code}\n{output}", [
                'command' => $message->command,
                'code' => $process->getExitCode(),
                'output' => $this->outputExcerpt($process),
            ]);
        }
    }

    /**
     * The start of stderr (an uncaught exception's message comes before its trace), otherwise the end of stdout
     * (AbstractCommand's result block).
     */
    private function outputExcerpt(Process $process): string
    {
        $errorOutput = trim($process->getErrorOutput());
        if ('' !== $errorOutput) {
            return mb_substr($errorOutput, 0, self::OUTPUT_EXCERPT_LENGTH);
        }

        return mb_substr(trim($process->getOutput()), -self::OUTPUT_EXCERPT_LENGTH);
    }
}
