<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'klizer_visualsearch/general/enabled';
    private const XML_PATH_BASE_URL = 'klizer_visualsearch/general/base_url';
    private const XML_PATH_API_KEY = 'klizer_visualsearch/general/api_key';
    private const XML_PATH_TIMEOUT = 'klizer_visualsearch/general/timeout';
    private const XML_PATH_TOP_K = 'klizer_visualsearch/general/top_k';
    private const XML_PATH_MATCH_MODE = 'klizer_visualsearch/general/default_match_mode';
    private const XML_PATH_FIND_SIMILAR = 'klizer_visualsearch/general/enable_find_similar';
    private const XML_PATH_HEADER_CAMERA = 'klizer_visualsearch/general/enable_header_camera';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        return rtrim((string)$this->scopeConfig->getValue(self::XML_PATH_BASE_URL, ScopeInterface::SCOPE_STORE, $storeId), '/');
    }

    public function getApiKey(?int $storeId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
        return $value !== '' ? $this->encryptor->decrypt($value) : '';
    }

    public function getTimeout(?int $storeId = null): int
    {
        $timeout = (int)$this->scopeConfig->getValue(self::XML_PATH_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId);
        return $timeout > 0 ? $timeout : 60;
    }

    public function getTopK(?int $storeId = null): int
    {
        $topK = (int)$this->scopeConfig->getValue(self::XML_PATH_TOP_K, ScopeInterface::SCOPE_STORE, $storeId);
        return $topK > 0 ? $topK : 12;
    }

    public function getDefaultMatchMode(?int $storeId = null): string
    {
        $mode = (string)$this->scopeConfig->getValue(self::XML_PATH_MATCH_MODE, ScopeInterface::SCOPE_STORE, $storeId);
        return $mode !== '' ? $mode : 'style';
    }

    public function isFindSimilarEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_FIND_SIMILAR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isHeaderCameraEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_HEADER_CAMERA, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
