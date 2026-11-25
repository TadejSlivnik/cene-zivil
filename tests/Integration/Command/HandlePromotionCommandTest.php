<?php

namespace App\Tests\Integration\Command;

use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use App\Tests\Support\ProductFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class HandlePromotionCommandTest extends KernelTestCase
{
    use ProductFactory;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testEndedPromotionResetsPriceToRegularPrice(): void
    {
        $ended = $this->createProduct(['source' => Product::SOURCE_LIDL, 'price' => 0.89, 'regularPrice' => 1.19, 'discount' => 25]);
        $endedHistory = $this->createPriceHistory($ended, ['promotionEndsDate' => new \DateTime('-1 day')]);
        $running = $this->createProduct(['source' => Product::SOURCE_LIDL, 'price' => 2.00, 'regularPrice' => 3.00, 'discount' => 33]);
        $this->createPriceHistory($running, ['promotionEndsDate' => new \DateTime('+3 days')]);

        $tester = new CommandTester((new Application(self::$kernel))->find('app:handle:promotions'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $em = $this->getEntityManager();
        $em->clear();

        $ended = $em->find(Product::class, $ended->getId());
        self::assertEqualsWithDelta(1.19, $ended->getPrice(), 0.000001);
        self::assertNotNull($em->find(ProductPriceHistory::class, $endedHistory->getId())->getPromotionHandled());
        self::assertSame(2, $em->getRepository(ProductPriceHistory::class)->count(['product' => $ended]), 'a history row records the reset');

        $running = $em->find(Product::class, $running->getId());
        self::assertEqualsWithDelta(2.00, $running->getPrice(), 0.000001);

        // Running it again does not handle the same promotion twice
        $tester->execute([]);
        $em->clear();
        self::assertSame(2, $em->getRepository(ProductPriceHistory::class)->count(['product' => $ended]));
    }
}
