<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Block\Header;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Klizer\AiCommerceAssistant\Model\UserIdentity;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Helper\Data as SearchHelper;

/**
 * Header search AI icon — opens the AI shopping entry experience.
 */
class Launcher extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly SearchHelper $searchHelper,
        private readonly UserIdentity $userIdentity,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled()
            && (
                $this->config->shouldEmbedOnSearchResults()
                || $this->config->shouldRedirectSearch()
            );
    }

    protected function _toHtml(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Empty-query AI landing (embed) or external assistant URL (redirect mode).
     */
    public function getEntryUrl(): string
    {
        if ($this->config->shouldRedirectSearch()) {
            return $this->config->buildAssistantUrl(
                null,
                null,
                $this->userIdentity->getRedirectQueryParams()
            );
        }

        return $this->getUrl('aicommerceassistant');
    }

    /**
     * Keyword search results URL builder base (append ?q=…).
     * Honors redirect mode via Search Helper plugin when enabled.
     */
    public function getSearchResultUrl(): string
    {
        return $this->searchHelper->getResultUrl();
    }
}
