<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testSetSourceAcceptsKnownSources(): void
    {
        foreach (array_keys(Product::SOURCES) as $source) {
            self::assertSame($source, (new Product())->setSource($source)->getSource());
        }
    }

    public function testSetSourceRejectsUnknownSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Product())->setSource('konzum');
    }

    public function testGetTrgovinaReturnsDisplayName(): void
    {
        self::assertSame('Tuš', (new Product())->setSource(Product::SOURCE_TUS)->getTrgovina());
        self::assertNull((new Product())->getTrgovina());
    }

    public function testGetEanArraySplitsCommaWrappedList(): void
    {
        self::assertSame([], (new Product())->getEanArray());

        $product = (new Product())->setEan(',4066447521153,3838900940273,');
        self::assertSame(['4066447521153', '3838900940273'], array_values($product->getEanArray()));
    }

    public function testIsUpToDateWithinSevenDays(): void
    {
        $product = new Product();
        self::assertFalse($product->isUpToDate());

        $product->setPriceUpdatedAt(new \DateTime('-6 days'));
        self::assertTrue($product->isUpToDate());

        $product->setPriceUpdatedAt(new \DateTime('-8 days'));
        self::assertFalse($product->isUpToDate());
    }

    public function testUpdatedToday(): void
    {
        $product = new Product();
        self::assertFalse($product->updatedToday());

        $product->setPriceUpdatedAt(new \DateTime());
        self::assertTrue($product->updatedToday());

        $product->setPriceUpdatedAt(new \DateTime('-1 day'));
        self::assertFalse($product->updatedToday());
    }

    public function testPriceHistoryAssociationIsKeptInSync(): void
    {
        $product = new Product();
        $history = new ProductPriceHistory();

        $product->addPriceHistory($history);
        $product->addPriceHistory($history);
        self::assertSame(1, $product->getPriceHistoriesCount());
        self::assertSame($product, $history->getProduct());

        $product->removePriceHistory($history);
        self::assertSame(0, $product->getPriceHistoriesCount());
        self::assertNull($history->getProduct());
    }
}
