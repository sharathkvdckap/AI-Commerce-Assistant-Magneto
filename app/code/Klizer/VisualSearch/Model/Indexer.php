<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Model;

use Klizer\VisualSearch\Model\Api\Client;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Indexes Magento product base image into the AI embedding service.
 */
class Indexer
{
    public function __construct(
        private readonly Config $config,
        private readonly Client $client,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    public function indexProduct(ProductInterface $product): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $productId = (string)$product->getId();
        if ($productId === '') {
            return;
        }

        try {
            $imageFile = (string)$product->getImage();
            if ($imageFile === '' || $imageFile === 'no_selection') {
                $this->logger->info('VisualSearch skip index: no image', ['productId' => $productId]);
                return;
            }

            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $relative = 'catalog/product' . $imageFile;
            if (!$media->isExist($relative)) {
                $this->logger->warning('VisualSearch image missing on disk', [
                    'productId' => $productId,
                    'path' => $relative,
                ]);
                return;
            }

            $absolute = $media->getAbsolutePath($relative);
            $this->client->indexProductImage($productId, $absolute, $imageFile);
            $this->logger->info('VisualSearch indexed product', ['productId' => $productId]);
        } catch (\Throwable $e) {
            // Never block product save on AI failures.
            $this->logger->error('VisualSearch index failed', [
                'productId' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
