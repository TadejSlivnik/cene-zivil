<?php

namespace App\Tests\Unit\Service\Shop;

use App\Entity\Product;
use App\Service\HoferService;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class HoferServiceTest extends TestCase
{
    use AssertsNormalizedItems;

    private function service(array $response)
    {
        return new class($response) extends HoferService {
            public array $requestedUrls = [];

            public function __construct(private array $response)
            {
            }

            public function getJson(string $url): array
            {
                $this->requestedUrls[] = $url;

                return $this->response;
            }
        };
    }

    public function testParsesPage(): void
    {
        $service = $this->service(Fixtures::json('shops/hofer.json'));
        $items = $service->getProductsData(2);

        self::assertStringContainsString('limit=60&offset=120', $service->requestedUrls[0]);
        self::assertCount(2, $items);
        $this->assertNormalizedItems($items);

        $this->assertItem([
            'source' => Product::SOURCE_HOFER,
            'url' => 'https://hofer.si/izdelek/happy-harvest-toast-000000000000100778',
            'title' => 'HAPPY HARVEST, Toast',
            'unit' => 'kg',
            'unitQuantity' => 1,
            'unitPrice' => 1.78,
            'price' => 0.89,
            'regularPrice' => 0.89,
            'discount' => null,
            'ean' => null,
            'productId' => '000000000000100778',
        ], $items[0]);
    }

    public function testProductWithoutComparisonPriceIsSoldPerPiece(): void
    {
        $items = $this->service(Fixtures::json('shops/hofer.json'))->getProductsData(0);

        self::assertSame('Vžigalnik', $items[1]['title']);
        self::assertSame(['kos', 1], [$items[1]['unit'], $items[1]['unitQuantity']]);
        self::assertEqualsWithDelta(1.49, $items[1]['unitPrice'], 0.000001);
    }

    public function testEmptyResponseThrows(): void
    {
        $this->expectExceptionMessageIs('No data found');

        $this->service([])->getProductsData(0);
    }
}
