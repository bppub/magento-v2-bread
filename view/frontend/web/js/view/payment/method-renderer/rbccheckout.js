define(
    [
        'Magento_Checkout/js/view/payment/default',
        'ko',
        'jquery',
        'buttonConfig',
        'buttonConfigPlatform',
        'splitPay',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/create-billing-address',
        'Magento_Checkout/js/action/select-billing-address',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/set-billing-address',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/shipping-save-processor/default',
        'Magento_Checkout/js/model/shipping-service',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote'
    ],
    function(
        Component,
        ko,
        $,
        button,
        buttonPlatform,
        splitPay,
        customer,
        createBillingAddress,
        selectBillingAddress,
        checkoutData,
        setBillingAddressAction,
        errorProcessor,
        defaultProcessor,
        shippingService,
        fullScreenLoader,
        additionalValidators,
        quote
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Bread_BreadCheckout/payment/rbccheckout'
            },

            breadTransactionId: ko.observable(window.checkoutConfig.payment.breadcheckout.transactionId),

            initialize: function() {
                this._super();
                return this;
            },

            /**
             * Payment code
             */
            getCode: function() {
                return 'rbccheckout';
            },

            /**
             * Get if default button size enabled from config
             */
            getDefaultSize: function() {
                return window.checkoutConfig.payment[this.getCode()].defaultSize;
            },

            /**
             * Transaction ID from Ui\ConfigProvider
             */
            getBreadTransactionId: function() {
                return window.checkoutConfig.payment[this.getCode()].transactionId;
            },

            setBreadTransactionId: function(transactionId) {
                this.breadTransactionId(transactionId);
                window.checkoutConfig.payment[this.getCode()].transactionId = transactionId;
            },

            /**
             * Payment Method tooltip from Ui\Configprovider
             */
            getMethodTooltip: function() {
                return window.checkoutConfig.payment[this.getCode()].breadConfig.methodTooltip;
            },

            getTitle: function() {
                if (window.checkoutConfig.payment[this.getCode()].apiVersion === 'bread_2') {
                    return window.checkoutConfig.payment[this.getCode()].breadConfig.methodTitle;
                } else {
                    var INSTALLMENTS_BLUE = '#5156ea';
                    var SPLITPAY_GREEN = '#57c594';
                    var label = jQuery('#rbccheckout').next('label').attr("for", "rbccheckout");
                    label.text('');
                    if (window.checkoutConfig.payment[this.getCode()].breadConfig.showSplitpayLabel) {
                        label.append('Pay Over Time with ' +
                            '<span style="color: ' + INSTALLMENTS_BLUE + '; font-weight: 600;">Installments</span> or ' +
                            '<span style="color: ' + SPLITPAY_GREEN + '; font-weight: 600;">SplitPay</span>');
                    } else {
                        label.append('Pay Over Time with ' +
                            '<span style="color: ' + INSTALLMENTS_BLUE + '; font-weight: 600;">Installments</span>');
                    }
                    return window.checkoutConfig.payment[this.getCode()].breadConfig.methodTitle;
                }
            },

            /**
             * Embedded checkout enabled
             */
            isEmbeddedCheckout: function() {
                return window.checkoutConfig.payment[this.getCode()].breadConfig.embeddedCheckout;
            },

            /**
             * Invalid product type message
             */
            getCheckoutMessage: function() {
                return window.checkoutConfig.payment[this.getCode()].breadConfig.productTypeMessage;
            },

            /**
             * Validate product types result
             */
            isCartValid: function() {
                return window.checkoutConfig.payment[this.getCode()].breadConfig.cartValidation;
            },

            /**
             * Initialize the bread checkout button
             */
            initComplete: function() {
                var data = window.checkoutConfig.payment[this.getCode()].breadConfig;
                if (window.checkoutConfig.payment[this.getCode()].apiVersion === 'bread_2') {
                    if (typeof window.BreadPayments !== 'undefined' || typeof window.RBCPayPlan !== 'undefined') {
                        //Load platform bread config 
                        buttonPlatform.configure(data, this);
                        if (data.embeddedCheckout && checkoutData.getSelectedPaymentMethod() === 'rbccheckout') {
                            buttonPlatform.setCouponDiscounts();
                            buttonPlatform.initEmbedded();
                        }
                    }
                    return true;
                } else {
                    if (typeof bread !== 'undefined') {
                        button.configure(data, this);

                        if (data.embeddedCheckout) {
                            button.embeddedCheckout();
                        }
                    }
                    return true;
                }


            },

            /**
             * Validate that Bread authorized amount matches
             * total in Magento quote, then call place order
             *
             * @return {boolean}
             */
            placeOrder: function(data, event) {
                this.data = data;
                this.event = event;

                if (additionalValidators.validate()) {

                    if (!this.breadTransactionId()) {
                        if (window.checkoutConfig.payment[this.getCode()].apiVersion === 'bread_2') {
                            buttonPlatform.setCouponDiscounts();
                            buttonPlatform.init();
                        } else {
                            button.setCouponDiscounts();
                            button.init();
                        }
                        return false;
                    }
                } else {
                    var errorInfo = {
                        bread_config: window.checkoutConfig.payment[this.getCode()].breadConfig,
                    };
                    document.logBreadIssue('error', errorInfo, 'Unable to properly validate order');
                }
            },

            buttonCallback: function(token) {
                this.setBreadTransactionId(token);
                var paymentUrl = window.checkoutConfig.payment[this.getCode()].breadConfig.paymentUrl;
                var breadConfig = window.checkoutConfig.payment[this.getCode()].breadConfig;

                $.ajax({
                    url: paymentUrl,
                    data: { token: token, currency: breadConfig.currencyCode, form_key: window.checkoutConfig && window.checkoutConfig.formKey},
                    type: 'post',
                    context: this,
                    beforeSend: function() {
                        fullScreenLoader.startLoader();
                    }
                }).done(
                    function(response) {
                        var errorInfo;
                        try {
                            errorInfo = {
                                bread_config: breadConfig,
                                response: response,
                                tx_id: token
                            };
                            if (response !== null && typeof response === 'object') {
                                if (response.error) {
                                    document.logBreadIssue('error', errorInfo, 'Error validating payment method');
                                    alert(response.error);
                                } else {
                                    $.when(
                                        this.updateAddress(response, errorInfo),
                                        this.validateTotals()
                                    ).done(
                                        $.proxy(
                                            function() {
                                                // Resets id in case placeOrder call below fails, so user can retry placing it.
                                                // If it succeeds we redirect so setting to null doesn't matter.
                                                // No better way to track errors coming from placeOrder unfortunately, so just have to do this
                                                this.setBreadTransactionId(null);

                                                return Component.prototype.placeOrder.call(this, this.data, this.event);
                                            }, this
                                        )
                                    ).fail(
                                        $.proxy(
                                            function(error) {
                                                errorProcessor.process(error, this.messageContainer);

                                                errorInfo = {
                                                    bread_config: breadConfig,
                                                    error: error,
                                                    tx_id: token,
                                                };
                                                document.logBreadIssue('error', errorInfo, 'Error updating address or validating totals');

                                                this.setBreadTransactionId(null)
                                            }, this
                                        )
                                    );
                                }
                                fullScreenLoader.stopLoader();
                            } else {
                                document.logBreadIssue('error', errorInfo, 'Response from ' + paymentUrl + ' was not of type Object');
                            }
                        } catch (e) {
                            errorInfo = {
                                response: response,
                                tx_id: token,
                                bread_config: breadConfig,
                            };
                            document.logBreadIssue('error', errorInfo, e);
                        }
                    }
                ).fail(
                    function(error) {
                        var errorInfo = {
                            bread_config: breadConfig,
                            tx_id: token,
                        };
                        document.logBreadIssue(
                            'error', errorInfo,
                            'Error code returned when calling ' + paymentUrl + ', with status: ' + error.statusText
                        );
                    }
                );
            },

            /**
             * Save billing address data in quote
             *
             * @param data {object}
             * @param token {string}
             *
             * @return {jQuery.Deferred}
             */
            updateAddress: function(data, errorInfo) {
                var self = this;

                // Bypass update address if using Mageplaza OSC
                if (self.isOscMagePlaza()) {
                    return;
                }

                /**
                 * Billing address
                 */
                document.logBreadIssue('info', errorInfo, 'starting update address');

                var billingAddressData = this.getAddressData(data.billingAddress);
                document.logBreadIssue('info', $.extend(true, {}, errorInfo, { billingAddressData: billingAddressData }), 'got billing address data');
                var newBillingAddress = createBillingAddress(billingAddressData);
                document.logBreadIssue('info', $.extend(true, {}, errorInfo, { newBillingAddress: newBillingAddress }), 'got new billing address');

                // New address must be selected as a billing address
                selectBillingAddress(newBillingAddress);
                document.logBreadIssue('info', errorInfo, 'selected new billing address');
                checkoutData.setSelectedBillingAddress(newBillingAddress.getKey());
                document.logBreadIssue('info', errorInfo, 'set selected billing address');
                checkoutData.setNewCustomerBillingAddress(billingAddressData);
                document.logBreadIssue('info', errorInfo, 'set new customer billing address');

                /**
                 * Reload checkout section & add bread token
                 */
                if (quote.isVirtual()) {
                    return defaultProcessor;
                }
                var defaultProcessorSavedShipping = defaultProcessor.saveShippingInformation();
                document.logBreadIssue('info', $.extend(true, {}, errorInfo, { defaultProcessorSavedShipping: defaultProcessorSavedShipping }), 'default processor saved shipping info');
                return defaultProcessorSavedShipping;
            },

            isOscMagePlaza: function() {
                return window.checkoutConfig && window.checkoutConfig.oscConfig;
            },

            /**
             * Validate order totals
             *
             * @return {jQuery.Deferred}
             */
            validateTotals: function() {
                var deferred = $.Deferred();
                var validateTotalsUrl = window.checkoutConfig.payment[this.getCode()].validateTotalsUrl;
                var breadConfig = window.checkoutConfig.payment[this.getCode()].breadConfig;
                var tx_id = this.breadTransactionId();

                $.ajax({
                    url: validateTotalsUrl,
                    data: { bread_transaction_id: tx_id, form_key: window.checkoutConfig && window.checkoutConfig.formKey },
                    type: 'post',
                    context: this
                }).done(
                    function(response) {
                        if (response.valid) {
                            deferred.resolve(response);
                        } else {
                            deferred.reject(response);
                        }
                    }
                ).fail(
                    function(error) {
                        var errorInfo = {
                            bread_config: breadConfig,
                            tx_id: tx_id,
                        };
                        document.logBreadIssue(
                            'error', errorInfo,
                            'Error code returned when calling ' + validateTotalsUrl + ', with status: ' + error.statusText
                        );
                    }
                );

                return deferred;
            },

            /**
             * Format street and set whether to save in address book
             *
             * @param  address {object}
             * @return {object}
             */
            getAddressData: function(address) {
                if (typeof address.street == 'string') {
                    address.street = {
                        0: address.street,
                        1: ""
                    };
                }

                address.save_in_address_book = ((customer.isLoggedIn() &&
                        customer.getBillingAddressList().length < 1)) ?
                    1 : 0;
                return address;
            }

        });
    }
);