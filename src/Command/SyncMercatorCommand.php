<?php

namespace App\Command;

use App\Service\MercatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncMercatorCommand extends AbstractSyncCommand
{
    protected $categories = [
        '14535405', // MLEKO, JAJCA IN MLEČNI IZDELKI
        '14535446', // MESO, MESNI IZDELKI IN RIBE
        '14535463', // SVEŽ KRUH IN PECIVO
        '14535481', // DELIKATESNI IZDELKI IN PRIPRAVLJENE JEDI
        '14535512', // ZAMRZNJENA HRANA
        '14535548', // OSNOVNA ŽIVILA / SHRAMBA
        '14535588', // VSE ZA ZAJTRK
        '14535612', // PIJAČE
        '14535661', // TESTENINE, JUHE, RIŽ IN OMAKE
        '14535681', // KONZERVIRANA HRANA
        '14535711', // ČOKOLADA IN DRUGI SLADKI PROGRAM
        '14535736', // SLANI PRIGRIZKI IN APERITIVI (JEDI)
        '14535749', // DIABETIČNA, DIETETIČNA IN DRUGA POSEBNA PREHRANA
        '14535768', // VSE ZA MALE DOMAČE ŽIVALI
        '14535803', // HRANA TUJIH DEŽEL
        '14535810', // SADJE IN ZELENJAVA
        '14535837', // VSE ZA OTROKA
        '14535864', // HIGIENA IN LEPOTA
        '14535906', // ČISTILA
        '14535941', // PROSTI ČAS
        '14535984', // VSE ZA DOM IN GOSPODINJSTVO
        '14536021', // OBLAČILA IN DODATKI
        '14536058', // VSE ZA ŠOLO IN PISARNO, DARILNI PROGRAM
        '14536089', // SEZONA
        '16873196', // EKOLOŠKA ŽIVILA
    ];

    protected $mercatorService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, MercatorService $mercatorService)
    {
        $this->setName('app:sync:mercator');
        parent::__construct($parameterBag, $logger, $em);
        $this->mercatorService = $mercatorService;
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Syncing Mercator Products');
        
        $commandLog = $this->getCommandLog();
        if (!$this->shouldCommandRun($commandLog)) {
            return Command::SUCCESS;
        }

        $k = $commandLog->getDailyRun();
        if (!array_key_exists($k, $this->categories)) {
            $this->io->writeln($this->getName() . ': All categories have been processed.');
            $commandLog->setCompletedAt(new \DateTime());
            $this->em->flush();
            return Command::SUCCESS;
        }

        $this->io->text('Processing category: ' . $this->categories[$k], "(Batch " . ($k + 1) . "/" . count($this->categories) . ")");

        $items = $this->mercatorService->getProductsData($this->categories[$k]);

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