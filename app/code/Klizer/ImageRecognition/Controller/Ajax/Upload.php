<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Controller\Ajax;

use Klizer\ImageRecognition\Model\ImageUploader;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Upload implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly ImageUploader $imageUploader,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            $upload = $this->imageUploader->processUpload('image');
            $redirectUrl = $this->urlBuilder->getUrl('imagerecognition/result/index');

            return $result->setData([
                'success' => true,
                'message' => $upload['matchCount'] > 0
                    ? __('Found similar products for your image...')
                    : __('No similar products found for this image.'),
                'productIds' => $upload['productIds'],
                'matchCount' => $upload['matchCount'],
                'redirectUrl' => $redirectUrl,
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Image recognition upload failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('Something went wrong while processing the image.'),
            ]);
        }
    }
}
