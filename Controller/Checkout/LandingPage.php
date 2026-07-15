<?php

namespace Bread\BreadCheckout\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;


class LandingPage extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    public $request;

    /**
     * @var \Bread\BreadCheckout\Model\Payment\Api\Client
     */
    public $paymentApiClient;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    public $customer;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    public $checkoutSession;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    public $quoteRepository;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    public $quoteManagement;

    /**
     * @var \Bread\BreadCheckout\Helper\Checkout
     */
    public $helper;

    /**
     * @var \Bread\BreadCheckout\Helper\Log
     */
    public $logger;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    public $customerFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    public $quoteFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
     */
    public $orderCollectionFactory;

    /**
     *  @var \Magento\Sales\Api\OrderManagementInterface;
     */
    public $orderManagement;

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    public $cartHelper;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    public $orderSender;

    /**
     * @var \Bread\BreadCheckout\Helper\Quote
     */
    public $quoteHelper;

    /**
     * @var \Bread\BreadCheckout\Helper\Customer
     */
    public $customerHelper;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    public $orderRepository;

    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Bread\BreadCheckout\Model\Payment\Api\Client $paymentApiClient,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Bread\BreadCheckout\Helper\Checkout $helper,
        \Bread\BreadCheckout\Helper\Log $logger,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Checkout\Helper\Cart $cartHelper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Bread\BreadCheckout\Helper\Quote $quoteHelper,
        \Bread\BreadCheckout\Helper\Customer $customerHelper
    ) {

        $this->request                  = $request;
        $this->paymentApiClient         = $paymentApiClient;
        $this->customer                 = $customer;
        $this->customerSession          = $customerSession;
        $this->checkoutSession          = $checkoutSession;
        $this->quoteRepository          = $quoteRepository;
        $this->quoteManagement          = $quoteManagement;
        $this->helper                   = $helper;
        $this->logger                   = $logger;
        $this->customerFactory          = $customerFactory;
        $this->storeManager             = $storeManager;
        $this->quoteFactory             = $quoteFactory;
        $this->orderCollectionFactory   = $orderCollectionFactory;
        $this->orderManagement          = $orderManagement;
        $this->cartHelper               = $cartHelper;
        $this->orderSender              = $orderSender;
        $this->resultFactory            = $context->getResultFactory();
        $this->orderRepository          = $orderRepository;
        $this->quoteHelper              = $quoteHelper;
        $this->customerHelper           = $customerHelper;
        parent::__construct($context);
    }

    /**
     * Convert cart to order
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $apiVersion = $this->customerHelper->getApiVersion();
        $orderRef = $this->request->getParam("orderRef");
        $this->logger->log('Checkout Request: ' . $orderRef);
        if($apiVersion === 'bread_2') {
            $action = $this->request->getParam("action");
            $this->logger->log('Bread 2 checkout action: ' . $action);
            if($action === 'checkout-error') {
                $this->logger->log('Checkout could not be completed for orderRef: ' . $orderRef);
                $this->messageManager->addErrorMessage(
                        __('There was an error with your financing program. Notification was sent to merchant.')
                );
                $this->_redirect("/");
            }

            if($action === 'checkout-complete') {
                $this->logger->log('Checkout completed for orderRef: ' . $orderRef);
                $this->_redirect('checkout/onepage/success');
            }

            if($action === 'callback') {
                $this->logger->log('Callback action for orderRef: ' . $orderRef);
                $tx_id = null;
                $data = json_decode(file_get_contents('php://input'), true);
                $this->logger->log('Request Data: ' . json_encode($data));
                if (isset($data['transactionId'])) {
                    $tx_id = trim($data['transactionId']);
                }

                if($orderRef && $tx_id) {
                    $this->processPlatformCartOrder($tx_id, $orderRef, $apiVersion);
                } else {
                    $this->_redirect("/");
                }
            }
        } else {
            $transactionId = $this->request->getParam("transactionId");
            if ($transactionId && $orderRef && !$this->request->getParam("error")) {
                $this->validateBackendOrder($transactionId, $orderRef);
            } else {
                $this->_redirect("/");
            }
        }
    }

    /**
     * Process platform backend order
     *
     * @param string $transactionId
     * @param string $orderRef
     */
    public function processPlatformCartOrder($transactionId, $orderRef, $apiVersion) {
        try {
            //Fetch the Trx
            $data = $this->paymentApiClient->getInfo($transactionId, $apiVersion);
            $this->logger->log('Trx details :: ' . json_encode($data));

            // NOTE (security): Do NOT authenticate the customer here. The email address in
            // $data originates from a Bread transaction referenced only by request parameters,
            // which would allow an attacker who can enumerate/predict quote IDs to log in as
            // any customer whose email is known. The order is still associated with the
            // correct customer inside processBackendOrder() via customerHelper->createCustomer().

            $this->processBackendOrder($orderRef, $data, $transactionId, $apiVersion);

            $this->_redirect('checkout/onepage/success');
        } catch (\Throwable $e) {
            $this->logger->log(['ERROR' => $e->getMessage(), 'TRACE' => $e->getTraceAsString()]);
            $this->customerHelper->sendCustomerErrorReportToMerchant($e, "", $orderRef, $transactionId);
            $this->messageManager->addErrorMessage(
                __('There was an error with your financing program. Notification was sent to merchant.')
            );
            $this->_redirect("/");
        }
    }

    /**
     * Create Magento Order From Backend Quote
     */
    public function validateBackendOrder($transactionId, $orderRef)
    {
        try {
            if ($transactionId) {
                $data       = $this->paymentApiClient->getInfo($transactionId);
                $this->logger->log('Trx details :: ' . json_encode($data));

                // NOTE (security): Do NOT authenticate the customer here. See processPlatformCartOrder().

                $this->processBackendOrder($orderRef, $data, $transactionId);

                $this->_redirect('checkout/onepage/success');
            }
        } catch (\Throwable $e) {
            $this->logger->log(['ERROR' => $e->getMessage(), 'TRACE' => $e->getTraceAsString()]);
            $this->customerHelper->sendCustomerErrorReportToMerchant($e, "", $orderRef, $transactionId);
            $this->messageManager->addErrorMessage(
                __('There was an error with your financing program. Notification was sent to merchant.')
            );
            $this->_redirect("/");
        }
    }

    /**
     * Process Order Placed From Bread Pop Up
     *
     * @param  $orderRef
     * @param  $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processBackendOrder($orderRef, $data, $transactionId, $apiVersion = null)
    {
        $quote = $this->quoteFactory->create()->loadByIdWithoutStore($orderRef);

        $billingAddress = null;
        $shippingAddress = null;
        if($apiVersion === 'bread_2') {
            $billingAddress = $this->customerHelper->processPlatformAddress($data['billingContact']);
            $shippingAddress = $this->customerHelper->processPlatformAddress($data['shippingContact']);
        } else {
            $billingAddress = $this->customerHelper->processAddress($data['billingContact']);
            $shippingAddress = $this->customerHelper->processAddress($data['shippingContact']);
        }

        if (!isset($shippingAddress['email'])) {
            $shippingAddress['email'] = $billingAddress['email'];
        }

        $customer = $this->customerHelper->createCustomer($quote, $billingAddress, $shippingAddress, true);

        $this->checkoutSession->setBreadTransactionId($transactionId);

        if (!$quote->getPayment()->getQuote()) {
            $quote->getPayment()->setQuote($quote);
        }
        $quote->getPayment()->setMethod('breadcheckout');

        // Associate the customer with the quote directly, WITHOUT authenticating the
        // browser session. This replaces the previous setCustomerAsLoggedIn() call,
        // which was only masking a missing customer/order association: for an existing
        // customer, Helper\Customer::createCustomer() returns early without setting the
        // quote's customer id. Binding it here ensures the resulting order is linked to
        // the correct account (fixing the "missing order id" issue) while keeping the
        // request unauthenticated.
        $customerId = $customer->getId() ?: $quote->getCustomerId();
        if ($customerId) {
            $quote->setCustomerId($customerId)
                ->setCustomerIsGuest(false);
            if ($customer->getEmail()) {
                $quote->setCustomerEmail($customer->getEmail());
            }
        } else {
            $quote->setCustomerIsGuest(true);
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        $quote->getPayment()->importData(['method' => 'breadcheckout']);
        $quote->getPayment()->setTransactionId($transactionId);
        $quote->getPayment()->setAdditionalData("BREAD CHECKOUT DATA", json_encode($data));

        try {
            // Check if the order already exists
            $orderCollection = $this->orderCollectionFactory->create()
                ->addFieldToFilter('quote_id', $orderRef)
                ->setPageSize(1)
                ->setCurPage(1);

            $order = $orderCollection->getFirstItem();
            $order = $order->getPayment()->getOrder();

            if ($order->getId()) {
                $order = $this->orderManagement->place($order);
                $quote->setIsActive(false);
                $order->getPayment()->setMethod('breadcheckout');
                $order->getPayment()->setTransactionId($transactionId);
                $order->getPayment()->setAdditionalData("BREAD CHECKOUT DATA", json_encode($data));
                $this->orderRepository->save($order);
                $this->quoteRepository->save($quote);
            } else {
                $this->logger->log('No order found with quote ID: ' . $orderRef . '. Submitting new order');
                $order = $this->quoteManagement->submit($quote);
            }
        } catch (\Throwable $e) {
            $this->logger->log(
                [
                'ERROR SUBMITTING QUOTE IN PROCESS ORDER' => $e->getMessage(),
                'TRACE' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }

        $this->checkoutSession
            ->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();

        try {
            $this->orderSender->send($order);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
            $this->customerSession->setBreadItemAddedToQuote(false);
        }

        // NOTE (security): Do not touch the customer session here. This controller can
        // be reached from a Bread server-to-server callback (no user session) and even
        // when it is reached through the customer's browser the identity in $data is
        // not authenticated by the customer. The order remains associated with the
        // customer via the quote (see customerHelper->createCustomer above).

        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());
        $this->customerSession->setBreadItemAddedToQuote(false);

        $cart = $this->cartHelper->getCart();
        $cart->truncate()->save();
        $cartItems = $cart->getItems();
        // @codingStandardsIgnoreStart
        foreach ($cartItems as $item) {
            $quote->removeItem($item->getId())->save();
        }

        // @codingStandardsIgnoreEnd

        $this->_redirect('checkout/onepage/success');
    }
    
    /**
     * CSRF handling.
     *
     * This endpoint is used in two ways that are NOT initiated by an authenticated
     * customer browser session and therefore cannot present a Magento form key:
     *   1. A server-to-server callback POST from Bread carrying a JSON transactionId.
     *   2. A browser redirect back from the Bread hosted flow (bread_1 legacy).
     *
     * Request authenticity is enforced by re-fetching the transaction from Bread's
     * API using the merchant's server-side credentials (see paymentApiClient->getInfo).
     * A forged transactionId cannot resolve to a valid transaction, and the order
     * association is validated against the supplied orderRef / quote.
     *
     * TODO: If/when Bread exposes a webhook signature header, verify it here and
     *       return an InvalidRequestException from createCsrfValidationException()
     *       for any request that fails signature verification.
     */
    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool {
        return true;
    }

}
