<?php

namespace App\Service;

use App\Entity\Product;

class SparService extends AbstractShopService
{
    /**
     * `eanImage` comes from Spar's API and app:queue:image downloads it and sends it to Gemini, so only
     * plain https URLs on Spar's own domain are allowed: no file:// or php:// wrappers, ports,
     * credentials or other hosts. A regex rather than parse_url(), which can disagree with the
     * http wrapper about which host a malformed URL points at.
     */
    public function isImageUrlAllowed(string $url): bool
    {
        return 1 === preg_match('#^https://(?:[a-z0-9-]+\.)*spar\.si/[^\s\\\\@]*$#i', $url);
    }

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

            $id = $item['id'];
            $item = $item['masterValues'];

            $price = $item['best-price'] ?? $item['price'];
            $unit = trim(explode('/', $item['price-per-unit'])[1]);
            $unitPrice = $item['price-per-unit-number'];
            [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);

            $regularPrice = $item['regular-price'] ?: $price;

            $data[] = [
                'source' => Product::SOURCE_SPAR,
                // 'url' => 'https://www.spar.si/online/' . ltrim($item['url'], '/'),
                'url' => "https://online.spar.si/p/$id",
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
        dd('TODO');

        return [];
    }
}
