<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Console\Command;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Klizer\ImageRecognition\Model\Api\ClipSearchClient;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexEmbeddings extends Command
{
    private const OPTION_STORE = 'store';
    private const OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly State $appState,
        private readonly Emulation $emulation,
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Filesystem $filesystem,
        private readonly ClipSearchClient $clipSearchClient,
        private readonly ImageRecognitionHelper $helper,
        private readonly Visibility $productVisibility,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('klizer:imagerecognition:reindex')
            ->setDescription('Index Magento product images into the local CLIP embedding service')
            ->addOption(
                self::OPTION_STORE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Store ID (default: default store)',
                null
            )
            ->addOption(
                self::OPTION_LIMIT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Limit number of products (for testing)',
                null
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code already set.
        }

        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Image recognition is disabled in admin config.</error>');
            return Command::FAILURE;
        }

        if ($this->helper->getServiceBaseUrl() === '' || $this->helper->getApiKey() === '') {
            $output->writeln('<error>Configure service_base_url and api_key before reindexing.</error>');
            return Command::FAILURE;
        }

        $storeId = $input->getOption(self::OPTION_STORE);
        $storeId = $storeId !== null && $storeId !== ''
            ? (int) $storeId
            : (int) $this->storeManager->getDefaultStoreView()->getId();

        $limit = $input->getOption(self::OPTION_LIMIT);
        $limit = $limit !== null && $limit !== '' ? (int) $limit : 0;

        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'image', 'small_image', 'thumbnail']);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            // Index only catalog-visible products (parent configurables), not size/color child variants.
            $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

            if ($limit > 0) {
                $collection->setPageSize($limit)->setCurPage(1);
            }

            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $indexed = 0;
            $failed = 0;
            $skipped = 0;

            $output->writeln(sprintf('<info>Indexing products for store %d...</info>', $storeId));

            foreach ($collection as $product) {
                $productId = (string) $product->getId();
                $imagePaths = $this->collectProductImagePaths($product, $mediaDir->getAbsolutePath('catalog/product'));

                if ($imagePaths === []) {
                    $skipped++;
                    $output->writeln(sprintf('  [skip] product %s — no readable images', $productId));
                    continue;
                }

                foreach ($imagePaths as $label => $absolutePath) {
                    try {
                        $this->clipSearchClient->indexProductImage(
                            $productId,
                            $absolutePath,
                            $label,
                            basename($absolutePath)
                        );
                        $indexed++;
                        $output->writeln(sprintf(
                            '  [ok] product %s (%s)',
                            $productId,
                            $label
                        ));
                    } catch (\Throwable $e) {
                        $failed++;
                        $output->writeln(sprintf(
                            '  [fail] product %s (%s): %s',
                            $productId,
                            $label,
                            $e->getMessage()
                        ));
                    }
                }
            }

            $output->writeln(sprintf(
                '<info>Done. indexed=%d failed=%d skipped_products=%d</info>',
                $indexed,
                $failed,
                $skipped
            ));
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<string, string> label => absolute path
     */
    private function collectProductImagePaths($product, string $catalogProductRoot): array
    {
        $paths = [];
        $candidates = [
            'image' => (string) $product->getData('image'),
            'small_image' => (string) $product->getData('small_image'),
            'thumbnail' => (string) $product->getData('thumbnail'),
        ];

        foreach ($candidates as $label => $relative) {
            if ($relative === '' || $relative === 'no_selection') {
                continue;
            }
            $absolute = rtrim($catalogProductRoot, '/') . '/' . ltrim($relative, '/');
            if (is_readable($absolute)) {
                $paths[$label] = $absolute;
            }
        }

        // Deduplicate by path while keeping first label.
        $unique = [];
        $seen = [];
        foreach ($paths as $label => $path) {
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $unique[$label] = $path;
        }

        return $unique;
    }
}
