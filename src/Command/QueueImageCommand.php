<?php

namespace App\Command;

use App\Entity\CommandQueueImage;
use App\Entity\Product;
use App\Service\Api\GeminiApi;
use App\Service\SparService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class QueueImageCommand extends AbstractCommand
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    protected $em;
    protected $geminiApi;
    protected SparService $sparService;

    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger, EntityManagerInterface $em, GeminiApi $geminiApi, SparService $sparService)
    {
        $this->setName('app:queue:image');
        parent::__construct($parameterBag, $logger);
        $this->em = $em;
        $this->geminiApi = $geminiApi;
        $this->sparService = $sparService;
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
            $this->io->writeln($this->getName().': No items in the queue.');

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
                $this->io->error('Error processing queue item: '.$th->getMessage());
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
            throw new \Exception('Product not set for queue item: '.$queueItem->getId());
        }

        switch ($product->getSource()) {
            case Product::SOURCE_SPAR:
                $jsonStructure = [
                    'type' => 'OBJECT',
                    'properties' => ['ean' => ['type' => 'string']],
                    'required' => ['ean'],
                ];

                if (!$this->sparService->isImageUrlAllowed((string) $queueItem->getImageUrl())) {
                    throw new \Exception('Image URL is not an https URL on spar.si: '.$queueItem->getImageUrl());
                }

                $eanNumbers = [];
                $context = stream_context_create(['http' => ['timeout' => 30]]);

                $lastImageBase64 = null;
                for ($i = 0; $i <= 10; ++$i) {
                    try {
                        $imageUrl = str_ireplace('dt_main.jpg', 'dt_sub'.(0 == $i ? '' : $i).'.jpg', $queueItem->getImageUrl());
                        $this->io->writeln('Processing image URL: '.$imageUrl);

                        $image = file_get_contents($imageUrl, false, $context, 0, self::MAX_IMAGE_BYTES);
                        if (false === $image) {
                            throw new \Exception('Failed to read image file');
                        }
                        $imageBase64 = base64_encode($image);

                        if ($lastImageBase64 == $imageBase64) {
                            throw new \Exception('Image has not changed since last request... throwing exception to stop loop');
                        }

                        $lastImageBase64 = $imageBase64;

                        $response = 'Extract the EAN code from the image of the product if it exists, otherwise return an empty string.';
                        $response = $this->geminiApi->apiRequest($response, $jsonStructure, $imageBase64);
                        $response = $response ? current($response) : null;
                        // Model output is untrusted (the image itself can carry text): keep only
                        // well-formed EANs, in the comma-wrapped format search matches on.
                        if (isset($response['ean']) && is_string($response['ean'])
                            && $ean = $this->sparService->parseEan(preg_replace('/[\s-]+/', '', $response['ean']))) {
                            $eanNumbers[] = $ean;
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

                $eanNumbers = array_unique($eanNumbers);

                $eanSize = sizeof($eanNumbers);
                if (1 == $eanSize) {
                    $ean = array_shift($eanNumbers);
                    $product->setEan($ean);
                    $queueItem->setCompletedAt(new \DateTime());
                } else {
                    throw new \Exception(($eanSize > 1 ? 'Multiple EANs' : 'No EAN').' found for product: '.$product->getId());
                }

                break;
            default:
                $this->io->writeln('Processing product from unknown source: '.$product->getSource());
                break;
        }
    }
}
