<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\HoferService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncHoferCommand extends AbstractSyncCommand
{
    protected $hoferService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, HoferService $hoferService)
    {
        $this->setName('app:sync:hofer');
        parent::__construct($parameterBag, $logger, $em);
        $this->hoferService = $hoferService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Syncing Hofer Products');

        $commandLog = $this->getCommandLog();
        if (!$this->shouldCommandRun($commandLog)) {
            return Command::SUCCESS;
        }

        $k = $commandLog->getDailyRun();
        $this->io->text('Daily run: ' . $k);

        $items = $this->hoferService->getProductsData($k);

        $commandLog->incrementDailyRun();
        $this->updateProducts($items);

        if (sizeof($items) < HoferService::ITEMS_PER_PAGE) {
            $commandLog = $this->getCommandLog();
            $this->io->writeln($this->getName() . ': No new products found - all products have been processed.');
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();

            $this->io->writeln($this->getName() . ': Marking products as deleted if older than 3 days.');
            $this->markProductsAsDeletedIfOlderThanDays(30, Product::SOURCE_HOFER);
        }

        $this->io->newLine();
        $this->io->writeln($this->getName() . ': ' . count($items) . ' products updated. Daily run: ' . $k);

        return Command::SUCCESS;
    }
}
