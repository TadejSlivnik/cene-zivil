<?php

namespace App\Service\Api;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class GeminiApi
{
    protected $apiKey;

    protected $lastImageBase64 = null;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function apiRequest(string $query, array $jsonResponseStructure = [], ?string $imageUrl = null, string $model = 'gemini-2.0-flash')
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
        $headers = [
            'Content-Type: application/json',
        ];

        $data = ['contents' => [['parts' => [['text' => $query]]]]];

        // Add image as a part if $imageUrl is provided
        if ($imageUrl) {
            $imageBase64 = base64_encode(file_get_contents($imageUrl));
            if ($this->lastImageBase64 === $imageBase64) {
                throw new \Exception('Image has not changed since last request... throwing exception to stop loop');
            } else {
                $this->lastImageBase64 = $imageBase64;
            }
            if ($imageBase64 === false) {
                throw new \Exception('Failed to read image file');
            }

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
            throw new ServiceUnavailableHttpException( null, $response['error']['message'], null, $response['error']['code'] ?? 0);
        }

        $response = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($jsonResponseStructure) {
            $response = json_decode($response, true);
        }
        return $response;
    }
}
