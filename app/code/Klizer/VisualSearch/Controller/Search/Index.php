<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Controller\Search;

use Klizer\VisualSearch\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * Visual search results landing page (UI shell; results loaded via AJAX upload).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly Config $config,
        private readonly ResultFactory $resultFactory
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('/');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Search by Image'));
        return $page;
    }
}
