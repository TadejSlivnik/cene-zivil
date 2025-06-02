<?php

namespace App\Service;

use App\Entity\Product;
use App\Service\Api\GeminiApi;
use Symfony\Component\DomCrawler\Crawler;

class HoferService extends AbstractShopService
{
    protected $geminiApi;

    public function __construct(GeminiApi $geminiApi)
    {
        $this->geminiApi = $geminiApi;
    }

    public function getProductsData(string $url): ?array
    {
        $html = $this->getHtml($url);
        if (!$html) {
            throw new \Exception("No data found for url: $url");
        }

        // Assume $html contains the HTML content
        // $crawler = new Crawler($html);


        $jsonStructure = [
            "type" => "OBJECT",
            "properties" => [
                'fullTitle' => ["type" => "string"],
                'newPrice' => ["type" => "number", "format" => 'double'],
                'oldPrice' => ["type" => "number", "format" => 'double'],
                'unitPrice' => ["type" => "number", "format" => 'double'],
                'unit' => ["type" => "string"],
                'productId' => ["type" => "string"],
                'dateFrom' => ["type" => "string", "format" => 'date-time'],
            ],
            "required" => [
                'fullTitle',
                'newPrice',
                'oldPrice',
                'unitPrice',
                'unit',
                'productId',
                'dateFrom',
            ]
        ];

        $response = "Extract the full title, new price, old price, unit price and unit from given html.";
        $response .= " If it exists, also extract the \"product id\" and \"Trajno znižano od\" date (dateFrom), return empty string if data doesn\'t exist.";
        $response .= " The date will probably have a Slovenian format in HTML, e.g.: \"dd.mm.YYYY\", and will be in the current year (" . date('Y') . ") or greater. The HTML is from a Slovenian website. \n\n##### The HTML: " . $html;
        $response = $this->geminiApi->apiRequest($response, $jsonStructure);

        $items = [];
        foreach ($response as $item) {
            if ($item['dateFrom']) {
                $date = \DateTime::createFromFormat('d.m.Y', $item['dateFrom']);
                if (!$date || $date->format('Y') < date('Y') || $date->format('Y-m-d') > date('Y-m-d')) {
                    continue;
                }
            }

            $items[] = [
                'source' => Product::SOURCE_HOFER,
                'url' => null,
                'title' => trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', str_replace("\n", ' ', $item['fullTitle']))),
                'unit' => $item['unit'],
                'unitQuantity' => 1,
                'unitPrice' => $item['unitPrice'],
                'price' => $item['newPrice'],
                'regularPrice' => strpos($url, 'trajno-znizano') !== false ? $item['newPrice'] : $item['oldPrice'],
                'discount' => $this->getDiscount($item['newPrice'], strpos($url, 'trajno-znizano') !== false ? $item['newPrice'] : $item['oldPrice']),
                // 'ean' => null,
                'productId' => $item['productId'] ?? null,
                'dateFrom' => $item['dateFrom'],
            ];
        }

        if (sizeof($items) < sizeof($response) * 0.8) {
            return null;
        }
        
        // // Select each product item (div.item) from the grid gallery
        // $items = $crawler->filter('.E12-grid-gallery .item')->each(function (Crawler $node) use ($url) {
        //     $data = array_filter($node->filter('figcaption div')->each(function (Crawler $node) {
        //         $text = $node->text('', true);
        //         $text = trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $text)); // remove invisible chars
        //         return $text;
        //     }));

        //     $title = [];
        //     $price = null;
        //     $regularPrice = null;
        //     $unitPrice = null;
        //     $trajnoZnizanoOd = null;
        //     $productId = null;
        //     foreach ($data as $item) {
        //         if (strpos($item, 'prej ') === 0) {
        //             if (!$regularPrice && strpos($item, 'Trajno znižano od') !== false && strpos($item, '€') !== false) {
        //                 $regularPrice = explode('€', $item)[0];
        //             } else {
        //                 $regularPrice = $item;
        //             }
        //         } elseif (!$unitPrice && strpos($item, 'za') !== false) {
        //             $unitPrice = trim(explode('za', $item)[1]);
        //             if (strpos($unitPrice, '€') === false) {
        //                 $unitPrice = "za $unitPrice = $price";
        //             }
        //         } elseif (strpos($item, 'za ') === 0) {
        //             $unitPrice = $item;
        //         } elseif (strpos($item, '€') !== false) {
        //             if (strpos($item, 'za') !== false) {
        //                 $item = explode('za', $item)[0];
        //             }
        //             $price = $item;
        //         } elseif (strpos($item, 'Trajno znižano od') !== false) {
        //             $trajnoZnizanoOd = $item;
        //         } elseif (strpos($item, 'Številka izdelka:') !== false) {
        //             $productId = trim(explode('Številka izdelka:', $item)[1]);
        //         } else {
        //             $title[] = trim($item);
        //         }
        //     }

        //     if (!$title || !$price) {
        //         return null;
        //     }

        //     $title = implode(', ', $title);

        //     $price = $this->parsePrice($price);
        //     $regularPrice = $regularPrice ? $this->parsePrice($regularPrice) : $price;

        //     if (strpos($url, 'trajno-znizano') !== false) {
        //         $regularPrice = $price;
        //     }

        //     $unitQuantity = 1;
        //     $unit = null;

        //     if ($unitPrice) {
        //         $unitPrice = str_ireplace(['€', 'za'], '', $unitPrice);
        //         $unitPrice = trim($unitPrice);

        //         $start = strpos($unitPrice, '(');
        //         $end = strpos($unitPrice, ')');
        //         if ($start !== false && $end !== false) {
        //             $unitPrice = substr($unitPrice, $start + 1, $end - $start - 1);
        //             $unitPrice = explode('=', $unitPrice);
        //             $unit = trim($unitPrice[0]);
        //             $unitPrice = trim($unitPrice[1]);
        //             $unitPrice = str_ireplace(',', '.', $unitPrice);
        //             $unitPrice = (float)trim($unitPrice);
        //             try {
        //                 [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);
        //             } catch (\Throwable $th) {
        //                 dd($unit, $unitPrice, $price);
        //             }
        //         } else {
        //             try {
        //                 [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unitPrice, $price, $price);
        //             } catch (\Throwable $th) {
        //                 dd($data, $unitPrice, $price, $price);
        //             }
        //         }
        //     }

        //     return [
        //         'source' => Product::SOURCE_HOFER,
        //         'url' => null,
        //         'title' => $title,
        //         'unit' => $unit,
        //         'unitQuantity' => $unitQuantity,
        //         'unitPrice' => $unitPrice,
        //         'price' => $price,
        //         'regularPrice' => $regularPrice,
        //         'discount' => $this->getDiscount($price, $regularPrice),
        //         'ean' => null,
        //         'productId' => $productId,
        //     ];
        // });

        $items = array_filter($items);

        return $items;
    }

    public function getProductData(string $url): array
    {
        dd("TODO");
        return [];
    }
}
