<?php

namespace App\Tests\Unit\Service;

use App\Service\AbstractShopService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AbstractShopServiceTest extends TestCase
{
    private AbstractShopService $service;

    protected function setUp(): void
    {
        $this->service = new class extends AbstractShopService {
            public function getProductData(string $url): array
            {
                return [];
            }
        };
    }

    public static function priceProvider(): iterable
    {
        yield 'comma decimal with euro sign' => ['2,65 €', 2.65];
        yield 'previous price label' => ['prej 3,65 €', 3.65];
        yield 'dot decimal' => ['0.99', 0.99];
        yield 'surrounding whitespace' => ['  12,00 € ', 12.0];
    }

    #[DataProvider('priceProvider')]
    public function testParsePrice(string $input, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->service->parsePrice($input), 0.000001);
    }

    public static function eanProvider(): iterable
    {
        yield 'single EAN-13' => ['4066447521153', ',4066447521153,'];
        yield 'EAN-8' => ['12345678', ',12345678,'];
        yield 'several, trimmed' => ['4066447521153, 3838900940273', ',4066447521153,3838900940273,'];
        yield 'invalid entries are dropped' => ['123,4066447521153,abc', ',4066447521153,'];
        yield 'too long (14 digits) is dropped' => ['12345678901234', null];
        yield 'only invalid' => ['123,abc', null];
        yield 'empty string' => ['', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('eanProvider')]
    public function testParseEanBuildsCommaWrappedList(?string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->service->parseEan($input));
    }

    public static function discountProvider(): iterable
    {
        yield 'discounted' => [2.45, 3.65, 33];
        yield 'rounded' => [0.99, 1.29, 23];
        yield 'same price' => [1.00, 1.00, null];
        yield 'price above regular price' => [2.00, 1.50, null];
    }

    #[DataProvider('discountProvider')]
    public function testGetDiscount(float $price, float $regularPrice, ?int $expected): void
    {
        self::assertSame($expected, $this->service->getDiscount($price, $regularPrice));
    }

    public static function unitProvider(): iterable
    {
        // [unit base, unit price, price] => [unit, quantity, unit price per kg/l/m/kos]
        yield 'ml to l' => ['ml', 0.005, 2.50, ['l', 1, 5.0]];
        yield '1 ml to l' => ['1 ml', 0.005, 2.50, ['l', 1, 5.0]];
        yield '10 ml to l' => ['10ml', 0.05, 2.50, ['l', 1, 5.0]];
        yield '100 ml to l' => ['100 ml', 0.82, 2.45, ['l', 1, 8.2]];
        yield 'litre' => ['1 l', 1.29, 1.29, ['l', 1, 1.29]];
        yield '10 l to l' => ['10l', 12.0, 12.0, ['l', 1, 1.2]];
        yield '100 l to l' => ['100l', 120.0, 120.0, ['l', 1, 1.2]];
        yield 'g to kg' => ['g', 0.002, 1.0, ['kg', 1, 2.0]];
        yield '10 g to kg' => ['10g', 0.02, 1.0, ['kg', 1, 2.0]];
        yield '100 g to kg' => ['100 g', 0.53, 2.65, ['kg', 1, 5.3]];
        yield 'kg, upper case' => ['1 KG', 1.78, 0.89, ['kg', 1, 1.78]];
        yield 'metre' => ['m', 0.5, 5.0, ['m', 1, 0.5]];
        yield '10 m to m' => ['10m', 5.0, 5.0, ['m', 1, 0.5]];
        yield '100 m to m' => ['100m', 50.0, 50.0, ['m', 1, 0.5]];
        yield 'pair is two pieces' => ['par', 10.0, 10.0, ['kos', 2, 5.0]];
        yield 'unknown unit falls back to piece price' => ['pak', 3.0, 4.99, ['kos', 1, 4.99]];
    }

    #[DataProvider('unitProvider')]
    public function testUnitPriceCalculationNormalisesUnits(string $unitBase, float $unitPrice, float $price, array $expected): void
    {
        [$unit, $quantity, $normalisedUnitPrice] = $this->service->unitPriceCalculation($unitBase, $unitPrice, $price);

        self::assertSame($expected[0], $unit);
        self::assertSame($expected[1], $quantity);
        self::assertEqualsWithDelta($expected[2], $normalisedUnitPrice, 0.000001);
    }
}
