<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Block\Result;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Klizer\ImageRecognition\Model\Search\ResultStorage;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductList extends AbstractProduct
{
    public const SORT_MATCH = 'match';
    public const SORT_PRICE_ASC = 'price_asc';
    public const SORT_PRICE_DESC = 'price_desc';
    public const SORT_NAME = 'name';

    public const DEFAULT_PAGE_SIZE = 12;

    /** @var list<int> */
    private const PAGE_SIZE_OPTIONS = [12, 24, 36, 48];

    private ?Collection $collection = null;

    /** @var list<Product>|null */
    private ?array $sortedProducts = null;

    /** @var array<int, float> display product id => best similarity score */
    private array $scoreByDisplayId = [];

    public function __construct(
        Context $context,
        private readonly ResultStorage $resultStorage,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Configurable $configurableType,
        private readonly Visibility $productVisibility,
        private readonly UrlHelper $urlHelper,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly ImageRecognitionHelper $imageRecognitionHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return list<array{productId: string, score: float, imageLabel?: string|null}>
     */
    public function getMatches(): array
    {
        return $this->resultStorage->getMatches();
    }

    public function getProductCollection(): Collection
    {
        if ($this->collection !== null) {
            return $this->collection;
        }

        $displayIds = $this->resolveDisplayProductIds($this->resultStorage->getProductIds());

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect([
            'name',
            'price',
            'small_image',
            'thumbnail',
            'image',
            'status',
            'visibility',
            'short_description',
            'sku',
        ]);
        $collection->addStoreFilter();
        $collection->addUrlRewrite();
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

        if ($displayIds === []) {
            $collection->addFieldToFilter('entity_id', ['eq' => 0]);
        } else {
            $collection->addIdFilter($displayIds);
            $collection->getSelect()->order(
                new Expression('FIELD(e.entity_id, ' . implode(',', array_map('intval', $displayIds)) . ')')
            );
        }

        $this->collection = $collection;
        return $this->collection;
    }

    /**
     * All products ordered by the current sort option.
     *
     * @return list<Product>
     */
    public function getSortedProducts(): array
    {
        if ($this->sortedProducts !== null) {
            return $this->sortedProducts;
        }

        $products = [];
        foreach ($this->getProductCollection() as $product) {
            $products[] = $product;
        }

        $sort = $this->getCurrentSort();

        usort($products, function (Product $a, Product $b) use ($sort): int {
            return match ($sort) {
                self::SORT_PRICE_ASC => $this->comparePrice($a, $b),
                self::SORT_PRICE_DESC => $this->comparePrice($b, $a),
                self::SORT_NAME => strcasecmp((string) $a->getName(), (string) $b->getName()),
                default => $this->compareMatch($a, $b),
            };
        });

        $this->sortedProducts = $products;
        return $this->sortedProducts;
    }

    /**
     * Products for the current page only.
     *
     * @return list<Product>
     */
    public function getPagedProducts(): array
    {
        $all = $this->getSortedProducts();
        $pageSize = $this->getCurrentPageSize();
        $offset = ($this->getCurrentPage() - 1) * $pageSize;

        return array_values(array_slice($all, $offset, $pageSize));
    }

    public function getTotalProductCount(): int
    {
        return count($this->getSortedProducts());
    }

    public function getCurrentPageSize(): int
    {
        $size = (int) $this->request->getParam('irs_limit', self::DEFAULT_PAGE_SIZE);
        if (!in_array($size, self::PAGE_SIZE_OPTIONS, true)) {
            return self::DEFAULT_PAGE_SIZE;
        }

        return $size;
    }

    /**
     * @return list<int>
     */
    public function getPageSizeOptions(): array
    {
        return self::PAGE_SIZE_OPTIONS;
    }

    public function getCurrentPage(): int
    {
        $page = max(1, (int) $this->request->getParam('p', 1));
        $last = $this->getLastPageNumber();

        return min($page, max(1, $last));
    }

    public function getLastPageNumber(): int
    {
        $total = $this->getTotalProductCount();
        $pageSize = $this->getCurrentPageSize();
        if ($total <= 0 || $pageSize <= 0) {
            return 1;
        }

        return (int) max(1, (int) ceil($total / $pageSize));
    }

    public function getFirstItemNumber(): int
    {
        if ($this->getTotalProductCount() === 0) {
            return 0;
        }

        return (($this->getCurrentPage() - 1) * $this->getCurrentPageSize()) + 1;
    }

    public function getLastItemNumber(): int
    {
        return min(
            $this->getCurrentPage() * $this->getCurrentPageSize(),
            $this->getTotalProductCount()
        );
    }

    public function shouldShowPager(): bool
    {
        return $this->getTotalProductCount() > $this->getCurrentPageSize();
    }

    /**
     * @return list<int>
     */
    public function getPageNumbers(): array
    {
        $last = $this->getLastPageNumber();
        $current = $this->getCurrentPage();
        $window = 2;
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);

        $pages = [];
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        return $pages;
    }

    public function getScoreForProduct(int $productId): ?float
    {
        // Ensure scores are resolved even if collection was not loaded yet.
        if ($this->scoreByDisplayId === [] && $this->resultStorage->getProductIds() !== []) {
            $this->resolveDisplayProductIds($this->resultStorage->getProductIds());
        }

        return $this->scoreByDisplayId[$productId] ?? null;
    }

    public function getSimilarityPercent(int $productId): ?int
    {
        $score = $this->getScoreForProduct($productId);
        return $score !== null ? (int) round($score * 100) : null;
    }

    public function getQueryImageUrl(): ?string
    {
        $relative = $this->resultStorage->getQueryImage();
        if ($relative === null) {
            return null;
        }

        $base = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        );

        return $base . '/' . ltrim($relative, '/');
    }

    public function getCurrentSort(): string
    {
        $sort = (string) $this->request->getParam('irs_sort', self::SORT_MATCH);
        $allowed = [
            self::SORT_MATCH,
            self::SORT_PRICE_ASC,
            self::SORT_PRICE_DESC,
            self::SORT_NAME,
        ];

        return in_array($sort, $allowed, true) ? $sort : self::SORT_MATCH;
    }

    /**
     * @return array<string, string>
     */
    public function getSortOptions(): array
    {
        return [
            self::SORT_MATCH => (string) __('Best match'),
            self::SORT_PRICE_ASC => (string) __('Price: Low to High'),
            self::SORT_PRICE_DESC => (string) __('Price: High to Low'),
            self::SORT_NAME => (string) __('Name'),
        ];
    }

    /**
     * Build result-page URL while preserving sort / limit / page.
     *
     * @param array<string, scalar|null> $overrides
     */
    public function getResultUrl(array $overrides = []): string
    {
        $params = [
            'irs_sort' => $this->getCurrentSort(),
            'irs_limit' => $this->getCurrentPageSize(),
            'p' => $this->getCurrentPage(),
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
                continue;
            }
            $params[$key] = $value;
        }

        // Drop defaults from the URL for cleaner links.
        if (($params['irs_sort'] ?? null) === self::SORT_MATCH) {
            unset($params['irs_sort']);
        }
        if ((int) ($params['irs_limit'] ?? 0) === self::DEFAULT_PAGE_SIZE) {
            unset($params['irs_limit']);
        }
        if ((int) ($params['p'] ?? 0) <= 1) {
            unset($params['p']);
        }

        return $this->getUrl('imagerecognition/result/index', $params);
    }

    public function getSortUrl(string $sort): string
    {
        return $this->getResultUrl([
            'irs_sort' => $sort,
            'p' => 1,
        ]);
    }

    public function getLimitUrl(int $limit): string
    {
        return $this->getResultUrl([
            'irs_limit' => $limit,
            'p' => 1,
        ]);
    }

    public function getPageUrl(int $page): string
    {
        return $this->getResultUrl(['p' => $page]);
    }

    public function getClearUrl(): string
    {
        return $this->getUrl('imagerecognition/result/clear');
    }

    public function getHomeUrl(): string
    {
        return $this->getUrl('');
    }

    public function getUploadUrl(): string
    {
        return $this->getUrl('imagerecognition/ajax/upload');
    }

    public function getMaxFileSizeBytes(): int
    {
        return $this->imageRecognitionHelper->getMaxFileSizeBytes();
    }

    /**
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        return $this->imageRecognitionHelper->getAllowedExtensions();
    }

    public function getAcceptAttribute(): string
    {
        $extensions = $this->getAllowedExtensions();
        if ($extensions === []) {
            return 'image/*';
        }

        return implode(',', array_map(
            static fn (string $ext): string => '.' . ltrim($ext, '.'),
            $extensions
        ));
    }

    /**
     * @return array{action: string, data: array{product: int, uenc: string}}
     */
    public function getAddToCartPostParams(Product $product): array
    {
        $url = $this->getAddToCartUrl($product, ['_escape' => false]);

        return [
            'action' => $url,
            'data' => [
                'product' => (int) $product->getEntityId(),
                ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
            ],
        ];
    }

    /**
     * Map configurable child variants (XS/L/XL, etc.) to the parent product and dedupe.
     *
     * @param list<int> $productIds
     * @return list<int>
     */
    private function resolveDisplayProductIds(array $productIds): array
    {
        $scoreByMatchedId = [];
        foreach ($this->getMatches() as $match) {
            if (!is_array($match)) {
                continue;
            }
            $id = (int) ($match['productId'] ?? 0);
            if ($id > 0) {
                $scoreByMatchedId[$id] = (float) ($match['score'] ?? 0);
            }
        }

        $displayIds = [];
        $this->scoreByDisplayId = [];

        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            $parentIds = $this->configurableType->getParentIdsByChild($productId);
            $displayId = $parentIds !== [] ? (int) $parentIds[0] : $productId;
            $score = $scoreByMatchedId[$productId] ?? 0.0;

            if (!isset($this->scoreByDisplayId[$displayId])) {
                $displayIds[] = $displayId;
                $this->scoreByDisplayId[$displayId] = $score;
            } else {
                $this->scoreByDisplayId[$displayId] = max($this->scoreByDisplayId[$displayId], $score);
            }
        }

        return $displayIds;
    }

    private function compareMatch(Product $a, Product $b): int
    {
        $scoreA = $this->getScoreForProduct((int) $a->getId()) ?? 0.0;
        $scoreB = $this->getScoreForProduct((int) $b->getId()) ?? 0.0;

        return $scoreB <=> $scoreA;
    }

    private function comparePrice(Product $a, Product $b): int
    {
        $priceA = (float) ($a->getFinalPrice() ?: $a->getPrice());
        $priceB = (float) ($b->getFinalPrice() ?: $b->getPrice());

        return $priceA <=> $priceB;
    }
}
