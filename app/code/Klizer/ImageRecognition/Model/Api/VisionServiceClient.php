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
 * HTTP client for the Node AI Image Recognition microservice.
 */
class VisionServiceClient
{
    private const ANALYZE_PATH = '/api/v1/images/analyze';

    public function __construct(
        private readonly ImageRecognitionHelper $helper,
        private readonly Random $mathRandom,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Analyze an image via the Node vision service.
     *
     * @return array{
     *     category?: string,
     *     tags?: string[],
     *     colors?: string[],
     *     description?: string,
     *     confidence?: float|int
     * }
     * @throws LocalizedException
     */
    public function analyze(string $imagePath, ?string $originalName = null): array
    {
        if (!is_readable($imagePath)) {
            throw new LocalizedException(__('Unable to read the uploaded image.'));
        }

        $baseUrl = $this->helper->getServiceBaseUrl();
        $apiKey = $this->helper->getApiKey();

        if ($baseUrl === '') {
            throw new LocalizedException(__('Image recognition service URL is not configured.'));
        }

        if ($apiKey === '') {
            throw new LocalizedException(__('Image recognition API key is not configured.'));
        }

        $requestId = $this->generateRequestId();
        $url = $baseUrl . self::ANALYZE_PATH;
        $fileName = $originalName ?: basename($imagePath);
        $mimeType = $this->detectMimeType($imagePath);

        $curl = curl_init();
        if ($curl === false) {
            throw new LocalizedException(__('Unable to initialize the image recognition request.'));
        }

        try {
            $curlFile = new \CURLFile($imagePath, $mimeType, $fileName);

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['image' => $curlFile],
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
                'Image recognition service request failed (requestId=%s, errno=%d): %s',
                $requestId,
                $errno,
                $error
            ));

            if ($errno === CURLE_OPERATION_TIMEDOUT || stripos($error, 'timed out') !== false) {
                throw new LocalizedException(__(
                    'Image recognition timed out. Please try again with a smaller image.'
                ));
            }

            throw new LocalizedException(__(
                'Unable to reach the image recognition service. Please try again later.'
            ));
        }

        if (!is_string($rawBody) || $rawBody === '') {
            $this->logger->error(sprintf(
                'Image recognition service returned an empty response (requestId=%s, status=%d).',
                $requestId,
                $statusCode
            ));
            throw new LocalizedException(__('Image recognition service returned an empty response.'));
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error(sprintf(
                'Image recognition service returned invalid JSON (requestId=%s, status=%d).',
                $requestId,
                $statusCode
            ));
            throw new LocalizedException(__('Image recognition service returned an invalid response.'));
        }

        if (!is_array($payload)) {
            throw new LocalizedException(__('Image recognition service returned an invalid response.'));
        }

        $success = (bool) ($payload['success'] ?? false);
        $message = trim((string) ($payload['message'] ?? ''));

        if ($statusCode < 200 || $statusCode >= 300 || !$success) {
            $this->logger->warning(sprintf(
                'Image recognition service error (requestId=%s, status=%d): %s',
                $requestId,
                $statusCode,
                $message !== '' ? $message : 'unknown error'
            ));

            if ($message !== '') {
                throw new LocalizedException(__('%1', $message));
            }

            throw new LocalizedException(__(
                'Image recognition failed. Please try a different image.'
            ));
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            throw new LocalizedException(__('Image recognition service returned incomplete data.'));
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
