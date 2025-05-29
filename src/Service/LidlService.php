<?php

namespace App\Service;

use App\Entity\Product;

class LidlService extends AbstractShopService
{
    // Lidl API supports fetching a maximum of 100 items per request
    const ITEMS_PER_PAGE = 100;

    public function getProductsData(int $offset): array
    {
        $itemsPerPage = self::ITEMS_PER_PAGE;
        $offset *= $itemsPerPage;
        $url = "https://www.lidl.de/q/api/search?offset=$offset&fetchsize=$itemsPerPage&locale=sl_SI&assortment=SI&version=2.1.0";

        $items = $this->getJson($url);
        $items = $items['items'] ?? [];
        if (!$items) {
            throw new \Exception("No data found.");
        }

        $items = array_map(function ($item) {
            $item = $item['gridbox'] ?? null;
            foreach (['ageRestriction', 'awards', 'brand', 'cutoutimage', 'dealOfDay', 'designTheme', 'disclaimers', 'image', 'image_V1', 'imageList', 'imageList_V1', 'regions', 'ribbons', 'keyfacts'] as $i) {
                unset($item['data'][$i]);
            }
            foreach (['categoryPaths', 'campaignPaths', 'retailLists', 'lists', 'wonCategoryBreadcrumbs', 'worldOfNeeds', 'preview'] as $i) {
                unset($item['meta'][$i]);
            }
            return $item;
        }, $items);


        $data = [];
        foreach ($items as $item) {

            $productId = $item['id'] ?? null;
            $title = $item['meta']['fullTitle'] ?? null;
            $item = $item['data'] ?? null;
            if (!$productId || !$title || !$item) {
                continue;
            }
            $title = trim($title);

            $url = "https://www.lidl.si/" . ltrim($item['canonicalPath'], '/');
            

            if ($item['lidlPlus']) {
                if (sizeof($item['lidlPlus']) > 1) {
                    dd($item['lidlPlus']);
                }
                $priceData = $item['lidlPlus'][0]['price'];
            } else {
                $priceData = $item['price'];
            }

            $price = $priceData['price'];
            $regularPrice = isset($priceData['oldPrice']) && $priceData['oldPrice'] ? $priceData['oldPrice'] : $price;

            if (isset($priceData['basePrice']['text'])) {
                $unit = $priceData['basePrice']['text'];
                $unit = explode('=', $unit);
                $unitPrice = $this->parsePrice(trim($unit[1]));
                $unit = trim($unit[0]);
            } else {
                $unit = 'kos';
                $unitPrice = $price;
            }

            $discount = $priceData['discount']['percentageDiscount'] ?? $this->getDiscount($price, $regularPrice);

            try {
                [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
            } catch (\Throwable $th) {
                throw $th;
            }

            // TODO ADD PROMOTION ENDS DATE

            $data[] = [
                'source' => Product::SOURCE_LIDL,
                'url' => $url,
                'title' => $title,
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $discount,
                'ean' => null,
                'productId' => $productId,
            ];
        }

        return $data;
    }

    public function getProductLinks(): array
    {
        $sitemap = file_get_contents('https://www.lidl.si/p/export/SI/sl/product_sitemap.xml.gz');
        if ($sitemap === false) {
            throw new \Exception('Failed to load sitemap');
        }
        $sitemap = gzdecode($sitemap);
        if ($sitemap === false) {
            throw new \Exception('Failed to decode gzipped sitemap');
        }
        $xml = simplexml_load_string($sitemap);
        if ($xml === false) {
            throw new \Exception('Failed to load XML file');
        }
        $links = [];
        foreach ($xml->url as $url) {
            $loc = (string)$url->loc;
            if (strpos($loc, '/p/') !== false) {
                $links[] = $loc;
            }
        }
        return $links;
    }

    public function getProductData(string $url): array
    {
        $html = $this->getHtml($url);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $title = $xpath->query('//h1[@class="heading__title"]')->item(0)->textContent;

        $price = $xpath->query('//div[@class="m-price__price"]')->item(0);
        if (!$price) {
            return [];
        }
        $price = $price->textContent;
        $price = $this->parsePrice($price);

        if ($regularPrice = $xpath->query('//span[@class="strikethrough m-price__rrp m-price__text"]')->item(0)) {
            if ($regularPrice = $regularPrice->textContent) {
                $regularPrice = $this->parsePrice($regularPrice);
            }
        }

        if (!$regularPrice || $regularPrice <= 0) {
            $regularPrice = $price;
        }

        $unit = null;
        $unitQuantity = null;
        $unitPrice = $xpath->query('//div[@class="price-footer"]')->item(0);
        if ($unitPrice) {
            if ($unitPrice = $unitPrice->textContent) {
                $unitPrice = array_map('trim', explode('=', $unitPrice));
                $unit = $unitPrice[0];
                $unitPrice = (float)$unitPrice[1];
                [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
            }
        }

        $internalId = $url;
        $internalId = explode('/p', $internalId);
        $internalId = array_pop($internalId);

        return [
            'source' => Product::SOURCE_LIDL,
            'url' => $url,
            'title' => trim($title),
            'unit' => $unit,
            'unitQuantity' => $unitQuantity,
            'unitPrice' => $unitPrice,
            'price' => $price,
            'regularPrice' => $regularPrice,
            'discount' => $this->getDiscount($price, $regularPrice),
            // 'ean' => null,
            'productId' => $internalId,
        ];
    }
}
