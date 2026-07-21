<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Block;

use Klizer\VisualSearch\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Camera extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isHeaderCameraEnabled();
    }

    public function getUploadUrl(): string
    {
        return $this->getUrl('visualsearch/search/upload');
    }

    public function getResultsPageUrl(): string
    {
        return $this->getUrl('visualsearch/search/index');
    }

    public function getDefaultMatchMode(): string
    {
        return $this->config->getDefaultMatchMode();
    }
}
