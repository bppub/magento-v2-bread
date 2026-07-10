<?php
/**
 * Save bread API version for transactions that belong to Bread payment
 * method
 * 
 * @since 2.3.0
 * @author Maritim, Kip
 */
namespace Bread\BreadCheckout\Observer;

use Bread\BreadCheckout\Helper\Log;
use Bread\BreadCheckout\Helper\Quote as Helper;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;

class SaveBreadApiVersionObserver implements ObserverInterface
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var State
     */
    protected $_state;

    /**
     * @var QuoteRepository
     */
    protected $_quoteRepository;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param Log             $logger
     * @param State           $state
     * @param QuoteRepository $quoteRepository
     * @param Helper          $helper
     */
    public function __construct(
        Log $logger,
        State $state,
        QuoteRepository $quoteRepository,
        Helper $helper
    ) {
        $this->logger = $logger;
        $this->_state = $state;
        $this->_quoteRepository = $quoteRepository;
        $this->helper = $helper;
    }

    /**
     * Executes the observer logic for processing payment data during specific events.
     *
     * @param EventObserver $observer The observer object that contains event data such as payment and order information.
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(EventObserver $observer) {

        if ($this->_state->getAreaCode() != \Magento\Framework\App\Area::AREA_ADMINHTML) {
            $paymentOrder = $observer->getEvent()->getPayment();
            $order = $paymentOrder->getOrder();
            if (!$order->getQuoteId()) {
                return;
            }
            $quote = $this->_quoteRepository->get($order->getQuoteId());
            $paymentQuote = $quote->getPayment();
            $method = $paymentQuote->getMethodInstance()->getCode();
            if ($method === \Bread\BreadCheckout\Model\Ui\ConfigProvider::CODE) {
                $paymentOrder->setData('bread_api_version', $this->helper->getApiVersion());
            }
        }
    }
}
