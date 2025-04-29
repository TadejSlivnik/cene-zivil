<?php

namespace App\Command;

use App\Service\DmService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncDmCommand extends AbstractSyncCommand
{
    protected $categories = [
        '010000', // licila
        '020000', // nega in disave
        '110000', // lasje
        // 'S4',
        '030000', // zdravje
        '040000', // prehrana
        '050000', // dojencek in otrok
        '060000', // gospodinjstvo
        '070000', // male zivali
    ];

    protected $dmService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, DmService $dmService)
    {
        $this->setName('app:sync:dm');
        parent::__construct($parameterBag, $logger, $em);
        $this->dmService = $dmService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Syncing Dm Products');
        
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
            return Command::SUCCESS;
        }

        $this->io->text('Processing category: ' . $this->categories[$k], "(Batch " . ($k + 1) . "/" . count($this->categories) . ")");

        $items = $this->dmService->getProductsData($this->categories[$k]);

        if (empty($items)) {
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();
            $this->io->writeln($this->getName() . ': No new products found.');
            return Command::SUCCESS;
        }

        $commandLog->incrementDailyRun();
        $this->updateProducts($items);

        $this->em->flush();

        $this->io->newLine();
        $this->io->writeln($this->getName() . ': ' . count($items) . ' products updated. Daily run: ' . $k);

        return Command::SUCCESS;
    }
}