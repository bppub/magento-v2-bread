<?php
/**
 * Bread Finance Payment Method
 *
 * @author Bread   copyright   2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Model\Payment\Method;

class Rbc extends \Magento\Payment\Model\Method\AbstractMethod
{

    /* internal action types */
    const ACTION_CAPTURE                = 'capture';
    const ACTION_REFUND                 = 'refund';
    const ACTION_VOID                   = 'void';

    public $_code                       = 'rbccheckout';
    public $_infoBlockType              = 'Bread\BreadCheckout\Block\Payment\Info';
    public $_formBlockType              = 'Bread\BreadCheckout\Block\Payment\Form';

    public $_isGateway               = true;
    public $_canAuthorize            = true;
    public $_canCapture              = true;
    public $_canCapturePartial       = false;
    public $_canOrder                = false;
    public $_canRefund               = true;
    public $_canRefundInvoicePartial = true;
    public $_canVoid                 = true;
    public $_canUseInternal          = true;
    public $_canUseCheckout          = true;
    public $_canUseForMultishipping  = false;
    public $_canFetchTransactionInfo = true;
    public $_canSaveCc               = false;
    public $_canReviewPayment        = true;
    public $_allowCurrencyCode       = ['CAD'];

    /**
     * @var \Bread\BreadCheckout\Model\Payment\Api\Client
     */
    public $apiClient;

    /**
     * @var \Bread\BreadCheckout\Helper\Log
     */
    public $breadLogger;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $helper;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    public $jsonHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    public $checkoutSession;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    public $transactionBuilder;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    public $quoteRepository;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    public $transactionRepository;

    /**
     * @var \Magento\Sales\Model\AdminOrder\Create
     */
    public $orderCreateModel;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    public $priceCurrency;

    /**
     * @var \Bread\BreadCheckout\Helper\Quote
     */
    private $quoteHelper;

    /**
     * Construct Sets API Client And Sets Available For Checkout Flag
     *
     * @param \Magento\Framework\Model\Context                                $context
     * @param \Bread\BreadCheckout\Model\Payment\Api\Client                   $apiClient
     * @param \Bread\BreadCheckout\Helper\Data                                $helper
     * @param \Bread\BreadCheckout\Helper\Quote                               $quoteHelper
     * @param \Magento\Framework\Json\Helper\Data                             $jsonHelper
     * @param \Magento\Framework\Registry                                     $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory               $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory                    $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                                    $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface              $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                            $logger
     * @param \Magento\Checkout\Model\Session\Proxy                           $checkoutSession
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Quote\Api\CartRepositoryInterface                      $quoteRepository
     * @param \Magento\Sales\Api\TransactionRepositoryInterface               $transactionRepository
     * @param \Magento\Sales\Model\AdminOrder\Create                          $orderCreateModel
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface               $priceCurrency
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Bread\BreadCheckout\Model\Payment\Api\Client $apiClient,
        \Bread\BreadCheckout\Helper\Data $helper,
        \Bread\BreadCheckout\Helper\Quote $quoteHelper,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Bread\BreadCheckout\Helper\Log $breadLogger,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\AdminOrder\Create $orderCreateModel,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
    ) {
    
        $this->apiClient = $apiClient;
        $this->breadLogger = $breadLogger;
        $this->helper = $helper;
        $this->jsonHelper = $jsonHelper;
        $this->_canUseCheckout = $this->helper->isPaymentMethodAtCheckout();
        $this->checkoutSession = $checkoutSession;
        $this->transactionBuilder = $transactionBuilder;
        $this->quoteRepository = $quoteRepository;
        $this->transactionRepository = $transactionRepository;
        $this->orderCreateModel = $orderCreateModel;
        $this->priceCurrency = $priceCurrency;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * Fetch Payment Info
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @param  string                               $transactionId
     * @return array|mixed
     * @throws \Exception
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        $this->apiClient->setOrder($payment->getOrder());
        return $this->apiClient->getInfo($transactionId);
    }

    /**
     * Validate Payment Method before allowing next step in checkout
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        $paymentInfo   = $this->getInfoInstance();
        $this->breadLogger->info('Validating payment method');
        if ($paymentInfo instanceof \Magento\Sales\Model\Order\Payment) {
            $this->breadLogger->info('payment info instance of payment');
            $billingCountry    = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            $this->breadLogger->info('payment info NOT instance of payment');
            $billingCountry    = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }

        if (!$this->canUseForCountry($billingCountry)) {
            $this->breadLogger->log('ERROR IN METHOD VALIDATE, INVALID BILLING COUNTRY '. $billingCountry);
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'This financing program is available to CA residents, please click the finance button 
                and complete the application in order to complete your purchase with the financing payment method.'
                )
            );
        }

        /**
         * 
         * Initial purpose of this message was to notify customer to continue with placeOrder action
         * 
         * This has no side effect on the checkout experience
         * 
          $token = $this->getToken();
          if (empty($token)) {
          $this->breadLogger->log('ERROR IN METHOD VALIDATE, MISSING BREAD TOKEN');
          throw new \Magento\Framework\Exception\LocalizedException(
          __(
          'This financing program is unavailable, please complete the application.
          If the problem persists, please contact us.'
          )
          );
          }
         * 
         */
        $this->breadLogger->info('validate succeeded');

        return $this;
    }

    /**
     * Process Cancel Payment
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @return $this|Bread
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->breadLogger->info('Process cancel payment');
        return $this->void($payment);
    }

    /**
     * Process Void Payment
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @return \Bread\BreadCheckout\Model\Payment\Method\Rbc
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$this->canVoid()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Void action is not available.'));
        }

        $transactionAdditionalInfo = $payment->getTransactionAdditionalInfo();
        $cancelResult = isset($transactionAdditionalInfo['cancel_result']) 
            ? json_decode($transactionAdditionalInfo['cancel_result'], true) 
            : null;

        if ($this->helper->isTransactionProcessed($payment, $cancelResult, 'CANCELLED', true)) {
            $this->breadLogger->info(sprintf(
                'Transaction ID %s has already cancelled. Skipping further processing.',
                $payment->getTxnId()
            ));
            return $this;
        }

        return $this->_place($payment, 0, self::ACTION_VOID);
    }

    /**
     * Process Authorize Payment
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @param  float                                $amount
     * @return \Bread\BreadCheckout\Model\Payment\Method\Rbc
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            $this->breadLogger->info('authorize action is not available');
            throw new \Magento\Framework\Exception\LocalizedException(__('Authorize action is not available.'));
        }

        $transactionAdditionalInfo = $payment->getTransactionAdditionalInfo();
        $authorizeResult = isset($transactionAdditionalInfo['authorize_result']) 
            ? json_decode($transactionAdditionalInfo['authorize_result'], true) 
            : null;

        if ($this->helper->isTransactionProcessed($payment, $authorizeResult, 'AUTHORIZED', true)) {
            $this->breadLogger->info(sprintf(
                'Transaction ID %s has already authorized. Skipping further processing.',
                $payment->getTxnId()
            ));
            return $this;
        }

        $order = $payment->getOrder();
        if ($order->getOrderCurrencyCode() !== $order->getBaseCurrencyCode()) {
            $amount = $order->getGrandTotal();
        }

        $this->breadLogger->info([
            'MESSAGE' => 'about to set amount in authorize',
            'amount' => $amount
        ]);
        $payment->setAmount($amount);
        $this->breadLogger->info('about to set isTxClosed in authorize');
        $payment->setIsTransactionClosed(false);
        $tx_id = $this->getToken();
        $this->breadLogger->info([
            'MESSAGE' => 'about to set tx_id in authorize',
            'tx_id' => $tx_id
        ]);
        $payment->setTransactionId($tx_id);

        $this->breadLogger->info('all payment info set in authorize');

        $this->_place($payment, $amount, self::ACTION_AUTHORIZE);
        return $this;
    }

    /**
     * Set capture transaction ID to invoice for informational purposes
     *
     * @param  \Magento\Sales\Model\Order\Invoice $invoice
     * @param  \Magento\Sales\Model\Order\Payment $payment
     * @return \Magento\Payment\Model\Method\AbstractMethod
     */
    public function processInvoice(
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Model\Order\Payment $payment
    ) {
    
        $invoice->setTransactionId($payment->getLastTransId());
        return $this;
    }

    /**
     * Check to make sure capture, refund, cancel actions can be performed
     * only if transaction ID is set. Transaction ID will not be set
     * for orders created in OMS
     * 
     * @param string @action
     * @return bool
     */
    public function canPerformAction($action) {
        if ($this->_appState->getAreaCode() != \Magento\Framework\App\Area::AREA_ADMINHTML) {
            return true;
        }
        $payment = $this->getInfoInstance();
        $transactionId = $payment->getLastTransId();
        if (!$transactionId && $this->_appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML) {
            $this->breadLogger->log('RBC Missing transaction ID. ' . $action . ' action skipped');
            return false;
        }
        return $this->{'_can'.ucfirst($action)};
    }

    /**
     * Check capture availability
     * 
     * @return bool
     */
    public function canCapture() {
        return $this->canPerformAction('capture');
    }

    /**
     * Check refund availability
     * 
     * @return bool
     */
    public function canRefund() {
        return $this->canPerformAction('refund');
    }

    /**
     * Check void availability
     * 
     * @return bool
     */
    public function canVoid() {
        return $this->canPerformAction('void');
    }

    /**
     * Process Capture Payment
     *
     * @param  \Magento\Framework\DataObject $payment
     * @param  float                         $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Capture action is not available.'));
        }

        $transactionAdditionalInfo = $payment->getTransactionAdditionalInfo();
        $settleResult = isset($transactionAdditionalInfo['settle_result']) 
            ? json_decode($transactionAdditionalInfo['settle_result'], true) 
            : null;

        if ($this->helper->isTransactionProcessed($payment, $settleResult, 'SETTLED', true)) {
            $this->breadLogger->info(sprintf(
                'Transaction ID %s has already been captured and settled. Skipping further processing.',
                $payment->getTxnId()
            ));
            return $this;
        }

        $apiVersion = $this->helper->getApiVersion();

        $order = $payment->getOrder();
        if ($order->getOrderCurrencyCode() !== $order->getBaseCurrencyCode()) {
            $amount = $order->getGrandTotal();
        }

        if ($this->helper->getPaymentAction() == self::ACTION_AUTHORIZE_CAPTURE) {
            $this->apiClient->setOrder($payment->getOrder());

            if ($this->_appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML) {
                $token = $this->orderCreateModel->getSession()->getBreadTransactionId();
            } else {
                $token  = $this->checkoutSession->getBreadTransactionId();
            }

            $result = $this->apiClient->authorize(
                $token,
                ($this->priceCurrency->round($amount) * 100),
                $payment->getOrder()->getIncrementId()
            );

            
            if($apiVersion === 'bread_2') {
                $payment->setTransactionId($result['id']);               
            } else {
                $payment->setTransactionId($result['breadTransactionId']);
            }
            
        } else {
            $token  = $payment->getAuthorizationTransaction()->getTxnId();
        }

        $payment->setTransactionId($token);
        $payment->setAmount($amount);
        if($apiVersion === 'bread_2') {           
            $settledAmount = ($this->priceCurrency->round($amount) * 100);
            $this->_place($payment, $settledAmount, self::ACTION_CAPTURE);
        } else {                      
            $this->_place($payment, $amount, self::ACTION_CAPTURE);
        }

        return $this;
    }

    /**
     * Set transaction ID into creditmemo for informational purposes
     *
     * @param  \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @param  \Magento\Sales\Model\Order\Payment    $payment
     * @return \Magento\Payment\Model\Method\AbstractMethod
     */
    public function processCreditmemo(
        \Magento\Sales\Model\Order\Creditmemo $creditmemo,
        \Magento\Payment\Model\InfoInterface $payment
    ) {
    
        $creditmemo->setTransactionId($payment->getLastTransId());

        return $this;
    }

    /**
     * Process Refund Payment
     *
     * @param  \Magento\Framework\DataObject $payment
     * @param  float                         $amount
     * @return \Bread\BreadCheckout\Model\Payment\Method\Rbc
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Refund action is not available.'));
        }

        $transactionAdditionalInfo = $payment->getTransactionAdditionalInfo();
        $refundResult = isset($transactionAdditionalInfo['refund_result']) 
            ? json_decode($transactionAdditionalInfo['refund_result'], true) 
            : null;

        if ($this->helper->isTransactionProcessed($payment, $refundResult, 'REFUNDED', true)) {
            $this->breadLogger->info(sprintf(
                'Transaction ID %s has already refunded. Skipping further processing.',
                $payment->getTxnId()
            ));
            return $this;
        }

        $order = $payment->getOrder();
        if ($order->getOrderCurrencyCode() !== $order->getBaseCurrencyCode()) {
            $amount = $order->getGrandTotal();
        }

        return $this->_place($payment, $amount, self::ACTION_REFUND);
    }

    /**
     * Order payment
     *
     * @param  \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param  float                                                                      $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->breadLogger->info('Place order called');
        $transaction = $this->transactionBuilder->setPayment($payment)
            ->setOrder($payment->getOrder())
            ->setTransactionId($payment->getTransactionId())
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);

        $transactionAdditionalInfo = $payment->getTransactionAdditionalInfo();
        if (array_key_exists('is_closed', $transactionAdditionalInfo)) {
            $transaction->setIsClosed((bool) $transactionAdditionalInfo['is_closed']);
        }
        if (array_key_exists('message', $transactionAdditionalInfo)) {
            $transaction->setMessage($transactionAdditionalInfo['message']);
        }
    }

    /**
     * Process API Call Based on Request Type And Add Normalized Magento Transaction Data To Orders
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @param  $amount
     * @param  $requestType
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _place(\Magento\Payment\Model\InfoInterface $payment, $amount, $requestType, $currency = null)
    {
        $this->apiClient->setOrder($payment->getOrder());
        $this->breadLogger->info($requestType . ' API client request. ');
        $apiVersion = $this->helper->getApiVersion();
        $client = 'RBC Payplan';
        if (is_null($currency)) {
            $currency = $payment->getOrder()->getOrderCurrencyCode();
        }
        $this->breadLogger->info([
            'order currency' => $payment->getOrder()->getOrderCurrencyCode()
        ]);
        switch ($requestType) {
            case self::ACTION_AUTHORIZE:
                $this->breadLogger->info('about to call api client authorize');
                $tx_id = $this->getValidatedTxId($payment);
                $this->breadLogger->info('got tx_id in place authorize');
                $amount = ($this->priceCurrency->round($amount) * 100);
                $this->breadLogger->info([
                    'MESSAGE' => 'got amount in place authorize',
                    'amount' => $amount
                ]);
                $orderId = $payment->getOrder()->getIncrementId();
                $this->breadLogger->info([
                    'MESSAGE' => 'got orderId in place authorize',
                    'orderId' => $orderId
                ]);

                $result     = $this->apiClient->authorize(
                    $tx_id,
                    $amount,
                    $orderId
                );
                $this->breadLogger->info('called api client authorize');
                $this->breadLogger->info('Response: ' . json_encode($result));
                $breadTransactionId = null;
                if($apiVersion === 'bread_2') {
                    $breadTransactionId = $result['id'];
                    $this->addTransactionInfo(
                            $payment,
                            ['bread_version' => 'bread_2'],
                            [],
                            $client . ' platform transaction'
                    );
                } else {
                    $breadTransactionId = $result['breadTransactionId'];
                }
                $payment->setTransactionId($breadTransactionId);
                $this->addTransactionInfo(
                    $payment,
                    ['is_closed' => false, 'authorize_result' => $this->jsonHelper->jsonEncode($result)],
                    [],
                    $client . ' Payment Authorized'
                );
                break;
            case self::ACTION_CAPTURE:
                $this->breadLogger->info('called api client capture.');
                $result     = $this->apiClient->settle($this->getValidatedTxId($payment), $amount, $currency);
                $breadTransactionId = null;
                if($apiVersion === 'bread_2') {
                    $breadTransactionId = $result['id'];
                } else {
                    $breadTransactionId = $result['breadTransactionId'];                    
                }
                $payment->setTransactionId($breadTransactionId);
                $this->breadLogger->info('Rbc Transaction Id: ' . $breadTransactionId);
                $payment->setAmount($amount);
                $this->addTransactionInfo(
                    $payment,
                    ['is_closed' => false, 'settle_result' => $this->jsonHelper->jsonEncode($result)],
                    [],
                    $client . ' Payment Captured'
                );
                break;
            case self::ACTION_REFUND:
                $this->breadLogger->info('called api client refund transaction.');
                $result     = $this->apiClient->refund(
                    $this->getValidatedTxId($payment),
                    ($this->priceCurrency->round($amount) * 100),
                    [],
                    $currency
                );
                $payment->setTransactionId($payment->getTransactionId())
                    ->setAmount($amount)
                    ->setIsTransactionClosed(1)
                    ->setShouldCloseParentTransaction(1);
                $this->breadLogger->info('breadTransactionId: ' . $this->getValidatedTxId($payment));
                $this->breadLogger->info('Response: ' . json_encode($result));
                $this->addTransactionInfo(
                    $payment,
                    ['is_closed' => false, 'refund_result' => $this->jsonHelper->jsonEncode($result)],
                    [],
                    $client . ' Payment Refunded'
                );
                break;
            case self::ACTION_VOID:
                $this->breadLogger->info('called api client void/cancel transaction.');
                $this->breadLogger->info('breadTransactionId: ' . $this->getValidatedTxId($payment));
                $result     = $this->apiClient->cancel($this->getValidatedTxId($payment));
                $this->breadLogger->info('Response: ' . json_encode($result));
                $payment->setTransactionId($payment->getTransactionId())
                    ->setIsTransactionClosed(1)
                    ->setShouldCloseParentTransaction(1);
                $this->addTransactionInfo(
                    $payment,
                    ['is_closed' => true, 'cancel_result' => $this->jsonHelper->jsonEncode($result)],
                    [],
                    $client . ' Payment Canceled'
                );
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * Add payment transaction info to payment object
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @param  array                                $transactionAdditionalInfo
     * @param  array                                $transactionDetails
     * @param  null                                 $message
     * @return \Magento\Payment\Model\InfoInterface
     */
    protected function addTransactionInfo(
        \Magento\Payment\Model\InfoInterface $payment,
        $transactionAdditionalInfo = [],
        $transactionDetails = [],
        $message = null
    ) {
        try {
            $payment->resetTransactionAdditionalInfo();

            foreach ($transactionAdditionalInfo as $key => $value) {
                $payment->setTransactionAdditionalInfo($key, $value);
            }

            $payment->setTransactionAdditionalInfo('message', $message);

            foreach ($transactionDetails as $key => $value) {
                $payment->unsetData($key);
            }

            $payment->unsLastTransId();

            return $payment;
        } catch (\Throwable $e) {
            $this->breadLogger->log(['ERROR'=>$e->getMessage(),'TRACE'=>$e->getTraceAsString()]);
        }
    }

    /**
     * Is the 'rbccheckout' payment method available
     *
     * @param  \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        if ($this->helper->getConfigClient() !== 'RBC') {
            return false;
        }

        if ($quote === null) {
            return true;
        }

        if (!$this->helper->aboveThreshold($quote->getGrandTotal())) {
            return false;
        }

        if ($this->quoteHelper->checkDisabledForSku()) {
            return false;
        }

        if (!$quote->getBreadTransactionId()) {
            return true;
        }

        if (!parent::isAvailable($quote)) {
            return false;
        }

        return true;
    }

    /**
     * Get Bread transaction ID saved in session or payment additional info
     *
     * @return string|null
     */
    protected function getToken()
    {
        if ($this->helper->isInAdmin()) {
            $token = $this->orderCreateModel->getSession()->getBreadTransactionId();
        } else {
            // First try the checkout session (standard Magento checkout)
            $token = $this->checkoutSession->getBreadTransactionId();
            
            // If not found, check quote payment additional info (Hyva Checkout)
            if (empty($token)) {
                $quote = $this->checkoutSession->getQuote();
                if ($quote && $quote->getPayment()) {
                    $token = $quote->getPayment()->getAdditionalInformation('bread_transaction_id');
                }
            }
        }

        return $token;
    }

    /**
     * Validates and sanitizes the given transaction ID for making
     * an API request to Bread
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getValidatedTxId(\Magento\Payment\Model\InfoInterface $payment)
    {
        $rawTransId = $payment->getTransactionId();
        
        /**
         * When creating breadCarts, breadTrxId does not exist. preg_match will throw an error
         * 
         * @since 2.1.9
         */
        if(is_null($rawTransId)) {
            $this->breadLogger->log('INVALID TRANSACTION ID PROVIDED: '. $rawTransId);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Unable to process request because an invalid transaction ID was provided.')
            );
        }
        
        if (preg_match('/^[a-z0-9]{8}-([a-z0-9]{4}-){3}[a-z0-9]{12}/', $rawTransId, $matches)) {
            return $matches[0];
        } else {
            $this->breadLogger->log('INVALID TRANSACTION ID PROVIDED: '. $rawTransId);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Unable to process request because an invalid transaction ID was provided.')
            );
        }
    }

    /**
     * Returns payment method code
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->_code;
    }

    /**
     * Returns payment title with monthly estimate
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTitle()
    {
        $apiVersion = $this->helper->getApiVersion();
        if($apiVersion === 'bread_2') {
            return parent::getTitle();
        } else {
            $title = parent::getTitle();
            $showPerMonth = $this->helper->showPerMonthCalculation();

            $areaIsRestOrFrontend = $this->_appState->getAreaCode() == \Magento\Framework\App\Area::AREA_WEBAPI_REST
                || $this->_appState->getAreaCode() == \Magento\Framework\App\Area::AREA_FRONTEND;

            if ($areaIsRestOrFrontend && $showPerMonth) {
                $data = $this->quoteHelper->submitQuote(null);
                if (isset($data['asLowAs']) && isset($data['asLowAs']['amount'])) {
                    $title .= ' ' . sprintf(__('as low as %s/month*'), $data['asLowAs']['amount']);
                }
            }
            return $title;
        }
        
    }

    /**
     * Returns base payment title
     *
     * @return string
     */
    public function getBaseTitle()
    {
        return parent::getTitle();
    }
}
