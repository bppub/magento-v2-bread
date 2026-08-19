<?php
/**
 * Handles Product View Block
 *
 * @copyright Bread   2016
 * @author    Joel    @Mediotype
 * @author    Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Block\Product;

class View extends \Magento\ConfigurableProduct\Block\Product\View\Type\Configurable
{
    public $_product;

    /**
     * @var \Magento\Framework\Registry
     */
    public $registry;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    public $jsonHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Catalog
     */
    public $catalogHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $dataHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Customer
     */
    public $customerHelper;

    /**
     * @var \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory
     */
    public $configurableProductFactory;

    /**
     * @var \Magento\ConfigurableProduct\Block\Product\View\Type\ConfigurableFactory
     */
    public $configurableBlockFactory;

    /**
     * @var \Bread\BreadCheckout\Helper\Quote
     */
    public $quoteHelper;

    /**
     * View constructor.
     *
     * @param \Magento\Catalog\Block\Product\Context                                   $context
     * @param \Magento\Framework\Json\Helper\Data                                      $jsonHelper
     * @param \Bread\BreadCheckout\Helper\Catalog                                      $catalogHelper
     * @param \Bread\BreadCheckout\Helper\Customer                                     $customerHelper
     * @param \Bread\BreadCheckout\Helper\Data                                         $dataHelper
     * @param \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory      $configurableProductFactory
     * @param \Magento\ConfigurableProduct\Block\Product\View\Type\ConfigurableFactory $configurableBlockFactory
     * @param \Magento\Framework\Stdlib\ArrayUtils                                     $arrayUtils
     * @param \Magento\Framework\Json\EncoderInterface                                 $jsonEncoder
     * @param \Magento\ConfigurableProduct\Helper\Data                                 $configurableHelper
     * @param \Magento\Catalog\Helper\Product                                          $catalogProductHelper
     * @param \Magento\Customer\Helper\Session\CurrentCustomer                         $currentCustomer
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface                        $priceCurrency
     * @param \Magento\ConfigurableProduct\Model\ConfigurableAttributeData             $configurableAttributeData
     * @param \Bread\BreadCheckout\Helper\Quote                                        $quoteHelper
     * @param array                                                                    $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Bread\BreadCheckout\Helper\Catalog $catalogHelper,
        \Bread\BreadCheckout\Helper\Customer $customerHelper,
        \Bread\BreadCheckout\Helper\Data $dataHelper,
        \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory $configurableProductFactory,
        \Magento\ConfigurableProduct\Block\Product\View\Type\ConfigurableFactory $configurableBlockFactory,
        \Magento\Framework\Stdlib\ArrayUtils $arrayUtils,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\ConfigurableProduct\Helper\Data $configurableHelper,
        \Magento\Catalog\Helper\Product $catalogProductHelper,
        \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\ConfigurableProduct\Model\ConfigurableAttributeData $configurableAttributeData,
        \Bread\BreadCheckout\Helper\Quote $quoteHelper,
        array $data = []
    ) {
        $this->registry = $context->getRegistry();
        $this->jsonHelper = $jsonHelper;
        $this->catalogHelper = $catalogHelper;
        $this->customerHelper = $customerHelper;
        $this->dataHelper = $dataHelper;
        $this->configurableProductFactory = $configurableProductFactory;
        $this->configurableBlockFactory = $configurableBlockFactory;
        $this->quoteHelper = $quoteHelper;

        parent::__construct(
            $context,
            $arrayUtils,
            $jsonEncoder,
            $configurableHelper,
            $catalogProductHelper,
            $currentCustomer,
            $priceCurrency,
            $configurableAttributeData,
            $data
        );
    }

    protected function _construct($bypass = false)
    {
        if (!$bypass) {
            $this->setBlockCode($this->getBlockCode());
        }
        parent::_construct();
    }

    /**
     * Get Current Product
     *
     * @return \Magento\Catalog\Model\Product
     */
    public function getProduct()
    {
        if (null === $this->_product) {
            $this->_product     = $this->registry->registry('product');
        }

        return $this->_product;
    }

    /**
     * Get Product Data as JSON
     *
     * @return string
     */
    public function getProductDataJson()
    {
        $product    = $this->getProduct();
        $data       = [$this->catalogHelper->getProductDataArray($product, null)];

        return $this->jsonEncode($data);
    }

    /**
     * Get initial grouped item data
     *
     * @return string
     */
    public function getGroupedDataJson()
    {
        $product = $this->getProduct();
        $data    = [$this->catalogHelper->getGroupedProductDataArray($product)];

        return $this->jsonEncode($data);
    }

    /**
     * Get grouped product update url
     *
     * @return string
     */
    public function getGroupedButtonUpdate()
    {
        return $this->dataHelper->getGroupedProductItemsUrl();
    }

    /**
     * Returns empty values so that the page can work the same as the cart page.
     *
     * @return string
     */
    public function getDiscountDataJson()
    {
        $data     = [];
        return $this->jsonEncode($data);
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
     * Get Default Customer Shipping Address If It Exists
     *
     * @return string
     */
    public function getShippingAddressData()
    {
        return $this->customerHelper->getShippingAddressData();
    }

    /**
     * Get Billing Address Default Data
     *
     * @return string
     */
    public function getBillingAddressData()
    {
        return $this->customerHelper->getBillingAddressData();
    }

    /**
     * Get As Low As Option Value
     *
     * @return string
     */
    public function getAsLowAs()
    {
        return ($this->catalogHelper->isAsLowAs()) ? 'true' : 'false';
    }

    /**
     * Checks Settings For Show On Product Detail Page During Output
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($this->getBlockCode() === \Bread\BreadCheckout\Helper\Data::BLOCK_CODE_PRODUCT_VIEW
            && $this->catalogHelper->isEnabledOnPDP()
            && $this->catalogHelper->allowedProductType($this->getProduct()->getTypeId())
            && $this->dataHelper->aboveThreshold(
                $this->getProduct()->getPriceInfo()->getPrice('final_price')->getValue()
            )
            && !$this->quoteHelper->checkDisabledForSku($this->getProduct()->getSku())
        ) {
            return parent::_toHtml();
        } elseif ($this->getBlockCode() === \Bread\BreadCheckout\Helper\Data::BLOCK_CODE_CHECKOUT_OVERVIEW
            && $this->catalogHelper->isEnabledOnCOP()
        ) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Get Shipping Estimate Url
     *
     * @return string
     */
    public function getShippingAddressEstimationUrl()
    {
        return $this->catalogHelper->getShippingEstimateUrl();
    }

    /**
     * Get Tax Estimate URL
     *
     * @return string
     */
    public function getTaxEstimationUrl()
    {
        return $this->catalogHelper->getTaxEstimateUrl();
    }

    /**
     * Get Validate Order URL
     *
     * @return string
     */
    public function getValidateOrderUrl()
    {
        return $this->catalogHelper->getValidateOrderURL();
    }

    /**
     * Get Config Data URL
     *
     * @return string
     */
    public function getConfigDataUrl()
    {
        return $this->catalogHelper->getConfigDataUrl();
    }

    /**
     * Get Discounts Data URL
     *
     * @return string
     */
    public function getDiscountsDataUrl()
    {
        return $this->catalogHelper->getDiscountsDataUrl();
    }

     /**
      * Get Clear Quote Data URL
      *
      * @return string
      */
    public function getClearQuoteUrl()
    {
        return $this->catalogHelper->getClearQuoteUrl();
    }

    /**
     * Get Extra Button Design CSS
     *
     * @return mixed
     */
    public function getButtonDesign()
    {
        if(!is_null($this->catalogHelper->getPDPButtonDesign())) {
            return $this->dataHelper->escapeCustomCSS($this->catalogHelper->getPDPButtonDesign());
        }
        return "";
    }

    /**
     * Get Is Button On Product
     *
     * @return string
     */
    public function getIsButtonOnProduct()
    {
        return ( $this->catalogHelper->isButtonOnProducts() ) ? 'true' : 'false';
    }

    /**
     * Get Default Button Size String For The View
     *
     * @return string
     */
    public function getIsDefaultSize()
    {
        return (string) $this->catalogHelper->getDefaultButtonSizeHtml();
    }

    /**
     * Check if checkout through Bread interaction is allowed
     *
     * @return mixed
     */
    public function getAllowCheckout()
    {
        return ($this->catalogHelper->getAllowCheckoutPDP()) ? 'true' : 'false';
    }

    /**
     * Is Healthcare mode?
     *
     * @return bool
     */
    public function isHealthcare()
    {
        return (bool) ($this->dataHelper->isHealthcare());
    }

    /**
     * Return Block View Product Code
     *
     * @return string
     */
    public function getBlockCode()
    {
        return (string) $this->catalogHelper->getBlockCodeProductView();
    }

    /**
     * Check if Cart Size financing is enabled
     *
     * @return bool
     */
    public function isTargetedFinancing()
    {
        return $this->dataHelper->isTargetedFinancing();
    }

    /**
     * Get cart size over which targeted financing is enabled
     *
     * @return string
     */
    public function getTargetedFinancingThreshold()
    {
        return $this->dataHelper->getTargetedFinancingThreshold();
    }

    /**
     * Get financing ID associated with cart size threshold
     *
     * @return string
     */
    public function getFinancingId()
    {
        return $this->dataHelper->getFinancingId();
    }

    /**
     * Wrapper for get financing mode
     *
     * @param string $mode
     * @return int
     */
    public function checkFinancingMode($mode)
    {
        return $this->dataHelper->checkFinancingMode($mode);
    }

    /**
     * Get product IDs from related products collection
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getChildProductIds(\Magento\Catalog\Model\Product $product)
    {
        $configurableProduct    = $this->configurableProductFactory->create();
        $usedChildrenProduct    = $configurableProduct->getUsedProductCollection($product)
            ->addAttributeToSelect('sku')
            ->addFilterByRequiredOptions();

        $itemIds         = [];
        foreach ($usedChildrenProduct as $simpleProduct) {
            $itemIds[]   = [ $simpleProduct->getId() => $simpleProduct->getSku() ];
        }

        return $itemIds;
    }

    /**
     * Get SKU and price data for custom options on product
     *
     * @param  $options
     * @return string
     */
    public function getCustomOptionsData($options)
    {
        $optionsData = [];

        foreach ($options as $option) {
            if ($option->getValues()) {
                foreach ($option->getValues() as $k => $v) {
                    $optionsData[$option->getId()][$k] = [
                        'sku' => $v->getSku(),
                        'price' => (int)($v->getPrice() * 100)
                    ];
                }
            } else {
                $optionsData[$option->getId()] = [
                    'sku' => $option->getSku(),
                    'price' => (int)($option->getPrice() * 100)
                ];
            }
        }

        return $this->jsonEncode($optionsData);
    }

    /**
     * Get button location string for product page
     *
     * @return string
     */
    public function getProductViewLocation()
    {
        return $this->dataHelper->getProductViewLocation();
    }

    /**
     * Get button location string for cart summary page
     *
     * @return string
     */
    public function getCartSummaryLocation()
    {
        return $this->dataHelper->getCartSummaryLocation();
    }

    /**
     * Get button location string for checkout page
     *
     * @return string
     */
    public function getCheckoutLocation()
    {
        return $this->dataHelper->getCheckoutLocation();
    }

    /**
     * Get button location string for financing page
     *
     * @return string
     */
    public function getFinancingLocation()
    {
        return $this->dataHelper->getFinancingLocation();
    }

    /**
     * Get button location string for marketing page
     *
     * @return string
     */
    public function getMarketingLocation()
    {
        return $this->dataHelper->getMarketingLocation();
    }

    /**
     * Get button location string for category page
     *
     * @return string
     */
    public function getCategoryPageLocation()
    {
        return $this->dataHelper->getCategoryPageLocation();
    }

    /**
     * Get button location string for other purposes
     *
     * @return string
     */
    public function getOtherLocation()
    {
        return $this->dataHelper->getOtherLocation();
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
     * Is downloadable type
     *
     * @return bool
     */
    public function isDownloadable()
    {
        return $this->getProduct()->getTypeId() === \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE;
    }
    
    /**
     * @since 2.1.0
     * @return type
     */
    public function getApiVersion() {
        return (string) $this->dataHelper->getApiVersion();
    }

    /**
     * @since 2.1.0
     * @return string
     */
    public function getIntegrationKey() {
        return $this->dataHelper->getIntegrationKey();
    }

    /**
     * @since 2.1.0
     * @return string
     */
    public function getConfigClient() {
        return $this->dataHelper->getConfigClient();
    }

    /**
     * @since 2.1.0
     * @return string
     */
    public function getCurrentCurrencyCode() {
        return $this->catalogHelper->getCurrentCurrencyCode();
    }
}
