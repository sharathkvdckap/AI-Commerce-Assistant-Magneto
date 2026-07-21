<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Model\Recognition;

use Klizer\ImageRecognition\Model\Api\VisionServiceClient;
use Magento\Framework\Exception\LocalizedException;

/**
 * Recognizes catalog search keywords from an uploaded image via the Node vision service.
 */
class ImageRecognizer
{
    public function __construct(
        private readonly VisionServiceClient $visionServiceClient
    ) {
    }

    /**
     * @param string $imagePath Absolute filesystem path to the uploaded image
     * @param string $originalName Original uploaded file name
     * @return string Search query derived from the image
     * @throws LocalizedException
     */
    public function recognize(string $imagePath, string $originalName): string
    {
        if (!is_readable($imagePath)) {
            throw new LocalizedException(__('Unable to read the uploaded image.'));
        }

        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            throw new LocalizedException(__('The uploaded file is not a valid image.'));
        }

        $analysis = $this->visionServiceClient->analyze($imagePath, $originalName);

        return $this->buildSearchQuery($analysis);
    }

    /**
     * @param array{
     *     category?: string,
     *     tags?: string[],
     *     colors?: string[],
     *     description?: string,
     *     confidence?: float|int
     * } $analysis
     */
    private function buildSearchQuery(array $analysis): string
    {
        $tags = $analysis['tags'] ?? [];
        if (is_array($tags)) {
            $tagWords = array_values(array_filter(array_map(
                static fn (mixed $tag): string => trim((string) $tag),
                $tags
            )));
            if ($tagWords !== []) {
                return implode(' ', $tagWords);
            }
        }

        $category = trim((string) ($analysis['category'] ?? ''));
        if ($category !== '') {
            return $category;
        }

        $description = trim((string) ($analysis['description'] ?? ''));
        if ($description !== '') {
            $words = preg_split('/\s+/', $description) ?: [];
            $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
            if ($words !== []) {
                return implode(' ', array_slice($words, 0, 8));
            }
        }

        return 'product';
    }
}
