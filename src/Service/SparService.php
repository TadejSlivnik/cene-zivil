<?php

namespace App\Service;

use App\Entity\Product;

class SparService extends AbstractShopService
{
    public function getProductsData(string $category): array
    {
        $itemsPerPage = 6000;
        $category = strtoupper($category);

        $url = "https://search-spar.spar-ics.com/fact-finder/rest/v4/search/products_lmos_si?query=*&q=*&page=1&hitsPerPage=$itemsPerPage&filter=category-path:$category";

        $items = $this->getJson($url);
        if (!$items) {
            throw new \Exception("No data found for category: $category");
        }

        $data = [];
        $items = $items['hits'];
        foreach ($items as $item) {
            if (!isset($item['masterValues']['price'])) {
                continue;
            }

            $item = $item['masterValues'];

            $price = $item['best-price'] ?? $item['price'];
            $unit = trim(explode('/', $item['price-per-unit'])[1]);
            $unitPrice = $item['price-per-unit-number'];
            [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);

            $regularPrice = $item['regular-price'] ?: $price;

            $data[] = [
                'source' => Product::SOURCE_SPAR,
                // 'url' => 'https://www.spar.si/online/' . ltrim($item['url'], '/'),
                'url' => 'https://online.spar.si/' . $item['id'],
                'title' => $item['title'],
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $this->getDiscount($price, $regularPrice),
                'ean' => null,
                'eanImage' => $item['image-url'] ?? null,
                'productId' => $item['product-number'],
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
