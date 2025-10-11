<?php

namespace App\Service;

use App\Entity\Product;

class DmService extends AbstractShopService
{
    public function getProductsData(string $category): array
    {
        $itemsPerPage = 3000;

        $url = "https://product-search.services.dmtech.com/si/search/crawl?allCategories.id=$category&pageSize=$itemsPerPage&searchType=editorial-search&sort=editorial_relevance";

        $items = $this->getJson($url);
        if (!$items) {
            throw new \Exception("No data found for category: $category");
        }

        $data = [];
        $items = $items['products'];
        foreach ($items as $item) {
            $item = $item['tileData'];
            unset($item['variants']);
            unset($item['rating']);
            unset($item['images']);
            
            $price = $item['trackingData']['price']["previous"] ?? $item['trackingData']['price']['current'] ?? $item['trackingData']['price'];
            $unit = trim($item['price']['tileInfos'][0], " )");
            $unit = explode(' | ', $unit)[0]; // Remove everything after |
            $unit = explode('(', $unit)[1];
            $unit = array_map('trim', explode('za', $unit));
            $unitPrice = $this->parsePrice($unit[0]);
            $unit = $unit[1] ?? '';

            try {
                [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
            } catch (\Throwable $th) {
                dump($item);
                throw $th;
            }

            $regularPrice = $item['trackingData']['price']["current"] ?? $item['trackingData']['price'];
            
            $data[] = [
                'source' => Product::SOURCE_DM,
                'url' => 'https://www.dm.si/' . ltrim($item['self'], '/'),
                'title' => implode(', ', array_filter([($item['title']['preheadline'] ?? ''), $item['title']['tileHeadline']])),
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $this->getDiscount($price, $regularPrice),
                'ean' => $this->parseEan($item['gtin']),
                'productId' => $item['dan'],
            ];
        }

        return $data;
    }

    public function getProductData(string $url): array
    {
        dd("TODO");
        return [];
    }
}
