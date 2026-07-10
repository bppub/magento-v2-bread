<?php
/**
 * Class Bread_BreadCheckout_Model_Payment_Api_Client
 *
 * @author Bread   copyright   2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 * @author Kip, Maritim @BreadFinancial
 */
namespace Bread\BreadCheckout\Model\Payment\Api;

class Client extends \Magento\Framework\Model\AbstractModel
{
    const STATUS_AUTHORIZED     = 'AUTHORIZED';
    const STATUS_SETTLED        = 'SETTLED';
    const STATUS_PENDING        = 'PENDING';
    const STATUS_CANCELED       = 'CANCELED';

    public $order               = null;

    /**
     * @var \Magento\Framework\Model\Context
     */
    public $context;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $helper;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    public $jsonHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeResolver;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    public $cache;
    
    /**
     *
     * @var \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     */
    public $configWriter;

    /**
     *
     * @var \Bread\BreadCheckout\Helper\Log $log
     */
    public $logger;
    
    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Bread\BreadCheckout\Helper\Data $helper
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Store\Model\StoreResolver $storeResolver
     * @param \Bread\BreadCheckout\Helper\Log $log
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Bread\BreadCheckout\Helper\Data $helper,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Store\Model\StoreResolver $storeResolver,
        \Bread\BreadCheckout\Helper\Log $log,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter    
    ) {
        $this->context = $context;
        $this->helper = $helper;
        $this->jsonHelper = $jsonHelper;
        $this->storeResolver = $storeResolver;
        $this->logger = $log;
        $this->cache = $cache;
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    public function setOrder(\Magento\Sales\Model\Order $order)
    {
        $this->order = $order;
    }

    /**
     * Call API Cancel Method
     *
     * @param  $breadTransactionId
     * @param  int                $amount
     * @param  array              $lineItems
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function cancel($breadTransactionId, $amount = 0, $lineItems = [])
    {
        $this->logger->info('Call API Cancel method. Bread trxId: '. $breadTransactionId);
        $this->helper->setConfigClientByCurrency($this->order->getOrderCurrencyCode());
        $apiVersion = $this->helper->getApiVersion();
        $payment = $this->order->getPayment();
        $paymentApiVersion = $payment->getData('bread_api_version');
        $this->logger->info('Payment API version: '. $paymentApiVersion);
        if(!is_null($paymentApiVersion) && in_array($paymentApiVersion, ['bread_2'])) {
            $apiVersion = strtolower($paymentApiVersion);
        }
        
        /* Check if already canceled in bread */
        $transaction = $this->getInfo($breadTransactionId, $apiVersion);
        if (strtoupper($transaction['status']) === self::STATUS_CANCELED || strtoupper($transaction['status']) === 'CANCELLED') {
            $this->logger->info('Transaction is already canceled in Bread. Bread trxId: '. $breadTransactionId);
            return $transaction;
        }
        
        
        if ($apiVersion === 'bread_2') {
            $this->logger->info('Bread platform transaction. Bread trxId: '. $breadTransactionId);
            $currency = $transaction['totalAmount']['currency'];
            $data = '{"amount": {"currency":"'.$currency.'","value":' . $transaction['totalAmount']['value'] . '}}';
            $this->logger->info('Payload data: ' . $data . ' Bread trxId: '. $breadTransactionId);
            $result = $this->call(
                    $this->getUpdateTransactionUrlPlatform($breadTransactionId, 'cancel'),
                    $data,
                    \Laminas\Http\Request::METHOD_POST,
                    false
            );
            $this->logger->info('Result: ' . json_encode($result). ' Bread trxId: '. $breadTransactionId);

            if (strtoupper($result['status']) !== 'CANCELLED') {
                $this->logger->log(['ERROR' => 'Transaction cancel failed', 'RESULT' => $result]);
                throw new \Magento\Framework\Exception\LocalizedException(
                        __('Transaction cancel failed (current transaction status :' . $result['status'] . ')')
                );
            }

            return $result;
        }
    }

    /**
     * Call API Authorize Method
     *
     * @param  $breadTransactionId
     * @param  $amount
     * @param  null               $merchantOrderId
     * @return mixed
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize($breadTransactionId, $amount, $merchantOrderId = null)
    {
        $this->logger->info('Call API Authorize method. Bread trxId: '. $breadTransactionId);
        $this->helper->setConfigClientByCurrency($this->order->getOrderCurrencyCode());
        $validateAmount = $this->getInfo($breadTransactionId);
        $this->logger->info('Trx: ' . json_encode($validateAmount));
        $apiVersion = $this->helper->getApiVersion();
        
        $payment = $this->order->getPayment();
        $paymentApiVersion = $payment->getData('bread_api_version');
        if(!is_null($paymentApiVersion) && in_array($paymentApiVersion, ['bread_2'])) {
            $apiVersion = strtolower($paymentApiVersion);
        }

        // set transaction id so it can be fetched for split payment cancel
        $this->setBreadTransactionId($breadTransactionId);
        
        if ($apiVersion === 'bread_2') {

            $breadAmount = trim($validateAmount['totalAmount']['value']);
            $currency = trim($validateAmount['totalAmount']['currency']);
            $amount = trim($amount);
            if (((int) $breadAmount != (int) $amount) && (abs((int) $breadAmount - (int) $amount) >= 2)) {
                $this->logger->log(
                        [
                            'ERROR' => 'BREAD AMOUNT AND QUOTE AMOUNT MIS-MATCH',
                            'BREAD AMOUNT' => (int) $breadAmount,
                            'QUOTE AMOUNT' => (int) $amount,
                            'RESULT' => $validateAmount
                        ]
                );
                throw new \Magento\Framework\Exception\LocalizedException(
                        __(
                                'There was a mismatch between the Bread amount and the transaction amount, Please contact '
                                . 'the store owner.'
                        )
                );
            }
            
            $data = '{"amount": {"currency":"'.$currency.'","value":' . $amount . '}}';
            $this->logger->info(['TrxId' => $breadTransactionId,
                    'Payload' => $data
                    ]);

            $result = $this->call(
                    $this->getUpdateTransactionUrlPlatform($breadTransactionId, 'authorize'),
                    $data,
                    \Laminas\Http\Request::METHOD_POST,
                    false
            );
            $this->logger->info('Response: ' . json_encode($result) . 'Bread trxId: '. $breadTransactionId);
            if ($result['status'] != self::STATUS_AUTHORIZED) {
                $this->logger->log(['ERROR' => 'AUTHORIZATION FAILED', 'RESULT' => $result]);
                throw new \Magento\Framework\Exception\LocalizedException(
                        __('Transaction authorize failed (current transaction status : ' . $result['status'] . ').')
                );
            }

            //Attach an orderId to this transaction
            $this->logger->log([
                'MESSAGE' => 'Running transaction update with merchantOrderId',
                'orderId' => $merchantOrderId
            ]);
            if (!empty($merchantOrderId)) {
                $data = '{"externalID":"' . $merchantOrderId . '","metadata":{"externalMerchantData":"externalInfo"}}';

                $updateMerchantOrderIdResult = $this->call(
                        $this->getTransactionInfoUrl($breadTransactionId),
                        $data,
                        \Laminas\Http\Request::METHOD_PATCH,
                        false
                );
                $this->logger->info('Response: ' . json_encode($updateMerchantOrderIdResult) . 'Bread trxId: '. $breadTransactionId);
            } else {
                $this->logger->log(['WARNING' => 'merchantOrderId is empty, skipping externalID update for Bread trxId: ' . $breadTransactionId]);
            }

            return $result;
        } else {
            $breadAmount = trim($validateAmount['total']);
            $amount = trim($amount);

            if (((int) $breadAmount != (int) $amount) && (abs((int) $breadAmount - (int) $amount) >= 2)) {
                $this->logger->log(
                        [
                            'ERROR' => 'BREAD AMOUNT AND QUOTE AMOUNT MIS-MATCH',
                            'BREAD AMOUNT' => (int) $breadAmount,
                            'QUOTE AMOUNT' => (int) $amount,
                            'RESULT' => $validateAmount
                        ]
                );
                throw new \Magento\Framework\Exception\LocalizedException(
                        __(
                                'There was a mismatch between the Bread amount and the transaction amount, Please contact '
                                . 'the store owner.'
                        )
                );
            }

            $data_array = ['type' => 'authorize'];
            if ($merchantOrderId != null) {
                $data_array['merchantOrderId'] = $merchantOrderId;
            }

            $result = $this->call(
                    $this->getUpdateTransactionUrl($breadTransactionId),
                    $data_array
            );
            $this->logger->info('Response: ' . json_encode($result) . 'Bread trxId: '. $breadTransactionId);
            if ($result['status'] != self::STATUS_AUTHORIZED) {
                $this->logger->log(['ERROR' => 'AUTHORIZATION FAILED', 'RESULT' => $result]);
                throw new \Magento\Framework\Exception\LocalizedException(
                        __('Transaction authorize failed (current transaction status : ' . $result['status'] . ').')
                );
            }

            return $result;
        }
    }

    /**
     * Call API Update Order Id
     *
     * @param  $breadTransactionId
     * @param  $merchantOrderId
     * @return mixed
     * @throws \Exception
     */
    public function updateOrderId($breadTransactionId, $merchantOrderId)
    {
        $result = $this->call(
            $this->getTransactionInfoUrl($breadTransactionId),
            ['merchantOrderId' => $merchantOrderId],
            \Laminas\Http\Request::METHOD_PUT
        );

        return $result;
    }
    
    /**
     * Call API Update Order Id Capture Authorized Transaction Method
     *
     * @param  $breadTransactionId
     * @return mixed
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function settle($breadTransactionId, $amount = null, $currency = null)
    {
        $this->logger->info('Call API settle method. Bread trxId: '. $breadTransactionId);
        $this->helper->setConfigClientByCurrency($this->order->getOrderCurrencyCode());
        $apiVersion = $this->helper->getApiVersion();
        
        $payment = $this->order->getPayment();
        $paymentApiVersion = $payment->getData('bread_api_version');
        if(!is_null($paymentApiVersion) && in_array($paymentApiVersion, ['bread_2'])) {
            $apiVersion = strtolower($paymentApiVersion);
        }
        
        if ($apiVersion === 'bread_2') {
            $validateAmount = $this->getInfo($breadTransactionId);
            $currency = trim($validateAmount['totalAmount']['currency']);
            $data = '{"amount": {"currency":"'.$currency.'","value":' . $amount . '}}';
            $this->logger->info('Payload: ' . $data . 'Bread trxId: '. $breadTransactionId);
            $result = $this->call(
                    $this->getUpdateTransactionUrlPlatform($breadTransactionId, 'settle'),
                    $data,
                    \Laminas\Http\Request::METHOD_POST,
                    false
            );
            $this->logger->info('Response: ' . json_encode($result) . 'Bread trxId: '. $breadTransactionId);
            if ($result['status'] != self::STATUS_SETTLED) {
                throw new \Magento\Framework\Exception\LocalizedException(
                        __('Transaction settle failed (current transaction status :' . $result['status'] . ')')
                );
            }

            return $result;
        } else {
            $result = $this->call(
                    $this->getUpdateTransactionUrl($breadTransactionId),
                    ['type' => 'settle']
            );
            $this->logger->info('Response: ' . json_encode($result) . 'Bread trxId: '. $breadTransactionId);
            if ($result['status'] != self::STATUS_SETTLED) {
                throw new \Magento\Framework\Exception\LocalizedException(
                        __('Transaction settle failed (current transaction status :' . $result['status'] . ')')
                );
            }

            return $result;
        }
    }

    /**
     * Call API Refund Method
     *
     * @param  $breadTransactionId
     * @param  int                $amount
     * @param  array              $lineItems
     * @return mixed
     * @throws \Exception
     */
    public function refund($breadTransactionId, $amount = 0, $lineItems = [], $currency = null)
    {
        $this->logger->info('Call API refund method. Bread trxId: '. $breadTransactionId);
        $this->helper->setConfigClientByCurrency($this->order->getOrderCurrencyCode());
        $apiVersion = $this->helper->getApiVersion();  
        
        $payment = $this->order->getPayment();
        $paymentApiVersion = $payment->getData('bread_api_version');
        if(!is_null($paymentApiVersion) && in_array($paymentApiVersion, ['bread_2'])) {
            $apiVersion = strtolower($paymentApiVersion);
        }
        
        if ($apiVersion === 'bread_2') {
            $validateAmount = $this->getInfo($breadTransactionId);
            $currency = trim($validateAmount['totalAmount']['currency']);
            $data = '{"amount": {"currency":"'.$currency.'","value":' . $amount . '}}';

            $result = $this->call(
                    $this->getUpdateTransactionUrlPlatform($breadTransactionId, 'refund'),
                    $data,
                    \Laminas\Http\Request::METHOD_POST,
                    false
            );
            $this->logger->info('Response: ' . json_encode($result) . 'Bread trxId: '. $breadTransactionId);
            return $result;
        } else {
            $data = ['type' => 'refund'];

            if (!$amount == 0) {
                $data['amount'] = $amount;
            }

            if (!empty($lineItems)) {
                $data['lineItems'] = $lineItems;
            }

            $result = $this->call($this->getUpdateTransactionUrl($breadTransactionId), $data);
            $this->logger->info('Response: ' . json_encode($result) . 'Bread trxId: '. $breadTransactionId);
            return $result;
            
        }
    }

    /**
     * Call API Get Info Method
     *
     * @param  $breadTransactionId
     * @return mixed
     * @throws \Exception
     */
    public function getInfo($breadTransactionId, $apiVersion = null)
    {
        $this->logger->info('Call API getInfo method. Bread trxId: '. $breadTransactionId);
        return $this->call(
            $this->getTransactionInfoUrl($breadTransactionId, $apiVersion),
            [],
            \Laminas\Http\Request::METHOD_GET
        );
    }

    /**
     * Submit cart data
     *
     * @param  $data
     * @return mixed
     * @throws \Exception
     */
    public function submitCartData($data)
    {
        return $this->call(
            $this->helper->getCartCreateApiUrl($this->getStoreId()),
            $data,
            \Laminas\Http\Request::METHOD_POST
        );
    }
    
    /**
     * Submit platform cart data
     * 
     * @param $data 
     * @return mixed
     */
    public function submitPlatformCartData($data) {
        $baseUrl = $this->helper->getTransactionApiUrl('bread_2', $this->getStoreId());
        return $this->call(
                        $baseUrl . '/cart',
                        $data,
                        \Laminas\Http\Request::METHOD_POST,
                        true
        );
    }

    /**
     * Use the “As low as” endpoint to calculate an “as low as” amount with compliant
     * financing disclosure based on your default or alternate financing program.
     *
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public function getAsLowAs($data)
    {
        $baseUrl = $this->helper->getTransactionApiUrl('classic', $this->getStoreId());
        $asLowAsUrl = join('/', [ trim($baseUrl, '/'), 'aslowas' ]);
        return $this->call(
            $asLowAsUrl,
            $data,
            \Laminas\Http\Request::METHOD_POST
        );
    }

    /**
     * Interact with the API
     *
     * @TODO switch over to using \Magento\Framework\HTTP\Client\Curl
     *
     * @param  $url
     * @param  $data
     * @param  string $method
     * @return mixed
     * @throws \Exception
     */
    protected function call($url, $data, $method = \Laminas\Http\Request::METHOD_POST, $jsonEncode = true)
    {
        $this->logger->info('We are at call');
        $storeId = $this->getStoreId();
        $apiVersion = $this->helper->getApiVersion();
        
        if(!is_null($this->order)) {
            $payment = $this->order->getPayment();
            $paymentApiVersion = $payment->getData('bread_api_version');
            if (!is_null($paymentApiVersion) && in_array($paymentApiVersion, ['bread_2'])) {
                $apiVersion = strtolower($paymentApiVersion);
            }
        }
        $this->logger->info("API Version :: " . $apiVersion );
        $username   = $this->helper->getApiPublicKey($apiVersion, $storeId);
        $password   = $this->helper->getApiSecretKey($apiVersion, $storeId);
            
        if($apiVersion === 'bread_2') {
            try {
                $authToken = $this->helper->getAuthToken();
                $authTokenUrl = $this->getAuthTokenUrl();
                if (is_null($authToken) || $authToken === '') {
                    $getToken = $this->generateAuthToken($authTokenUrl, $username, $password);
                    if (isset($getToken['token'])) {
                        $authToken = $getToken['token'];
                        $this->configWriter->save('payment/breadcheckout/bread_auth_token', $authToken, 'default');
                    } else {
                        $errorMessage = 'Call to Bread APIs failed.';
                        throw new \Magento\Framework\Exception\LocalizedException(
                                __($errorMessage)
                        );
                    }
                }

                $response = $this->callBread($url, $authToken, $data, $method, $jsonEncode);

                if (isset($response['error']) && $response['error'] === 'jwt_auth_error') {

                    $getToken = $this->generateAuthToken($authTokenUrl, $username, $password);
                    if (isset($getToken['token'])) {
                        $authToken = $getToken['token'];
                        $this->configWriter->save('payment/breadcheckout/bread_auth_token', $authToken, 'default');

                        $response = $this->callBread($url, $authToken, $data, $method, $jsonEncode);

                        if ((isset($response['error']) && $response['error'] === 'jwt_auth_error')) {
                            $this->logger->info('Error generating token. ' . json_encode($response));
                            $errorMessage = 'Call to Bread APIs failed.';
                            throw new \Magento\Framework\Exception\LocalizedException(
                                    __($errorMessage)
                            );
                        } else {
                            return $this->jsonHelper->jsonDecode($response['data']);
                        }
                    } else {
                        $errorMessage = 'Call to Bread API failed. Auth token generate';
                        $this->logger->info('Error generating token. ' . $errorMessage);
                        throw new \Magento\Framework\Exception\LocalizedException(
                                __($errorMessage)
                        );
                    }
                } elseif (isset($response['data'])) {
                    $result = $this->jsonHelper->jsonDecode($response['data']);
                    $this->logger->info('Response: ' . $response['data']);
                    return $result;
                } else {
                    $this->logger->info('Call to Bread API failed. ' . json_encode($response));
                    $errorMessage = 'Call to Bread API failed.';
                    throw new \Magento\Framework\Exception\LocalizedException(
                            __($errorMessage)
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->log([
                    'URL' => $url,
                    'DATA' => $data,
                ]);
                throw $e;
            }
        }
    }
    
    /**
     * 
     * @param type $url
     * @param type $authToken
     * @param array $data
     * @param type $method
     * @return type
     * @throws \Throwable
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function callBread($url, $authToken, $data, $method = \Laminas\Http\Request::METHOD_POST, $jsonEncode = true) {
        $curl = curl_init($url);
        try {
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            if ($method == \Laminas\Http\Request::METHOD_POST) {

                curl_setopt($curl, CURLOPT_POST, 1);
                $authorization = "Authorization: Bearer " . $authToken;
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    $authorization]);
                if(is_array($data)) {
                    $data = $this->jsonHelper->jsonEncode($data);
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }

            if ($method == \Laminas\Http\Request::METHOD_PUT || $method == \Laminas\Http\Request::METHOD_PATCH) {
                $authorization = "Authorization: Bearer " . $authToken;
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    $authorization]);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            }

            if ($method == \Laminas\Http\Request::METHOD_GET) {
                $authorization = "Authorization: Bearer " . $authToken;
                curl_setopt($curl, CURLOPT_HTTPHEADER, [$authorization]);
            }

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            $this->logger->log([
                'Process Request',
                'URL' => $url,
                'data' => $data,
                'Encode' => $jsonEncode
            ]);

            if ($status == 401) {
                $this->logger->log([
                    'MESSAGE' => 'JWT ERROR',
                    'URL' => $url,
                    'DATA' => $data,
                ]);
                return [
                    'error' => 'jwt_auth_error',
                    'description' => 'JWT Auth error'
                ];
            }

            if (!in_array($status, [200,201,204])) {
                $this->logger->log([
                    'ERROR' => 'Code is not equal to 200 or 201 or 204',
                    'STATUS' => $status,
                    'RESULT' => $result,
                    'TOKEN' => $authToken,
                    'URL' => $url
                ]);
                $errorMessage = 'Call to Bread API failed';

                throw new \Magento\Framework\Exception\LocalizedException(
                        __($errorMessage)
                );
            }
            
            if($status == 204) {
                $result = json_encode(['STATUS' => 204]);
            }

            $this->logger->log([
                'RESPONSE' => $result
            ]);

            return [
                'error' => null,
                'data' => $result
            ];
        } catch (\Throwable $e) {
            $this->logger->log([
                'AUTHTOKEN' => $authToken,
                'URL' => $url,
                'DATA' => $data,
                'MESSAGE' => $e->getMessage()
            ]);

            curl_close($curl);
            throw $e;
        }
    }

    /**
     * Send cart sms
     *
     * @param  string $cartId
     * @param  string $phone
     * @return mixed
     * @throws \Exception
     */
    public function sendSms($cartId, $phone)
    {
        $baseUrl = $this->helper->getTransactionApiUrl($this->getStoreId());
        $sendSmsUrl = join('/', [ trim($baseUrl, '/'), 'carts', trim($cartId, '/'), 'text' ]);
        $data = [ 'phone' => $phone ];
        return $this->call(
            $sendSmsUrl,
            $data,
            \Laminas\Http\Request::METHOD_POST
        );
    }

    /**
     * Send cart email
     *
     * @param  string $cartId
     * @param  string $email
     * @param  string $name
     * @return mixed
     * @throws \Exception
     */
    public function sendEmail($cartId, $email, $name)
    {
        $baseUrl = $this->helper->getTransactionApiUrl($this->getStoreId());
        $sendEmailUrl = join('/', [ trim($baseUrl, '/'), 'carts', trim($cartId, '/'), 'email' ]);
        $data = [ 'email' => $email, 'name' => $name ];
        return $this->call(
            $sendEmailUrl,
            $data,
            \Laminas\Http\Request::METHOD_POST
        );
    }
    
    /**
     * Send platform email
     */
    public function sendPlatformEmail($cartId, $data) {
        $baseUrl = $this->helper->getTransactionApiUrl('bread_2', $this->getStoreId());
        return $this->call(
                        $baseUrl . '/cart/' . $cartId . '/notify',
                        $data,
                        \Laminas\Http\Request::METHOD_POST,
                        true
        );
    }

    /**
     *
     * @param  $transactionId
     * @param  $trackingNumber
     * @param  $carrierName
     * @return mixed
     * @throws \Exception
     */
    public function setShippingDetails($transactionId, $trackingNumber, $carrierName)
    {
        $apiVersion = $this->helper->getApiVersion();
        $baseUrl = $this->helper->getTransactionApiUrl(null, $this->getStoreId());

        if ($apiVersion === 'bread_2') {

            $data = '{"amount": {"tracking_number":"' . $trackingNumber . '","carrier":"' . $carrierName . '"}}';
            $result = $this->call(
                    $this->getUpdateTransactionUrlPlatform($transactionId, 'fulfillment'),
                    $data,
                    \Laminas\Http\Request::METHOD_POST,
                    false
            );

            return $result;
        } else {
            $setShippingDetailsUrl = join(
                    '/',
                    [trim($baseUrl, '/'), 'transactions', trim($transactionId), 'shipment']
            );
            $data = ['trackingNumber' => $trackingNumber, 'carrierName' => $carrierName];
            return $this->call(
                            $setShippingDetailsUrl,
                            $data,
                            \Laminas\Http\Request::METHOD_POST
            );
        }
    }

    /**
     * Form transaction info URI string
     *
     * @param  $transactionId
     * @return string
     */
    protected function getTransactionInfoUrl($transactionId, $breadApiVersion = null)
    {   
        $apiVersion = $this->helper->getApiVersion();
        if(!is_null($breadApiVersion)) {
            $apiVersion = $breadApiVersion;
        }
        $baseUrl = $this->helper->getTransactionApiUrl($apiVersion, $this->getStoreId());
        if ($apiVersion === 'bread_2') {
            return join('/', [trim($baseUrl, '/'), 'transaction', trim($transactionId, '/')]);
        } else {
            return join('/', [trim($baseUrl, '/'), 'transactions', trim($transactionId, '/')]);
        }
    }

    /**
     * Form update transaction URI string
     *
     * @param  $transactionId
     * @return string
     */
    protected function getUpdateTransactionUrl($transactionId)
    {
        $baseUrl = $this->helper->getTransactionApiUrl('classic', $this->getStoreId());
        return join(
            '/',
            [ trim($baseUrl, '/'), 'transactions/actions', trim($transactionId, '/') ]
        );
    }
    
    /**
     * Form transaction info URI string
     *
     * @param  $transactionId
     * @return string
     */
    protected function getAuthTokenUrl() {
        $baseUrl = $this->helper->getTransactionApiUrl('bread_2', $this->getStoreId());
        return join('/', [trim($baseUrl, '/'), 'auth/service/authorize']);
    }
    
    /**
     * 
     * @param type $url
     * @param type $apiKey
     * @param type $apiSecret
     * @return type
     * @throws \Throwable
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function generateAuthToken($url, $apiKey, $apiSecret) {
        $data = [
            'apiKey' => $apiKey,
            'secret' => $apiSecret
        ];
        $curl = curl_init($url);
        try {
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_USERPWD, $apiKey. ':' . $apiSecret);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($this->jsonHelper->jsonEncode($data))]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->jsonHelper->jsonEncode($data));

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($status != 200) {
                $this->logger->log([
                    'URL' => $url,
                    'RESULT' => $result,
                    'CODE' => '200'
                ]);
                $errorMessage = 'Call to Bread Auth API failed';

                throw new \Magento\Framework\Exception\LocalizedException(
                        __($errorMessage)
                );
            }

            $response = $this->jsonHelper->jsonDecode($result);

            if (isset($response['token'])) {
                $this->logger->log([
                    'MESSAGE' => 'SUCCESS',
                    'RESPONSE' => $response
                ]);
                return $response;
            } else {
                $errorMessage = 'Call to Bread Auth API failed';

                throw new \Magento\Framework\Exception\LocalizedException(
                        __($errorMessage)
                );
            }
        } catch (\Throwable $e) {
            $this->logger->log([
                'MESSAGE' => $e->getMessage(),
                'DATA' => $data
            ]);

            curl_close($curl);
            throw $e;
        }
    }

    /**
     * Check a string to verify JSON format is valid
     *
     * @param  $string
     * @return bool
     */
    protected function isJson($string)
    {
        try {
            $this->jsonHelper->jsonDecode($string);
        } catch (\Throwable $e) {
            $this->logger->log($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Returns current store ID
     *
     * @return int
     */
    protected function getStoreId()
    {
        try {
            $isInAdmin = ($this->context->getAppState()->getAreaCode() == \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE);
        } catch (\Throwable $e) {
            $isInAdmin = false;
        }

        if ($isInAdmin) {
            $adminStoreId = $this->cache->load('admin_store_id');
            return $adminStoreId ? $adminStoreId : $this->storeResolver->getCurrentStoreId();
        }

        if (!isset($this->order)) {
            return $this->storeResolver->getCurrentStoreId();
        }
        return $this->order->getData('store_id');
    }
    
    /**
     * 
     * @param type $transactionId
     * @param type $action
     */
    protected function getUpdateTransactionUrlPlatform($transactionId, $action) {
        $baseUrl = $this->helper->getTransactionApiUrl('bread_2', $this->getStoreId());
        $url = join('/', [trim($baseUrl, '/'), 'transaction', $transactionId, $action]);
        return $url;
    }
}
