<?php

namespace App\Command;

use App\Entity\CommandQueueImage;
use App\Entity\Product;
use App\Service\Api\GeminiApi;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class QueueImageCommand extends AbstractCommand
{
    protected $em;
    protected $geminiApi;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, GeminiApi $geminiApi)
    {
        $this->setName('app:queue:image');
        parent::__construct($parameterBag, $logger);
        $this->em = $em;
        $this->geminiApi = $geminiApi;
    }

    protected function configure(): void
    {
        $this->setDescription('Processes image queue items to extract EAN codes from product images.');
        $this->setHelp('This command processes items in the image queue, extracting EAN codes from product images using the Gemini API.');
        $this->addArgument('all', null, 'Process all items in the queue', false);
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Queue Image Command');

        $limit = $input->getArgument('all') ? null : 5;

        $this->removeCompletedQueueItems();

        $queueItems = $this->em->getRepository(CommandQueueImage::class)->getNextInQueue($limit);
        if (!$queueItems) {
            $this->io->writeln($this->getName() . ': No items in the queue.');
            return Command::SUCCESS;
        }

        foreach ($queueItems as $queueItem) {
            try {
                $success = true;
                $this->handleQueueItem($queueItem);
            } catch (ServiceUnavailableHttpException $th) {
                $this->io->warning($th->getMessage());
                break;
            } catch (\Throwable $th) {
                $this->io->error('Error processing queue item: ' . $th->getMessage());
                $queueItem->setLastError($th->getMessage());
                $success = false;
            }
            $this->em->flush();
        }

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    protected function removeCompletedQueueItems(): void
    {
        $this->io->note('Removing completed queue items older than 14 days.');
        $this->em->createQueryBuilder()
            ->delete(CommandQueueImage::class, 'q')
            ->andWhere('q.completedAt IS NOT NULL')
            ->andWhere('q.completedAt < :date')
            ->setParameter('date', (new \DateTime())->modify('-14 day'))
            ->getQuery()->execute();
    }

    protected function handleQueueItem(CommandQueueImage $queueItem): void
    {
        $queueItem->incrementTries();

        $product = $queueItem->getProduct();
        if (!$product instanceof Product) {
            throw new \Exception('Product not set for queue item: ' . $queueItem->getId());
        }

        switch ($product->getSource()) {
            case Product::SOURCE_SPAR:
                $jsonStructure = [
                    "type" => "OBJECT",
                    "properties" => ['ean' => ["type" => "string"]],
                    "required" => ['ean']
                ];

                $eanNumbers = [];

                $lastImageBase64 = null;
                for ($i = 0; $i <= 10; $i++) {
                    try {
                        $imageUrl = str_ireplace('dt_main.jpg', "dt_sub" . ($i == 0 ? '' : $i) . ".jpg", $queueItem->getImageUrl());
                        $this->io->writeln('Processing image URL: ' . $imageUrl);

                        $imageBase64 = base64_encode(file_get_contents($imageUrl));
                        if ($imageBase64 === false) {
                            throw new \Exception('Failed to read image file');
                        }

                        if ($lastImageBase64 == $imageBase64) {
                            throw new \Exception('Image has not changed since last request... throwing exception to stop loop');
                        }

                        $lastImageBase64 = $imageBase64;

                        $response = 'Extract the EAN code from the image of the product if it exists, otherwise return an empty string.';
                        $response = $this->geminiApi->apiRequest($response, $jsonStructure, $imageBase64);
                        $response = $response ? current($response) : null;
                        if (isset($response['ean']) && $response['ean']) {
                            $eanNumbers[] = $response['ean'];
                        }
                    } catch (ServiceUnavailableHttpException $th) {
                        throw $th;
                    } catch (\Throwable $th) {
                        // if ($this->parameterBag->get('kernel.environment') === 'dev') {
                        //     dd($th);
                        // }
                        break;
                    }
                }

                $eanNumbers = array_filter($eanNumbers);
                $eanNumbers = array_unique($eanNumbers);

                $eanSize = sizeof($eanNumbers);
                if ($eanSize == 1) {
                    $ean = array_shift($eanNumbers);
                    $product->setEan($ean);
                    $queueItem->setCompletedAt(new \DateTime());
                } else {
                    throw new \Exception(($eanSize > 1 ? 'Multiple EANs' : 'No EAN') . ' found for product: ' . $product->getId());
                }

                break;
            default:
                $this->io->writeln('Processing product from unknown source: ' . $product->getSource());
                break;
        }
    }
}
