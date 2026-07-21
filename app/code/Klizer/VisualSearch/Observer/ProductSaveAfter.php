<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Observer;

use Klizer\VisualSearch\Model\Indexer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveAfter implements ObserverInterface
{
    public function __construct(private readonly Indexer $indexer)
    {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if ($product) {
            $this->indexer->indexProduct($product);
        }
    }
}
