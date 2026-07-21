<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Controller\Search;

use Klizer\VisualSearch\Model\Api\Client;
use Klizer\VisualSearch\Model\Config;
use Klizer\VisualSearch\Model\ItemFormatter;
use Klizer\VisualSearch\Model\ProductResolver;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * AJAX proxy: browser uploads image → Magento → AI service → Magento products JSON.
 * Keeps API key on the server.
 */
class Upload implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly Client $client,
        private readonly ProductResolver $productResolver,
        private readonly ItemFormatter $itemFormatter,
        private readonly Filesystem $filesystem,
        private readonly File $file,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => (string)__('Visual search is disabled.'),
            ]);
        }

        try {
            $files = $this->request->getFiles()->toArray();
            $upload = $files['image'] ?? null;
            if (!$upload || empty($upload['tmp_name']) || !empty($upload['error'])) {
                return $result->setHttpResponseCode(400)->setData([
                    'success' => false,
                    'message' => (string)__('Please upload an image.'),
                ]);
            }

            $matchMode = (string)$this->request->getParam('matchMode', $this->config->getDefaultMatchMode());
            $excludeProductId = (string)$this->request->getParam('excludeProductId', '');

            $uploadTmp = (string)$upload['tmp_name'];
            if (!is_uploaded_file($uploadTmp) || !is_readable($uploadTmp)) {
                return $result->setHttpResponseCode(400)->setData([
                    'success' => false,
                    'message' => (string)__('Please upload an image.'),
                ]);
            }

            $tmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
            $tmpDir->create();
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string)$upload['name'])) ?: 'upload.jpg';
            $targetName = 'vs_' . uniqid('', true) . '_' . $safeName;
            $absolute = $tmpDir->getAbsolutePath($targetName);
            if (!@move_uploaded_file($uploadTmp, $absolute) && !$this->file->cp($uploadTmp, $absolute)) {
                throw new \RuntimeException('Unable to store uploaded image for visual search.');
            }
            @chmod($absolute, 0644);

            try {
                $aiData = $this->client->searchByImage(
                    $absolute,
                    $matchMode,
                    $this->config->getTopK(),
                    $excludeProductId !== '' ? $excludeProductId : null
                );
            } finally {
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }

            $resolved = $this->productResolver->resolve($aiData, $matchMode, true);
            $items = [];
            foreach ($resolved['products'] as $product) {
                $items[] = $this->itemFormatter->format($product, $resolved['scores']);
            }

            return $result->setData([
                'success' => true,
                'items' => $items,
                'meta' => $resolved['meta'],
                'resultsUrl' => $this->urlBuilder->getUrl('visualsearch/search/index'),
                'textSearchUrl' => $this->urlBuilder->getUrl('catalogsearch/result'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('VisualSearch upload failed', ['error' => $e->getMessage()]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string)__('Visual search failed. Please try again.'),
            ]);
        }
    }
}
