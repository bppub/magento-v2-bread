<?php
/**
 * Get Tax Estimate
 *
 * @author Bread   copyright 2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Controller\Checkout;

class EstimateTax extends \Bread\BreadCheckout\Controller\Checkout
{
    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    public $resultFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    public $logger;

    /**
     * @var \Bread\BreadCheckout\Helper\Data
     */
    public $helper;

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
    
        $this->resultFactory = $context->getResultFactory();
        $this->logger = $logger;
        $this->helper = $helper;
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
     * Get tax amount for quote
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {
            $shippingInfo       = $this->getRequest()->getParam('shippingInfo');
            $data               = is_string($shippingInfo) ? json_decode($shippingInfo, true) : null;
            $shippingAddress    = $this->getShippingAddressForQuote($data);
            if (!$shippingAddress instanceof \Magento\Quote\Model\Quote\Address) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Shipping address is not an instance of Magento\Quote\Model\Quote\Address')
                );
            }

            $result             = round($shippingAddress->getTaxAmount() * 100);
            $response           = ['result' => $result];
        } catch (\Throwable $e) {
            $this->logger->log(['EXCEPTION IN TAX ESTIMATE ACTION' => $e->getMessage()]);
            $response = ['error' => 1,
                         'message'  => 'There was an error calculating the estimated tax'];
        }

        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)->setData($response);
    }
}
