<?php

namespace App\Tests\Unit\Service\Shop;

use App\Entity\Product;

/**
 * Every shop service must return items in the shape AbstractSyncCommand::updateProducts() expects.
 */
trait AssertsNormalizedItems
{
    private const REQUIRED_KEYS = ['source', 'productId', 'title', 'url', 'price', 'regularPrice', 'discount', 'unit', 'unitPrice', 'unitQuantity'];
    private const ALLOWED_KEYS = [...self::REQUIRED_KEYS, 'ean', 'eanImage', 'promotionEndsDate'];
    private const UNITS = ['kg', 'l', 'm', 'kos'];

    private function assertNormalizedItems(array $items): void
    {
        foreach ($items as $i => $item) {
            $context = "item #$i";
            foreach (self::REQUIRED_KEYS as $key) {
                self::assertArrayHasKey($key, $item, $context);
            }
            self::assertSame([], array_diff(array_keys($item), self::ALLOWED_KEYS), "$context has unexpected keys");

            self::assertArrayHasKey($item['source'], Product::SOURCES, $context);
            self::assertNotSame('', trim((string) $item['title']), $context);
            self::assertStringStartsWith('https://', $item['url'], $context);
            self::assertContains($item['unit'], self::UNITS, $context);
            self::assertIsInt($item['unitQuantity'], $context);
            self::assertGreaterThan(0, $item['price'], $context);
            self::assertGreaterThanOrEqual($item['price'], $item['regularPrice'], "$context: regular price below price");
            self::assertGreaterThan(0, $item['unitPrice'], $context);
            if (null !== ($item['discount'] ?? null)) {
                self::assertIsInt($item['discount'], $context);
            }
            if (null !== ($item['ean'] ?? null)) {
                self::assertMatchesRegularExpression('/^,(\d{8,13},)+$/', $item['ean'], $context);
            }
        }
    }

    /**
     * Strict comparison regardless of key order. Unit prices are floats computed by
     * multiplication, so they are compared with a delta.
     */
    private function assertItem(array $expected, array $item): void
    {
        self::assertEqualsWithDelta($expected['unitPrice'], $item['unitPrice'], 0.000001, 'unitPrice');
        unset($expected['unitPrice'], $item['unitPrice']);
        ksort($expected);
        ksort($item);

        self::assertSame($expected, $item);
    }
}
