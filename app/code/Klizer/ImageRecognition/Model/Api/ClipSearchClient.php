<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Model\Api;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the local Node CLIP embedding service.
 */
class ClipSearchClient
{
    private const SEARCH_PATH = '/api/v1/images/search';
    private const INDEX_PATH = '/api/v1/embeddings/index';

    public function __construct(
        private readonly ImageRecognitionHelper $helper,
        private readonly Random $mathRandom,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return list<array{productId: string, score: float, imageLabel?: string}>
     * @throws LocalizedException
     */
    public function searchByImage(string $imagePath, ?string $originalName = null): array
    {
        $payload = $this->request(
            self::SEARCH_PATH,
            $imagePath,
            $originalName,
            [
                'topK' => (string) $this->helper->getSearchTopK(),
                'minSimilarity' => (string) $this->helper->getMinSimilarity(),
            ]
        );

        $matches = $payload['matches'] ?? [];
        if (!is_array($matches)) {
            throw new LocalizedException(__('Image search returned incomplete data.'));
        }

        $minSimilarity = $this->helper->getMinSimilarity();
        $normalized = [];
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }
            $productId = trim((string) ($match['productId'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $score = (float) ($match['score'] ?? 0);
            // Hide weak matches (default: score <= 50%).
            if ($score <= $minSimilarity) {
                continue;
            }

            $normalized[] = [
                'productId' => $productId,
                'score' => $score,
                'imageLabel' => isset($match['imageLabel']) ? (string) $match['imageLabel'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array{id?: string, productId: string, dimensions?: int, model?: string}
     * @throws LocalizedException
     */
    public function indexProductImage(
        string $productId,
        string $imagePath,
        ?string $imageLabel = null,
        ?string $originalName = null
    ): array {
        $fields = ['productId' => $productId];
        if ($imageLabel !== null && $imageLabel !== '') {
            $fields['imageLabel'] = $imageLabel;
        }

        $payload = $this->request(self::INDEX_PATH, $imagePath, $originalName, $fields);
        if (empty($payload['productId'])) {
            $payload['productId'] = $productId;
        }

        return $payload;
    }

    /**
     * @param array<string, string> $extraFields
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function request(
        string $path,
        string $imagePath,
        ?string $originalName,
        array $extraFields = []
    ): array {
        if (!is_readable($imagePath)) {
            throw new LocalizedException(__('Unable to read the image file.'));
        }

        $baseUrl = $this->helper->getServiceBaseUrl();
        $apiKey = $this->helper->getApiKey();

        if ($baseUrl === '') {
            throw new LocalizedException(__('Image search service URL is not configured.'));
        }
        if ($apiKey === '') {
            throw new LocalizedException(__('Image search API key is not configured.'));
        }

        $requestId = $this->generateRequestId();
        $url = $baseUrl . $path;
        $fileName = $originalName ?: basename($imagePath);
        $mimeType = $this->detectMimeType($imagePath);

        $curl = curl_init();
        if ($curl === false) {
            throw new LocalizedException(__('Unable to initialize the image search request.'));
        }

        try {
            $postFields = $extraFields;
            $postFields['image'] = new \CURLFile($imagePath, $mimeType, $fileName);

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->helper->getRequestTimeoutSeconds(),
                CURLOPT_HTTPHEADER => [
                    'x-api-key: ' . $apiKey,
                    'x-request-id: ' . $requestId,
                    'Accept: application/json',
                ],
            ]);

            $rawBody = curl_exec($curl);
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($curl);
        }

        if ($errno !== 0) {
            $this->logger->error(sprintf(
                'CLIP service request failed (path=%s, requestId=%s, errno=%d): %s',
                $path,
                $requestId,
                $errno,
                $error
            ));

            if ($errno === CURLE_OPERATION_TIMEDOUT || stripos($error, 'timed out') !== false) {
                throw new LocalizedException(__('Image search timed out. Please try again.'));
            }

            throw new LocalizedException(__('Unable to reach the image search service. Please try again later.'));
        }

        if (!is_string($rawBody) || $rawBody === '') {
            throw new LocalizedException(__('Image search service returned an empty response.'));
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new LocalizedException(__('Image search service returned an invalid response.'));
        }

        if (!is_array($payload)) {
            throw new LocalizedException(__('Image search service returned an invalid response.'));
        }

        $success = (bool) ($payload['success'] ?? false);
        $message = trim((string) ($payload['message'] ?? ''));

        if ($statusCode < 200 || $statusCode >= 300 || !$success) {
            $this->logger->warning(sprintf(
                'CLIP service error (path=%s, requestId=%s, status=%d): %s',
                $path,
                $requestId,
                $statusCode,
                $message !== '' ? $message : 'unknown error'
            ));

            if ($message !== '') {
                throw new LocalizedException(__('%1', $message));
            }

            throw new LocalizedException(__('Image search failed. Please try a different image.'));
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            throw new LocalizedException(__('Image search service returned incomplete data.'));
        }

        return $data;
    }

    private function generateRequestId(): string
    {
        try {
            return $this->mathRandom->getUniqueHash();
        } catch (\Exception) {
            return bin2hex(random_bytes(16));
        }
    }

    private function detectMimeType(string $imagePath): string
    {
        $imageInfo = @getimagesize($imagePath);
        if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
            return (string) $imageInfo['mime'];
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
