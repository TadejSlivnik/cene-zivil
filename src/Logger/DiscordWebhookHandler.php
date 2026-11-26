<?php

namespace App\Logger;

use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts error log records to a Discord webhook, one message per batch.
 *
 * In prod (config/packages/monolog.yaml) a filter (error and above) and a deduplication handler sit in front of it:
 * the deduplication handler buffers a process's records and hands them over in one batch when the request or
 * command ends, and drops the batch when every error in it was already reported within the last hour.
 */
final class DiscordWebhookHandler extends AbstractHandler
{
    /** Discord's limit for a message's content. */
    public const MAX_LENGTH = 2000;
    private const MAX_RECORD_LENGTH = 500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $webhookUrl,
        int|string|Level $level = Level::Error,
    ) {
        parent::__construct($level);
    }

    public function handle(LogRecord $record): bool
    {
        $this->handleBatch([$record]);

        return false === $this->bubble;
    }

    public function handleBatch(array $records): void
    {
        $records = array_values(array_filter($records, fn (LogRecord $record): bool => $this->isHandling($record) && !$this->isClientError($record)));
        if ([] === $records || '' === $this->webhookUrl) {
            return;
        }

        try {
            $this->httpClient->request('POST', $this->webhookUrl, [
                'json' => [
                    'content' => $this->format($records),
                    // log messages may contain "@everyone" or user ids; never ping anyone
                    'allowed_mentions' => ['parse' => []],
                ],
                'timeout' => 5,
            ])->getStatusCode();
        } catch (\Throwable) {
            // A failed alert must not break the request or command that logged the error.
        }
    }

    /**
     * 4xx HTTP exceptions (404s from bots and crawlers, mostly) are logged at error level by Symfony.
     */
    private function isClientError(LogRecord $record): bool
    {
        $exception = $record->context['exception'] ?? null;

        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500;
    }

    /**
     * @param list<LogRecord> $records
     */
    private function format(array $records): string
    {
        $content = '`'.$this->origin().'`'."\n";

        foreach ($records as $i => $record) {
            $line = \sprintf('**%s** [%s] %s', $record->level->getName(), $record->channel, $record->message);

            $exception = $record->context['exception'] ?? null;
            if ($exception instanceof \Throwable && !str_contains($record->message, $exception->getFile())) {
                $line .= \sprintf(' (%s at %s:%d)', $exception::class, basename($exception->getFile()), $exception->getLine());
            }

            if (mb_strlen($line) > self::MAX_RECORD_LENGTH) {
                $line = mb_substr($line, 0, self::MAX_RECORD_LENGTH - 1).'…';
            }

            // keep room for the "+N more" line
            if (mb_strlen($content.$line) > self::MAX_LENGTH - 20) {
                return $content.\sprintf('… +%d more', \count($records) - $i);
            }

            $content .= $line."\n";
        }

        return rtrim($content);
    }

    /**
     * The console command or the HTTP request that logged the records.
     */
    private function origin(): string
    {
        if (\PHP_SAPI === 'cli') {
            return $_SERVER['argv'][1] ?? 'cli';
        }

        return ($_SERVER['REQUEST_METHOD'] ?? '').' '.($_SERVER['REQUEST_URI'] ?? '');
    }
}
