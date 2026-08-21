<?php

namespace Bread\BreadCheckout\Controller\Adminhtml\Bread;

/**
 * Class ValidateCredentials
 *
 * @package Bread\BreadCheckout\Controller\Adminhtml
 */
class ValidateCredentials extends \Magento\Backend\App\Action {

    const API_SANDBOX_URI = 'https://api-sandbox.getbread.com/carts/';
    const API_LIVE_URI = 'https://api.getbread.com/carts/';

    /**
     * @var \Bread\BreadCheckout\Helper\Log
     */
    public $logger;

    /**
     * @since 2.1.0
     * @var \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     */
    public $configWriter;
    
    /**
     *@since 2.1.0
     * @var \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public $jsonHelper;

    /**
     *@since 2.1.0
     * @var \Bread\BreadCheckout\Helper\Data $dataHelper
     */
    public $dataHelper;

    /**
     * ValidateCredentials constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Bread\BreadCheckout\Helper\Log $log
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param \Bread\BreadCheckout\Helper\Data $dataHelper
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper 
     */
    public function __construct(
            \Magento\Backend\App\Action\Context $context,
            \Bread\BreadCheckout\Helper\Log $log,
            \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
            \Bread\BreadCheckout\Helper\Data $dataHelper,
            \Magento\Framework\Json\Helper\Data $jsonHelper  
    ) {
        $this->logger = $log;
        $this->configWriter = $configWriter;
        $this->jsonHelper = $jsonHelper;
        $this->dataHelper = $dataHelper;
        parent::__construct($context);
    }

    public function execute() {

        $params = $this->getRequest()->getParams();
        $apiMode = isset($params['apiMode']) ? $params['apiMode'] : null;
        $pubKey = isset($params['pubKey']) ? $params['pubKey'] : null;
        $secKey = isset($params['secKey']) ? $params['secKey'] : null;
        $apiVersion = isset($params['apiVersion']) ? $params['apiVersion'] : null;
        $tenant = isset($params['tenant']) ? $params['tenant'] : null;
        
        $result = $this->test($apiMode, $pubKey, $secKey, $apiVersion, $tenant);

        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)->setData($result);
    }

    private function test($apiMode, $username, $password, $apiVersion, $tenant) {
        if($apiVersion === 'bread_2') {
            return $this->testPlatformCredentials($username, $password, $apiMode, $tenant);
        }
    }
    
     
    /**
     * Test Bread platform
     * 
     * @since 2.1.0
     * @param string $apiKey
     * @param string $apiSecret
     * @return bool 
     */
    public function testPlatformCredentials($key, $secret, $apiMode, $tenant) {
        $tenant = strtoupper($tenant);
        try {
            $data = array(
                'apiKey' => "$key",
                'secret' => "$secret"
            );
            $env = $apiMode === '1' ? 'LIVE' : 'SANDBOX';
            $link = $this->dataHelper->getPlatformApiUri($tenant, $env);

            $tenantLoaded = false;
            $response = null;

            $url = join('/', [trim($link, '/'), 'auth/service/authorize']);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt(
                    $curl,
                    CURLOPT_HTTPHEADER,
                    [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($this->jsonHelper->jsonEncode($data))
                    ]
            );
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($status != 200) {
                $this->logger->log(
                        [
                            'STATUS' => 'KEY/SECRET VALIDATION FAIL',
                            'RESULT' => 'Key validation failed'
                        ]
                );
                $this->configWriter->save('payment/breadcheckout/tenant', $tenant, 'default');
                return false;
            } else {
                $response = (array) $this->jsonHelper->jsonDecode($result);
                
                if (isset($response['token'])) {
                    $tenantLoaded = true;
                    $this->dataHelper->saveAuthToken($response['token']);
                    $this->configWriter->save('payment/breadcheckout/tenant', $tenant, 'default');
                    return true;
                }
            }
            // Case for all api urls calls returning status != 200 or token is not set in response
            $this->dataHelper->saveAuthToken("0");
            $this->configWriter->save('payment/breadcheckout/tenant', $tenant, 'default');
            return false;
        } catch (Exception $ex) {
            $this->configWriter->save('payment/breadcheckout/tenant', $tenant, 'default');
            $this->logger->log(
                    [
                        'STATUS' => 'BACKEND API KEYS TEST',
                        'RESULT' => $ex->getMessage()
                    ]
            );
            return false;
        }
    }
}
