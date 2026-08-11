<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Block\Result;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Klizer\AiCommerceAssistant\Model\UserIdentity;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * AI assistant panel on Magento catalog search results (PLP).
 */
class Assistant extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly UserIdentity $userIdentity,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->shouldEmbedOnSearchResults();
    }

    protected function _toHtml(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }

    public function getQueryText(): string
    {
        return trim((string) $this->getRequest()->getParam('q', ''));
    }

    public function getStartUrl(): string
    {
        return $this->getUrl('aicommerceassistant/ajax/start');
    }

    public function getMessageUrl(): string
    {
        return $this->getUrl('aicommerceassistant/ajax/message');
    }

    public function getContextSearchUrl(): string
    {
        return $this->getUrl('aicommerceassistant/context/search');
    }

    public function getTrackUrl(): string
    {
        return $this->getUrl('aicommerceassistant/ajax/track');
    }

    /**
     * Stable AI memory key for the current shopper.
     * Logged-in: customer_{id}. Guest: empty (JS generates a guest id).
     */
    public function getUserId(): string
    {
        return $this->userIdentity->resolve();
    }
}
