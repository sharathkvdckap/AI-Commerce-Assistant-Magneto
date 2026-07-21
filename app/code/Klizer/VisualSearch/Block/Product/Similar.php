<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Block\Product;

use Klizer\VisualSearch\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Similar extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isFindSimilarEnabled();
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    public function getSimilarUrl(): string
    {
        $product = $this->getProduct();
        if (!$product) {
            return '';
        }
        return $this->getUrl('visualsearch/product/similar', ['product_id' => $product->getId()]);
    }
}
