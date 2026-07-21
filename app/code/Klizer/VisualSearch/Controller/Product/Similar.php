<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Controller\Product;

use Klizer\VisualSearch\Model\Api\Client;
use Klizer\VisualSearch\Model\Config;
use Klizer\VisualSearch\Model\ItemFormatter;
use Klizer\VisualSearch\Model\ProductResolver;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * PDP Find Similar — GET /visualsearch/product/similar?product_id=42
 */
class Similar implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly Client $client,
        private readonly ProductResolver $productResolver,
        private readonly ItemFormatter $itemFormatter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isFindSimilarEnabled()) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => (string)__('Find similar is disabled.'),
            ]);
        }

        $productId = (string)$this->request->getParam('product_id', '');
        if ($productId === '' || !ctype_digit($productId)) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string)__('product_id is required.'),
            ]);
        }

        try {
            $matchMode = (string)$this->request->getParam('matchMode', $this->config->getDefaultMatchMode());
            $aiData = $this->client->searchSimilar($productId, $matchMode, $this->config->getTopK());
            $resolved = $this->productResolver->resolve($aiData, $matchMode, true);

            $items = [];
            foreach ($resolved['products'] as $product) {
                if ((string)$product->getId() === $productId) {
                    continue;
                }
                $items[] = $this->itemFormatter->format($product, $resolved['scores']);
            }

            return $result->setData([
                'success' => true,
                'items' => $items,
                'meta' => $resolved['meta'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('VisualSearch similar failed', [
                'productId' => $productId,
                'error' => $e->getMessage(),
            ]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string)__('Unable to load similar products.'),
            ]);
        }
    }
}
