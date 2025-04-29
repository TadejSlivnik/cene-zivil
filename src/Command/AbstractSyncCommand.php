<?php

namespace App\Command;

use App\Entity\CommandLog;
use App\Entity\Product;
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

	protected function updateProducts(array $items): void
	{
        $progressBar = $this->io->createProgressBar(count($items));

        $k = 0;
        $handled = [];
        foreach ($items as $item) {
            if (in_array($item['productId'], $handled)) {
                continue;
            }

            $product = $this->em->getRepository(Product::class)->findOneBy(['source' => $item['source'], 'productId' => $item['productId']]);
            if (!$product instanceof Product) {
                $product = new Product();
                $product->setSource($item['source']);
                $product->setProductId($item['productId']);
                $this->em->persist($product);
            }

            $handled[] = $item['productId'];
            
            $product->setTitle($item['title']);
            $product->setPrice($item['price']);
            $product->setRegularPrice($item['regularPrice']);
            $product->setUrl($item['url']);
            $product->setUnit($item['unit']);
            $product->setUnitPrice($item['unitPrice']);
            $product->setUnitQuantity($item['unitQuantity']);
            $product->setDiscount($item['discount']);

            if (++$k % static::DB_BATCH === 0) {
                $this->em->flush();
                $this->em->clear();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        
        $this->em->flush();
        $this->em->clear();
	}
}