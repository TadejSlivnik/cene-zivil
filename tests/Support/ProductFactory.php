<?php

namespace App\Tests\Support;

use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and persists products for database tests. Every test runs inside a
 * transaction that DAMADoctrineTestBundle rolls back, so nothing needs cleaning up.
 */
trait ProductFactory
{
    abstract protected function getEntityManager(): EntityManagerInterface;

    protected function createProduct(array $overrides = []): Product
    {
        static $sequence = 0;
        ++$sequence;

        $data = array_merge([
            'source' => Product::SOURCE_MERCATOR,
            'productId' => 'test-'.$sequence,
            'title' => 'Testni izdelek '.$sequence,
            'price' => 1.00,
            'regularPrice' => 1.00,
            'discount' => null,
            'unit' => 'kos',
            'unitPrice' => 1.00,
            'unitQuantity' => 1,
            'url' => 'https://example.test/'.$sequence,
            'ean' => null,
            'priceUpdatedAt' => new \DateTime(),
            'deletedAt' => null,
        ], $overrides);

        $product = (new Product())
            ->setSource($data['source'])
            ->setProductId($data['productId'])
            ->setTitle($data['title'])
            ->setPrice($data['price'])
            ->setRegularPrice($data['regularPrice'])
            ->setDiscount($data['discount'])
            ->setUnit($data['unit'])
            ->setUnitPrice($data['unitPrice'])
            ->setUnitQuantity($data['unitQuantity'])
            ->setUrl($data['url'])
            ->setEan($data['ean'])
            ->setPriceUpdatedAt($data['priceUpdatedAt'])
            ->setDeletedAt($data['deletedAt']);

        $em = $this->getEntityManager();
        $em->persist($product);
        $em->flush();

        return $product;
    }

    protected function createPriceHistory(Product $product, array $overrides = []): ProductPriceHistory
    {
        $history = (new ProductPriceHistory())
            ->setProduct($product)
            ->setPrice($overrides['price'] ?? $product->getPrice())
            ->setRegularPrice($overrides['regularPrice'] ?? $product->getRegularPrice())
            ->setDiscount(array_key_exists('discount', $overrides) ? $overrides['discount'] : $product->getDiscount())
            ->setUnitPrice($product->getUnitPrice())
            ->setUnit($product->getUnit())
            ->setUnitQuantity($product->getUnitQuantity())
            ->setSource($product->getSource())
            ->setPromotionEndsDate($overrides['promotionEndsDate'] ?? null);

        $em = $this->getEntityManager();
        $em->persist($history);
        $em->flush();

        return $history;
    }

    /**
     * Gedmo overwrites updated_at/created_at on flush, so backdate them with SQL.
     */
    protected function backdate(Product $product, string $column, \DateTimeInterface $date): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            "UPDATE product SET $column = :date WHERE id = :id",
            ['date' => $date->format('Y-m-d H:i:s'), 'id' => $product->getId()]
        );
    }
}
