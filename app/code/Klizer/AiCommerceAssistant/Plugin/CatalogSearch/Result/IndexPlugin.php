<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Plugin\CatalogSearch\Result;

use Klizer\AiCommerceAssistant\Helper\Data as AssistantHelper;
use Magento\CatalogSearch\Controller\Result\Index;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Safety net: redirect /catalogsearch/result/?q=... to the AI assistant.
 */
class IndexPlugin
{
    public function __construct(
        private readonly AssistantHelper $assistantHelper,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function aroundExecute(Index $subject, callable $proceed)
    {
        if (
            !$this->assistantHelper->shouldRedirectSearch()
            || !$this->assistantHelper->isCatalogSearchRedirectEnabled()
        ) {
            return $proceed();
        }

        $query = trim((string) $subject->getRequest()->getParam('q', ''));
        $target = $this->assistantHelper->buildAssistantUrl($query !== '' ? $query : null);

        if ($target === '') {
            return $proceed();
        }

        return $this->redirectFactory->create()->setUrl($target);
    }
}
