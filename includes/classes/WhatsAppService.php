<?php

class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;
    private string $apiUrl;

    public function __construct()
    {
        $this->token = Project::getWhatsAppApi();
        $this->phoneNumberId = Project::getWhatsAppNumber();
        $this->apiUrl = "https://graph.facebook.com/v22.0/{$this->phoneNumberId}/messages";
    }

    public function normalizePhoneNumber(string $number): string|false
    {
        $number = preg_replace('/[\s\-()]/', '', trim($number));

        if (!$number) {
            return false;
        }

        // Sri Lanka: 0771234567 -> +94771234567
        if (preg_match('/^0(7[0-9]{8})$/', $number, $matches)) {
            return '+94' . $matches[1];
        }

        // Sri Lanka: 94771234567 -> +94771234567
        if (preg_match('/^94(7[0-9]{8})$/', $number, $matches)) {
            return '+94' . $matches[1];
        }

        // Already international format
        if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
            return str_starts_with($number, '+') ? $number : '+' . $number;
        }

        return false;
    }

    public function sendTemplate(
        string $to,
        string $templateName,
        array $params = [],
        string $lang = 'en_US'
    ): array {
        $formattedTo = $this->normalizePhoneNumber($to);

        if ($formattedTo === false) {
            return [
                "success" => false,
                "response" => null,
                "error" => "Invalid WhatsApp phone number."
            ];
        }

        $parameters = array_map(function ($param) {
            return [
                "type" => "text",
                "text" => (string) $param
            ];
        }, $params);

        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $formattedTo,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => [
                    "code" => $lang
                ],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $parameters
                    ]
                ]
            ]
        ];

        $curl = curl_init();

        if ($curl === false) {
            return [
                "success" => false,
                "response" => null,
                "error" => "Failed to initialize cURL."
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->token}",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES)
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return [
            "success" => $response !== false && empty($curlError) && $httpCode >= 200 && $httpCode < 300,
            "http_code" => $httpCode,
            "response" => $response ? json_decode($response, true) : null,
            "error" => $curlError ?: null
        ];
    }
}