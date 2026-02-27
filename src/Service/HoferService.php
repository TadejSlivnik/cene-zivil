<?php

namespace App\Service;

use App\Entity\Product;

class HoferService extends AbstractShopService
{
    const ITEMS_PER_PAGE = 60; // Max allowed by the API

    public function getProductsData(int $page): array
    {
        $limit = self::ITEMS_PER_PAGE;
        $offset = $page * $limit;
        $url = "https://api.hofer.si/v3/product-search?currency=EUR&serviceType=walk-in&limit=$limit&offset=$offset&sort=relevance";

        $items = $this->getJson($url);
        if (!$items) {
            throw new \Exception("No data found");
        }

        $data = [];
        $items = $items['data'];
        foreach ($items as $item) {
            $title = implode(', ', array_filter([$item['brandName'], $item['name']]));

            $price = (float)($item['price']['amount'] / 100.0);
            $regularPrice = $price;

            $unit = $item['price']['comparisonDisplay'] ?: null;
            $unit = $this->parseUnit($unit);
            $unitPrice = $item['price']['comparison'] ? (float)($item['price']['comparison'] / 100.0) : null;
            if (!$unitPrice && $unit == 'kos') {
                $unitPrice = (float)($item['price']['amount'] / 100.0);
            }

            $unitQuantity = null;
            if ($unit && $unitPrice) {
                try {
                    [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
                } catch (\Throwable $th) {
                    dd($th->getMessage(), $unit, $item['price']['comparisonDisplay']);
                }
            }

            $data[] = [
                'source' => Product::SOURCE_HOFER,
                'url' => 'https://hofer.si/izdelek/' . $item['urlSlugText'] . "-" . $item['sku'],
                'title' => $title,
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $this->getDiscount($price, $regularPrice),
                'ean' => null,
                'productId' => $item['sku'],
            ];
        }

        return $data;
    }

    public function getProductData(string $url): array
    {
        dd("TODO");
        return [];
    }

    private function parseUnit(?string $unit): string
    {
        # example: "11,20 €/1 kg"
        if (!$unit) {
            return 'kos';
        }
        $unitPart = explode('/', $unit)[1] ?? null;
        if ($unitPart) {
            $unitPart = trim($unitPart);
            if (str_contains($unitPart, ' ')) {
                $unitPart = explode(' ', $unitPart);
                $unitPart = end($unitPart);
            }
            return $unitPart;
        }

        return 'kos';
    }
}
