<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Model\Search;

use Magento\Framework\Session\SessionManagerInterface;

/**
 * Stores CLIP search matches in customer/frontend session between upload and result page.
 */
class ResultStorage
{
    private const SESSION_KEY = 'klizer_imagerecognition_matches';
    private const SESSION_KEY_QUERY_IMAGE = 'klizer_imagerecognition_query_image';

    public function __construct(
        private readonly SessionManagerInterface $session
    ) {
    }

    /**
     * @param list<array{productId: string, score: float, imageLabel?: string|null}> $matches
     */
    public function saveMatches(array $matches): void
    {
        $this->session->setData(self::SESSION_KEY, $matches);
    }

    /**
     * @return list<array{productId: string, score: float, imageLabel?: string|null}>
     */
    public function getMatches(): array
    {
        $matches = $this->session->getData(self::SESSION_KEY);
        return is_array($matches) ? $matches : [];
    }

    /**
     * @return list<int>
     */
    public function getProductIds(): array
    {
        $ids = [];
        foreach ($this->getMatches() as $match) {
            if (!is_array($match)) {
                continue;
            }
            $id = (int) ($match['productId'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Persist relative media path of the uploaded query image (e.g. imagerecognition/tmp/foo.jpg).
     */
    public function saveQueryImage(string $relativeMediaPath): void
    {
        $this->session->setData(self::SESSION_KEY_QUERY_IMAGE, ltrim($relativeMediaPath, '/'));
    }

    public function getQueryImage(): ?string
    {
        $path = $this->session->getData(self::SESSION_KEY_QUERY_IMAGE);
        if (!is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }

    public function clear(): void
    {
        $this->session->unsetData(self::SESSION_KEY);
        $this->session->unsetData(self::SESSION_KEY_QUERY_IMAGE);
    }
}
