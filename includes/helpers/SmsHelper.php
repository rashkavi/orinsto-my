<?php

/**
 * SmsHelper.php
 * Wrapper for hashnetlk.com SMS Gateway API
 */

class SmsHelper
{
    private $apiBase = 'https://sender.hashnetlk.com/api.php';
    private $username = 'info@orinsto.nl';
    private $password = '76561741368c1413e8f87e285269436';
    private $senderId = 'ORINSTO';
    private $bulkChunkSize = 20;

   
    public function sendSingleGet($to, $text)
    {
        $params = [
            'FUN'      => 'SEND_SINGLE',
            'with_get' => 'true',
            'un'       => $this->username,
            'up'       => $this->password,
            'senderID' => $this->senderId,
            'to'       => $to,
            'text'     => $text,
        ];

        $url = $this->apiBase . '?' . http_build_query($params);

        return $this->curlRequest($url, 'GET');
    }

    public function sendSingle($to, $msg)
    {
        $url = $this->apiBase . '?FUN=SEND_SINGLE';

        $body = [
            'senderID' => $this->senderId,
            'to'       => $to,
            'msg'      => $msg,
        ];

        return $this->curlRequest($url, 'POST', $body);
    }

    public function sendBulkSame(array $numbers, $msg)
    {
        $numbers = array_values(array_unique(array_filter($numbers)));
        $chunks  = array_chunk($numbers, $this->bulkChunkSize);
        $results = [];

        $url = $this->apiBase . '?FUN=SEND_BULK_SAME';

        foreach ($chunks as $chunk) {
            $body = [
                'senderID' => $this->senderId,
                'msg'      => $msg,
                'to'       => $chunk,
            ];
            $results[] = $this->curlRequest($url, 'POST', $body);
        }

        return $results;
    }

    public function sendBulkDifferent(array $messages)
    {
        $chunks  = array_chunk($messages, $this->bulkChunkSize);
        $results = [];

        $url = $this->apiBase . '?FUN=SEND_BULK_DIFFERENT';

        foreach ($chunks as $chunk) {
            $msgList = array_map(function ($m) {
                return ['msg' => $m['msg'], 'to' => $m['to']];
            }, $chunk);

            $body = [
                'senderID' => $this->senderId,
                'msg_list' => $msgList,
            ];
            $results[] = $this->curlRequest($url, 'POST', $body);
        }

        return $results;
    }

    public function checkBalance()
    {
        $url = $this->apiBase . '?FUN=CHECK_BALANCE';

        $body = [
            'un' => $this->username,
            'up' => $this->password,
        ];

        return $this->curlRequest($url, 'POST', $body);
    }

    private function curlRequest($url, $method = 'POST', $body = null)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // API docs specify Basic Auth (username + API key) for POST endpoints -
            // the JSON body itself doesn't carry credentials like the GET query params do.
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("cURL error: $error | URL: $url");
            return ['success' => false, 'error' => $error];
        }

        $decoded = json_decode($response, true);

        $this->log("SMS API call [$method] $url | HTTP $httpCode | Response: $response");

        return [
            'success'   => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'raw'       => $response,
            'data'      => $decoded,
        ];
    }

    private function log($message)
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logDir . '/sms.log', $line, FILE_APPEND);
    }
}
