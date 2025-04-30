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
            $price = $item['price']['value'];
            $unit = $item['basePriceQuantity'] . $item['basePriceUnit'];
            $unitPrice = $this->parsePrice($item['basePrice']['formattedValue']);

            try {
                [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
            } catch (\Throwable $th) {
                dump($item);
                throw $th;
            }

            $regularPrice = $item['price']['value'];
            
            $data[] = [
                'source' => Product::SOURCE_DM,
                'url' => 'https://www.dm.si/' . ltrim($item['relativeProductUrl'], '/'),
                'title' => $item['brandName'] ?? '' . $item['title'],
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $this->getDiscount($price, $regularPrice),
                // 'ean' => $item['gtin'],
                'productId' => $item['dan'],
            ];
        }

        return $data;
    }
}
