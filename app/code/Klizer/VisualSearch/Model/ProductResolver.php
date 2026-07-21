<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Model;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;

/**
 * Turns AI product IDs into Magento products (hybrid filter layer).
 * Magento is source of truth for catalog, stock, and price.
 */
class ProductResolver
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StockHelper $stockHelper,
        private readonly Config $config
    ) {
    }

    /**
     * @param array{matches?: array<int, array{productId?: string, score?: float, confidence?: string}>, overallConfidence?: string, suggestedAction?: string, hybridHint?: string} $aiData
     * @return array{products: list<\Magento\Catalog\Model\Product>, scores: array<string, float>, meta: array}
     */
    public function resolve(array $aiData, string $matchMode = 'style', bool $inStockOnly = true): array
    {
        $matches = $aiData['matches'] ?? [];
        $ids = [];
        $scores = [];

        foreach ($matches as $match) {
            $id = (string)($match['productId'] ?? '');
            if ($id === '' || !ctype_digit($id)) {
                continue;
            }
            $ids[] = (int)$id;
            $scores[$id] = (float)($match['score'] ?? 0);
        }

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect([
            'name',
            'price',
            'small_image',
            'image',
            'thumbnail',
            'status',
            'url_key',
            'manufacturer',
            'size',
        ]);
        $collection->addAttributeToFilter('entity_id', ['in' => $ids ?: [0]]);
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->addCategoryIds();
        $collection->setVisibility([
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]);

        if ($inStockOnly) {
            $this->stockHelper->addInStockFilterToCollection($collection);
        }

        $itemsById = [];
        foreach ($collection as $product) {
            $itemsById[(string)$product->getId()] = $product;
        }

        $ordered = [];
        foreach ($ids as $id) {
            $key = (string)$id;
            if (isset($itemsById[$key])) {
                $ordered[] = $itemsById[$key];
            }
        }

        if ($matchMode === 'cheaper') {
            usort($ordered, static function ($a, $b) {
                return $a->getFinalPrice() <=> $b->getFinalPrice();
            });
        }

        return [
            'products' => $ordered,
            'scores' => $scores,
            'meta' => [
                'overallConfidence' => $aiData['overallConfidence'] ?? null,
                'suggestedAction' => $aiData['suggestedAction'] ?? null,
                'hybridHint' => $aiData['hybridHint'] ?? null,
                'matchMode' => $matchMode ?: $this->config->getDefaultMatchMode(),
            ],
        ];
    }
}
