<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Controller\Result;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $resultForwardFactory,
        private readonly ImageRecognitionHelper $helper
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->helper->isEnabled()) {
            /** @var Forward $resultForward */
            $resultForward = $this->resultForwardFactory->create();
            return $resultForward->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Visual Search Results'));
        return $page;
    }
}
