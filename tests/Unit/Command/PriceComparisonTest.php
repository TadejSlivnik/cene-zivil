<?php

namespace App\Tests\Unit\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Covers the price comparison that decides whether a sync writes a ProductPriceHistory row.
 */
final class PriceComparisonTest extends TestCase
{
    private ExposedSyncCommand $command;

    protected function setUp(): void
    {
        $this->command = new ExposedSyncCommand(new ParameterBag(['kernel.environment' => 'test']), new NullLogger(), $this->createStub(EntityManagerInterface::class));
    }

    public static function normalizeProvider(): iterable
    {
        yield 'float' => [1.5, '1.500000'];
        yield 'int' => [2, '2.000000'];
        yield 'decimal string from the database' => ['0.990000', '0.990000'];
        yield 'comma decimal string' => ['1,5', '1.500000'];
        yield 'rounds to 6 decimals' => [0.1234567, '0.123457'];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalizePrice(mixed $input, string $expected): void
    {
        self::assertSame($expected, $this->command->normalizePrice($input));
    }

    public function testNormalizePriceRejectsNonNumericTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->command->normalizePrice(null);
    }

    public function testNewProductCountsAsPriceChange(): void
    {
        self::assertTrue($this->command->priceChanged(null, $this->item()));
    }

    public function testUnchangedPricesAreNotAChangeEvenWhenStoredAsDecimalStrings(): void
    {
        self::assertFalse($this->command->priceChanged($this->product(), $this->item()));
    }

    public static function changedFieldProvider(): iterable
    {
        yield 'price' => ['price', 0.89];
        yield 'regular price' => ['regularPrice', 1.49];
        yield 'unit' => ['unit', 'l'];
        yield 'unit price' => ['unitPrice', 2.0];
        yield 'discount' => ['discount', 10];
    }

    #[DataProvider('changedFieldProvider')]
    public function testAnyPriceFieldChangeIsDetected(string $field, mixed $value): void
    {
        self::assertTrue($this->command->priceChanged($this->product(), $this->item([$field => $value])));
    }

    private function item(array $overrides = []): array
    {
        return array_merge(['price' => 0.99, 'regularPrice' => 1.29, 'unit' => 'kg', 'unitPrice' => 2.475, 'discount' => 23], $overrides);
    }

    private function product(): Product
    {
        // Mimic values hydrated from decimal(20,6) columns
        $product = new Product();
        $reflection = new \ReflectionObject($product);
        foreach (['price' => '0.990000', 'regularPrice' => '1.290000', 'unitPrice' => '2.475000'] as $property => $value) {
            $reflection->getProperty($property)->setValue($product, $value);
        }

        return $product->setUnit('kg')->setDiscount(23);
    }
}
