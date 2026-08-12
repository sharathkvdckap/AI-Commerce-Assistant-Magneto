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
    private const XML_PATH_API_BASE_URL = 'aicommerceassistant/general/api_base_url';
    private const XML_PATH_TIMEOUT = 'aicommerceassistant/general/timeout';
    private const XML_PATH_ASSISTANT_URL = 'aicommerceassistant/general/assistant_url';
    private const XML_PATH_REDIRECT_CATALOG_SEARCH = 'aicommerceassistant/general/redirect_catalog_search';
    private const XML_PATH_CUSTOMER_ID_HMAC_SECRET = 'aicommerceassistant/general/customer_id_hmac_secret';
    private const XML_PATH_CUSTOMER_ID_TOKEN_TTL = 'aicommerceassistant/general/customer_id_token_ttl';
    private const XML_PATH_ANALYTICS_ENABLED = 'aicommerceassistant/analytics/enabled';
    private const XML_PATH_ANALYTICS_DASHBOARD_URL = 'aicommerceassistant/analytics/dashboard_url';
    private const XML_PATH_ANALYTICS_WINDOW_DAYS = 'aicommerceassistant/analytics/window_days';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * AI Commerce Assistant Node API (e.g. http://127.0.0.1:3001).
     */
    public function getApiBaseUrl(?int $storeId = null): string
    {
        $url = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_API_BASE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $url !== '' ? rtrim($url, '/') : '';
    }

    public function getTimeout(?int $storeId = null): int
    {
        $timeout = (int) $this->scopeConfig->getValue(
            self::XML_PATH_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $timeout > 0 ? $timeout : 90;
    }

    /**
     * Legacy external React UI URL. Only used when redirect mode is enabled.
     */
    public function getAssistantUrl(?int $storeId = null): string
    {
        $url = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ASSISTANT_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $url !== '' ? rtrim($url, '/') : '';
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
     * Shared with Node CUSTOMER_ID_HMAC_SECRET. Empty = unsigned customer ids (dev only).
     */
    public function getCustomerIdHmacSecret(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOMER_ID_HMAC_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getCustomerIdTokenTtlSec(?int $storeId = null): int
    {
        $ttl = (int) $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOMER_ID_TOKEN_TTL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $ttl > 0 ? $ttl : 3600;
    }

    /**
     * @param array<string, scalar> $extraQuery Extra query params (customer_id, cid_exp, cid_sig, …)
     */
    public function buildAssistantUrl(
        ?string $query = null,
        ?int $storeId = null,
        array $extraQuery = []
    ): string {
        $base = $this->getAssistantUrl($storeId);
        if ($base === '') {
            return '';
        }

        $params = $extraQuery;
        $query = $query !== null ? trim($query) : '';
        if ($query !== '') {
            $params = ['q' => $query] + $params;
        }

        if ($params === []) {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($params);
    }

    /**
     * External :5173 redirect is opt-in only. Default is Magento PLP embed.
     */
    public function shouldRedirectSearch(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->isCatalogSearchRedirectEnabled($storeId)
            && $this->getAssistantUrl($storeId) !== '';
    }

    /**
     * Embed AI search experience and hide Magento's default catalog search PLP.
     */
    public function shouldEmbedOnSearchResults(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && !$this->shouldRedirectSearch($storeId)
            && $this->getApiBaseUrl($storeId) !== '';
    }

    public function isAnalyticsEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(
                self::XML_PATH_ANALYTICS_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    public function getAnalyticsDashboardUrl(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ANALYTICS_DASHBOARD_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getAnalyticsWindowDays(?int $storeId = null): int
    {
        $days = (int) $this->scopeConfig->getValue(
            self::XML_PATH_ANALYTICS_WINDOW_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $days > 0 ? $days : 30;
    }
}
