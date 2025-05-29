<?php

namespace App\Command;

use App\Service\LidlService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncLidlCommand extends AbstractSyncCommand
{
    protected $lidlService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, LidlService $lidlService)
    {
        $this->setName('app:sync:lidl');
        parent::__construct($parameterBag, $logger, $em);
        $this->lidlService = $lidlService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Syncing Lidl Products');
        
        $commandLog = $this->getCommandLog();
        if (!$this->shouldCommandRun($commandLog)) {
            return Command::SUCCESS;
        }

        $k = $commandLog->getDailyRun();

        $items = $this->lidlService->getProductsData($k);

        if (sizeof($items) < LidlService::ITEMS_PER_PAGE) {
            $commandLog->setCompletedAt(new \DateTime());
        }

        $commandLog->incrementDailyRun();
        $this->updateProducts($items);

        $this->em->flush();

        $this->io->newLine();
        $this->io->writeln($this->getName() . ': ' . count($items) . ' products updated. Daily run: ' . $k);

        return Command::SUCCESS;
    }
}