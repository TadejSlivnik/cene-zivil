<?php

namespace App\Command;

use App\Entity\ProductPriceHistory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HandlePromotionCommand extends AbstractSyncCommand
{
    public function __construct(
        EntityManagerInterface $em,
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger
    ) {
        $this->setName('app:handle:promotions');
        parent::__construct($parameterBag, $logger, $em);
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $productPriceHistories = $this->em->getRepository(ProductPriceHistory::class)
            ->createQueryBuilder('p')
            ->addSelect('pr')
            ->leftJoin('p.product', 'pr')
            ->andWhere('p.promotionHandled IS NULL')
            ->andWhere('p.promotionEndsDate < :now')
            ->setParameter('now', (new \DateTime())->format('Y-m-d 00:00:00'))
            ->getQuery()
            ->getResult();

        foreach ($productPriceHistories as $ph) {
            $product = $ph->getProduct();
            if (!$product) {
                continue;
            }

            $this->io->writeln("Promotion ended for product: " . $product->getTitle() . " (ID: " . $product->getId() . ")");

            $ph->setPromotionHandled(new \DateTime());
            $product->setPrice($ph->getRegularPrice());
            $this->createProductPriceHistory($product);
        }

        $this->em->flush();

        return Command::SUCCESS;
    }
}
