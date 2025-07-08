<?php

namespace App\Command;

use App\Entity\CommandLog;
use App\Entity\CommandQueueImage;
use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class AbstractSyncCommand extends AbstractCommand
{
    const DB_BATCH = 100;

    protected $em;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em)
    {
        parent::__construct($parameterBag, $logger);
        $this->em = $em;
    }

    protected function getCommandLog(): CommandLog
    {
        $commandLog = $this->em->getRepository(CommandLog::class)->findOneBy(['command' => $this->getName()]);
        if (!$commandLog instanceof CommandLog) {
            $commandLog = new CommandLog();
            $commandLog->setCommand($this->getName());
            if ($this->getName() === 'app:sync:tus') {
                $commandLog->setMaxDailyRuns(200);
            }
            $this->em->persist($commandLog);
        }
        return $commandLog;
    }

    protected function shouldCommandRun(CommandLog $commandLog): bool
    {
        if ($commandLog->getCompletedAt() && $commandLog->getCompletedAt()->format('Y-m-d') == (new \DateTime())->format('Y-m-d')) {
            $this->io->writeln($this->getName() . ': Command already completed today.');
            return false;
        }

        if ($commandLog->getUpdatedAt() && $commandLog->getUpdatedAt()->format('Y-m-d') < (new \DateTime())->format('Y-m-d')) {
            $commandLog->setDailyRun(0);
        }

        if ($commandLog->getDailyRun() >= $commandLog->getMaxDailyRuns()) {
            $this->io->writeln($this->getName() . ': Daily run limit reached. Max daily runs: ' . $commandLog->getMaxDailyRuns());
            return false;
        }

        return true;
    }

    protected function markProductsAsDeletedIfOlderThanDays(int $days, string $source): void
    {
        $date = new \DateTime('midnight');
        $date->setTimezone(new \DateTimeZone('Europe/Ljubljana'));
        $date->modify('-' . $days . ' days');
        $this->io->writeln('Marking products as deleted if older than ' . $days . ' days. Date: ' . $date->format('Y-m-d H:i:s'));

        $this->em->createQueryBuilder()
            ->update(Product::class, 'p')
            ->set('p.deletedAt', ':deletedAt')->setParameter('deletedAt', new \DateTime())
            ->andWhere('p.updatedAt < :date')->setParameter('date', $date)
            ->andWhere('p.source = :source')->setParameter('source', $source)
            ->getQuery()
            ->execute();
    }

    protected function updateProducts(array $items): void
    {
        $progressBar = $this->io->createProgressBar(count($items));

        $k = 0;
        $handled = [];
        foreach ($items as $item) {

            try {
                if (in_array($item['productId'], $handled)) {
                    continue;
                }

                if ($item['productId']) {
                    $handled[] = $item['productId'];
                    $product = $this->em->getRepository(Product::class)->findOneBy(['source' => $item['source'], 'productId' => $item['productId']]);
                } else {
                    $product = $this->em->getRepository(Product::class)->findOneBy(['source' => $item['source'], 'title' => $item['title']]);
                }

                // product price changed or is new product
                $productPriceChanged = $this->productPriceChanged($product, $item);
                if (!$product instanceof Product) {
                    $product = new Product();
                    $product->setSource($item['source']);
                    $product->setProductId($item['productId']);
                    $this->em->persist($product);
                }

                $product->setTitle($item['title']);
                $product->setPrice($item['price']);
                $product->setRegularPrice($item['regularPrice']);
                $product->setUrl($item['url']);
                $product->setUnit($item['unit']);
                $product->setUnitPrice($item['unitPrice']);
                $product->setUnitQuantity($item['unitQuantity']);
                $product->setDiscount($item['discount']);
                $product->setDeletedAt(null);
                $product->setPriceUpdatedAt(new \DateTime());

                if ($productPriceChanged) {
                    $this->createProductPriceHistory($product);
                }

                if (isset($item['ean']) && $item['ean']) {
                    $product->setEan($item['ean']);
                } elseif (!$product->getEan() && isset($item['eanImage']) && $item['eanImage']) {
                    $this->em->flush();

                    $commandQueueImage = $this->em->getRepository(CommandQueueImage::class)->findOneBy(['product' => $product, 'completedAt' => null]);
                    if (!$commandQueueImage instanceof CommandQueueImage) {
                        $commandQueueImage = new CommandQueueImage();
                        $commandQueueImage->setProduct($product);
                        $commandQueueImage->setImageUrl($item['eanImage']);
                        $this->em->persist($commandQueueImage);
                    } else {
                        $commandQueueImage->setImageUrl($item['eanImage']);
                    }

                    $this->em->flush();
                    $this->em->clear();
                    $k = 0;
                }

                if (++$k % static::DB_BATCH === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }

                $progressBar->advance();
            } catch (\Throwable $th) {
                if ($this->parameterBag->get('kernel.environment') === 'dev') {
                    dump($item);
                    throw $th;
                }

                $this->logger->error($th->getMessage(), [
                    'command' => $this->getName(),
                    'item' => $item,
                    'exception' => $th,
                ]);
            }
        }

        $progressBar->finish();

        $this->em->flush();
        $this->em->clear();
    }

    protected function updateProductPrice(Product $product, array $item): void
    {
        $product->setPrice($item['price']);
        $product->setRegularPrice($item['regularPrice']);
        $product->setUnit($item['unit']);
        $product->setUnitPrice($item['unitPrice']);
        $product->setUnitQuantity($item['unitQuantity']);
        $product->setDiscount($item['discount']);
        $product->setPriceUpdatedAt(new \DateTime());

        $this->em->flush();
    }

    /**
     * Create a new ProductPriceHistory entry for the given product.
     * This method is called when the product price changes or when a new product is created.
     */
    protected function createProductPriceHistory(Product $product): void
    {
        $productPriceHistory = new ProductPriceHistory();
        $productPriceHistory->setProduct($product);
        $productPriceHistory->setPrice($product->getPrice());
        $productPriceHistory->setRegularPrice($product->getRegularPrice());
        $productPriceHistory->setDiscount($product->getDiscount());
        $productPriceHistory->setUnitPrice($product->getUnitPrice());
        $productPriceHistory->setUnit($product->getUnit());
        $productPriceHistory->setUnitQuantity($product->getUnitQuantity());
        $productPriceHistory->setSource($product->getSource());

        $this->em->persist($productPriceHistory);
    }

    /**
     * Check if product price has changed based on the provided item data or if the product is new.
     */
    protected function productPriceChanged(?Product $product, array $item): bool
    {
        return !$product instanceof Product ||
            $this->normalizePrice($product->getPrice()) !== $this->normalizePrice($item['price']) ||
            $this->normalizePrice($product->getRegularPrice()) !== $this->normalizePrice($item['regularPrice']) ||
            $product->getUnit() !== $item['unit'] ||
            $this->normalizePrice($product->getUnitPrice()) !== $this->normalizePrice($item['unitPrice']) ||
            $product->getDiscount() !== $item['discount']
        ;
    }

    public function normalizePrice($price)
    {
        # 6 decimal places for price
        if (is_numeric($price)) {
            $price = number_format((float)$price, 6, '.', '');
        } elseif (is_string($price)) {
            $price = str_replace(',', '.', $price);
            $price = number_format((float)$price, 6, '.', '');
        } else {
            throw new \InvalidArgumentException('Price must be a numeric value or a string representing a number.');
        }
        return $price;
    }
}
