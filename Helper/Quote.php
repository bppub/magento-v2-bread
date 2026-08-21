<?php
/**
 * Helps Integration With Session Quote
 *
 * @author Bread   copyright   2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Helper;

class Quote extends Data
{
    const BREAD_SESSION_QUOTE_RESULT_KEY  = "bread_quote_result";
    const BREAD_SESSION_QUOTE_UPDATED_KEY = "bread_quote_updated_at";
    const BREAD_SESSION_QUOTE_SHIPPING_METHOD_PICKUP_KEY = "instore_pickup";

    /**
     * @var \Magento\Sales\Model\Quote
     */
    public $quote = null;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    public $checkoutSession;

    /**
     * @var Bread\BreadCheckout\Helper\Catalog
     */
    public $helperCatalog;

    /**
     * @var \Magento\Sales\Model\AdminOrder\Create
     */
    public $orderCreateModel;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    public $priceCurrency;
    /**
     * @var \Bread\BreadCheckout\Model\Payment\Api\Client
     */
    public $paymentApiClient;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    public $productRepository;

    /**
     * Quote constructor.
     *
     * @param \Magento\Framework\App\Helper\Context             $helperContext
     * @param \Magento\Framework\Model\Context                  $context
     * @param \Magento\Framework\App\Request\Http\Proxy         $request
     * @param \Magento\Framework\Encryption\Encryptor           $encryptor
     * @param \Magento\Framework\UrlInterfaceFactory            $urlInterfaceFactory
     * @param \Magento\Checkout\Model\Session\Proxy             $checkoutSession
     * @param Catalog                                           $helperCatalog
     * @param \Magento\Sales\Model\AdminOrder\Create            $orderCreateModel
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Bread\BreadCheckout\Model\Payment\Api\Client     $paymentApiClient
     * @param \Magento\Catalog\Api\ProductRepositoryInterface   $productRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $helperContext,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\UrlInterfaceFactory $urlInterfaceFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Bread\BreadCheckout\Helper\Catalog $helperCatalog,
        \Magento\Sales\Model\AdminOrder\Create $orderCreateModel,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Bread\BreadCheckout\Model\Payment\Api\Client $paymentApiClient,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helperCatalog = $helperCatalog;
        $this->orderCreateModel = $orderCreateModel;
        $this->priceCurrency = $priceCurrency;
        $this->paymentApiClient = $paymentApiClient;
        $this->productRepository = $productRepository;

        parent::__construct(
            $helperContext,
            $context,
            $request,
            $encryptor,
            $urlInterfaceFactory,
            $storeManager,
            $configWriter
        );
    }

    /**
     * Get Grand Total From Quote
     *
     * @return mixed
     */
    public function getGrandTotal()
    {
        if ($this->isInAdmin()) {
            $this->orderCreateModel->collectRates();
            $grandTotal = $this->orderCreateModel->getQuote()->getGrandTotal();
        } else {
            $quote = $this->getSessionQuote();
            $quote->collectTotals();
            $grandTotal = $quote->getGrandTotal();
        }

        return round($grandTotal * 100);
    }

    /**
     * get Tax Value from Quote
     *
     * @param bool $collectRates
     *
     * @return float
     */
    public function getTaxValue($collectRates = true)
    {
        if ($this->isInAdmin()) {
            if ($collectRates) {
                $this->orderCreateModel->collectRates();
            }
            $taxAmount = $this->orderCreateModel->getShippingAddress()->getTaxAmount();
        } else {
            $quote = $this->getSessionQuote();
            if ($collectRates) {
                $quote->collectTotals();
            }
            $taxAmount = $quote->getShippingAddress()->getTaxAmount();
        }

        return round($taxAmount * 100);
    }

    /**
     * get Discount Data From Quote
     *
     * @return array
     */
    public function getDiscountData()
    {
        if ($this->isInAdmin()) {
            $discount = $this->orderCreateModel->getQuote()->getSubtotal() -
                        $this->orderCreateModel->getQuote()->getSubtotalWithDiscount();
            $couponTitle = $this->orderCreateModel->getQuote()->getCouponCode();
        } else {
            $quote = $this->getSessionQuote();
            $discount = $quote->getSubtotal() -
                        $quote->getSubtotalWithDiscount();
            $couponTitle = $quote->getCouponCode();
        }

        $discount = round($discount * 100);

        if ($discount > 0) {
            $discount = [
                'amount'      => $discount,
                'description' => ($couponTitle) ? $couponTitle : __('Discount')
            ];
        } else {
            return [];
        }

        return [$discount];
    }

    /**
     * Get Quote Items Data for checkout form
     *
     * @return array
     */
    public function getQuoteItemsData()
    {

        if ($this->isInAdmin()) {
            $quoteItems = $this->orderCreateModel->getQuote()->getAllVisibleItems();
        } else {
            $quoteItems = $this->getSessionQuote()->getAllVisibleItems();
        }

        if (count($quoteItems) < 1) {
            return [];
        }

        $itemsData     = [];
        foreach ($quoteItems as $item) {
            $price                  = $item->getCalculationPrice();
            $baseProduct            = $item->getProduct();
            $simpleProductItem      = $item->getOptionByCode('simple_product');
            $thisProduct            = null;
            if ($simpleProductItem == null) {
                $thisProduct            = $baseProduct;
                $baseProduct            = null;
            } else {
                $thisProduct            = $item->getOptionByCode('simple_product')->getProduct();
            }

            $itemsData[]       = $this->helperCatalog
                ->getProductDataArray($thisProduct, $baseProduct, (int) $item->getQty(), $price);
        }

        return $itemsData;
    }

    /**
     * Get Bread Formatted Billing Address Data From Address Model
     *
     * @return array
     */
    public function getBillingAddressData()
    {
        if ($this->isInAdmin()) {
            $billingAddress = $this->orderCreateModel->getBillingAddress();
        } else {
            $billingAddress     = $this->getSessionQuote()->getBillingAddress();
        }

        if (!$billingAddress->getStreetLine(1)) {
            return false;
        }

        return [
            'address'       => $billingAddress->getStreetLine(1) .
                ($billingAddress->getStreetLine(2) == '' ? '' : (' ' . $billingAddress->getStreetLine(2))),
            'address2'      => $billingAddress->getStreetLine(3) .
                ($billingAddress->getStreetLine(4) == '' ? '' : (' ' . $billingAddress->getStreetLine(4))),
            'city'          => $billingAddress->getCity(),
            'state'         => $billingAddress->getRegionCode(),
            'zip'           => $billingAddress->getPostcode(),
            'phone'         => is_null($billingAddress->getTelephone()) ? '' : substr(preg_replace('/[^0-9]+/', '', $billingAddress->getTelephone()), -10),
            'email'         => $billingAddress->getEmail(),
            'firstName'     => trim($billingAddress->getFirstname()),
            'lastName'      => trim($billingAddress->getLastname()),
        ];
    }

    /**
     * Get shipping method from session quote
     *
    */
    public function getShippingMethod()
    {
        if ($this->isInAdmin()) {
            $shippingAddress = $this->orderCreateModel->getShippingAddress();
        } else {
            $shippingAddress = $this->getSessionQuote()->getShippingAddress();
        }
        $shippingMethod = $shippingAddress->getShippingMethod();

        return $shippingMethod;
    }

    /**
     * Checks if shipping method is store pickup
     *
     * @return boolean
     */
    public function isStorePickup()
    {
        $shippingMethod = $this->getShippingMethod();
        return $shippingMethod === self::BREAD_SESSION_QUOTE_SHIPPING_METHOD_PICKUP_KEY;
    }

    public function getFulfillmentType()
    {
        $fulfillmentType = 'DELIVERY';
        $shippingMethod = $this->getShippingMethod();
        if ($shippingMethod === self::BREAD_SESSION_QUOTE_SHIPPING_METHOD_PICKUP_KEY) {
            $fulfillmentType = 'PICKUP';
        }
        return $fulfillmentType;
    }

    /**
     * Get Bread Formatted Shipping Address Data From Address Model
     *
     * @return array
     */
    public function getShippingAddressData()
    {

        if ($this->isInAdmin()) {
            $shippingAddress = $this->orderCreateModel->getShippingAddress();
        } else {
            $shippingAddress = $this->getSessionQuote()->getShippingAddress();
        }

        if (!$shippingAddress->getStreetLine(1)) {
            return false;
        }

        return [
            'fulfillmentType' => $this->getFulfillmentType(),
            'fullName'      => trim($shippingAddress->getName()),
            'address'       => $shippingAddress->getStreetLine(1) .
                ($shippingAddress->getStreetLine(2) == '' ? '' : (' ' . $shippingAddress->getStreetLine(2))),
            'address2'      => $shippingAddress->getStreetLine(3) .
                ($shippingAddress->getStreetLine(4) == '' ? '' : (' ' . $shippingAddress->getStreetLine(4))),
            'city'          => $shippingAddress->getCity(),
            'email'         => $shippingAddress->getEmail(),
            'state'         => $shippingAddress->getRegionCode(),
            'zip'           => $shippingAddress->getPostcode(),
            'phone'         => is_null($shippingAddress->getTelephone()) ? '' : substr(preg_replace('/[^0-9]+/', '', $shippingAddress->getTelephone()), -10)
        ];
    }

    /**
     * Get Bread Formatted Shipping Address Data From Address Model For API
     *
     * @return array
     */
    public function getShippingAddressAPIData()
    {
        if ($this->isInAdmin()) {
            $shippingAddress = $this->orderCreateModel->getShippingAddress();
        } else {
            $shippingAddress = $this->getSessionQuote()->getShippingAddress();
        }

        if (!$shippingAddress->getStreetLine(1)) {
            return false;
        }

        // @codingStandardsIgnoreStart
        return [
            'firstName'     => $shippingAddress->getFirstname(),
            'lastName'      => $shippingAddress->getLastname(),
            'address'       => $shippingAddress->getStreetLine(1) . ($shippingAddress->getStreetLine(2) == '' ? '' : (' ' . $shippingAddress->getStreetLine(2))),
            'address2'      => $shippingAddress->getStreetLine(3) . ($shippingAddress->getStreetLine(4) == '' ? '' : (' ' . $shippingAddress->getStreetLine(4))),
            'city'          => $shippingAddress->getCity(),
            'state'         => $shippingAddress->getRegionCode(),
            'zip'           => $shippingAddress->getPostcode(),
            'phone'         => is_null($shippingAddress->getTelephone()) ? '' : substr(preg_replace('/[^0-9]+/', '', $shippingAddress->getTelephone()), -10)
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * Get Bread Formatted Shipping Options Information
     *
     * @return string
     */
    public function getShippingOptions()
    {
        if ($this->isInAdmin()) {
            $shippingAddress = $this->orderCreateModel->getShippingAddress();
        } else {
            $shippingAddress = $this->getSessionQuote()->getShippingAddress();
        }

        if (!$shippingAddress->getShippingMethod()) {
            return false;
        }

        return ['type'   => $shippingAddress->getShippingDescription(),
                'typeId' => $shippingAddress->getShippingMethod(),
                'cost'   => round($shippingAddress->getShippingAmount() * 100)];
    }

    /**
     * Get stored bread transaction ID from quote
     *
     * @return string
     */
    public function getBreadTransactionId()
    {
        return $this->getSessionQuote()->getBreadTransactionId();
    }

    /**
     * Get Session Quote object for admin or frontend
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getSessionQuote()
    {
        if ($this->quote === null) {
            if ($this->isInAdmin()) {
                $this->quote = $this->orderCreateModel->getQuote();
            } else {
                $this->quote = $this->checkoutSession->getQuote();
            }
        }

        return $this->quote;
    }

    /**
     * @return \Magento\Checkout\Model\Session|\Magento\Sales\Model\AdminOrder\Create
     */
    public function getSession()
    {
        if ($this->isInAdmin()) {
            return $this->orderCreateModel;
        } else {
            return $this->checkoutSession;
        }
    }

    /**
     * @param  null $quote
     * @return mixed
     * @throws \Exception
     */
    public function submitQuote($quote = null)
    {
        if (!$quote) {
            $quote = $this->getSessionQuote();
        }

        $session = $this->getSession();
        $apiVersion = $this->getApiVersion();
        return $session->getData(self::BREAD_SESSION_QUOTE_RESULT_KEY);
    }

    /**
     * Check if Product Type is allowed in the Cart
     *
     * @deprecated
     * @return     bool
     */
    public function isProductsAllowedInCart()
    {
        $quote = $this->getSessionQuote();
        $notAllowedProductTypes = [
            \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE,
        ];

        foreach ($quote->getAllVisibleItems() as $cartItem) {
            if (in_array($cartItem->getProduct()->getTypeId(), $notAllowedProductTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get Tooltip message to show on payment method
     *
     * @return string
     */
    public function getMethodTooltip()
    {
        $session = $this->getSession();
        $quoteResult = $session->getData(self::BREAD_SESSION_QUOTE_RESULT_KEY);

        if (empty($quoteResult)) {
            $quoteResult = $this->submitQuote(null);
        }

        if ($quoteResult && array_key_exists('asLowAs', $quoteResult)) {
            return $quoteResult['asLowAs']['asLowAsText'];
        }
    }

    /**
     * Check if cart contains valid product types
     *
     * @return bool
     */
    public function validateAllowedProductTypes()
    {
        $quote = $this->getSessionQuote();
        $items = $quote->getAllVisibleItems();

        foreach ($items as $item) {
            if ($this->allowedProductType($item->getProductType()) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if for given sku bread checkout is disabled
     *
     * @param string $sku
     * @param string $store
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkDisabledForSku($sku = null, $store = \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
    {
        $disabledSkus = $this->getConfigValue("XML_CONFIG_DISABLED_FOR_SKUS", $store);
        if(is_null($disabledSkus)) {
            return false;
        }
        $disabledSkus = preg_replace('/\s/', '', $disabledSkus);

        $disabledSkus = explode(',', $disabledSkus);
        $output = false;

        if ($sku !== null) {
            $output = in_array($sku, $disabledSkus);
        } else {
            $skus = $this->getParentSkus();
            foreach ($skus as $sku) {
                if (in_array($sku, $disabledSkus)) {
                    $output = true;
                    break;
                }
            }
        }

        return $output;
    }

    /**
     * Find all top level skus for quote items
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getParentSkus()
    {
        $quoteItems = $this->getSessionQuote()->getAllItems();
        $parentSkus = [];

        foreach ($quoteItems as $item) {

            $parentItem = $item->getParentItem();
            $skipItem = !$parentItem && in_array($item->getProductType(), ['configurable','bundle']);

            if ($skipItem) {
                continue;
            } elseif ($parentItem) {
                $product = $this->productRepository->getById($parentItem->getProduct()->getId());
                // using sku as key to avoid having multiple values set from child items
                $parentSkus[$product->getSku()] = null;
            } else {
                $parentSkus[$item->getSku()] = null;
            }
        }

        return array_keys($parentSkus);
    }

    /**
     * Check if items in quote match
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isFinancingBySku($storeCode = null)
    {
        $quote = $this->getSessionQuote();
        $financingAllowedSkus = $this->getTargetedFinancingSkus($storeCode);

        $parentItems = $this->getParentSkus();
        $allowed = [];

        foreach ($parentItems as $itemSku) {

            if (in_array($itemSku, $financingAllowedSkus)) {
                $allowed[] = $itemSku;
            }

        }

        return (int)$quote->getItemsCount() === count($allowed);
    }

    /**
     * Returns targeted financing program id and if it should be used or not
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTargetedFinancingStatus()
    {
        $financingInfo = $this->getFinancingData();

        return [
            'shouldUseFinancingId' => $this->shouldUseFinancingId($financingInfo),
            'id' => $financingInfo['id']
        ];
    }

    /**
     * Checks if we should use alternate financing program Id
     *
     * @param array $financingInfo
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function shouldUseFinancingId($financingInfo)
    {
        if (!$financingInfo['enabled']) {
            return false;
        }

        if ($financingInfo['mode']['cart']) {
            $quoteGrandTotal = round($this->getSessionQuote()->getGrandTotal(), 2);
            return $quoteGrandTotal >= $financingInfo['threshold'];
        }

        if ($financingInfo['mode']['sku']) {
            return $this->isFinancingBySku();
        }

        return false;
    }
}
