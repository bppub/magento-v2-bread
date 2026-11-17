<?php

namespace Bread\BreadCheckout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

/**
 * Class DispatchShipmentInfo
 *
 * @package Bread\BreadCheckout\Observer
 */
class DispatchShipmentInfo implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Bread\BreadCheckout\Model\Payment\Method\Bread
     */
    private $breadPayment;

    /**
     * @var \Bread\BreadCheckout\Helper\Data $helper
     */
    private $helper;

    /**
     * @var \Bread\BreadCheckout\Helper\Log $logger
     */
    private $logger;

    /**
     * @var \Bread\BreadCheckout\Model\Payment\Api\Client $client
     */
    private $client;

    /**
     * @var \Magento\Sales\Model\Order\Payment\TransactionFactory $transactionFactory
     */
    private $transactionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction
     */
    private $transactionResource;

    /**
     * DispatchShipmentInfo constructor.
     *
     * @param \Bread\BreadCheckout\Model\Payment\Method\Bread $breadPayment
     */
    public function __construct(
        \Bread\BreadCheckout\Model\Payment\Method\BreadPaymentMethodFactory $breadPaymentMethodFactory,
        \Bread\BreadCheckout\Model\Payment\Api\Client $client,
        \Bread\BreadCheckout\Helper\Data $helper,
        \Bread\BreadCheckout\Helper\Log $logger,
        \Magento\Sales\Model\Order\Payment\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction $transactionResource
    ) {
        $this->breadPayment = $breadPaymentMethodFactory->create();
        $this->client = $client;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->transactionFactory = $transactionFactory;
        $this->transactionResource = $transactionResource;
    }

    /**
     * Sends api request to bread with shipment data
     *
     * @param  Observer $observer
     * @return $this|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        /**
         * @var \Magento\Sales\Model\Order\Shipment\Track $shipmentTrack
         */
        $shipmentTrack = $observer->getEvent()->getDataObject();
        $order = $shipmentTrack->getShipment()->getOrder();
        $payment = $order->getPayment();

        /**
         * this is to prevent code from triggering in case of programmatic changes done to tracking items later on
         */
        if ($order->getStatus() === Order::STATE_COMPLETE && $order->getState() == Order::STATE_COMPLETE) {
            return $this;
        }

        /**
         * At the time being, only send for first shipment
         */
        if ($order->getShipmentsCollection()->count() > 1) {
            return $this;
        }

        if ($this->helper->dispatchShipmentData() && ($payment->getMethod() === $this->breadPayment->getMethodCode())) {
            $trackingNumber = $shipmentTrack->getNumber();
            $carrierCode = $shipmentTrack->getCarrierCode();

            $transaction = $this->transactionFactory->create();
            $this->transactionResource->load($transaction, $payment->getEntityId(), 'payment_id');

            try {

                $data = $this->client->setShippingDetails(
                    $transaction->getTxnId(),
                    $trackingNumber,
                    $carrierCode
                );
                $this->logger->log(
                    [
                        'DISPATCH SHIPPING DETAILS'      => $data,
                    ]
                );

            } catch (\Throwable $e) {
                $this->logger->log($e->getMessage());
            }
        }
    }
}
