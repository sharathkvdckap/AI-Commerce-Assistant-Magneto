<?php
declare(strict_types=1);

namespace Klizer\VisualSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MatchMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'exact', 'label' => __('Exact look')],
            ['value' => 'style', 'label' => __('Same style')],
            ['value' => 'color', 'label' => __('Same color')],
            ['value' => 'cheaper', 'label' => __('Cheaper alternative')],
        ];
    }
}
