<?php
/**
 * Create Magento Order From Bread Pop Up Order
 *
 * @author Bread   copyright 2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Controller\Checkout;

class ValidateOrder extends \Bread\BreadCheckout\Controller\Checkout
{
    /**
     * @var \Bread\BreadCheckout\Model\Payment\Api\Client
     */
    public $paymentApiClient;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    public $checkoutSession;

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    public $cartHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Log
     */
    public $logger;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    public $messageManager;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    public $resultRedirectFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $customerSession;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    public $quoteManagement;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    public $catalogProductFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $helper;

    /**
     * @var \Bread\BreadCheckout\Helper\Customer
     */
    public $customerHelper;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    public $orderSender;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Bread\BreadCheckout\Model\Payment\Api\Client $paymentApiClient,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Cart $cartHelper,
        \Bread\BreadCheckout\Helper\Log $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Bread\BreadCheckout\Helper\Checkout $helper,
        \Bread\BreadCheckout\Helper\Customer $customerHelper,
        \Magento\Catalog\Model\ResourceModel\ProductFactory $catalogResourceModelProductFactory,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    ) {
    
        $this->paymentApiClient = $paymentApiClient;
        $this->checkoutSession = $checkoutSession;
        $this->cartHelper = $cartHelper;
        $this->logger = $logger;
        $this->messageManager = $context->getMessageManager();
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->customerSession = $customerSession;
        $this->quoteManagement = $quoteManagement;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->customerHelper = $customerHelper;
        $this->orderSender = $orderSender;
        parent::__construct(
            $context,
            $catalogResourceModelProductFactory,
            $dataObjectFactory,
            $checkoutSession,
            $quoteFactory,
            $catalogProductFactory,
            $logger,
            $helper,
            $totalsCollector,
            $quoteRepository,
            $customerSession,
            $quoteManagement
        );
    }

    /**
     * Validate and process the order
     *
     * @return $this|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $token = $this->getRequest()->getParam('token');
            if ($token) {
                $this->logger->log(
                    [
                    'VALIDATE ORDER TOKEN' => $token,
                    ]
                );
                $data = $this->paymentApiClient->getInfo($token);
                $this->processOrder($data);
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();

            $this->logger->log(['MESSAGE' => $errorMessage]);

            //TODO: rewrite this when API is updated to better handle errors, instead of searching
            //TODO: through error message string

            $partOfDeclineMessage = "The credit/debit card portion of your transaction was declined.";
            $isNotSplitPayDecline = strpos($errorMessage, $partOfDeclineMessage) === false;

            if ($isNotSplitPayDecline) {
                $errorMessage .= ' Try to checkout by going through the standard checkout process and'
                    . ' selecting Pay Over Time as your payment method.';
            }

            $this->messageManager->addErrorMessage(
                __($errorMessage)
            );

            $resultRedirect = $this->resultRedirectFactory
                ->create()
                ->setUrl($this->_redirect->getRefererUrl());

            return $resultRedirect;
        }
    }

    /**
     * Process Order Placed From Bread Pop Up
     *
     * @param  $data
     * @throws \Exception
     */
    protected function processOrder($data)
    {
        // @codingStandardsIgnoreStart
        $this->logger->log(['PROCESS ORDER DATA' => $data]);

        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->checkoutSession->getQuote();

        $storeId = $this->storeManager->getStore()->getId();
        $quote->setStoreId($storeId);

        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomerId();
            $quote->setCustomerId($customerId);
        } else {
            $quote->setCustomerEmail($data['billingContact']['email']);
        }

        // bread_2 API uses 'id'; classic API uses 'breadTransactionId'
        $breadTransactionId = isset($data['id']) ? $data['id'] : $data['breadTransactionId'];
        $this->checkoutSession->setBreadTransactionId($breadTransactionId);

        $productPage = $this->getRequest()->getParam('product_page');
        if ($productPage) {
            $quote->setCouponCode('');
        } else {
            if (isset($data['discounts']) && !empty($data['discounts'])) {
                $discountDescription = $data['discounts'][0]['description'];
                $quote->setCouponCode($discountDescription);
            }
        }

        $billingContact = $this->customerHelper->processAddress($data['billingContact']);
        $shippingContact = $quote->isVirtual() ? $billingContact : $this->customerHelper->processAddress($data['shippingContact']);

        if (!isset($shippingContact['email'])) {
            $shippingContact['email'] = $billingContact['email'];
        }

        if ($billingContact['city'] == null) {
            $billingContact['city'] = $shippingContact['city'];
            $billingContact['region_id'] = $shippingContact['region_id'];
        }

        $this->logger->log(['SHIPPING CONTACT' => $shippingContact, 'BILLING CONTACT' => $billingContact]);

        $billingAddress = $quote->getBillingAddress()->addData($billingContact);
        $shippingAddress = $quote->getShippingAddress()->addData($shippingContact)->setCollectShippingRates(true);

        if (!isset($data['shippingMethodCode'])) {
            $this->logger->log('Shipping Method Code Is Not Set On The Response');
        }

        $shippingAddress->setShippingMethod($data['shippingMethodCode']);

        if (!$quote->isVirtual() && $quote->getShippingAddress()) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        if (!$quote->getPayment()->getQuote()) {
            $quote->getPayment()->setQuote($quote);
        }
        $quote->getPayment()->setMethod('breadcheckout');

        $customer = $this->customerHelper->createCustomer($quote, $billingContact, $shippingContact,false);

        if (!$customer->getId()) {
            $quote->setCustomerIsGuest(true);
        } else {
            $quote->setCustomer($customer->getDataModel());
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        $quote->getPayment()->importData(['method' => 'breadcheckout']);
        $quote->getPayment()->setTransactionId($breadTransactionId);
        $quote->getPayment()->setAdditionalData('BREAD CHECKOUT DATA', json_encode($data));

        try {
            $order = $this->quoteManagement->submit($quote);
        } catch (\Throwable $e) {
            $this->logger->log(['ERROR SUBMITTING QUOTE IN PROCESS ORDER' => $e->getMessage()]);
            throw $e;
        }

        $this->checkoutSession
            ->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();

        if ($customer->getId()) {
            $this->customerSession->setCustomerAsLoggedIn($customer);
        }

        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());
        $this->customerSession->setBreadItemAddedToQuote(false);

        $this->_redirect('checkout/onepage/success');
        // @codingStandardsIgnoreEnd
    }
}
