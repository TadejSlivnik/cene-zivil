<?php

namespace App\Service;

abstract class AbstractShopService
{
    public function getHtml(string $url): string
    {
        $html = file_get_contents($url);
        if ($html === false) {
            throw new \Exception('Failed to load HTML file');
        }
        return $html;
    }

    public function getJson(string $url): array
    {
        $json = file_get_contents($url);
        if ($json === false) {
            throw new \Exception('Failed to load JSON file');
        }
        return json_decode($json, true);
    }

    public function parsePrice(string $price): float
    {
        $price = str_replace(['€', ' '], '', $price);
        $price = (float)str_replace([','], '.', trim($price));
        return $price;
    }

    public function getDiscount(float $price, float $regularPrice): ?int
    {
        return $regularPrice > $price ? (int)round(100 - ($price * 100 / $regularPrice)) : null;
    }

    public function unitPriceCalculation(string $unitBase, float $unitPrice, float $price): array
    {
        switch ($unitBase) {
            case '1ml':
            case 'ml':
                $unitPrice *= 10;
            case '10ml':
                $unitPrice *= 10;
            case '100ml':
                $unitPrice *= 10;
            case 'l':
            case '1l':
                $unit = 'l';
                $unitQuantity = 1;
                break;
            case 'g':
                $unitPrice *= 10;
            case '10g':
                $unitPrice *= 10;
            case '100g':
                $unitPrice *= 10;
            case 'kg':
            case '1kg':
                $unit = 'kg';
                $unitQuantity = 1;
                break;
            case '100list': // DM - Maca Vitae, prehransko dopolnilo, 90 kos
            case '10pranj': // DM - Maca Vitae, prehransko dopolnilo, 90 kos
            case '10kos': // DM - Maca Vitae, prehransko dopolnilo, 90 kos
            case '100kos': // DM - Namizno sladilo na osnovi stevie, v obliki tablet, 100 kos
            case '10vr': // DM - vrecke caja
            case '1kos': // DM
            case 'kos':
                $unit = 'kos';
                $unitQuantity = 1;
                $unitPrice = $price;
                break;
            case 'm':
            case '1m':
                $unit = 'm';
                $unitQuantity = 1;
                break;
            case '100m':
                $unitPrice /= 10;
            case '10m':
                $unit = 'm';
                $unitQuantity = 1;
                $unitPrice /= 10;
                break;
            default:
                throw new \Exception("Invalid unit base: $unitBase");
        }

        return [$unit, $unitQuantity, $unitPrice];
    }
}
