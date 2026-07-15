<?php
/**
 * Handles Checking Out From The Product Page
 *
 * @author Bread   copyright 2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Controller;

abstract class Checkout extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    public $catalogResourceModelProductFactory;
    
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    public $dataObjectFactory;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    public $checkoutSession;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    public $quoteFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    public $catalogProductFactory;

    /**
     * @var \Bread\BreadCheckout\Helper\Log
     */
    public $logger;

    /**
     * @var \Bread\BreadCheckout\Helper\Checkout
     */
    public $helper;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    public $resultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\TotalsCollector
     */
    public $totalsCollector;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    public $quoteRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $customerSession;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    public $quoteManagement;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\ResourceModel\ProductFactory $catalogResourceModelProductFactory,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Bread\BreadCheckout\Helper\Log $logger,
        \Bread\BreadCheckout\Helper\Checkout $helper,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteManagement $quoteManagement
    ) {
    
        $this->catalogResourceModelProductFactory = $catalogResourceModelProductFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteFactory = $quoteFactory;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->totalsCollector = $totalsCollector;
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession;
        $this->quoteManagement = $quoteManagement;
        $this->resultFactory = $context->getResultFactory();
        parent::__construct($context);
    }

    /**
     * Whether the current request is an AJAX (XHR) request.
     *
     * Used as a defense-in-depth CSRF control on state-changing endpoints, in
     * addition to Magento's default form-key validation. A custom header such as
     * X-Requested-With cannot be set by a cross-origin HTML form auto-submit, and
     * setting it from fetch/XHR forces a CORS preflight the server will not approve.
     *
     * @return bool
     */
    protected function isAjaxRequest()
    {
        return strtolower((string) $this->getRequest()->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Add Item To Quote
     *
     * @param  \Magento\Quote\Model\Quote     $quote
     * @param  \Magento\Catalog\Model\Product $product
     * @param  \Magento\Catalog\Model\Product $baseProduct
     * @param  array                          $customOptionPieces
     * @param  $quantity
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return void
     */
    protected function addItemToQuote(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\Product $baseProduct,
        $buyRequest,
        array $customOptionPieces,
        $quantity
    ) {

        $productId          = $product->getId();
        $baseProductId      = $baseProduct->getId();
        $buyInfo            = ['qty' =>  $quantity];

        if ($baseProductId != $productId) {
            /**
             * @var $catalogResource \Magento\Catalog\Model\ResourceModel\ProductFactory
             */
            $catalogResource            = $this->catalogResourceModelProductFactory->create();
            $options                    = [];
            $productAttributeOptions    = $baseProduct
                ->getTypeInstance(true)
                ->getConfigurableAttributesAsArray($baseProduct);
            foreach ($productAttributeOptions as $option) {
                $options[$option['attribute_id']]   =
                    $catalogResource->getAttributeRawValue($productId, $option['attribute_id'], null);
            }

            $buyInfo['super_attribute']     = $options;
        }

        if (isset($buyRequest['bundle_options'])) {
            foreach ($buyRequest['bundle_options'] as $key => $value) {
                if (empty($value)) {
                    unset($buyRequest['bundle_options'][$key]);
                }
            }
        }

        if (!empty($buyRequest['bundle_option'])) {
            $bundleOptionQty = isset($buyRequest['bundle_option_qty']) ? $buyRequest['bundle_option_qty'] : [];
            $buyInfo['product']                     = $baseProductId;
            $buyInfo['selected_configurable_option']= $buyRequest['selected_configurable_option'];
            $buyInfo['related_product']             = $buyRequest['related_product'];
            $buyInfo['bundle_option']               = $buyRequest['bundle_option'];
            $buyInfo['bundle_option_qty']           = $bundleOptionQty;
            $buyInfo['qty']                         = $buyRequest['qty'];
        }

        if (count($customOptionPieces)) {
            $customOptionConfig     = [];
            foreach ($customOptionPieces as $customOption) {

                $optionKeyValue     = explode('===', $customOption);
                $found              = false;
                foreach ($baseProduct->getOptions() as $o) {
                    if ($found) {
                        break;
                    }

                    $values     = $o->getValues();
                    if (!empty($values)) {
                        foreach ($values as $v) {
                            if ($this->compareOptions($v, $optionKeyValue[0])) {
                                $customOptionConfig[$v->getOptionId()][] = $optionKeyValue[1];
                                $found      = true;
                                break;
                            }
                        }
                    } else {
                        if ($this->compareOptions($o, $optionKeyValue[0])) {
                            unset($optionKeyValue[0]);
                            $optionId = $o->getOptionId();

                            if ($o->getType() === \Magento\Catalog\Model\Product\Option::OPTION_TYPE_DATE) {
                                if (!isset($buyInfo['validate_datetime_' . $optionId])) {
                                    $buyInfo['validate_datetime_' . $optionId] = '';
                                }

                                $optionGroups = array_chunk($optionKeyValue, 2);
                                foreach ($optionGroups as $group) {
                                    if (count($group) === 2) {
                                        $customOptionConfig[$optionId][$group[0]] = $group[1];
                                    }
                                }
                            } else {
                                $customOptionConfig[$optionId] = $optionKeyValue[1];
                            }

                            $found      = true;
                        }
                    }
                }
            }
            $buyInfo['options']     = $customOptionConfig;
        }

        $buyRequest = $this->dataObjectFactory->create();
        $buyRequest->addData($buyInfo);

        $quote->addProduct($baseProduct, $buyRequest);
    }

    /**
     * Compare selected option ID or SKU to current option
     * in loop
     *
     * @param  $optionData
     * @param  $optionValue
     * @return bool
     */
    protected function compareOptions($optionData, $optionValue)
    {
        if (preg_match('/^id~(.+)$/', $optionValue, $matches)) {
            return (bool) ($optionData->getOptionId() == $matches[1]);
        } else {
            return (bool) ($optionData['sku'] == $optionValue);
        }
    }

    /**
     * Collect Totals Tax and Shipping Estimate Actions
     *
     * @param  array $data
     * @return \Magento\Quote\Model\Quote\Address
     */
    protected function getShippingAddressForQuote(array $data)
    {
        try {
            $quote      = $this->getQuote($data);

            if ($quote->getIsVirtual()) {
                return $quote->getBillingAddress();
            }

            $address    = $quote->getShippingAddress();

            $regionId   = $address->getRegionModel()
                ->loadByCode($data['state'], $this->helper->getDefaultCountry())
                ->getId();

            $address->setCountryId($this->helper->getDefaultCountry())

                ->setStreet($data['address'])
                ->setCity($data['city'])
                ->setPostcode($data['zip'])
                ->setRegionId($regionId)
                ->setRegion($data['state'])
                ->setCollectShippingRates(true);

            if (isset($data['selectedShippingOption']) && isset($data['selectedShippingOption']['typeId'])) {
                $address->setShippingMethod($data['selectedShippingOption']['typeId']);
            }

            $this->totalsCollector->collectAddressTotals($quote, $address);
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->quoteRepository->save($quote);

            return $address;
        } catch (\Throwable $e) {
            $this->logger->log(['MESSAGE' => $e->getMessage(),'TRACE' => $e->getTraceAsString()]);
            return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)->setData(
                [
                'result' => ['error' => 1, 'text'  => 'Internal error']
                ]
            );
        }
    }

    /**
     * Get existing or generate new quote from supplied data
     *
     * @param  array $data
     * @return \Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getQuote(array $data)
    {
        $requestCode    = $data['block_key'];

        switch ($requestCode) {
            case \Bread\BreadCheckout\Helper\Data::BLOCK_CODE_CHECKOUT_OVERVIEW:
                $quote      = $this->checkoutSession->getQuote();
                break;
            case \Bread\BreadCheckout\Helper\Data::BLOCK_CODE_PRODUCT_VIEW:
                $removeItems = true;

                if (!$this->checkoutSession->getQuoteId()) {
                    if ($this->customerSession->isLoggedIn()) {
                        $quoteId = $this->quoteManagement
                            ->createEmptyCartForCustomer($this->customerSession->getCustomerId());
                    } else {
                        $quoteId = $this->quoteManagement->createEmptyCart();
                    }
                    $this->checkoutSession->setQuoteId($quoteId);
                    $removeItems = false;
                }

                $quote = $this->checkoutSession->getQuote();

                if (!$this->customerSession->getBreadItemAddedToQuote() && $removeItems) {
                    $quote->removeAllItems(); // Reset items in quote
                }

                if (!$this->customerSession->getBreadItemAddedToQuote() || !$quote->getAllVisibleItems()) {

                    if (array_key_exists('product_type', $data)
                        && $data['product_type'] === \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE
                    ) {
                        $this->processGroupedItems($quote, $data);
                    } else {
                        $this->processOrderItem($quote, $data);
                    }
                }
                break;
        }

        $address    = $quote->getShippingAddress();
        $this->totalsCollector->collectAddressTotals($quote, $address);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        return $quote;
    }

    /**
     * Add product to quote when checking out from product view page
     *
     * @param  $quote
     * @param  array $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processOrderItem($quote, array $data)
    {
        $selectedProductId  = $data['selected_simple_product_id'];
        $mainProductId      = $data['main_product_id'];
        $customOptionPieces = explode('***', $data['selected_sku']);
        $buyRequest         = [];

        if (isset($data['buy_request'])) {
            parse_str($data['buy_request'], $buyRequest);
        }

        $mainProduct = $this->catalogProductFactory->create()->load($mainProductId);
        $simpleProduct = $this->catalogProductFactory->create()->load($selectedProductId);
        // Qty always 1 when checking out from product view
        $this->addItemToQuote($quote, $simpleProduct, $mainProduct, $buyRequest, $customOptionPieces, 1);
        // Flag to prevent same item from getting added to quote many times
        $this->customerSession->setBreadItemAddedToQuote(true);
    }

    /**
     * Add product to quote when checking out from product view page with grouped product
     *
     * @param $quote \Magento\Quote\Model\Quote
     * @param array                            $data
     */
    protected function processGroupedItems($quote, array $data)
    {
        foreach ($data['items'] as $item) {
            $simpleProduct = $this->catalogProductFactory->create()->loadByAttribute('sku', $item['sku']);
            $this->addItemToQuote($quote, $simpleProduct, $simpleProduct, [], [], $item['quantity']);
        }
        $this->customerSession->setBreadItemAddedToQuote(true);
    }
}
