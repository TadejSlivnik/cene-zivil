<?php

namespace App\Service;

use App\Entity\Product;

class LidlService extends AbstractShopService
{
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

        if ($priceRegular = $xpath->query('//span[@class="strikethrough m-price__rrp m-price__text"]')->item(0)) {
            if ($priceRegular = $priceRegular->textContent) {
                $priceRegular = $this->parsePrice($priceRegular);
            }
        }

        if (!$priceRegular || $priceRegular <= 0) {
            $priceRegular = $price;
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
            'regularPrice' => $priceRegular,
            // 'ean' => null,
            'productId' => $internalId,
        ];
    }
}