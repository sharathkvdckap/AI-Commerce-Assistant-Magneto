<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\AiCommerceAssistant\Observer;

use Klizer\AiCommerceAssistant\Helper\Data as Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;

/**
 * When AI Commerce Assistant is enabled (embed mode), replace Magento's
 * default catalog search PLP with the AI search experience.
 */
class ReplaceCatalogSearchLayout implements ObserverInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $fullActionName = (string) $observer->getData('full_action_name');
        if ($fullActionName !== 'catalogsearch_result_index') {
            return;
        }

        if (!$this->config->shouldEmbedOnSearchResults()) {
            return;
        }

        /** @var LayoutInterface $layout */
        $layout = $observer->getData('layout');
        if (!$layout || !method_exists($layout, 'getUpdate')) {
            return;
        }

        $layout->getUpdate()->addHandle('aicommerceassistant_catalogsearch_replace');
    }
}
