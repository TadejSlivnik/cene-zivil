<?php

namespace App\Service;

abstract class AbstractShopService
{
    public function curlRequest(string $url, array $data = [], array $headers = []): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $headers[] = 'Content-Type: application/json';

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);

            $jsonData = json_encode($data);
            $jsonData = str_replace('"variables":[]', '"variables":{}', $jsonData); // Fix empty variables array -> convert to object
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'content-length: ' . strlen($jsonData);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Curl error: $error");
        }

        curl_close($ch);

        $response = json_decode($response, true);

        return $response;
    }

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
        $price = str_replace(['€', ' ', 'prej'], '', $price);
        $price = (float)str_replace([','], '.', trim($price));
        return $price;
    }

    public function parseEan(?string $ean): ?string
    {
        if (!$ean) {
            return null;
        }

        $ean = explode(',', $ean);
        $ean = array_map('trim', $ean);
        $ean = array_filter($ean, function ($value) {
            return preg_match('/^\d{8,13}$/', $value);
        });

        if (!$ean) {
            return null;
        }

        $ean = ',' . implode(',', $ean) . ',';
        return $ean;
    }
    

    public function getDiscount(float $price, float $regularPrice): ?int
    {
        return $regularPrice > $price ? (int)round(100 - ($price * 100 / $regularPrice)) : null;
    }

    public function unitPriceCalculation(string $unitBase, float $unitPrice, float $price): array
    {
        $unitBase = strtolower(str_replace([' '], '', $unitBase));

        $unitQuantity = 1;
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
                break;
            case '100list': // DM
            case '10pranj': // DM
            case '10kos': // DM - Maca Vitae, prehransko dopolnilo, 90 kos
            case '100kos': // DM - Namizno sladilo na osnovi stevie, v obliki tablet, 100 kos
            case '10vr': // DM - vrecke caja
                $unitPrice = $price;
            case '1kos': // DM
            case 'kos':
                $unit = 'kos';
                break;
            case 'm':
            case '1m':
                $unit = 'm';
                break;
            case '100m':
                $unitPrice /= 10;
            case '10m':
                $unit = 'm';
                $unitPrice /= 10;
                break;
            case 'polnovedro':
                $unit = 'kos';
                $unitPrice = $price;
                break;
            default:
                throw new \Exception("Invalid unit base: $unitBase");
        }

        return [$unit, $unitQuantity, $unitPrice];
    }
}
