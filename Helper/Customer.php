<?php
/**
 * Helps Integration With Customer
 *
 * @copyright Bread   copyright   2016
 * @author    Joel    @Mediotype
 * @author    Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Helper;

class Customer extends Data
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $customerSession;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    public $customerFactory;

    /**
     * @var \Magento\Customer\Model\AddressFactory
     */
    public $customerAddressFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    public $jsonHelper;

    /**
     * @var \Magento\Framework\Math\Random
     */
    public $random;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    public $_transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    public $inlineTranslation;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    public $regionFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    public $customerRepository;

    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    public $addressRepository;

    /**
     * @var Log
     */
    public $logger;

    public function __construct(
        \Magento\Framework\App\Helper\Context $helperContext,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\UrlInterfaceFactory $urlInterfaceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $customerAddressFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Math\Random $random,
        \Magento\Framework\Mail\Template\TransportBuilder $_transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Bread\BreadCheckout\Helper\Log $logger  
    ) {
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customerFactory = $customerFactory;
        $this->customerAddressFactory = $customerAddressFactory;
        $this->jsonHelper = $jsonHelper;
        $this->random = $random;
        $this->_transportBuilder = $_transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->regionFactory = $regionFactory;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->logger = $logger;
        parent::__construct($helperContext, $context, $request, $encryptor, $urlInterfaceFactory, $storeManager);
    }
    /**
     * Pass Back Bread Formatted Default Customer Address If It Exists
     *
     * @return array
     */
    public function getFormattedDefaultShippingAddress()
    {
        $session                    = $this->customerSession;
        $customer                   = $session->getCustomer();

        if (empty($customer)) {
            return [];
        }

        $defaultShippingAddress     = $customer->getPrimaryShippingAddress();

        if (!$defaultShippingAddress || !$defaultShippingAddress->getStreetLine(1)) {
            return [];
        }

        $primaryData        = [
            'fullName'      => trim($defaultShippingAddress->getName()),
            'address'       => $defaultShippingAddress->getStreetLine(1) .
                ($defaultShippingAddress->getStreetLine(2) == '' ?
                    '' : (' ' . $defaultShippingAddress->getStreetLine(2))),
            'address2'      => $defaultShippingAddress->getStreetLine(3) .
                ($defaultShippingAddress->getStreetLine(4) == '' ?
                    '' : (' ' . $defaultShippingAddress->getStreetLine(4))),
            'city'          => $defaultShippingAddress->getCity(),
            'state'         => $defaultShippingAddress->getRegionCode(),
            'zip'           => $defaultShippingAddress->getPostcode(),
            'phone'         => is_null($defaultShippingAddress->getTelephone()) ? "": substr(preg_replace('/[^0-9]+/', '', $defaultShippingAddress->getTelephone()), -10)
        ];

        return $primaryData;
    }

    /**
     * Pass Back Bread Formatted Default Customer Address If It Exists
     *
     * @return array
     */
    public function getFormattedDefaultBillingAddress()
    {
        $session                    = $this->customerSession;
        $customer                   = $session->getCustomer();

        if (empty($customer)) {
            return [];
        }

        $defaultBillingAddress     = $customer->getPrimaryBillingAddress();
        if (!$defaultBillingAddress) {
            return [];
        }

        $primaryData        = [
            'fullName'      => $defaultBillingAddress->getName(),
            'address'       => $defaultBillingAddress->getStreetLine(1) .
                ($defaultBillingAddress->getStreetLine(2) == '' ?
                    '' : (' ' . $defaultBillingAddress->getStreetLine(2))),
            'address2'      => $defaultBillingAddress->getStreetLine(3) .
                ($defaultBillingAddress->getStreetLine(4) == '' ?
                    '' : (' ' . $defaultBillingAddress->getStreetLine(4))),
            'city'          => $defaultBillingAddress->getCity(),
            'state'         => $defaultBillingAddress->getRegionCode(),
            'zip'           => $defaultBillingAddress->getPostcode(),
            'email'         => $customer->getEmail(),
            'phone'         => is_null($defaultBillingAddress->getTelephone()) ? "" : substr(preg_replace('/[^0-9]+/', '', $defaultBillingAddress->getTelephone()), -10)
        ];

        return $primaryData;
    }

    /**
     * Create Customer Called From Order Place Process
     *
     * @param  $quote
     * @param  $billingContact
     * @param  $shippingContact
     * @param  $createCartsOrder
     * @return \Magento\Customer\Model\Customer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createCustomer($quote, $billingContact, $shippingContact, $createCartsOrder = false)
    {
        $session    = $this->customerSession;
        if ($session->isLoggedIn() && $createCartsOrder === false) {
            return $session->getCustomer();
        }

        $quote->setCustomerLastname($billingContact['lastname']);
        $quote->setCustomerFirstname($billingContact['firstname']);

        /**
         * @var \Magento\Customer\Model\CustomerFactory
         */
        $customer   = $this->customerFactory->create();
        if ($this->isAutoCreateCustomerAccountEnabled() == false) {
            return $customer;
        }

        $email      = $quote->getCustomerEmail();

        $customer->setWebsiteId($this->storeManager->getWebsite()->getId());

        // Don't create a new account if one already exists for this email
        $customer->loadByEmail($email);
        if ($customer->getId()) {
            // Associate the existing customer with the quote so the resulting order is
            // linked to the correct account. Do NOT authenticate the session here.
            $quote->setCustomerId($customer->getId())
                ->setCustomerIsGuest(false);
            return $customer;
        }

        $billingAddress     = $this->customerAddressFactory->create();
        $billingAddress->setData($billingContact);
        $shippingAddress    = $this->customerAddressFactory->create();
        $shippingAddress->setData($shippingContact);

        $customer->setEmail($email)->setPassword($this->generatePassword(7));
        $customer->setWebsiteId($this->storeManager->getWebsite()->getId());
        $customer->setLastname($quote->getCustomerLastname())
            ->setFirstname($quote->getCustomerFirstname());

        try {
            $customerInterface = $this->customerRepository->save($customer->getDataModel());

            $customer->setConfirmation(null);
            $quote->setCustomerId($customerInterface->getId());

            $billingAddressDataObject = $billingAddress->getDataModel();
            $billingAddressDataObject->setIsDefaultBilling(true);
            if ($billingContact == $shippingContact) {
                $billingAddressDataObject->setIsDefaultShipping(true);
            }
            $billingAddressDataObject->setCustomerId($customerInterface->getId());

            $this->addressRepository->save($billingAddressDataObject);

            if ($billingContact !== $shippingContact) {

                $shippingAddressDataObject = $shippingAddress->getDataModel();
                $shippingAddressDataObject->setIsDefaultShipping(true);
                $shippingAddressDataObject->setCustomerId($customerInterface->getId());

                $this->addressRepository->save($shippingAddressDataObject);

            }

            $customer->sendNewAccountEmail('registered', '', $customerInterface->getStoreId());

            if ($createCartsOrder) {
                $this->customerSession->setCustomer($customer);
            }

        } catch (\Throwable $e) {
            $this->logger->log(['MESSAGE' => $e->getMessage(), 'TRACE' => $e->getTraceAsString()]);
        }

        return $customer;
    }

     /**
      * Get Default Customer Shipping Address If It Exists
      *
      * @return string
      */
    public function getShippingAddressData()
    {
        if ($this->isUserLoggedIn() == false || $this->hasShippingAddress() == false) {
            return 'false';
        }

        $primaryAddressData     = $this->getFormattedDefaultShippingAddress();
        return $this->jsonHelper->jsonEncode($primaryAddressData);
    }

    /**
     * Get Billing Address Default Data
     *
     * @return string
     */
    public function getBillingAddressData()
    {
        if ($this->isUserLoggedIn() == false || $this->hasBillingAddress() == false) {
            return 'false';
        }

        $primaryAddressData     = $this->getFormattedDefaultBillingAddress();
        return $this->jsonHelper->jsonEncode($primaryAddressData);
    }

    /**
     * Check if Customer has billing address
     *
     * @return bool
     */
    public function hasBillingAddress()
    {
        if ($this->customerSession->getCustomer()->getPrimaryBillingAddress() == false) {
            return false;
        }

        return true;
    }

    /**
     * Check if Customer has shipping address
     *
     * @return bool
     */
    public function hasShippingAddress()
    {
        if ($this->customerSession->getCustomer()->getPrimaryShippingAddress() == false) {
            return false;
        }

        return true;
    }

     /**
      * Check if current visitor is logged in
      *
      * @return bool
      */
    public function isUserLoggedIn()
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * Generate random password during automatic customer account creation
     *
     * @param  $length int
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function generatePassword($length)
    {
        return $this->encryptor->getHash($this->random->getRandomString($length), true);
    }

    /**
     * Send activation link to customer
     *
     * @param  \Magento\Customer\Model\Customer customer
     * @param  url
     * @param  Array items
     * @ignore
     */
    public function sendCartActivationEmailToCustomer($customer, $url, $items)
    {
        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
            'store' => $this->storeManager->getStore()->getId()
        ];

        $templateVars = [
            'subject' => 'Complete your checkout at ' . $this->storeManager->getStore()->getName() . ' with financing',
            'url' => $url,
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'items' => $items,
        ];

        $from = [
            'email' => $this->scopeConfig->getValue(
                'trans_email/ident_general/email',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'name'  => $this->scopeConfig->getValue(
                'trans_email/ident_general/name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        ];

        $this->inlineTranslation->suspend();

        $to = [$customer->getEmail()];

        $transport = $this->_transportBuilder->setTemplateIdentifier('payment_confirmation_template')
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFrom($from)
            ->addTo($to)
            ->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();
    }

    /**
     * Send error report to merchant
     *
     * @param Exception exception
     * @param response
     * @param quoteId
     * @param customer
     * @param transactionId
     */
    public function sendCustomerErrorReportToMerchant($exception, $response = "", $quoteId = "", $transactionId = null)
    {
        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
            'store' => $this->storeManager->getStore()->getId()
        ];

        $from = [
            'name' => $this->scopeConfig->getValue(
                'trans_email/ident_general/name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'email' => $this->scopeConfig->getValue(
                'trans_email/ident_general/email',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        ];

        $templateVars = [
            'exception_message' => $exception->getMessage(),
            'quote' => $quoteId,
            'token' => $transactionId,
            'response' => $response
        ];

        $subject = __('Error report');

        $emailData['subject'] = $subject;

        $this->inlineTranslation->suspend();

        $recipients = $this->scopeConfig->getValue(
            'sales_email/order/copy_to',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($recipients) {
            $transport = $this->_transportBuilder->setTemplateIdentifier('error_report_template')
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars($templateVars)
                ->setFrom($from)
                ->addTo(explode(',', $recipients))
                ->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        }
    }

    /**
     * Format Address Data
     *
     * @param  array $contactData
     * @return array
     */
    public function processAddress($contactData)
    {
        $regionId   = null;
        if (isset($contactData['state'])) {
            /**
             * @var \Magento\Directory\Model\RegionFactory
             */
            $region = $this->regionFactory->create();
            $region->loadByCode($contactData['state'], $this->getDefaultCountry());
            if ($region->getId()) {
                $regionId   = $region->getId();
            }
        }

        $fullName       = isset($contactData['fullName']) ? preg_split("/\s+/", trim($contactData['fullName']), 2) : '';
        $addressData    = [
            'firstname'     => isset($contactData['firstName']) ? $contactData['firstName'] : $fullName[0],
            'lastname'      => isset($contactData['lastName']) ?
                $contactData['lastName'] : (isset($fullName[1]) ? $fullName[1] : ''),
            'street'        => $contactData['address'] . (isset($contactData['address2']) ?
                    (' ' .  $contactData['address2']) : ''),
            'city'          => $contactData['city'],
            'postcode'      => $contactData['zip'],
            'telephone'     => $contactData['phone'],
            'country_id'    => $this->getDefaultCountry()
        ];

        if (null !== $regionId) {
            $addressData['region']      = $contactData['state'];
            $addressData['region_id']   = $regionId;
        }

        if (isset($contactData['email'])) {
            $addressData['email']   = $contactData['email'];
        }

        return $addressData;
    }
    
    public function processPlatformAddress($contactData) {
        $regionId = null;
        if (isset($contactData['region'])) {
            /**
             * @var \Magento\Directory\Model\RegionFactory
             */
            $region = $this->regionFactory->create();
            $region->loadByCode($contactData['region'], $this->getDefaultCountry());
            if ($region->getId()) {
                $regionId = $region->getId();
            }
        }

        $addressData = [
            'firstname' => isset($contactData['name']['givenName']) ? $contactData['name']['givenName'] : '',
            'lastname' => isset($contactData['name']['familyName']) ? $contactData['name']['familyName'] : '',
            'street' => $contactData['address']['address1'] . (isset($contactData['address']['address2']) ?
            (' ' . $contactData['address']['address2']) : ''),
            'city' => $contactData['address']['locality'],
            'postcode' => $contactData['address']['postalCode'],
            'telephone' => $contactData['phone'],
            'country_id' => $this->getDefaultCountry()
        ];

        if (null !== $regionId) {
            $addressData['region'] = $contactData['address']['region'];
            $addressData['region_id'] = $regionId;
        }

        if (isset($contactData['email'])) {
            $addressData['email'] = $contactData['email'];
        }

        return $addressData;
    }

}
