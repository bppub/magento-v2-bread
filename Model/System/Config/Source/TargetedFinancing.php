<?php
declare(strict_types = 1);

namespace Bread\BreadCheckout\Model\System\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TargetedFinancing implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('No')],
            ['value' => 1, 'label' => __('By Cart Size')],
            ['value' => 2, 'label' => __('By SKU List')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            0 => __('No'),
            1 => __('By Cart Size'),
            2 => __('By SKU List')
        ];
    }
}
