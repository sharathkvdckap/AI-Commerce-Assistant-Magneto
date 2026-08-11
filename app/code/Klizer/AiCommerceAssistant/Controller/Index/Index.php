<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Controller\Index;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * Standalone AI assistant entry (empty query). Used by the header AI icon.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        if (!$this->config->shouldEmbedOnSearchResults()) {
            return $this->redirectFactory->create()->setPath('/');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('AI Commerce Assistant'));

        return $page;
    }
}
