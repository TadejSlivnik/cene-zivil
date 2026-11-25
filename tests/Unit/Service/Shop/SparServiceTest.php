<?php

namespace App\Tests\Unit\Service\Shop;

use App\Entity\Product;
use App\Service\SparService;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class SparServiceTest extends TestCase
{
    use AssertsNormalizedItems;

    private function service(array $response)
    {
        return new class($response) extends SparService {
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

    public function testParsesCategory(): void
    {
        $service = $this->service(Fixtures::json('shops/spar.json'));
        $items = $service->getProductsData('s1');

        self::assertStringContainsString('filter=category-path:S1', $service->requestedUrls[0], 'category is upper-cased');
        self::assertCount(2, $items, 'hits without a price are skipped');
        $this->assertNormalizedItems($items);

        $this->assertItem([
            'source' => Product::SOURCE_SPAR,
            'url' => 'https://online.spar.si/p/504441',
            'title' => 'Jogurt navadni 3,2 %, 180 g',
            'unit' => 'kg',
            'unitQuantity' => 1,
            'unitPrice' => 3.06,
            'price' => 0.55,
            'regularPrice' => 0.69,
            'discount' => 20,
            'ean' => null,
            'eanImage' => 'https://cdn.spar.si/images/504441.jpg',
            'productId' => '504441',
        ], $items[0]);
    }

    public function testMissingRegularPriceFallsBackToPrice(): void
    {
        $items = $this->service(Fixtures::json('shops/spar.json'))->getProductsData('S1');

        self::assertSame(1.29, $items[1]['regularPrice']);
        self::assertNull($items[1]['discount']);
        self::assertNull($items[1]['eanImage']);
        self::assertSame('l', $items[1]['unit']);
    }

    public function testEmptyResponseThrows(): void
    {
        $this->expectExceptionMessageIs('No data found for category: S1');

        $this->service([])->getProductsData('s1');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('imageUrls')]
    public function testOnlyHttpsSparImageUrlsAreAllowed(string $url, bool $allowed): void
    {
        self::assertSame($allowed, $this->service([])->isImageUrlAllowed($url));
    }

    public static function imageUrls(): iterable
    {
        yield 'cdn image' => ['https://cdn.spar.si/images/504441.jpg', true];
        yield 'apex domain with query' => ['https://spar.si/img/dt_main.jpg?v=2', true];
        yield 'plain http' => ['http://cdn.spar.si/images/1.jpg', false];
        yield 'local file' => ['file:///etc/passwd', false];
        yield 'php wrapper' => ['php://filter/convert.base64-encode/resource=/app/.env.local', false];
        yield 'other host' => ['https://example.com/1.jpg', false];
        yield 'lookalike suffix' => ['https://evilspar.si/1.jpg', false];
        yield 'spar as subdomain' => ['https://cdn.spar.si.example.com/1.jpg', false];
        yield 'userinfo' => ['https://cdn.spar.si@127.0.0.1/1.jpg', false];
        yield 'userinfo after path' => ['https://cdn.spar.si/x@127.0.0.1/1.jpg', false];
        yield 'port' => ['https://cdn.spar.si:8080/1.jpg', false];
        yield 'fragment host trick' => ['https://127.0.0.1#.spar.si/1.jpg', false];
        yield 'backslash' => ['https://cdn.spar.si\\@127.0.0.1/1.jpg', false];
        yield 'no path' => ['https://cdn.spar.si', false];
    }
}
