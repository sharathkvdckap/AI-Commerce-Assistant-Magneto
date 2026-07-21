<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'imagerecognition/general/enabled';
    private const XML_PATH_MAX_FILE_SIZE = 'imagerecognition/general/max_file_size';
    private const XML_PATH_ALLOWED_EXTENSIONS = 'imagerecognition/general/allowed_extensions';
    private const XML_PATH_SERVICE_BASE_URL = 'imagerecognition/general/service_base_url';
    private const XML_PATH_API_KEY = 'imagerecognition/general/api_key';
    private const XML_PATH_REQUEST_TIMEOUT = 'imagerecognition/general/request_timeout_seconds';
    private const XML_PATH_SEARCH_TOP_K = 'imagerecognition/general/search_top_k';
    private const XML_PATH_MIN_SIMILARITY = 'imagerecognition/general/min_similarity';

    public function __construct(
        Context $context,
        private readonly EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMaxFileSizeMb(?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_FILE_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMaxFileSizeBytes(?int $storeId = null): int
    {
        return (int) round($this->getMaxFileSizeMb($storeId) * 1024 * 1024);
    }

    /**
     * @return string[]
     */
    public function getAllowedExtensions(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_ALLOWED_EXTENSIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $extensions = array_filter(array_map(
            static fn (string $ext): string => strtolower(trim($ext)),
            explode(',', $value)
        ));

        return array_values($extensions);
    }

    public function getServiceBaseUrl(?int $storeId = null): string
    {
        return rtrim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SERVICE_BASE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ), '/');
    }

    public function getApiKey(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value === '') {
            return '';
        }

        try {
            return $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function getRequestTimeoutSeconds(?int $storeId = null): int
    {
        $timeout = (int) $this->scopeConfig->getValue(
            self::XML_PATH_REQUEST_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $timeout > 0 ? $timeout : 60;
    }

    public function getSearchTopK(?int $storeId = null): int
    {
        $topK = (int) $this->scopeConfig->getValue(
            self::XML_PATH_SEARCH_TOP_K,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $topK > 0 ? $topK : 120;
    }

    public function getMinSimilarity(?int $storeId = null): float
    {
        $value = (float) $this->scopeConfig->getValue(
            self::XML_PATH_MIN_SIMILARITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value < 0) {
            return 0.0;
        }
        if ($value > 1) {
            return 1.0;
        }

        return $value > 0 ? $value : 0.5;
    }
}
