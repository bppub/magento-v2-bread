<?php
/**
 * Bread BreadCheckout - Hyva JS Block
 *
 * Template-based JS block for Hyva theme compatibility
 */
namespace Bread\BreadCheckout\Block\Hyva;

use Magento\Framework\View\Element\Template;

class Js extends Template
{
    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $helper;

    /**
     * @var \Bread\BreadCheckout\Helper\Category
     */
    protected $categoryHelper;

    /**
     * @param Template\Context $context
     * @param \Bread\BreadCheckout\Helper\Data $helper
     * @param \Bread\BreadCheckout\Helper\Category $categoryHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Bread\BreadCheckout\Helper\Data $helper,
        \Bread\BreadCheckout\Helper\Category $categoryHelper,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->categoryHelper = $categoryHelper;
        parent::__construct($context, $data);
    }

    /**
     * Check if extension is active
     *
     * @return bool
     */
    public function isActive()
    {
        if (!$this->helper->isActive()) {
            return false;
        }

        // Check if any feature that needs SDK is enabled
        return $this->helper->isEnabledOnPDP()
            || $this->helper->isEnabledOnCOP()
            || $this->helper->isPaymentMethodAtCheckout()
            || $this->helper->showMinicartLink()
            || $this->categoryHelper->isEnabledOnCAT();
    }

    /**
     * Get API Key
     *
     * @return string
     */
    public function getPublicApiKey()
    {
        return $this->helper->getApiPublicKey();
    }

    /**
     * Get JS URI
     *
     * @return string
     */
    public function getJsLibLocation()
    {
        return $this->helper->getJsLibLocation();
    }
}
