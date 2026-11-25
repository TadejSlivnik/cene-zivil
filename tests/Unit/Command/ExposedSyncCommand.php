<?php

namespace App\Tests\Unit\Command;

use App\Command\AbstractSyncCommand;
use App\Entity\Product;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exposes AbstractSyncCommand's protected price comparison for unit tests.
 */
final class ExposedSyncCommand extends AbstractSyncCommand
{
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        return self::SUCCESS;
    }

    public function priceChanged(?Product $product, array $item): bool
    {
        return $this->productPriceChanged($product, $item);
    }
}
