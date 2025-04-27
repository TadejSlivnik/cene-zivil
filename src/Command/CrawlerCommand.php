<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\DmService;
use App\Service\LidlService;
use App\Service\MercatorService;
use App\Service\SparService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrawlerCommand extends Command
{
    const DB_BATCH = 100;

    protected $io;
    
    protected $em;
    protected $mercatorService;
    protected $sparService;
    protected $lidlService;
    protected $dmService;

    public function __construct(EntityManagerInterface $em, MercatorService $mercatorService, SparService $sparService, LidlService $lidlService, DmService $dmService)
    {
        parent::__construct('app:crawler');
        $this->em = $em;
        $this->mercatorService = $mercatorService;
        $this->sparService = $sparService;
        $this->lidlService = $lidlService;
        $this->dmService = $dmService;
    }

    protected function configure(): void
    {
        $this->setDescription('Crawl a website and save the data to the database');
        $this->setHelp('This command allows you to crawl a website and save the data to the database');
        $this->addOption('source', null, InputOption::VALUE_OPTIONAL, 'The source of the crawl.');
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'The limit of pages to crawl.');
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $source = $input->getOption('source');
        if ($source && !in_array($source, Product::SOURCES)) {
            $this->io->error("Invalid source: $source. Valid sources are: " . implode(', ', Product::SOURCES));
            return Command::FAILURE;
        }

        $limit = $input->getOption('limit');
        if ($limit && (!is_numeric($limit) || $limit <= 0 || !ctype_digit((string)$limit))) {
            $this->io->error("Invalid limit: $limit. Limit must be a positive integer.");
            return Command::FAILURE;
        }

        // Here you would implement the crawling logic

        $this->io->title('Crawling started');
        $this->io->section('Crawling source: ' . ($source ?: 'all'));
        
        switch ($source) {
            case Product::SOURCE_DM:
                $items = $this->dmService->getProductsData('040000');
                break;
            case Product::SOURCE_LIDL:
                $links = $this->lidlService->getProductLinks();
                $item = $this->lidlService->getProductData($links[1913]);
                break;
            case Product::SOURCE_SPAR:
                $items = $this->sparService->getProductsData('s1');
                break;
            case Product::SOURCE_MERCATOR:
                $items = $this->mercatorService->getProductsData(0);
                break;
            default:
                throw new \InvalidArgumentException('Invalid source provided.');
                break;
        }

        $progressBar = $this->io->createProgressBar(count($items));
        $k = 0;
        foreach ($items as $item) {

            $product = $this->em->getRepository(Product::class)->findOneBy(['source' => $item['source'], 'productId' => $item['productId']]);
            if (!$product instanceof Product) {
                $product = new Product();
                $product->setSource($item['source']);
                $product->setProductId($item['productId']);
                $this->em->persist($product);
            }
            
            $product->setTitle($item['title']);
            $product->setPrice($item['price']);
            $product->setRegularPrice($item['regularPrice']);
            $product->setUrl($item['url']);
            $product->setUnit($item['unit']);
            $product->setUnitPrice($item['unitPrice']);
            $product->setUnitQuantity($item['unitQuantity']);

            if (++$k % self::DB_BATCH === 0) {
                $this->em->flush();
                $this->em->clear();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine();
        
        $this->em->flush();
        $this->em->clear();

        $this->io->success('Crawling completed successfully.');

        return Command::SUCCESS;
    }
}