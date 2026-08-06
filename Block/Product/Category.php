<?php

namespace Bread\BreadCheckout\Block\Product;

/**
 * Class Category
 *
 * @package Bread\BreadCheckout\Block\Product
 */
class Category extends \Magento\Framework\View\Element\Template
{

    public $_template = 'Bread_BreadCheckout::breadcheckout/list_product.phtml';
    /**
     * @var \Bread\BreadCheckout\Helper\Category
     */
    private $categoryHelper;
    /**
     * @var \Bread\BreadCheckout\Helper\Customer
     */
    private $customerHelper;
    /**
     * @var \Bread\BreadCheckout\Helper\Catalog
     */
    private $catalogHelper;
    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    private $jsonHelper;
    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    private $moduleList;

    /**
     * @var \Bread\BreadCheckout\Helper\Quote
     */
    private $quoteHelper;
    
    /**
     * 
     * @var \Bread\BreadCheckout\Helper\Data
     */
    private $dataHelper;

    /**
     * Category constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Bread\BreadCheckout\Helper\Category             $categoryHelper
     * @param \Bread\BreadCheckout\Helper\Customer             $customerHelper
     * @param \Bread\BreadCheckout\Helper\Catalog              $catalogHelper
     * @param \Magento\Framework\Module\ModuleListInterface    $moduleList
     * @param \Magento\Framework\Json\Helper\Data              $jsonHelper
     * @param \Bread\BreadCheckout\Helper\Quote                $quoteHelper
     * @param \Bread\BreadCheckout\Helper\Data                 $dataHelper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Bread\BreadCheckout\Helper\Category $categoryHelper,
        \Bread\BreadCheckout\Helper\Customer $customerHelper,
        \Bread\BreadCheckout\Helper\Catalog $catalogHelper,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Bread\BreadCheckout\Helper\Quote $quoteHelper,
        \Bread\BreadCheckout\Helper\Data $dataHelper,    
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->categoryHelper = $categoryHelper;
        $this->customerHelper = $customerHelper;
        $this->catalogHelper  = $catalogHelper;
        $this->jsonHelper     = $jsonHelper;
        $this->moduleList     = $moduleList;
        $this->quoteHelper    = $quoteHelper;
        $this->dataHelper = $dataHelper;
    }

    public function _toHtml()
    {
        $output = '';
        $product = $this->getProduct();

        if ($this->categoryHelper->aboveThreshold($product->getPriceInfo()->getPrice('final_price')->getValue())
            && !$this->quoteHelper->checkDisabledForSku($product->getSku())
        ) {
            $output = parent::_toHtml();
        }

        return $output;
    }

    /**
     * @return mixed
     */
    public function useDefaultButtonSizeCategory()
    {
        if ($this->categoryHelper->useDefaultButtonSizeCategory()) {
            return 'data-bread-default-size="true"';
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    public function getShippingAddressData()
    {
        return $this->customerHelper->getShippingAddressData();
    }

    /**
     * @return string
     */
    public function getBillingAddressData()
    {
        return $this->customerHelper->getBillingAddressData();
    }

    /**
     * @return mixed
     */
    public function isLabelOnlyOnCategories()
    {
        return $this->categoryHelper->isLabelOnlyOnCategories() ? 'true' : 'false';
    }

    /**
     * @return mixed
     */
    public function getCATButtonDesign()
    {
        $design = $this->categoryHelper->getCATButtonDesign();
        if (!$design) {
            $design = $this->catalogHelper->getPDPButtonDesign();
        }
        return $this->categoryHelper->escapeCustomCSS($design);
    }

    /**
     * @return string
     */
    public function isAsLowAsCAT()
    {
        return (string)$this->categoryHelper->isAsLowAsCAT() ? 'true' : 'false';
    }

    /**
     * @return mixed
     */
    public function getShowInWindowCAT()
    {
        return $this->categoryHelper->getShowInWindowCAT() ? 'true' : 'false';
    }

    /**
     * @return bool
     */
    public function isHealthcare()
    {
        return $this->categoryHelper->isHealthcare();
    }

    /**
     * Get button location string for category page
     *
     * @return string
     */
    public function getCategoryPageLocation()
    {
        return $this->categoryHelper->getCategoryPageLocation();
    }

    /**
     * @return string
     */
    public function getValidateOrderURL()
    {
        return $this->categoryHelper->getValidateOrderURL();
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return array
     */
    public function getProductDataArray($product)
    {
        return $this->catalogHelper->getProductDataArray($product, null);
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return bool|string
     */
    public function getProductDataJson($product)
    {
        $product->unsetData("final_price");
        return json_encode($this->getProductDataArray($product), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Get targeted financing configuration
     *
     * @return string
     */
    public function getFinancingJson()
    {
        $data     = $this->catalogHelper->getFinancingData();
        return $this->jsonEncode($data);
    }

    /**
     * @return string
     */
    public function getModuleVersion()
    {
        $module = $this->moduleList->getOne($this->getModuleName());
        return isset($module["setup_version"]) ? $module["setup_version"] : "";
    }

    /**
     * Publicly accessible json encoder
     *
     * @param  $data
     * @return string
     */
    public function jsonEncode($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * @since 2.2.0
     * @return type
     */
    public function getApiVersion() {
        return (string) $this->dataHelper->getApiVersion();
    }
    
    /**
     * @since 2.2.0
     * @return string
     */
    public function getIntegrationKey() {
        return $this->dataHelper->getIntegrationKey();
    }

    /**
     * @since 2.2.0
     * @return string
     */
    public function getConfigClient() {
        return $this->dataHelper->getConfigClient();
    }

    /**
     * @since 2.2.0
     * @return string
     */
    public function getCurrentCurrencyCode() {
        return $this->catalogHelper->getCurrentCurrencyCode();
    }
}
