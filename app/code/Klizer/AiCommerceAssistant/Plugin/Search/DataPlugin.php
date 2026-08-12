<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Plugin\Search;

use Klizer\AiCommerceAssistant\Helper\Data as AssistantHelper;
use Klizer\AiCommerceAssistant\Model\UserIdentity;
use Magento\Search\Helper\Data as SearchHelper;

/**
 * Point mini-search (and any getResultUrl() consumer) at the AI assistant.
 * Works with Klizer_ImageRecognition form.mini.phtml which already calls getResultUrl().
 */
class DataPlugin
{
    public function __construct(
        private readonly AssistantHelper $assistantHelper,
        private readonly UserIdentity $userIdentity
    ) {
    }

    /**
     * @param string|null $query
     * @return string
     */
    public function aroundGetResultUrl(
        SearchHelper $subject,
        callable $proceed,
        $query = null
    ) {
        if (!$this->assistantHelper->shouldRedirectSearch()) {
            return $proceed($query);
        }

        $url = $this->assistantHelper->buildAssistantUrl(
            $query !== null && $query !== '' ? (string) $query : null,
            null,
            $this->userIdentity->getRedirectQueryParams()
        );

        return $url !== '' ? $url : $proceed($query);
    }
}
