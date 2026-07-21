<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Block\Search;

use Klizer\VisualSearch\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Visual search results / upload landing page (Phase 2 wireframe).
 */
class Page extends Template
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
        return $this->config->isEnabled();
    }

    public function getUploadUrl(): string
    {
        return $this->getUrl('visualsearch/search/upload');
    }

    public function getTextSearchUrl(): string
    {
        return $this->getUrl('catalogsearch/result');
    }

    public function getDefaultMatchMode(): string
    {
        return $this->config->getDefaultMatchMode();
    }

    /**
     * @return list<array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function getMatchModes(): array
    {
        return [
            ['value' => 'exact', 'label' => __('Exact look')],
            ['value' => 'style', 'label' => __('Same style')],
            ['value' => 'color', 'label' => __('Same color')],
            ['value' => 'cheaper', 'label' => __('Cheaper alt')],
        ];
    }
}
