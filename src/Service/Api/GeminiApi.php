<?php

namespace App\Service\Api;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class GeminiApi
{
    const MODEL_CACHE_KEY = 'gemini_model';

    const MODELS = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite-preview-06-17',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash',
        'gemini-1.5-flash-8b',
    ];

    protected $apiKey;
    protected $model = null;

    protected CacheItemPoolInterface $cache;

    public function __construct($apiKey, CacheItemPoolInterface $cache)
    {
        $this->apiKey = $apiKey;
        $this->cache = $cache;
    }

    protected function getModel()
    {
        if ($this->model) {
            return $this->model;
        }

        $item = $this->cache->getItem(self::MODEL_CACHE_KEY);
        if ($item->isHit()) {
            $this->model = $item->get();
            return $this->model;
        }

        return $this->model === null ? self::MODELS[0] : $this->model;
    }

    protected function incrementModel()
    {
        $item = $this->cache->getItem(self::MODEL_CACHE_KEY);
        if ($item->isHit()) {
            $currentModel = $item->get();
            $currentIndex = array_search($currentModel, self::MODELS);
            $currentIndex = $currentIndex !== false ? $currentIndex : -1;
            $this->model = self::MODELS[($currentIndex + 1) % count(self::MODELS)];
        } else {
            $this->model = self::MODELS[0];
        }

        $item->set($this->model);
        $item->expiresAfter(86400); // 1 day
        $this->cache->save($item);

        return $this->model;
    }

    public function apiRequest(string $query, array $jsonResponseStructure = [], ?string $imageBase64 = null, int $tries = 0)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->getModel() . ':generateContent?key=' . $this->apiKey;
        $headers = [
            'Content-Type: application/json',
        ];

        $data = ['contents' => [['parts' => [['text' => $query]]]]];

        // Add image as a part if $imageBase64 is provided
        if ($imageBase64) {
            $data['contents'][0]['parts'][] = [
                'inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => $imageBase64,
                ]
            ];
        }

        if ($jsonResponseStructure) {
            $data["generationConfig"] = [
                "responseMimeType" => "application/json",
                "responseSchema" => [
                    "type" => "ARRAY",
                    "items" => $jsonResponseStructure,
                ]
            ];
        }

        $data = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);
        if (isset($response['error'])) {
            if ($tries > 1) {
                throw new ServiceUnavailableHttpException(null, $response['error']['message'], null, $response['error']['code'] ?? 0);
            }
            $this->incrementModel();
            // Retry the request with the next model
            return $this->apiRequest($query, $jsonResponseStructure, $imageBase64, $tries + 1);
        }

        $response = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($jsonResponseStructure) {
            $response = json_decode($response, true);
        }
        return $response;
    }
}
