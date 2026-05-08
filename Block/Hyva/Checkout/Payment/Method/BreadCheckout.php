<?php
/**
 * Bread BreadCheckout - Hyva Checkout Payment Method Block
 *
 * Provides data for rendering the Bread payment method in Hyva Checkout.
 */
namespace Bread\BreadCheckout\Block\Hyva\Checkout\Payment\Method;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Bread\BreadCheckout\Helper\Data as BreadHelper;
use Bread\BreadCheckout\Helper\Quote as QuoteHelper;
use Bread\BreadCheckout\Helper\Customer as CustomerHelper;
use Magento\Checkout\Model\Session as CheckoutSession;

class BreadCheckout extends Template
{
    /**
     * @param Context $context
     * @param BreadHelper $breadHelper
     * @param QuoteHelper $quoteHelper
     * @param CustomerHelper $customerHelper
     * @param CheckoutSession $checkoutSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected BreadHelper $breadHelper,
        protected QuoteHelper $quoteHelper,
        protected CustomerHelper $customerHelper,
        protected CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if Bread payment is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->breadHelper->isPaymentMethodAtCheckout();
    }

    /**
     * Get integration key for SDK
     *
     * @return string
     */
    public function getIntegrationKey(): string
    {
        return (string) $this->breadHelper->getIntegrationKey();
    }

    /**
     * Get SDK name (BreadPayments or RBCPayPlan)
     *
     * @return string
     */
    public function getSdkName(): string
    {
        $configClient = $this->breadHelper->getConfigClient();
        return $configClient === 'RBC' ? 'RBCPayPlan' : 'BreadPayments';
    }

    /**
     * Get JS SDK location
     *
     * @return string
     */
    public function getJsLocation(): string
    {
        return (string) $this->breadHelper->getJsLibLocation();
    }

    /**
     * Get currency code
     *
     * @return string
     */
    public function getCurrencyCode(): string
    {
        try {
            return $this->checkoutSession->getQuote()->getQuoteCurrencyCode() ?: 'USD';
        } catch (\Exception $e) {
            return 'USD';
        }
    }

    /**
     * Get payment URL
     *
     * @return string
     */
    public function getPaymentUrl(): string
    {
        return (string) $this->breadHelper->getPaymentUrl();
    }

    /**
     * Get validate totals URL
     *
     * @return string
     */
    public function getValidateTotalsUrl(): string
    {
        return (string) $this->breadHelper->getValidateTotalsUrl();
    }

    /**
     * Get checkout data for SDK
     *
     * @return array
     */
    public function getCheckoutData(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            
            $items = [];
            foreach ($quote->getAllVisibleItems() as $item) {
                $qty = (float) ($item->getQty() ?? 1);
                $price = (float) ($item->getPrice() ?? 0);
                $taxAmount = (float) ($item->getTaxAmount() ?? 0);
                
                $items[] = [
                    'name' => $item->getName(),
                    'quantity' => (int) $qty,
                    'unitPrice' => [
                        'value' => (int) round($price * 100),
                        'currency' => $this->getCurrencyCode()
                    ],
                    'itemUrl' => $item->getProduct()->getProductUrl(),
                    'shippingCost' => [
                        'value' => 0,
                        'currency' => $this->getCurrencyCode()
                    ],
                    'shippingDescription' => '',
                    'unitTax' => [
                        'value' => $qty > 0 ? (int) round(($taxAmount / $qty) * 100) : 0,
                        'currency' => $this->getCurrencyCode()
                    ]
                ];
            }

            $subtotal = (int) round((float) ($quote->getSubtotal() ?? 0) * 100);
            $shippingAmount = (int) round(
                (float) ($quote->getShippingAddress()->getShippingAmount() ?? 0) * 100
            );
            $taxAmount = (int) round(
                (float) ($quote->getShippingAddress()->getTaxAmount() ?? 0) * 100
            );
            $discountAmount = (int) round(
                abs((float) ($quote->getShippingAddress()->getDiscountAmount() ?? 0)) * 100
            );
            $grandTotal = (int) round((float) ($quote->getGrandTotal() ?? 0) * 100);
            
            return [
                'items' => $items,
                'subTotal' => ['value' => $subtotal, 'currency' => $this->getCurrencyCode()],
                'totalShipping' => ['value' => $shippingAmount, 'currency' => $this->getCurrencyCode()],
                'totalTax' => ['value' => $taxAmount, 'currency' => $this->getCurrencyCode()],
                'totalDiscounts' => ['value' => $discountAmount, 'currency' => $this->getCurrencyCode()],
                'totalPrice' => ['value' => $grandTotal, 'currency' => $this->getCurrencyCode()]
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get billing contact data
     *
     * @return array
     */
    public function getBillingContact(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $billingAddress = $quote->getBillingAddress();

            return [
                'firstName' => $billingAddress->getFirstname() ?? '',
                'lastName' => $billingAddress->getLastname() ?? '',
                'email' => $billingAddress->getEmail() ?? $quote->getCustomerEmail() ?? '',
                'phone' => $billingAddress->getTelephone() ?? '',
                'address' => [
                    'address1' => $billingAddress->getStreetLine(1) ?? '',
                    'address2' => $billingAddress->getStreetLine(2) ?? '',
                    'locality' => $billingAddress->getCity() ?? '',
                    'region' => $billingAddress->getRegionCode() ?? '',
                    'postalCode' => $billingAddress->getPostcode() ?? '',
                    'country' => $billingAddress->getCountryId() ?? 'US'
                ]
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get shipping contact data
     *
     * @return array
     */
    public function getShippingContact(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $shippingAddress = $quote->getShippingAddress();

            return [
                'firstName' => $shippingAddress->getFirstname() ?? '',
                'lastName' => $shippingAddress->getLastname() ?? '',
                'email' => $shippingAddress->getEmail() ?? $quote->getCustomerEmail() ?? '',
                'phone' => $shippingAddress->getTelephone() ?? '',
                'address' => [
                    'address1' => $shippingAddress->getStreetLine(1) ?? '',
                    'address2' => $shippingAddress->getStreetLine(2) ?? '',
                    'locality' => $shippingAddress->getCity() ?? '',
                    'region' => $shippingAddress->getRegionCode() ?? '',
                    'postalCode' => $shippingAddress->getPostcode() ?? '',
                    'country' => $shippingAddress->getCountryId() ?? 'US'
                ]
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get existing transaction ID if any
     *
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $payment = $quote->getPayment();
            if ($payment) {
                return $payment->getAdditionalInformation('bread_transaction_id');
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    /**
     * Check if payment is already approved
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return !empty($this->getTransactionId());
    }
}
