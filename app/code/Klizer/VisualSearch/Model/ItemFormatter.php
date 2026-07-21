<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Model;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Formats Magento products for visual-search JSON (hybrid UI).
 */
class ItemFormatter
{
    public function __construct(
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @param array<string, float> $scores
     * @return array<string, mixed>
     */
    public function format(Product $product, array $scores = []): array
    {
        $id = (string)$product->getId();
        $price = (float)$product->getFinalPrice();

        return [
            'id' => (int)$product->getId(),
            'name' => (string)$product->getName(),
            'url' => $product->getProductUrl(),
            'price' => $price,
            'price_formatted' => $this->priceCurrency->format($price, false),
            'image' => $this->imageHelper->init($product, 'product_base_image')->getUrl(),
            'score' => $scores[$id] ?? null,
            'brand' => $this->attributeLabel($product, 'manufacturer'),
            'size' => $this->attributeLabel($product, 'size'),
            'categories' => $this->categoryNames($product),
            'in_stock' => true,
        ];
    }

    private function attributeLabel(Product $product, string $code): string
    {
        try {
            $text = $product->getAttributeText($code);
            if (is_array($text)) {
                return implode(', ', array_map('strval', $text));
            }
            if ($text !== false && $text !== null && $text !== '') {
                return (string)$text;
            }
            $raw = $product->getData($code);
            return $raw !== null && $raw !== '' ? (string)$raw : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @return list<string>
     */
    private function categoryNames(Product $product): array
    {
        $names = [];
        try {
            $collection = $product->getCategoryCollection()
                ->addAttributeToSelect('name')
                ->addIsActiveFilter();
            foreach ($collection as $category) {
                if ((int)$category->getLevel() <= 1) {
                    continue;
                }
                $name = trim((string)$category->getName());
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_unique($names));
    }
}
