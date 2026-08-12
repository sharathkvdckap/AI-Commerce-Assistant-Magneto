<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Model\Api;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Server-side client for AI Commerce Assistant Node API.
 */
class Client
{
    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * POST /api/assistant/start
     *
     * @param array<string, mixed> $reuseFilters
     * @param array{customerId: string, exp: int, sig: string}|null $identity
     * @return array<string, mixed>
     */
    public function start(
        string $query,
        string $userId = '',
        array $reuseFilters = [],
        string $reuseHistoryId = '',
        ?array $identity = null
    ): array {
        $payload = ['query' => $query];
        $this->attachIdentity($payload, $userId, $identity);

        if ($reuseFilters !== []) {
            $payload['reuseFilters'] = $reuseFilters;
        }
        if ($reuseHistoryId !== '') {
            $payload['reuseHistoryId'] = $reuseHistoryId;
        }

        return $this->postJson('/api/assistant/start', $payload);
    }

    /**
     * POST /api/assistant/message
     *
     * @param array{customerId: string, exp: int, sig: string}|null $identity
     * @return array<string, mixed>
     */
    public function message(
        string $sessionId,
        string $answer,
        string $userId = '',
        ?array $identity = null
    ): array {
        $payload = [
            'sessionId' => $sessionId,
            'answer' => $answer,
        ];
        $this->attachIdentity($payload, $userId, $identity);

        return $this->postJson('/api/assistant/message', $payload);
    }

    /**
     * POST /api/assistant/search
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function search(array $filters): array
    {
        return $this->postJson('/api/assistant/search', $filters);
    }

    /**
     * POST /api/context/search — find a similar previous shopping session.
     *
     * @param array{customerId: string, exp: int, sig: string}|null $identity
     * @return array<string, mixed>
     */
    public function contextSearch(
        string $userId,
        string $query,
        ?array $identity = null
    ): array {
        $payload = ['query' => $query];
        $this->attachIdentity($payload, $userId, $identity);

        return $this->postJson('/api/context/search', $payload);
    }

    /**
     * POST /api/analytics/track — product impressions & clicks (CTR).
     *
     * @param list<array<string, mixed>> $events
     * @param array{customerId: string, exp: int, sig: string}|null $identity
     * @return array<string, mixed>
     */
    public function trackAnalytics(
        string $userId,
        array $events,
        ?string $sessionId = null,
        ?string $searchId = null,
        ?string $source = null,
        ?array $identity = null
    ): array {
        $payload = [
            'events' => $events,
        ];
        $this->attachIdentity($payload, $userId, $identity);

        if ($sessionId !== null && $sessionId !== '') {
            $payload['sessionId'] = $sessionId;
        }
        if ($searchId !== null && $searchId !== '') {
            $payload['searchId'] = $searchId;
        }
        if ($source !== null && $source !== '') {
            $payload['source'] = $source;
        }

        return $this->postJson('/api/analytics/track', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{customerId: string, exp: int, sig: string}|null $identity
     */
    private function attachIdentity(array &$payload, string $userId, ?array $identity): void
    {
        if ($userId !== '') {
            $payload['userId'] = $userId;
        }
        if ($identity !== null
            && isset($identity['customerId'], $identity['exp'], $identity['sig'])
            && $identity['customerId'] !== ''
            && $identity['sig'] !== ''
        ) {
            $payload['identity'] = [
                'customerId' => (string) $identity['customerId'],
                'exp' => (int) $identity['exp'],
                'sig' => (string) $identity['sig'],
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        $base = $this->config->getApiBaseUrl();
        if ($base === '') {
            throw new \RuntimeException('AI API base URL is not configured.');
        }

        $url = $base . $path;
        $body = $this->json->serialize($payload);

        $this->logger->warning('AiCommerceAssistant → Node', [
            'path' => $path,
            'userId' => $payload['userId'] ?? null,
            'identityAttached' => isset($payload['identity']),
            'identityCustomerId' => $payload['identity']['customerId'] ?? null,
            'identityExp' => $payload['identity']['exp'] ?? null,
        ]);

        $this->curl->setTimeout($this->config->getTimeout());
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->post($url, $body);

        $status = (int) $this->curl->getStatus();
        $responseBody = (string) $this->curl->getBody();

        try {
            $decoded = $this->json->unserialize($responseBody);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('AiCommerceAssistant invalid JSON', [
                'url' => $url,
                'status' => $status,
                'body' => substr($responseBody, 0, 500),
            ]);
            throw new \RuntimeException('AI service returned invalid JSON.');
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('AI service returned an unexpected response.');
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? ('AI service HTTP ' . $status));
            $this->logger->error('AiCommerceAssistant API error', [
                'url' => $url,
                'status' => $status,
                'message' => $message,
            ]);
            throw new \RuntimeException($message);
        }

        return $decoded;
    }
}
