<?php
/**
 * API version options
 *
 * @since 2.1.0
 * @copyright (c) Bread
 * @author Kip, Maritim <kip.maritim@breadfinancial.com>
 */
declare(strict_types=1);

namespace Bread\BreadCheckout\Model\System\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Tenant implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'core', 'label' => __('CORE (US)')],
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
            'core' => __('CORE (US)'),
        ];
    }
}
