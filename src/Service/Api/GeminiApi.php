<?php

namespace App\Service\Api;

class GeminiApi
{
    protected $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function apiRequest(string $query, array $jsonResponseStructure = [], string $model = 'gemini-2.0-flash')
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
        $headers = [
            'Content-Type: application/json',
        ];

        $data = ['contents' => [['parts' => [['text' => $query]]]]];

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
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);

        $response = json_decode($response, true);
        if (isset($response['error'])) {
            throw new \Exception('API error: ' . $response['error']['message']);
        }

        $response = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($jsonResponseStructure) {
            $response = json_decode($response, true);
        }
        return $response;
    }
}
