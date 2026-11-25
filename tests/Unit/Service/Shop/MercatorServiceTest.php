<?php

namespace App\Tests\Unit\Service\Shop;

use App\Entity\Product;
use App\Service\MercatorService;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class MercatorServiceTest extends TestCase
{
    use AssertsNormalizedItems;

    private function service(array $json = [], string $html = '')
    {
        return new class($json, $html) extends MercatorService {
            public array $requestedUrls = [];

            public function __construct(private array $json, private string $html)
            {
            }

            public function getJson(string $url): array
            {
                $this->requestedUrls[] = $url;

                return $this->json;
            }

            public function getHtml(string $url): string
            {
                $this->requestedUrls[] = $url;

                return $this->html;
            }
        };
    }

    public function testParsesCategoryListing(): void
    {
        $service = $this->service(Fixtures::json('shops/mercator.json'));
        $items = $service->getProductsData('14535405');

        self::assertStringContainsString('filterData[categories]=14535405', $service->requestedUrls[0]);
        self::assertCount(2, $items, 'products without "data" are skipped');
        $this->assertNormalizedItems($items);

        $this->assertItem([
            'source' => Product::SOURCE_MERCATOR,
            'url' => 'https://mercatoronline.si/izdelek/17931243/kisla-smetana-mercator-20-m-m-400-g',
            'title' => 'Kisla smetana, Mercator, 20 % m.m., 400 g',
            'unit' => 'kg',
            'unitQuantity' => 1,
            'unitPrice' => 2.475,
            'price' => 0.99,
            'regularPrice' => 1.29,
            'discount' => 23,
            'ean' => ',3838900940273,',
            'productId' => '17931243',
        ], $items[0]);
    }

    public function testPieceProductWithoutUnitPriceAndZeroNormalPrice(): void
    {
        $items = $this->service(Fixtures::json('shops/mercator.json'))->getProductsData('14535405');

        self::assertSame(['kos', 1], [$items[1]['unit'], $items[1]['unitQuantity']]);
        self::assertEqualsWithDelta(1.59, $items[1]['unitPrice'], 0.000001);
        self::assertSame(1.59, $items[1]['regularPrice'], 'a normal price of 0 falls back to the current price');
        self::assertNull($items[1]['discount']);
        self::assertNull($items[1]['ean']);
    }

    public function testParsesProductPage(): void
    {
        $url = 'https://mercatoronline.si/izdelek/17931243/kisla-smetana';
        $data = $this->service(html: Fixtures::contents('shops/mercator-product.html'))->getProductData($url);

        self::assertSame(Product::SOURCE_MERCATOR, $data['source']);
        self::assertSame($url, $data['url']);
        self::assertSame(0.99, $data['price']);
        self::assertSame(1.29, $data['regularPrice']);
        self::assertSame(23, $data['discount']);
        self::assertSame(['kg', 1], [$data['unit'], $data['unitQuantity']]);
        self::assertEqualsWithDelta(2.48, $data['unitPrice'], 0.000001);
    }

    public function testEmptyResponseThrows(): void
    {
        $this->expectExceptionMessageIs('No data found');

        $this->service()->getProductsData('1');
    }
}
