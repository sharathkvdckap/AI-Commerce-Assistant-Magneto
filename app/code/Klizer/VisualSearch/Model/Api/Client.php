<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Model\Api;

use Klizer\VisualSearch\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for ai-image-recognition-service.
 * API key stays server-side — never expose to the browser.
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
     * POST /api/v1/images/search (multipart image)
     *
     * @return array{matches: array, overallConfidence?: string, suggestedAction?: string, hybridHint?: string}
     */
    public function searchByImage(
        string $absoluteImagePath,
        ?string $matchMode = null,
        ?int $topK = null,
        ?string $excludeProductId = null
    ): array {
        $fields = [
            'topK' => (string)($topK ?? $this->config->getTopK()),
            'matchMode' => $matchMode ?: $this->config->getDefaultMatchMode(),
        ];
        if ($excludeProductId) {
            $fields['excludeProductId'] = $excludeProductId;
        }

        return $this->postMultipart('/api/v1/images/search', $absoluteImagePath, $fields);
    }

    /**
     * POST /api/v1/images/similar (JSON)
     */
    public function searchSimilar(string $productId, ?string $matchMode = null, ?int $topK = null): array
    {
        $payload = [
            'productId' => $productId,
            'topK' => $topK ?? $this->config->getTopK(),
            'matchMode' => $matchMode ?: $this->config->getDefaultMatchMode(),
        ];

        return $this->postJson('/api/v1/images/similar', $payload);
    }

    /**
     * POST /api/v1/embeddings/index (multipart image + productId)
     */
    public function indexProductImage(
        string $productId,
        string $absoluteImagePath,
        ?string $imageLabel = null
    ): array {
        $fields = ['productId' => $productId];
        if ($imageLabel) {
            $fields['imageLabel'] = $imageLabel;
        }

        return $this->postMultipart('/api/v1/embeddings/index', $absoluteImagePath, $fields);
    }

    private function postJson(string $path, array $payload): array
    {
        $url = $this->config->getBaseUrl() . $path;
        $this->curl->setTimeout($this->config->getTimeout());
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('x-api-key', $this->config->getApiKey());
        $this->curl->post($url, $this->json->serialize($payload));

        return $this->parseResponse($url, $this->curl->getStatus(), $this->curl->getBody());
    }

    private function postMultipart(string $path, string $absoluteImagePath, array $fields): array
    {
        if (!is_readable($absoluteImagePath)) {
            throw new \RuntimeException('Image file not readable: ' . $absoluteImagePath);
        }

        $url = $this->config->getBaseUrl() . $path;
        $mime = mime_content_type($absoluteImagePath) ?: 'image/jpeg';
        $filename = basename($absoluteImagePath);

        // Prefer curl_* for true multipart file upload.
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to init curl');
        }

        $post = $fields;
        $post['image'] = new \CURLFile($absoluteImagePath, $mime, $filename);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->config->getApiKey(),
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $this->logger->error('VisualSearch curl error', ['url' => $url, 'error' => $error]);
            throw new \RuntimeException('AI service request failed: ' . $error);
        }

        return $this->parseResponse($url, $status, (string)$body);
    }

    private function parseResponse(string $url, int $status, string $body): array
    {
        $decoded = [];
        try {
            $decoded = $this->json->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('VisualSearch invalid JSON', ['url' => $url, 'body' => $body]);
            throw new \RuntimeException('AI service returned invalid JSON');
        }

        if ($status < 200 || $status >= 300 || empty($decoded['success'])) {
            $message = $decoded['message'] ?? ('AI service HTTP ' . $status);
            $this->logger->error('VisualSearch API error', [
                'url' => $url,
                'status' => $status,
                'message' => $message,
            ]);
            throw new \RuntimeException((string)$message);
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }
}
