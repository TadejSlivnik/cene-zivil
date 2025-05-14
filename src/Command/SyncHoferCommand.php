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
    protected $urls = [
        'https://www.hofer.si/sl/ponudba/sadje-in-zelenjava-v-akciji.html',
        'https://www.hofer.si/sl/ponudba/trajno-znizano.html'
    ];

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
        if (!array_key_exists($k, $this->urls)) {

            $this->io->writeln($this->getName() . ': All urls have been processed.');
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();

            // $this->io->writeln($this->getName() . ': Marking products as deleted if older than 3 days.');
            // $this->markProductsAsDeletedIfOlderThanDays(3, Product::SOURCE_HOFER);

            return Command::SUCCESS;
        }

        $this->io->text('Processing urls: ' . $this->urls[$k], "(Batch " . ($k + 1) . "/" . count($this->urls) . ")");

        $items = $this->hoferService->getProductsData($this->urls[$k]);
        if ($items === null) {
            $this->io->writeln($this->getName() . ': Not OK data from AI. Will try again...');
            return Command::SUCCESS;
        }

        if (empty($items)) {
            $this->io->writeln($this->getName() . ': No new products found.');
            return Command::SUCCESS;
        }

        // $commandLog->incrementDailyRun();
        $this->updateProducts($items);

        $this->io->newLine();
        $this->io->writeln($this->getName() . ': ' . count($items) . ' products updated. Daily run: ' . $k);

        return Command::SUCCESS;
    }
}