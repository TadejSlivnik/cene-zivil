<?php

namespace App\Tests\Unit\Service\Shop;

use App\Entity\Product;
use App\Service\Api\GeminiApi;
use App\Service\TusService;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class TusServiceTest extends TestCase
{
    use AssertsNormalizedItems;

    private function service(array $itemsResponse, GeminiApi $gemini)
    {
        return new class(new ArrayAdapter(), $gemini, $itemsResponse) extends TusService {
            /** @var list<array{operation: string, headers: array}> */
            public array $requests = [];

            public function __construct(ArrayAdapter $cache, GeminiApi $gemini, private array $itemsResponse)
            {
                parent::__construct($cache, $gemini);
            }

            public function curlRequest(string $url, array $data = [], array $headers = []): ?array
            {
                $this->requests[] = ['operation' => $data['operationName'], 'headers' => $headers];

                return match ($data['operationName']) {
                    'getApiVersion' => ['data' => ['getApiVersion' => '0.4.30']],
                    'createSession' => ['data' => ['createSession' => ['id' => 'session-1']]],
                    'getItemsForSelectedSubCategory' => $this->itemsResponse,
                    default => null,
                };
            }
        };
    }

    public function testParsesSubCategoryAndSendsSessionHeaders(): void
    {
        $gemini = $this->createMock(GeminiApi::class);
        $gemini->expects(self::exactly(2))->method('apiRequest')->willReturnCallback(
            fn (string $query) => str_starts_with($query, 'Calculate unit price') ? [['priceEm' => 0.39]] : [['em' => 'l']]
        );

        $service = $this->service(Fixtures::json('shops/tus.json'), $gemini);
        $items = $service->getProductsData('Pijače', 'Voda');

        $last = end($service->requests);
        self::assertSame('getItemsForSelectedSubCategory', $last['operation']);
        self::assertSame(['apiversion: 0.4.30', 'sessionid: session-1'], $last['headers']);

        self::assertCount(2, $items, 'items without an EAN or price are skipped');
        $this->assertNormalizedItems($items);

        $this->assertItem([
            'source' => Product::SOURCE_TUS,
            'url' => 'https://hitrinakup.com/izdelki/a1',
            'title' => 'Kava mleta 500 g',
            'unit' => 'kg',
            'unitQuantity' => 1,
            'unitPrice' => 11.98,
            'price' => 5.99,
            'regularPrice' => 6.99,
            'discount' => 14,
            'ean' => ',3838600000011,',
            'productId' => 'a1',
        ], $items[0]);

        // missing unit price and unit are filled in by Gemini
        self::assertSame('l', $items[1]['unit']);
        self::assertEqualsWithDelta(0.39, $items[1]['unitPrice'], 0.000001);
        self::assertNull($items[1]['discount']);
    }

    public function testApiVersionAndSessionAreCachedAcrossCalls(): void
    {
        $gemini = $this->createStub(GeminiApi::class);
        $gemini->method('apiRequest')->willReturn([]);

        $service = $this->service(Fixtures::json('shops/tus.json'), $gemini);
        $service->getProductsData('A', 'B');
        $service->getProductsData('A', 'C');

        self::assertSame(
            ['getApiVersion', 'createSession', 'getItemsForSelectedSubCategory', 'getItemsForSelectedSubCategory'],
            array_column($service->requests, 'operation')
        );
    }

    public function testEmptyResponseThrows(): void
    {
        $this->expectExceptionMessageIs('No items found for category: A / B');

        $this->service([], $this->createStub(GeminiApi::class))->getProductsData('A', 'B');
    }
}
