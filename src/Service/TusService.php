<?php

namespace App\Service;

use App\Entity\Product;
use App\Service\Api\GeminiApi;
use Psr\Cache\CacheItemPoolInterface;

class TusService extends AbstractShopService
{
    const URL = 'https://hitrinakup.com/graphql';
    const STORE_ID = '5861';

    protected $apiVersion = null;
    protected $sessionId = null;

    protected CacheItemPoolInterface $cache;
    protected GeminiApi $geminiApi;

    public function __construct(CacheItemPoolInterface $cache, GeminiApi $geminiApi)
    {
        $this->cache = $cache;
        $this->geminiApi = $geminiApi;
    }

    public function getProductsData(string $category, string $subCategory): array
    {
        $data = [
            'operationName' => 'getItemsForSelectedSubCategory',
            'variables' => [
                "categoriesLimit" => 3000,
                "categoriesSkip" => 0,
                "categoryName" => $category,
                "cypherQuery" => "123a17d4-b159-4976-ac50-8590059b48c0",
                "date" => (new \DateTime())->format('D M d Y'),
                "filterProperties" => [],
                "storeId" => self::STORE_ID,
                "subcategoryName" => $subCategory,
            ],
            'query' => 'query getItemsForSelectedSubCategory($categoriesLimit: Int, $categoriesSkip: Int, $categoryName: String, $cypherQuery: String, $date: String, $filterProperties: [FilterCategoryDataInput], $storeId: String, $subcategoryName: String) { getItemsForSelectedSubCategory( categoriesLimit: $categoriesLimit categoriesSkip: $categoriesSkip categoryName: $categoryName cypherQuery: $cypherQuery date: $date filterProperties: $filterProperties storeId: $storeId subcategoryName: $subcategoryName ) { items { weight EAN name price discountedPrice group quantity inStock priceEm em discountEan id brand category percDiscount } name } } ',
        ];

        $headers = [
            'apiversion: ' . $this->getApiVersion(),
            'sessionid: ' . $this->getSessionId(),
        ];

        $items = $this->curlRequest(self::URL, $data, $headers);
        $items = $items['data']['getItemsForSelectedSubCategory']['items'] ?? false;
        if (!$items) {
            throw new \Exception("No items found for category: $category / $subCategory");
        }

        $data = [];
        foreach ($items as $item) {
            if (!isset($item['price']) || !$item['price'] || !isset($item['EAN']) || !$item['EAN']) {
                continue;
            }

            $price = $item['discountedPrice'] ?? $item['price'];
            $unit = $item['em'];
            $unitPrice = $item['priceEm'];

            if (!$unitPrice) {
                $res = $this->geminiApi->apiRequest(
                    "Calculate unit price (priceEm) from data, if you can. Data: " . json_encode($item),
                    [
                        "type" => "OBJECT",
                        "properties" => ['priceEm' => ["type" => "number", "format" => 'double']],
                        "required" => ['priceEm']
                    ]
                );
                $res = $res[0]['priceEm'] ?? null;
                $unitPrice = $res ?? $price;
            }

            if (!$unit) {
                $res = $this->geminiApi->apiRequest(
                    "Return the unit base (em) from data, if you can. Data: " . json_encode($item),
                    [
                        "type" => "OBJECT",
                        "properties" => ['em' => ["type" => "string"]],
                        "required" => ['em']
                    ]
                );
                $res = $res[0]['em'] ?? null;
                $unit = $res ?? 'kos';
            }

            [$unit, $unitQuantity, $unitPrice] = $this->unitPriceCalculation($unit, $unitPrice, $price);

            $regularPrice = $item['price'] ?: $price;

            $data[] = [
                'source' => Product::SOURCE_TUS,
                'url' => 'https://hitrinakup.com/izdelki/' . $item['id'],
                'title' => trim($item['name']),
                'unit' => $unit,
                'unitQuantity' => $unitQuantity,
                'unitPrice' => $unitPrice,
                'price' => $price,
                'regularPrice' => $regularPrice,
                'discount' => $item['percDiscount'] ?? $this->getDiscount($price, $regularPrice),
                'ean' => $this->parseEan($item['EAN']),
                'productId' => $item['id'],
            ];
        }

        return $data;
    }

    protected function getApiVersion(): string
    {
        if ($this->apiVersion === null) {

            $cacheKey = 'tus_api_version';
            $item = $this->cache->getItem($cacheKey);

            if ($item->isHit()) {
                $this->apiVersion = $item->get();
            } else {
                $res = $this->curlRequest(self::URL, [
                    'operationName' => 'getApiVersion',
                    'variables' => [],
                    'query' => 'query getApiVersion { getApiVersion }',
                ]);

                $this->apiVersion = $res['data']['getApiVersion'] ?? false;

                if ($this->apiVersion) {
                    $item->set($this->apiVersion);
                    $item->expiresAfter(86400);
                    $this->cache->save($item);
                }
            }
        }
        return $this->apiVersion;
    }

    protected function getSessionId(): string
    {
        if ($this->sessionId === null) {

            $cacheKey = 'tus_session_id';
            $item = $this->cache->getItem($cacheKey);

            if ($item->isHit()) {
                $this->sessionId = $item->get();
            } else {

                $res = $this->curlRequest(self::URL, [
                    'operationName' => 'createSession',
                    'variables' => [
                        "userId" => "8e558917-c00c-48b5-be43-80477c61ba09",
                        "uri" => "hitrinakup.com",
                        "agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0",
                        "url" => "https://hitrinakup.com",
                        "prevSessionId" => null,
                        "code" => null,
                        // "version" => "0.4.30"
                    ],
                    'query' => 'mutation createSession($userId: String!, $uri: String!, $agent: String!, $url: String, $prevSessionId: String, $code: String, $version: String) { createSession( userId: $userId uri: $uri agent: $agent url: $url prevSessionId: $prevSessionId code: $code version: $version ) { id date agent __typename } }',
                ], [
                    'apiversion: ' . $this->getApiVersion(),
                ]);

                $this->sessionId = $res['data']['createSession']['id'] ?? false;

                if ($this->sessionId) {
                    $item->set($this->sessionId);
                    $item->expiresAfter(3600);
                    $this->cache->save($item);
                }
            }
        }
        return $this->sessionId;
    }

    public function getCategories(): array
    {
        $cacheKey = 'tus_categories';
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $data = [
            'operationName' => 'getCategories',
            'variables' => [
                "userId" => "",
                "storeId" => self::STORE_ID,
                "limit" => 0,
                "cypherQuery" => "2d078df3-117a-4b05-be35-cfb99105fb77",
            ],
            // 'query' => 'query getCategories($userId: String, $limit: Int, $cypherQuery: String) { getCategories(userId: $userId, limit: $limit, cypherQuery: $cypherQuery) { name key children { name key children { name key } } } }',
            'query' => 'query getCategories($userId: String, $limit: Int, $cypherQuery: String) { getCategories(userId: $userId, limit: $limit, cypherQuery: $cypherQuery) { name children { name } } }',
        ];

        $headers = [
            'apiversion: ' . $this->getApiVersion(),
            'sessionid: ' . $this->getSessionId(),
        ];

        $items = $this->curlRequest(self::URL, $data, $headers);
        $items = $items['data']['getCategories'] ?? false;
        if (!$items) {
            throw new \Exception("No categories found");
        }

        $items = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'children' => array_map(function ($child) {
                    return $child['name'];
                }, $item['children'] ?? []),
            ];
        }, $items);

        if ($items) {
            $item->set($items);
            $item->expiresAfter(86400);
            $this->cache->save($item);
        }

        return $items;
    }

    public function getCategoryAndSubcategoryInOrder(int $k): array
    {
        $categories = $this->getCategories();

        $i = 0;
        foreach ($categories as $category) {
            foreach ($category['children'] as $subCategory) {
                if ($k === $i++) {
                    return [$category['name'], $subCategory];
                }
            }
        }

        return ['', ''];
    }

    public function getProductData(string $url): array
    {
        dd("TODO");
        return [];
    }
}
