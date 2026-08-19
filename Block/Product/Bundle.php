<?php
/**
 * Handles Product View Block
 *
 * @copyright Bread   2016
 * @author    Joel    @Mediotype
 * @author    Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Block\Product;

class Bundle extends \Magento\Bundle\Block\Catalog\Product\View\Type\Bundle
{
    public $_product;

    /**
     * @var \Magento\Framework\Registry
     */
    public $registry;

    /**
     * @var Magento\Framework\Json\Helper\Data
     */
    public $jsonHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Catalog
     */
    public $catalogHelper;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    public $catalogProduct;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $dataHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Customer
     */
    public $customerHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Quote
     */
    public $quoteHelper;

    /**
     * Bundle constructor.
     *
     * @param \Magento\Catalog\Block\Product\Context     $context
     * @param \Magento\Framework\Json\Helper\Data        $jsonHelper
     * @param \Bread\BreadCheckout\Helper\Catalog        $catalogHelper
     * @param \Magento\Catalog\Helper\Product            $catalogProduct
     * @param \Bread\BreadCheckout\Helper\Customer       $customerHelper
     * @param \Bread\BreadCheckout\Helper\Data           $dataHelper
     * @param \Magento\Framework\Stdlib\ArrayUtils       $arrayUtils
     * @param \Magento\Framework\Json\EncoderInterface   $jsonEncoder
     * @param \Magento\Bundle\Model\Product\PriceFactory $productPrice
     * @param \Magento\Framework\Locale\FormatInterface  $localeFormat
     * @param \Bread\BreadCheckout\Helper\Quote          $quoteHelper
     * @param array                                      $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Bread\BreadCheckout\Helper\Catalog $catalogHelper,
        \Magento\Catalog\Helper\Product $catalogProduct,
        \Bread\BreadCheckout\Helper\Customer $customerHelper,
        \Bread\BreadCheckout\Helper\Data $dataHelper,
        \Magento\Framework\Stdlib\ArrayUtils $arrayUtils,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Bundle\Model\Product\PriceFactory $productPrice,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Bread\BreadCheckout\Helper\Quote $quoteHelper,
        array $data = []
    ) {
        $this->registry = $context->getRegistry();
        $this->jsonHelper = $jsonHelper;
        $this->catalogHelper = $catalogHelper;
        $this->customerHelper = $customerHelper;
        $this->dataHelper = $dataHelper;
        $this->catalogProduct = $catalogProduct;
        $this->quoteHelper = $quoteHelper;

        parent::__construct(
            $context,
            $arrayUtils,
            $catalogProduct,
            $productPrice,
            $jsonEncoder,
            $localeFormat,
            $data
        );
    }

    public function toHtml()
    {
        $aboveThreshold = $this->dataHelper->aboveThreshold(
            $this->getProduct()->getPriceInfo()->getPrice('final_price')->getValue()
        );
        $disabledSku = !$this->quoteHelper->checkDisabledForSku($this->getProduct()->getSku());

        $output = '';
        if ($aboveThreshold && $disabledSku) {
            $output = parent::toHtml();
        }

        return $output;
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
            $this->_product = $this->registry->registry('product');
        }

        return $this->_product;
    }

    /**
     * Get Minimal Product price
     *
     * @return int
     */
    public function getMinPrice()
    {
        return $this->getProduct()
            ->getPriceInfo()
            ->getPrice(\Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE)
            ->getMinimalPrice()
            ->getValue();
    }

    /**
     * Get Selected Product Price
     *
     * @return int
     */
    public function getSelectedProductPrice()
    {
        $product = $this->getProduct();

        $selectionCollection = $product->getTypeInstance(true)
            ->getSelectionsCollection(
                $product->getTypeInstance(true)->getOptionsIds($product),
                $product
            );

        $selectedPrice = 0;

        foreach ($selectionCollection as $selection) {
            $qty = ($selection->getSelectionQty() * 1) ?: 1;

            $optionPriceAmount = $product->getPriceInfo()
                ->getPrice('bundle_option')
                ->getOptionSelectionAmount($selection);
            $priceFinal = $optionPriceAmount->getValue();

            if ($selection->getIsDefault()) {
                $selectedPrice += $priceFinal * $qty;
            }
        }

        return $selectedPrice;
    }

    /**
     * Get Product Data as JSON
     *
     * @return string
     */
    public function getProductDataJson()
    {
        $product = $this->getProduct();
        $basePrice = $product->getPrice();

        if ((int)$product->getPriceType() === \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED) {
            $selectedPrice = $basePrice + $this->getSelectedProductPrice();
        } else {
            $selectedPrice = $this->getSelectedProductPrice();
        }

        $bundlePrice = ($selectedPrice > 0) ? $selectedPrice : $this->getMinPrice();

        $data = [$this->catalogHelper->getProductDataArray($product, null, 1, $bundlePrice)];

        return $this->jsonEncode($data);
    }

    /**
     * Get Bundle Product Data as JSON
     * Returns JSON encoded config to be used in JS scripts
     *
     * @return string
     */
    public function getBundleProductDataJson()
    {
        $product = $this->getProduct();
        $customOptions = $this->getCustomOptionsData($product->getOptions());

        $data = [
            'bundleId'      => $product->getId(),
            'sku'           => $product->getSku(),
            'name'          => $product->getName(),
            'basePrice'     => floatval($product->getPrice()),
            'selectedPrice' => 0,
            'options'       => []
        ];

        $selectionCollection = $product->getTypeInstance(true)
            ->getSelectionsCollection(
                $product->getTypeInstance(true)->getOptionsIds($product),
                $product
            );

        $optionsCollection = $product->getTypeInstance(true)
            ->getOptionsCollection($product);

        foreach ($optionsCollection as $options) {
            $data['options'][$options->getOptionId()]['title'] = $options->getDefaultTitle();
            $data['options'][$options->getOptionId()]['type'] = $options->getType();
            $data['options'][$options->getOptionId()]['required'] = $options->getRequired();
        }

        $selectedPrice = 0;

        foreach ($selectionCollection as $key => $selection) {
            $qty = ($selection->getSelectionQty() * 1) ?: 1;

            $optionPriceAmount = $product->getPriceInfo()
                ->getPrice('bundle_option')
                ->getOptionSelectionAmount($selection);
            $priceFinal = $optionPriceAmount->getValue();

            $data['options'][$selection->getOptionId()]['selections'][$key]['optionId'] = $selection->getProductId();
            $data['options'][$selection->getOptionId()]['selections'][$key]['name'] = $selection->getName();
            $data['options'][$selection->getOptionId()]['selections'][$key]['sku'] = $selection->getSku();
            $data['options'][$selection->getOptionId()]['selections'][$key]['qty'] = $qty;
            $data['options'][$selection->getOptionId()]['selections'][$key]['price'] = $priceFinal;
            $data['options'][$selection->getOptionId()]['selections'][$key]['default'] = $selection->getIsDefault();

            if ($selection->getIsDefault()) {
                $selectedPrice += $priceFinal * $qty;
            }
        }

        if ((int)$product->getPriceType() === \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED) {
            $selectedPrice += $data['basePrice'];
        }

        $bundlePrice = ($selectedPrice > 0) ? $selectedPrice : $this->getMinPrice();

        $data['selectedPrice'] = round($bundlePrice * 100);

        return $this->jsonEncode($data);
    }

    /**
     * Returns is Dynamic Price
     *
     * @return string
     */
    public function isDynamicPrice()
    {
        $product = $this->getProduct();

        if ((int)$product->getPriceType() !== \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC) {
            return false;
        }

        return true;
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

        return $optionsData;
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
     * Get cart size financing configuration
     *
     * @return string
     */
    public function getFinancingJson()
    {
        $data     = $this->catalogHelper->getFinancingData();
        return $this->jsonEncode($data);
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
        if ($this->catalogHelper->isEnabledOnPDP()) {
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
        return $this->dataHelper->escapeCustomCSS($this->catalogHelper->getPDPButtonDesign());
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