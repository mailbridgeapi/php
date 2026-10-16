<?php
declare(strict_types=1);

namespace MailBridgeAPI;

use InvalidArgumentException;
use JsonException;
use MailBridgeAPI\Exception\ApiException;

final class Client
{
    private const DEFAULT_BASE_URL = 'https://api.mailbridgeapi.com';

    /** @var string */
    private $accountKey;

    /** @var string */
    private $mailboxKey;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeout;

    public function __construct(
        string $accountKey,
        string $mailboxKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeout = 30
    ) {
        if (trim($accountKey) === '' || trim($mailboxKey) === '') {
            throw new InvalidArgumentException('The account API key and mailbox API key are required.');
        }
        if ($timeout < 1) {
            throw new InvalidArgumentException('The timeout must be at least one second.');
        }

        $this->accountKey = $accountKey;
        $this->mailboxKey = $mailboxKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /** @return array<string,mixed> */
    public function health(): array
    {
        return $this->request('GET', '/health/', [], null, false, false);
    }

    /** @return array<string,mixed> */
    public function mailboxStatus(): array
    {
        return $this->request('GET', '/mailbox/status/');
    }

    /** @return array<string,mixed> */
    public function mailboxInsights(int $days = 30): array
    {
        return $this->request('GET', '/mailbox/insights/', ['days' => $days]);
    }

    /**
     * @param array<string,mixed> $message
     * @return array<string,mixed>
     */
    public function sendEmail(array $message): array
    {
        foreach (['recipient_email', 'mail_subject', 'mail_body'] as $field) {
            if (!isset($message[$field]) || trim((string) $message[$field]) === '') {
                throw new InvalidArgumentException($field . ' is required.');
            }
        }
        $payload = array_merge([
            'apikey' => $this->accountKey,
            'apikeybox' => $this->mailboxKey,
        ], $message);
        $response = $this->request('POST', '/sendemail/', [], $payload, true, false);
        if ((string) ($response['result']['error'] ?? '1') !== '0') {
            throw new ApiException(
                (string) ($response['result']['sent'] ?? 'Email delivery failed.'),
                200,
                $response
            );
        }
        return $response;
    }

    /** @return array<string,mixed> */
    public function getEmail(string $folder = 'INBOX', int $start = 0): array
    {
        return $this->request('GET', '/getemail/', [
            'apikey' => $this->accountKey,
            'apikeybox' => $this->mailboxKey,
            'mbox' => $folder,
            'start' => $start,
        ], null, false, false);
    }

    /** @return array<string,mixed> */
    public function listUnsubscribed(?string $email = null): array
    {
        $query = ['apikey' => $this->accountKey, 'apikeybox' => $this->mailboxKey];
        if ($email !== null && $email !== '') {
            $query['email'] = $email;
        }
        return $this->request('GET', '/email/unsubscribed/', $query, null, false, false);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function listSent(array $filters = []): array
    {
        return $this->request('GET', '/email/sent/', $filters);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function listReceived(array $filters = []): array
    {
        return $this->request('GET', '/email/received/', $filters);
    }

    /** @return array<string,mixed> */
    public function getMessage(string $direction, string $id): array
    {
        if (!in_array($direction, ['sent', 'received'], true)) {
            throw new InvalidArgumentException('direction must be sent or received.');
        }
        return $this->request('GET', '/email/message/', ['direction' => $direction, 'id' => $id]);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function searchEmails(string $query, array $filters = []): array
    {
        return $this->request('GET', '/email/search/', array_merge($filters, ['query' => $query]));
    }

    /** @return array<string,mixed> */
    public function activity(int $days = 30): array
    {
        return $this->request('GET', '/email/activity/', ['days' => $days]);
    }

    /** @param array<string,mixed> $message @return array<string,mixed> */
    public function validateEmail(array $message): array
    {
        return $this->request('POST', '/email/validate/', [], $message);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $form = false,
        bool $authenticated = true
    ): array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = ['Accept: application/json', 'User-Agent: mailbridgeapi-php/1.0'];
        if ($authenticated) {
            $headers[] = 'X-API-Key: ' . $this->accountKey;
            $headers[] = 'X-Mailbox-Key: ' . $this->mailboxKey;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new ApiException('Unable to initialize cURL.');
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            if ($form) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body, '', '&', PHP_QUERY_RFC3986));
            } else {
                $headers[] = 'Content-Type: application/json';
                try {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
                } catch (JsonException $exception) {
                    curl_close($curl);
                    throw new ApiException('The request body could not be encoded.', 0, [], $exception);
                }
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($curl);
        if ($raw === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new ApiException('MailBridgeAPI network error: ' . $message);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        try {
            $response = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiException('MailBridgeAPI returned invalid JSON.', $status, []);
        }
        if (!is_array($response)) {
            throw new ApiException('MailBridgeAPI returned an unexpected response.', $status, []);
        }
        if ($status < 200 || $status >= 300 || ($authenticated && isset($response['ok']) && $response['ok'] !== true)) {
            $message = (string) ($response['error']['message'] ?? 'MailBridgeAPI request failed.');
            throw new ApiException($message, $status, $response);
        }
        return $response;
    }
}

