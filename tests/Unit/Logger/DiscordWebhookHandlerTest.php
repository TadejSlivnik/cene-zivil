<?php

namespace App\Tests\Unit\Logger;

use App\Logger\DiscordWebhookHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class DiscordWebhookHandlerTest extends TestCase
{
    private const URL = 'https://discord.com/api/webhooks/1/token';

    /** @var list<array{method: string, url: string, body: array<string, mixed>}> */
    private array $requests = [];

    public function testPostsOneMessagePerBatch(): void
    {
        $handler = $this->handler();

        $handler->handleBatch([
            $this->record(Level::Critical, 'Sync failed', ['exception' => new \RuntimeException('boom')]),
            $this->record(Level::Error, 'Item skipped'),
        ]);

        self::assertCount(1, $this->requests);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame(self::URL, $this->requests[0]['url']);
        self::assertSame(['parse' => []], $this->requests[0]['body']['allowed_mentions']);

        $content = $this->requests[0]['body']['content'];
        self::assertStringContainsString('**CRITICAL** [app] Sync failed (RuntimeException at DiscordWebhookHandlerTest.php:', $content);
        self::assertStringContainsString('**ERROR** [app] Item skipped', $content);
    }

    public function testSkipsRecordsBelowErrorAndClientErrors(): void
    {
        $handler = $this->handler();

        $handler->handleBatch([
            $this->record(Level::Warning, 'Slow response'),
            $this->record(Level::Error, 'No route found for "GET /wp-login.php"', ['exception' => new NotFoundHttpException()]),
        ]);

        self::assertSame([], $this->requests);
    }

    public function testReportsServerErrorHttpExceptions(): void
    {
        $this->handler()->handleBatch([
            $this->record(Level::Critical, 'Down', ['exception' => new ServiceUnavailableHttpException()]),
        ]);

        self::assertCount(1, $this->requests);
    }

    public function testDoesNothingWithoutWebhookUrl(): void
    {
        $this->handler('')->handleBatch([$this->record(Level::Critical, 'Sync failed')]);

        self::assertSame([], $this->requests);
    }

    public function testTruncatesToDiscordLimit(): void
    {
        $records = [];
        for ($i = 0; $i < 20; ++$i) {
            $records[] = $this->record(Level::Error, str_repeat('x', 1000));
        }

        $this->handler()->handleBatch($records);

        $content = $this->requests[0]['body']['content'];
        self::assertLessThanOrEqual(DiscordWebhookHandler::MAX_LENGTH, mb_strlen($content));
        self::assertStringEndsWith('more', $content);
    }

    public function testSwallowsTransportErrors(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Discord is down']));
        $handler = new DiscordWebhookHandler($client, self::URL);

        $handler->handleBatch([$this->record(Level::Critical, 'Sync failed')]);

        self::assertSame(1, $client->getRequestsCount());
    }

    public function testHandleBubbles(): void
    {
        self::assertFalse($this->handler()->handle($this->record(Level::Critical, 'Sync failed')));
        self::assertCount(1, $this->requests);
    }

    private function handler(string $url = self::URL): DiscordWebhookHandler
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'], true)];

            return new MockResponse('', ['http_code' => 204]);
        });

        return new DiscordWebhookHandler($client, $url);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(Level $level, string $message, array $context = []): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), 'app', $level, $message, $context);
    }
}
