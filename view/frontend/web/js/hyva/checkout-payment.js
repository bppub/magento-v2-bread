(function() {
    'use strict';

    // enable via localStorage: localStorage.setItem('bread_debug', '1')
    var DEBUG = localStorage.getItem('bread_debug') === '1';
    
    function log() {
        if (DEBUG) {
            console.log.apply(console, ['[Bread]'].concat(Array.prototype.slice.call(arguments)));
        }
    }
    
    function logError() {
        // alwas log errors, even in production
        console.error.apply(console, ['[Bread]'].concat(Array.prototype.slice.call(arguments)));
    }

    var BreadCheckoutPayment = {
        config: null,
        isApproved: false,
        transactionId: null,
        initialized: false,
        sdkReady: false,
        sdkWasSetup: false,
        originalPlaceOrder: null,

        init: function() {
            if (this.initialized) return;
            
            var container = document.getElementById('bread-payment-container');
            if (!container) {
                this.watchForContainer();
                return;
            }

            this.initialized = true;
            log('Initializing Bread checkout payment');
            
            var configAttr = container.getAttribute('data-bread-config');
            if (configAttr) {
                this.config = JSON.parse(configAttr);
                this.isApproved = this.config.isApproved || false;
                this.transactionId = this.config.transactionId || null;
                log('Config loaded, isApproved:', this.isApproved, 'transactionId:', this.transactionId);
            }

            this.waitForSdk();
            this.hookIntoHyvaCheckout();
        },

        watchForContainer: function() {
            var self = this;
            var observer = new MutationObserver(function(mutations) {
                var container = document.getElementById('bread-payment-container');
                if (container && !self.initialized) {
                    observer.disconnect();
                    self.init();
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Hook into Hyva Checkout's order.place method
         */
        hookIntoHyvaCheckout: function() {
            var self = this;
            
            // Wait for hyvaCheckout to be available
            var checkHyvaCheckout = function() {
                if (typeof hyvaCheckout !== 'undefined' && hyvaCheckout.order && hyvaCheckout.order.place) {
                    log('Found hyvaCheckout.order.place, hooking into it');
                    log('Original place function:', hyvaCheckout.order.place.toString().substring(0, 100));
                    
                    self.originalPlaceOrder = hyvaCheckout.order.place.bind(hyvaCheckout.order);

                    hyvaCheckout.order.place = function() {
                        log('Hooked place function called!');
                        return self.handlePlaceOrder();
                    };
                    
                    log('Successfully hooked into hyvaCheckout.order.place');
                    
                    // Verify hook is still in place after a delay
                    setTimeout(function() {
                        var isHooked = hyvaCheckout.order.place.toString().indexOf('Hooked place function') !== -1;
                        log('Hook verification after 1s:', isHooked ? 'STILL HOOKED' : 'HOOK OVERWRITTEN!');
                        if (!isHooked) {
                            log('Current place function:', hyvaCheckout.order.place.toString().substring(0, 200));
                        }
                    }, 1000);
                } else {
                    log('hyvaCheckout not ready, waiting...');
                    setTimeout(checkHyvaCheckout, 100);
                }
            };
            
            checkHyvaCheckout();
        },
        handlePlaceOrder: function() {
            var self = this;
            
            log('handlePlaceOrder called');
            log('isBreadSelected:', this.isBreadSelected());
            log('isApproved:', this.isApproved);
            log('transactionId:', this.transactionId);
            
            if (!this.isBreadSelected()) {
                log('Bread not selected, calling original place order');
                return this.originalPlaceOrder();
            }
            
            if (this.isApproved && this.transactionId) {
                log('Already approved, calling original place order');
                return this.originalPlaceOrder();
            }
            
            log('Need Bread approval, opening modal');
            
            return new Promise(function(resolve, reject) {
                self.pendingResolve = resolve;
                self.pendingReject = reject;
                self.openBreadModal();
            });
        },

        isBreadSelected: function() {
            var breadRadio = document.querySelector('input[name="payment-method-option"][value="breadcheckout"]:checked');
            if (breadRadio) {
                log('Found checked breadcheckout radio');
                return true;
            }
            
            var methodList = document.getElementById('payment-method-list');
            if (methodList) {
                var selectedMethod = methodList.getAttribute('data-method');
                if (selectedMethod === 'breadcheckout') {
                    log('Found breadcheckout in data-method');
                    return true;
                }
            }
            
            var container = document.getElementById('bread-payment-container');
            if (container) {
                var paymentView = container.closest('[id^="payment-method-view-"]');
                if (paymentView && paymentView.offsetParent !== null) {
                    log('Found visible bread container');
                    return true;
                }
            }
            
            return false;
        },

        waitForSdk: function() {
            var self = this;
            var sdkName = this.config.sdkName; // 'BreadPayments' or 'RBCPayPlan'
            
            var checkSdk = function() {
                if (window[sdkName]) {
                    self.sdkReady = true;
                    self.setupBreadSdk();
                } else {
                    setTimeout(checkSdk, 100);
                }
            };
            checkSdk();
        },

        setupBreadSdk: function() {
            if (this.sdkWasSetup) {
                log('SDK already set up, skipping');
                return;
            }

            var sdk = window[this.config.sdkName];
            if (!sdk) {
                logError('SDK not found:', this.config.sdkName);
                return;
            }

            var self = this;

            var shippingContact = this.config.shippingContact;
            var shippingAddress = shippingContact && shippingContact.address ? {
                address1: shippingContact.address.address1 || '',
                address2: shippingContact.address.address2 || '',
                country: shippingContact.address.country || 'US',
                locality: shippingContact.address.locality || '',
                region: shippingContact.address.region || '',
                postalCode: shippingContact.address.postalCode || ''
            } : null;

            var setupConfig = {
                integrationKey: this.config.integrationKey
            };

            if (shippingAddress && shippingAddress.address1) {
                setupConfig.buyer = {
                    shippingAddress: shippingAddress
                };
            }

            log('SDK setup config:', setupConfig);
            sdk.setup(setupConfig);
            sdk.setInitMode('manual');
            
            if (sdk.__internal__ && sdk.__internal__.setAutoRender) {
                sdk.__internal__.setAutoRender(false);
            }
            
            sdk.on('INSTALLMENT:APPLICATION_DECISIONED', function(application) {
                log('Application decisioned:', application);
            });
            sdk.on('INSTALLMENT:APPLICATION_CHECKOUT', function(application) {
                log('Application checkout:', application);
                if (application && application.transactionID) {
                    self.handleCheckoutComplete(application.transactionID);
                }
            });
            sdk.on('INSTALLMENT:CUSTOMER_CLOSE', function() {
                log('Customer closed modal');
                self.handleModalClose();
            });

            this.sdkWasSetup = false;
            
            log('SDK setup complete');
        },

        getShippingAddressFromCheckout: function() {
            // Try to get from Hyva checkout state first
            if (typeof hyvaCheckout !== 'undefined' && hyvaCheckout.shipping) {
                var shippingData = hyvaCheckout.shipping.getAddress ? hyvaCheckout.shipping.getAddress() : null;
                if (shippingData && shippingData.street && shippingData.city) {
                    log('Got shipping from hyvaCheckout.shipping:', shippingData);
                    return {
                        firstName: shippingData.firstname || '',
                        lastName: shippingData.lastname || '',
                        email: shippingData.email || '',
                        phone: shippingData.telephone || '',
                        address: {
                            address1: Array.isArray(shippingData.street) ? shippingData.street[0] : (shippingData.street || ''),
                            address2: Array.isArray(shippingData.street) ? (shippingData.street[1] || '') : '',
                            locality: shippingData.city || '',
                            region: shippingData.region_code || shippingData.region || '',
                            postalCode: shippingData.postcode || '',
                            country: shippingData.country_id || 'US'
                        }
                    };
                }
            }

            // Try to get from Alpine.js component data
            var shippingForm = document.querySelector('[x-data*="shipping"]');
            if (shippingForm && shippingForm.__x) {
                var alpineData = shippingForm.__x.$data;
                if (alpineData && alpineData.address) {
                    log('Got shipping from Alpine component:', alpineData.address);
                    var addr = alpineData.address;
                    return {
                        firstName: addr.firstname || '',
                        lastName: addr.lastname || '',
                        email: addr.email || '',
                        phone: addr.telephone || '',
                        address: {
                            address1: Array.isArray(addr.street) ? addr.street[0] : (addr.street || ''),
                            address2: Array.isArray(addr.street) ? (addr.street[1] || '') : '',
                            locality: addr.city || '',
                            region: addr.region_code || addr.region || '',
                            postalCode: addr.postcode || '',
                            country: addr.country_id || 'US'
                        }
                    };
                }
            }
            return this.config.shippingContact || null;
        },
        validateShippingAddress: function(shippingContact) {
            if (!shippingContact || !shippingContact.address) {
                return false;
            }
            var addr = shippingContact.address;
            return addr.address1 && addr.locality && addr.region && addr.postalCode && addr.country;
        },

        openBreadModal: function() {
            log('openBreadModal called');
            log('sdkReady:', this.sdkReady);
            
            if (!this.sdkReady) {
                log('SDK not ready, waiting...');
                var self = this;
                setTimeout(function() { self.openBreadModal(); }, 100);
                return;
            }

            var sdk = window[this.config.sdkName];
            log('SDK object:', sdk ? 'found' : 'NOT FOUND');
            
            var self = this;

            var shippingContact = this.getShippingAddressFromCheckout();
            var billingContact = this.config.billingContact;

            log('Opening checkout modal');
            log('Shipping contact:', shippingContact);
            log('Billing contact:', billingContact);
            log('Checkout data:', this.config.checkoutData);

            if (!this.validateShippingAddress(shippingContact)) {
                logError('Invalid shipping address:', shippingContact);
                alert('Please complete your shipping address before proceeding with Bread checkout.');
                if (this.pendingReject) {
                    this.pendingReject('Shipping address required');
                }
                return;
            }

            var checkoutData = this.config.checkoutData || {};
            var items = checkoutData.items || [];
            var subTotal = checkoutData.subTotal || { value: 0, currency: this.config.currency || 'USD' };
            var totalPrice = checkoutData.totalPrice || { value: 0, currency: this.config.currency || 'USD' };
            var totalDiscounts = checkoutData.totalDiscounts || { value: 0, currency: this.config.currency || 'USD' };
            var totalShipping = checkoutData.totalShipping || { value: 0, currency: this.config.currency || 'USD' };
            var totalTax = checkoutData.totalTax || { value: 0, currency: this.config.currency || 'USD' };

            var placementObject = {
                allowCheckout: true,
                financingType: 'installment',
                locationType: 'checkout',
                domID: 'bread-checkout-btn-hyva',
                order: {
                    currency: this.config.currency || 'USD',
                    items: items,
                    subTotal: subTotal,
                    totalPrice: totalPrice,
                    totalDiscounts: totalDiscounts,
                    totalShipping: totalShipping,
                    totalTax: totalTax
                },
                billingContact: billingContact,
                shippingContact: shippingContact
            };

            log('Placement object:', placementObject);
            log('Items count:', items.length);
            log('Items:', JSON.stringify(items));
            log('Order totals - subTotal:', subTotal, 'totalPrice:', totalPrice);
            log('Registering placement and opening experience, sdkWasSetup:', this.sdkWasSetup);
            
            try {
                sdk.registerPlacements([placementObject]);
                
                if (!this.sdkWasSetup) {
                    sdk.init();
                    this.sdkWasSetup = true;
                } else {
                    sdk.openExperienceForPlacement([placementObject]);
                }
            } catch (e) {
                logError('SDK error:', e);
                alert('Error opening Bread checkout: ' + e.message);
                if (this.pendingReject) {
                    this.pendingReject(e);
                }
            }
        },

        handleModalClose: function() {
            log('Handling modal close. re-enabling Place Order button');
            
            // Reject the pending promise so the checkout flow knows we cancelled
            if (this.pendingReject) {
                this.pendingReject('Customer closed Bread modal');
                this.pendingReject = null;
                this.pendingResolve = null;
            }
        },

        handleCheckoutComplete: function(txnId) {
            log('Checkout complete, transaction ID:', txnId);
            var self = this;
            
            this.validatePaymentMethod(txnId)
                .then(function(response) {
                    log('validatePaymentMethod response:', response);
                    
                    if (response.error) {
                        logError('Payment validation error:', response.error);
                        alert(response.error);
                        if (self.pendingReject) {
                            self.pendingReject(response.error);
                        }
                        return Promise.reject(response.error);
                    }
                    
                    log('Payment method validated');
                    
                    return self.validateTotals(txnId);
                })
                .then(function(response) {
                    log('validateTotals response:', response);
                    
                    if (!response.valid) {
                        logError('Totals validation failed');
                        if (self.pendingReject) {
                            self.pendingReject('Totals validation failed');
                        }
                        return Promise.reject('Totals validation failed');
                    }
                    
                    log('Totals validated, proceeding with order');
                    
                    self.transactionId = txnId;
                    self.isApproved = true;
                    
                    var input = document.getElementById('bread-transaction-id-input');
                    if (input) {
                        input.value = txnId;
                    }
                    
                    if (typeof Livewire !== 'undefined') {
                        Livewire.emit('bread_transaction_complete', txnId);
                    }
                    
                    self.showApproved();
                    
                    log('Calling original place order');
                    return self.originalPlaceOrder();
                })
                .then(function(result) {
                    log('Original place order result:', result);
                    if (self.pendingResolve) {
                        self.pendingResolve(result);
                    }
                })
                .catch(function(error) {
                    logError('Error in checkout flow:', error);
                    if (self.pendingReject) {
                        self.pendingReject(error);
                    }
                });
        },
        validatePaymentMethod: function(txnId) {
            var paymentUrl = this.config.paymentUrl;
            
            log('Calling validatePaymentMethod:', paymentUrl, 'with txnId:', txnId);
            
            return fetch(paymentUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'token=' + encodeURIComponent(txnId) + 
                      '&currency=' + encodeURIComponent(this.config.currency)
            })
            .then(function(response) {
                log('validatePaymentMethod HTTP status:', response.status);
                return response.json();
            })
            .catch(function(error) {
                logError('validatePaymentMethod fetch error:', error);
                return { error: error.message };
            });
        },

        validateTotals: function(txnId) {
            var validateTotalsUrl = this.config.validateTotalsUrl;
            
            log('Calling validateTotals:', validateTotalsUrl, 'with txnId:', txnId);
            
            return fetch(validateTotalsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'bread_transaction_id=' + encodeURIComponent(txnId)
            })
            .then(function(response) {
                log('validateTotals HTTP status:', response.status);
                return response.json();
            })
            .catch(function(error) {
                logError('validateTotals fetch error:', error);
                return { valid: false, error: error.message };
            });
        },

        showApproved: function() {
            var approvedMsg = document.getElementById('bread-approved-message');
            var buttonContainer = document.getElementById('bread-button-container');
            
            if (approvedMsg) approvedMsg.style.display = 'flex';
            if (buttonContainer) buttonContainer.style.display = 'none';
        }
    };

    window.BreadCheckoutPayment = BreadCheckoutPayment;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BreadCheckoutPayment.init();
        });
    } else {
        BreadCheckoutPayment.init();
    }

    window.addEventListener('checkout:init', function() {
        log('checkout:init event received');
        if (!BreadCheckoutPayment.initialized) {
            BreadCheckoutPayment.init();
        }
    });
})();
