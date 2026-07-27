<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'aicommerceassistant/general/enabled';
    private const XML_PATH_ASSISTANT_URL = 'aicommerceassistant/general/assistant_url';
    private const XML_PATH_REDIRECT_CATALOG_SEARCH = 'aicommerceassistant/general/redirect_catalog_search';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isCatalogSearchRedirectEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REDIRECT_CATALOG_SEARCH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Base assistant URL without query string or trailing slash.
     */
    public function getAssistantUrl(?int $storeId = null): string
    {
        $url = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ASSISTANT_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if ($url === '') {
            return '';
        }

        return rtrim($url, '/');
    }

    /**
     * Build full assistant URL, optionally with ?q=.
     */
    public function buildAssistantUrl(?string $query = null, ?int $storeId = null): string
    {
        $base = $this->getAssistantUrl($storeId);
        if ($base === '') {
            return '';
        }

        $query = $query !== null ? trim($query) : '';
        if ($query === '') {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . 'q=' . rawurlencode($query);
    }

    public function shouldRedirectSearch(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getAssistantUrl($storeId) !== '';
    }
}
