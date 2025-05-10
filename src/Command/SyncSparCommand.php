<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\SparService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncSparCommand extends AbstractSyncCommand
{
    protected $categories = [
        'S1',
        'S2',
        'S3',
        'S4',
        'S5',
        'S6',
        'S7',
        'S8',
        'S9',
        'S10',
        'S11',
        'S12',
        'S13',
        'S14',
        // 'S15',
    ];

    protected $sparService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, SparService $sparService)
    {
        $this->setName('app:sync:spar');
        parent::__construct($parameterBag, $logger, $em);
        $this->sparService = $sparService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Syncing Spar Products');
        
        $commandLog = $this->getCommandLog();
        if (!$this->shouldCommandRun($commandLog)) {
            return Command::SUCCESS;
        }

        $k = $commandLog->getDailyRun();
        $this->io->text('Daily run: ' . $k);
        if (!array_key_exists($k, $this->categories)) {

            $this->io->writeln($this->getName() . ': All categories have been processed.');
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();

            $this->io->writeln($this->getName() . ': Marking products as deleted if older than 3 days.');
            $this->markProductsAsDeletedIfOlderThanDays(3, Product::SOURCE_SPAR);

            return Command::SUCCESS;
        }

        $this->io->text('Processing category: ' . $this->categories[$k], "(Batch " . ($k + 1) . "/" . count($this->categories) . ")");

        $items = $this->sparService->getProductsData($this->categories[$k]);

        if (empty($items)) {
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();
            $this->io->writeln($this->getName() . ': No new products found.');
            return Command::SUCCESS;
        }

        $commandLog->incrementDailyRun();
        $this->updateProducts($items);

        $this->io->newLine();
        $this->io->writeln($this->getName() . ': ' . count($items) . ' products updated. Daily run: ' . $k);

        return Command::SUCCESS;
    }
}