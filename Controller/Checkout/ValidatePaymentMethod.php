<?php
/**
 * Add Token To Session Once Approved & Update Quote
 *
 * @author Bread   copyright 2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class ValidatePaymentMethod extends \Bread\BreadCheckout\Controller\Checkout implements CsrfAwareActionInterface
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
     * @var \Magento\Framework\Controller\ResultFactory
     */
    public $resultFactory;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    public $regionFactory;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $dataHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Bread\BreadCheckout\Model\Payment\Api\Client $paymentApiClient,
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
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Bread\BreadCheckout\Helper\Data $dataHelper
    ) {
    
        $this->paymentApiClient = $paymentApiClient;
        $this->checkoutSession = $checkoutSession;
        $this->resultFactory = $context->getResultFactory();
        $this->regionFactory = $regionFactory;
        $this->dataHelper = $dataHelper;
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
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Add bread transaction ID to session and update
     * address data in quote
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = null;
        try {
            $token = $this->getRequest()->getParam('token');
            $currencyCode = $this->getRequest()->getParam('currency', 'USD');
            $this->dataHelper->setConfigClientByCurrency($currencyCode);
            $newData = [];

            if ($token) {
                $data = $this->paymentApiClient->getInfo($token);
                $apiVersion = $this->helper->getApiVersion();
                if($apiVersion === 'bread_2') {
                    $this->logger->log([
                        'Validate Payment',
                        'DATA' => $data
                    ]);
                    if(isset($data['id'])) {
                        $this->checkoutSession->setBreadTransactionId($token);
                        $newData = $this->updateQuotePlatform($data);
                    }
                      
                    if(isset($data['adjustedAmount'])) {
                        $this->helper->setBreadTransactionAmount($data['adjustedAmount']['value']);
                    }
                } else {
                    if ($data['breadTransactionId']) {
                        $this->checkoutSession->setBreadTransactionId($token);
                        $newData = $this->updateQuote($token);
                    }
                    if ($data['adjustedTotal']) {
                        $this->helper->setBreadTransactionAmount($data['adjustedTotal']);
                    }
                }
                
            }

            $result = $newData;
        } catch (\Throwable $e) {
            $this->logger->log(['MESSAGE' => $e->getMessage(), 'TRACE' => $e->getTraceAsString()]);
            $result = ['error' => (string) __('Error: Unable to process transaction.')];
        }

        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)->setData($result);
    }

    /**
     * Update quote with billing address from Bread checkout popup
     *
     * @param  string $token
     * @return array
     */
    protected function updateQuote($token)
    {
        $data = $this->paymentApiClient->getInfo($token);
        $billingData = $this->getFormattedAddress($data['billingContact']);

        $quote = $this->checkoutSession->getQuote();
        $quote->getBillingAddress()->addData($billingData);

        return ['billingAddress' => $quote->getBillingAddress()->getData()];
    }
    
    /**
     * @since 2.0.2
     * @param array $data
     * @return array
     */
    protected function updateQuotePlatform($data) {
        $billingData = $this->getFormattedAddress($data['billingContact']);

        $quote = $this->checkoutSession->getQuote();
        $quote->getBillingAddress()->addData($billingData);

        return ['billingAddress' => $quote->getBillingAddress()->getData()];
    }

    /**
     * Get address in correct format to add to Address object
     *
     * @param  array $data
     * @return array
     */
    protected function getFormattedAddress(array $data)
    {
        $breadVersion = $this->helper->getApiVersion();
        if ($breadVersion === 'bread_2') {
            $merchantCountry = $this->helper->getMerchantCountry();
            $regionId = $this->regionFactory->create()->loadByCode($data['address']['region'], $merchantCountry)->getId();
            return [
                'firstname' => $data['name']['givenName'],
                'lastname' => $data['name']['familyName'],
                'street' => $data['address']['address1'],
                'city' => $data['address']['locality'],
                'country_id' => $merchantCountry,
                'region' => $data['address']['region'],
                'region_id' => $regionId,
                'postcode' => $data['address']['postalCode'],
                'telephone' => $data['phone'],
                'save_in_address_book' => 1
            ];
            
        } else {
            $name = explode(' ', trim($data['fullName']));
            $regionId = $this->regionFactory->create()->loadByCode($data['state'], 'US')->getId();

            return [
                'firstname' => $name[0],
                'lastname' => $name[1],
                'street' => $data['address'],
                'city' => $data['city'],
                'country_id' => 'US',
                'region' => $data['state'],
                'region_id' => $regionId,
                'postcode' => $data['zip'],
                'telephone' => $data['phone'],
                'save_in_address_book' => 1
            ];
        }
    }
}
