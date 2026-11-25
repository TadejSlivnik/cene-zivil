<?php

namespace App\Tests\Integration\Command;

use App\Entity\CommandLog;
use App\Entity\CommandQueueImage;
use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use App\Service\DmService;
use App\Service\HoferService;
use App\Service\SparService;
use App\Tests\Support\ProductFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs the real sync commands against the test database with the shop services stubbed out.
 */
final class SyncCommandTest extends KernelTestCase
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

    private function item(array $overrides = []): array
    {
        return array_merge([
            'source' => Product::SOURCE_DM,
            'productId' => 'dm-1',
            'title' => 'Šampon 300 ml',
            'url' => 'https://www.dm.si/p/d/1',
            'price' => 2.45,
            'regularPrice' => 2.45,
            'discount' => null,
            'unit' => 'l',
            'unitPrice' => 8.1667,
            'unitQuantity' => 1,
            'ean' => ',4058172000001,',
        ], $overrides);
    }

    /**
     * @param array<string, object> $services service id => stub
     */
    private function runCommand(string $name, array $services = []): CommandTester
    {
        foreach ($services as $id => $service) {
            self::getContainer()->set($id, $service);
        }

        $tester = new CommandTester((new Application(self::$kernel))->find($name));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->getEntityManager()->clear();

        return $tester;
    }

    private function dmReturning(array ...$items): DmService
    {
        $dm = $this->createStub(DmService::class);
        $dm->method('getProductsData')->willReturn($items);

        return $dm;
    }

    /**
     * A service can only be replaced before it is first used, so multi-run tests get one stub.
     */
    private function dmReturningPerRun(array ...$runs): DmService
    {
        $dm = $this->createStub(DmService::class);
        $dm->method('getProductsData')->willReturn(...array_values($runs));

        return $dm;
    }

    private function findProduct(string $source, string $productId): ?Product
    {
        // No soft-delete filter is configured, so this also finds soft-deleted products
        return $this->getEntityManager()->getRepository(Product::class)->findOneBy(['source' => $source, 'productId' => $productId]);
    }

    private function historyCount(Product $product): int
    {
        return $this->getEntityManager()->getRepository(ProductPriceHistory::class)->count(['product' => $product]);
    }

    private function commandLog(string $command): CommandLog
    {
        return $this->getEntityManager()->getRepository(CommandLog::class)->find($command);
    }

    public function testNewProductsAreCreatedWithPriceHistory(): void
    {
        $tester = $this->runCommand('app:sync:dm', [DmService::class => $this->dmReturning($this->item(), $this->item(['productId' => 'dm-2', 'title' => 'Krema']))]);

        self::assertStringContainsString('2 products updated', $tester->getDisplay());
        $product = $this->findProduct(Product::SOURCE_DM, 'dm-1');
        self::assertSame('Šampon 300 ml', $product->getTitle());
        self::assertSame(',4058172000001,', $product->getEan());
        self::assertNotNull($product->getCreatedAt(), 'Gedmo Timestampable sets createdAt');
        self::assertTrue($product->updatedToday());
        self::assertSame(1, $this->historyCount($product));
        self::assertSame(1, $this->commandLog('app:sync:dm')->getDailyRun());
    }

    public function testUnchangedPriceDoesNotAddHistoryButOtherFieldsUpdate(): void
    {
        $this->runCommand('app:sync:dm', [DmService::class => $this->dmReturningPerRun([$this->item()], [$this->item(['title' => 'Šampon 300 ml NOVO'])])]);
        $this->runCommand('app:sync:dm');

        $product = $this->findProduct(Product::SOURCE_DM, 'dm-1');
        self::assertSame('Šampon 300 ml NOVO', $product->getTitle());
        self::assertSame(1, $this->historyCount($product));
        self::assertSame(2, $this->commandLog('app:sync:dm')->getDailyRun());
    }

    public function testPriceChangeAddsHistory(): void
    {
        $this->runCommand('app:sync:dm', [DmService::class => $this->dmReturningPerRun([$this->item()], [$this->item(['price' => 1.99, 'discount' => 19])])]);
        $this->runCommand('app:sync:dm');

        $product = $this->findProduct(Product::SOURCE_DM, 'dm-1');
        self::assertEqualsWithDelta(1.99, $product->getPrice(), 0.000001);
        self::assertSame(19, $product->getDiscount());
        self::assertSame(2, $this->historyCount($product));
    }

    public function testDuplicateItemsInOneRunAreHandledOnce(): void
    {
        $this->runCommand('app:sync:dm', [DmService::class => $this->dmReturning($this->item(), $this->item(['price' => 9.99]))]);

        $product = $this->findProduct(Product::SOURCE_DM, 'dm-1');
        self::assertEqualsWithDelta(2.45, $product->getPrice(), 0.000001, 'the first occurrence wins');
        self::assertSame(1, $this->historyCount($product));
    }

    public function testSoftDeletedProductIsRestoredWhenSeenAgain(): void
    {
        $this->createProduct(['source' => Product::SOURCE_DM, 'productId' => 'dm-1', 'price' => 2.45, 'regularPrice' => 2.45, 'unit' => 'l', 'unitPrice' => 8.1667, 'deletedAt' => new \DateTime('-5 days')]);

        $this->runCommand('app:sync:dm', [DmService::class => $this->dmReturning($this->item())]);

        self::assertNull($this->findProduct(Product::SOURCE_DM, 'dm-1')->getDeletedAt());
    }

    public function testProductWithEanImageButNoEanIsQueuedForGemini(): void
    {
        $spar = $this->createStub(SparService::class);
        $spar->method('getProductsData')->willReturn([$this->item(['source' => Product::SOURCE_SPAR, 'productId' => 'spar-1', 'ean' => null, 'eanImage' => 'https://cdn.spar.si/1.jpg'])]);

        $this->runCommand('app:sync:spar', [SparService::class => $spar]);

        $queue = $this->getEntityManager()->getRepository(CommandQueueImage::class)->findAll();
        self::assertCount(1, $queue);
        self::assertSame('https://cdn.spar.si/1.jpg', $queue[0]->getImageUrl());
        self::assertSame('spar-1', $queue[0]->getProduct()->getProductId());
    }

    public function testCommandDoesNothingOnceCompletedToday(): void
    {
        $dm = $this->createMock(DmService::class);
        $dm->expects(self::never())->method('getProductsData');

        $log = (new CommandLog())->setCommand('app:sync:dm')->setCompletedAt(new \DateTime());
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();

        $tester = $this->runCommand('app:sync:dm', [DmService::class => $dm]);

        self::assertStringContainsString('Command already completed today', $tester->getDisplay());
    }

    public function testCompletesAfterTheLastCategory(): void
    {
        $dm = $this->createMock(DmService::class);
        $dm->expects(self::never())->method('getProductsData');

        $log = (new CommandLog())->setCommand('app:sync:dm')->setDailyRun(8);
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();

        $this->runCommand('app:sync:dm', [DmService::class => $dm]);

        self::assertNotNull($this->commandLog('app:sync:dm')->getCompletedAt());
    }

    public function testDailyRunResetsOnANewDay(): void
    {
        $log = (new CommandLog())->setCommand('app:sync:dm')->setDailyRun(5)->setCompletedAt(new \DateTime('-1 day'));
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->getConnection()->executeStatement("UPDATE command_log SET updated_at = NOW() - INTERVAL 1 DAY WHERE command = 'app:sync:dm'");
        $this->getEntityManager()->clear();

        $this->runCommand('app:sync:dm', [DmService::class => $this->dmReturning($this->item())]);

        self::assertSame(1, $this->commandLog('app:sync:dm')->getDailyRun(), 'category 0 processed after the reset');
    }

    public function testLastHoferPageSoftDeletesStaleHoferProducts(): void
    {
        $stale = $this->createProduct(['source' => Product::SOURCE_HOFER, 'productId' => 'h-stale']);
        $recent = $this->createProduct(['source' => Product::SOURCE_HOFER, 'productId' => 'h-recent']);
        $otherShop = $this->createProduct(['source' => Product::SOURCE_LIDL, 'productId' => 'l-stale']);
        $this->backdate($stale, 'updated_at', new \DateTime('-40 days'));
        $this->backdate($recent, 'updated_at', new \DateTime('-10 days'));
        $this->backdate($otherShop, 'updated_at', new \DateTime('-40 days'));

        $hofer = $this->createStub(HoferService::class);
        $hofer->method('getProductsData')->willReturn([$this->item(['source' => Product::SOURCE_HOFER, 'productId' => 'h-new', 'ean' => null])]);

        $this->runCommand('app:sync:hofer', [HoferService::class => $hofer]);

        self::assertNotNull($this->findProduct(Product::SOURCE_HOFER, 'h-stale')->getDeletedAt(), 'not updated for 30+ days');
        self::assertNull($this->findProduct(Product::SOURCE_HOFER, 'h-recent')->getDeletedAt());
        self::assertNull($this->findProduct(Product::SOURCE_HOFER, 'h-new')->getDeletedAt());
        self::assertNull($this->findProduct(Product::SOURCE_LIDL, 'l-stale')->getDeletedAt(), 'other shops are untouched');
        self::assertNotNull($this->commandLog('app:sync:hofer')->getCompletedAt(), 'fewer than 60 items means the last page');
    }

    public function testFullHoferPageDoesNotComplete(): void
    {
        $items = array_map(fn ($i) => $this->item(['source' => Product::SOURCE_HOFER, 'productId' => "h-$i", 'ean' => null]), range(1, HoferService::ITEMS_PER_PAGE));
        $hofer = $this->createStub(HoferService::class);
        $hofer->method('getProductsData')->willReturn($items);

        $this->runCommand('app:sync:hofer', [HoferService::class => $hofer]);

        self::assertNull($this->commandLog('app:sync:hofer')->getCompletedAt());
        self::assertSame(1, $this->commandLog('app:sync:hofer')->getDailyRun());
    }
}
