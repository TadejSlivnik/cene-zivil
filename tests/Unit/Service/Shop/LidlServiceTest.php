<?php

namespace App\Tests\Unit\Service\Shop;

use App\Entity\Product;
use App\Service\LidlService;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class LidlServiceTest extends TestCase
{
    use AssertsNormalizedItems;

    private function service(array $response)
    {
        return new class($response) extends LidlService {
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
        $service = $this->service(Fixtures::json('shops/lidl.json'));
        $items = $service->getProductsData(3);

        self::assertStringContainsString('offset=200&fetchsize=100', $service->requestedUrls[0]);
        self::assertCount(3, $items, 'items without a title are skipped');
        $this->assertNormalizedItems($items);

        $promotionEndsDate = $items[0]['promotionEndsDate'];
        unset($items[0]['promotionEndsDate']);
        $this->assertItem([
            'source' => Product::SOURCE_LIDL,
            'url' => 'https://www.lidl.si/p/favorina-praznicna-skodelica-s-cokolado/p11000033',
            'title' => 'FAVORINA Praznična skodelica s čokolado',
            'unit' => 'kg',
            'unitQuantity' => 1,
            'unitPrice' => 45.36,
            'price' => 4.99,
            'regularPrice' => 4.99,
            'discount' => null,
            'ean' => null,
            'productId' => '11000033',
        ], $items[0]);

        self::assertInstanceOf(\DateTime::class, $promotionEndsDate, 'DBAL\'s datetime type needs a mutable DateTime');
        self::assertSame(1798325999, $promotionEndsDate->getTimestamp());
        self::assertSame('Europe/Ljubljana', $promotionEndsDate->getTimezone()->getName());
    }

    public function testLidlPlusPriceWins(): void
    {
        $items = $this->service(Fixtures::json('shops/lidl.json'))->getProductsData(1);

        self::assertSame(0.89, $items[1]['price']);
        self::assertSame(1.19, $items[1]['regularPrice']);
        self::assertSame(25, $items[1]['discount'], 'percentageDiscount from the API is used as-is');
        self::assertSame('l', $items[1]['unit']);
        self::assertNull($items[1]['promotionEndsDate']);
    }

    public function testProductWithoutBasePriceIsSoldPerPiece(): void
    {
        $items = $this->service(Fixtures::json('shops/lidl.json'))->getProductsData(1);

        self::assertSame(['kos', 1], [$items[2]['unit'], $items[2]['unitQuantity']]);
        self::assertEqualsWithDelta(12.99, $items[2]['unitPrice'], 0.000001);
        self::assertSame(19, $items[2]['discount']);
    }

    public function testPageMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service([])->getProductsData(0);
    }

    public function testEmptyResponseThrows(): void
    {
        $this->expectExceptionMessageIs('No data found.');

        $this->service(['items' => []])->getProductsData(1);
    }
}
