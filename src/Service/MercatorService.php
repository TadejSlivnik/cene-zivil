<?php

namespace App\Service;

use App\Entity\Product;

class MercatorService extends AbstractShopService
{
    public function getProductsData(string $category): array
    {
        $itemsPerPage = 2500;
        $url = "https://mercatoronline.si/products/browseProducts/getProducts?limit=$itemsPerPage&offset=0&filterData[categories]=$category";

        $items = $this->getJson($url);
        if (!$items) {
            throw new \Exception("No data found");
        }

        $data = [];
        $items = $items['products'];
        foreach ($items as $item) {
            if (!isset($item['data'])) {
                continue;
            }

            $unit = $item['data']['price_per_unit_base'];
            $unitPrice = $item['data']['price_per_unit'];

            if (!$unit || !$unitPrice) {
                if (strtolower($item['data']['invoice_unit']) == 'kos') {
                    $unit = 'kos';
                    $unitPrice = $item['data']['current_price'];
                }
            }

            $price = (float)$item['data']['current_price'];

            try {
                [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
            } catch (\Throwable $th) {
                dd($item, $th);
            }

            $regularPrice = (float)$item['data']['normal_price'] ?: $price;
            
            $data[] = [
                'source' => Product::SOURCE_MERCATOR,
                'url' => 'https://mercatoronline.si/' . ltrim($item['url'], '/'),
                'title' => $item['data']['name'],
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $this->getDiscount($price, $regularPrice),
                // 'ean' => implode(',', array_column($item['data']['gtins'], 'gtin')),
                'productId' => $item['itemId'],
            ];
        }

        return $data;
    }

    // public function getProductLinks(): array
    // {
    //     $xml = simplexml_load_file('https://mercatoronline.si/sitemap.xml');
    //     if ($xml === false) {
    //         throw new \Exception('Failed to load XML file');
    //     }
    //     $links = [];
    //     foreach ($xml->url as $url) {
    //         $loc = (string)$url->loc;
    //         if (strpos($loc, '/izdelek/') !== false) {
    //             $links[] = $loc;
    //         }
    //     }

    //     return $links;
    // }

    // public function getProductData(string $url): array
    // {
    //     $html = $this->getHtml($url);
    //     $dom = new \DOMDocument();
    //     @$dom->loadHTML($html);
    //     $xpath = new \DOMXPath($dom);

    //     $title = $xpath->query('//h1[@class="lib-analytics-product-name"]')->item(0)->textContent;

    //     $price = $xpath->query('//span[@class="price"]')->item(0)->textContent;
    //     $price = $this->parsePrice($price);

    //     if ($priceRegular = $xpath->query('//span[@class="price-old "]')->item(0)) {
    //         if ($priceRegular = $priceRegular->textContent) {
    //             $priceRegular = $this->parsePrice($priceRegular);
    //         }
    //     }

    //     if (!$priceRegular || $priceRegular <= 0) {
    //         $priceRegular = $price;
    //     }

    //     $code = null;
    //     $unit = null;
    //     $unitPrice = null;
    //     foreach ($xpath->query('//div[@class="description"]')->getIterator() as $item) {
    //         if ($item = $item->textContent) {
    //             $item = explode("\n", $item);
    //             $item = array_map('trim', $item);
    //             $item = array_filter($item);
                
    //             foreach ($item as $v) {
    //                 if (strpos($v, 'Koda artikla:') !== false) {
    //                     $code = trim(explode(':', $v)[1]);
    //                 } else if (strpos($v, 'Cena na enoto:') !== false) {
    //                     $unitPrice = trim(explode(':', $v)[1]);
    //                     $unitPrice = explode('/', $unitPrice);
    //                     $unit = trim($unitPrice[1]);
    //                     $unitPrice = $this->parsePrice($unitPrice[0]);

    //                 }
    //             }
    //         }
    //     }

    //     $internalId = $url;
    //     $internalId = explode('/izdelek/', $internalId);
    //     $internalId = explode('/', $internalId[1])[0];

    //     return [
    //         'source' => Product::SOURCE_MERCATOR,
    //         'url' => $url,
    //         'title' => trim($title),
    //         'unit' => $unit,
    //         'unitPrice' => $unitPrice,
    //         'price' => $price,
    //         'regularPrice' => $priceRegular,
    //         'internalId' => $internalId,
    //         'productId' => $code,
    //     ];
    // }
}