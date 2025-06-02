<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\TusService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncTusCommand extends AbstractSyncCommand
{
    protected $tusService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, TusService $tusService)
    {
        $this->setName('app:sync:tus');
        parent::__construct($parameterBag, $logger, $em);
        $this->tusService = $tusService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Syncing Tus Products');

        $this->syncProductData();

        return Command::SUCCESS;
    }

    protected function syncProductData(int $run = 0): void
    {
        if ($run >= 4) {
            return;
        }
        
        $this->em->clear();
        $commandLog = $this->getCommandLog();
        if (!$this->shouldCommandRun($commandLog)) {
            return;
        }

        $k = $commandLog->getDailyRun();
        $this->io->text('Daily run: ' . $k);

        [$category, $subCategory] = $this->tusService->getCategoryAndSubcategoryInOrder($k);

        if (!$category || !$subCategory) {
            $this->io->writeln($this->getName() . ': All categories have been processed.');
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();

            $this->io->writeln($this->getName() . ': Marking products as deleted if older than 3 days.');
            $this->markProductsAsDeletedIfOlderThanDays(10, Product::SOURCE_TUS);

            return;
        }

        $this->io->text("Processing category: $category / $subCategory");

        try {
            $items = $this->tusService->getProductsData($category, $subCategory);
        } catch (\Throwable $th) {
            $this->io->error($th->getMessage());
        }

        $commandLog->incrementDailyRun();
        if (isset($items)) {
            $this->updateProducts($items);
            $this->io->newLine();
            $this->io->writeln($this->getName() . ': ' . count($items) . ' products updated. Daily run: ' . $k);

            if (sizeof($items) < 20) {
                $this->syncProductData($run + 1);
            }
        } else {
            $this->em->flush();
            $this->syncProductData($run + 1);
        }
    }
}
