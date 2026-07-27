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
     * @return array<string, mixed>
     */
    public function start(string $query): array
    {
        return $this->postJson('/api/assistant/start', ['query' => $query]);
    }

    /**
     * POST /api/assistant/message
     *
     * @return array<string, mixed>
     */
    public function message(string $sessionId, string $answer): array
    {
        return $this->postJson('/api/assistant/message', [
            'sessionId' => $sessionId,
            'answer' => $answer,
        ]);
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
