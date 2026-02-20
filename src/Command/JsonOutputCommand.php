<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JsonOutputCommand extends Command
{
    protected static $defaultName = 'app:json-output';
    private const BATCH_SIZE = 3500;

    private $em;
    private $parameterBag;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $parameterBag)
    {
        parent::__construct();
        $this->em = $em;
        $this->parameterBag = $parameterBag;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Processing products...');

        $this->processBatch($output);
        $output->writeln("Done!");

        return Command::SUCCESS;
    }

    /**
     * Process products in batches and write to JSON files
     */
    private function processBatch(OutputInterface $output): void
    {
        $repository = $this->em->getRepository(Product::class);
        $page = 1;
        $batchNumber = 1;
        $sources = [Product::SOURCE_DM, Product::SOURCE_MERCATOR, Product::SOURCE_TUS];

        while (true) {
            $products = $repository->findProductsWithPriceHistory($sources, self::BATCH_SIZE, $page);

            if (empty($products)) {
                break;
            }

            $formattedProducts = [];
            foreach ($products as $product) {
                $formattedProducts[] = $this->formatProduct($product);
            }

            $this->writeJsonFile($formattedProducts, $batchNumber);
            $output->writeln("Written batch $batchNumber with " . count($formattedProducts) . " products");

            $batchNumber++;
            $page++;
            $this->em->clear();
        }
    }

    /**
     * Format a Product entity with all its price histories
     */
    private function formatProduct(Product $product): array
    {
        $prices = [];
        $lastPrice = null;
        $lastRegularPrice = null;
        $cutoffDate = new \DateTime('2025-12-09');

        // Reverse to get ascending order (oldest first)
        $priceHistories = $product->getPriceHistories()->toArray();
        $priceHistories = array_reverse($priceHistories);

        /** @var ProductPriceHistory $priceHistory */
        foreach ($priceHistories as $priceHistory) {
            // Skip prices from 2026 and later
            if ($priceHistory->getCreatedAt() >= $cutoffDate) {
                continue;
            }

            $currentPrice = (float) $priceHistory->getPrice();
            $currentRegularPrice = (float) $priceHistory->getRegularPrice();

            // Skip if same as previous price
            if ($lastPrice == $currentPrice && $lastRegularPrice == $currentRegularPrice) {
                $prices[count($prices) - 1]['verifiedAt'] = $priceHistory->getCreatedAt(); // Update timestamp of last entry
                continue;
            }

            if ($lastPrice) {
                $verifiedDate = clone $priceHistory->getCreatedAt();
                $verifiedDate->modify('-1 day');
                $prices[count($prices) - 1]['verifiedAt'] = $verifiedDate; // Update timestamp of last entry
            }

            $prices[] = [
                'id' => $priceHistory->getId(),
                'price' => $currentPrice,
                'regularPrice' => $currentRegularPrice,
                'verifiedAt' => $priceHistory->getCreatedAt(),
                'createdAt' => $priceHistory->getCreatedAt(),
            ];

            $lastPrice = $currentPrice;
            $lastRegularPrice = $currentRegularPrice;
        }

        if ($prices) {
            $prices[count($prices) - 1]['verifiedAt'] = new \DateTime('2025-12-08');
        }

        return [
            'id' => $product->getId(),
            'ean' => $product->getEan(),
            'source' => $product->getSource(),
            'prices' => $prices,
        ];
    }

    /**
     * Write a single JSON batch file
     */
    private function writeJsonFile(array $batch, int $batchNumber): void
    {
        $jsonPath = $this->parameterBag->get('kernel.project_dir') . '/var/cene_zivil_produkti_' . $batchNumber . '.json';
        file_put_contents($jsonPath, json_encode($batch, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
