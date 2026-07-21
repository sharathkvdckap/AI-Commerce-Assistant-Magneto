<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\ViewModel;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class SearchConfig implements ArgumentInterface
{
    public function __construct(
        private readonly ImageRecognitionHelper $helper,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->helper->isEnabled();
    }

    public function getUploadUrl(): string
    {
        return $this->urlBuilder->getUrl('imagerecognition/ajax/upload');
    }

    public function getMaxFileSizeBytes(): int
    {
        return $this->helper->getMaxFileSizeBytes();
    }

    /**
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        return $this->helper->getAllowedExtensions();
    }

    public function getAcceptAttribute(): string
    {
        $extensions = $this->getAllowedExtensions();
        if (!$extensions) {
            return 'image/*';
        }

        return implode(',', array_map(
            static fn (string $ext): string => '.' . ltrim($ext, '.'),
            $extensions
        ));
    }
}
