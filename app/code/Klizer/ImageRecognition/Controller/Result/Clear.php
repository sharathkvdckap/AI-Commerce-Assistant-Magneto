<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Controller\Result;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Klizer\ImageRecognition\Model\Search\ResultStorage;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;

class Clear implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultStorage $resultStorage,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder,
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

        $this->resultStorage->clear();

        $redirect = $this->redirectFactory->create();
        $redirect->setUrl($this->urlBuilder->getUrl(''));

        return $redirect;
    }
}
