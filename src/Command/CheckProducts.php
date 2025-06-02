<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\AbstractShopService;
use App\Service\DmService;
use App\Service\HoferService;
use App\Service\LidlService;
use App\Service\MercatorService;
use App\Service\SparService;
use App\Service\TusService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckProducts extends AbstractSyncCommand
{
    protected $tusService;
    protected $mercatorService;
    protected $sparService;
    protected $hoferService;
    protected $lidlService;
    protected $dmService;

    public function __construct(
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger,
        EntityManagerInterface $em,
        TusService $tusService,
        MercatorService $mercatorService,
        SparService $sparService,
        HoferService $hoferService,
        LidlService $lidlService,
        DmService $dmService
    ) {
        $this->setName('app:check:products');
        parent::__construct($parameterBag, $logger, $em);
        $this->tusService = $tusService;
        $this->mercatorService = $mercatorService;
        $this->sparService = $sparService;
        $this->hoferService = $hoferService;
        $this->lidlService = $lidlService;
        $this->dmService = $dmService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Checking non-updated products for 404 or price change.');

        $products = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.url IS NOT NULL')
            ->andWhere('p.updatedAt < :threshold')
            ->setParameter('threshold', new \DateTime('midnight'))
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $deleted = 0;
        $progressBar = $this->getProgressBar(sizeof($products));
        /** @var Product */
        foreach ($products as $product) {
            $product->setCheck404(new \DateTime());
            try {
                /** @var AbstractShopService */
                $service = $this->{$product->getSource().'Service'};
                $itemData = $service->getProductData($product->getUrl());
                if ($itemData && is_array($itemData)) {
                    $this->updateProductPrice($product, $itemData);
                }
            } catch (NotFoundHttpException $e) {
                $product->setDeletedAt(new \DateTime());
                $deleted++;
            } catch (\Throwable $th) {
                if ($this->parameterBag->get('kernel.environment') === 'dev') {
                    $this->io->error(sprintf('Error checking product %s: %s, line %d', $product->getId(), $th->getMessage(), $th->getLine()));
                }
                $this->logger->error(sprintf('Error checking product %s: %s, line %d', $product->getId(), $th->getMessage(), $th->getLine()));
            }
            $progressBar->advance();
        }

        $progressBar->finish();

        $this->em->flush();

        $this->io->success(sprintf('Checked %d products, deleted %d products.', sizeof($products), $deleted));

        return Command::SUCCESS;
    }
}
