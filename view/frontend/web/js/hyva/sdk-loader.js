/**
 * Bread BreadCheckout SDK Loader for Hyva
 * Handles SDK loading and initialization without inline scripts (CSP compliant)
 */

(function() {
    'use strict';

    window.BreadSDKHelper = (function() {
        let setupComplete = false;
        let currentSdkName = null;

        function waitForSdk(sdkName, maxAttempts) {
            maxAttempts = maxAttempts || 50;
            return new Promise(function(resolve, reject) {
                let attempts = 0;
                function check() {
                    attempts++;
                    if (window[sdkName]) {
                        resolve(window[sdkName]);
                    } else if (attempts < maxAttempts) {
                        setTimeout(check, 100);
                    } else {
                        reject(new Error('Bread SDK not loaded after ' + maxAttempts + ' attempts'));
                    }
                }
                check();
            });
        }

        function setupSdk(sdk, integrationKey, callbacks) {
            if (setupComplete && currentSdkName === sdk) {
                return false;
            }
            callbacks = callbacks || {};
            sdk.setup({ integrationKey: integrationKey });
            sdk.on('INSTALLMENT:APPLICATION_DECISIONED', callbacks.onDecisioned || function() {});
            sdk.on('INSTALLMENT:APPLICATION_CHECKOUT', callbacks.onCheckout || function() {});
            setupComplete = true;
            currentSdkName = sdk;
            return true;
        }

        function registerAndInit(sdk, placements) {
            if (placements && placements.length) {
                sdk.registerPlacements(placements);
                sdk.setInitMode('manual');
                sdk.init();
            }
        }

        function createPlacement(options) {
            var currency = options.currency || 'USD';
            var items = options.items || [];
            var totalPrice = options.totalPrice || 0;
            return {
                allowCheckout: options.allowCheckout !== undefined ? options.allowCheckout : false,
                domID: options.domID,
                order: {
                    currency: currency,
                    items: items,
                    subTotal: { value: options.subTotal || totalPrice, currency: currency },
                    totalPrice: { value: totalPrice, currency: currency },
                    totalDiscounts: { value: options.totalDiscounts || 0, currency: currency },
                    totalShipping: { value: options.totalShipping || 0, currency: currency },
                    totalTax: { value: options.totalTax || 0, currency: currency }
                }
            };
        }

        function createItem(options) {
            var currency = options.currency || 'USD';
            return {
                name: options.name || '',
                quantity: options.quantity || 1,
                unitPrice: { value: options.price || 0, currency: currency },
                itemUrl: options.url || '',
                shippingCost: { value: options.shippingCost || 0, currency: currency },
                shippingDescription: options.shippingDescription || '',
                unitTax: { value: options.unitTax || 0, currency: currency }
            };
        }

        function init(config, placements, callbacks) {
            return waitForSdk(config.sdkName)
                .then(function(sdk) {
                    var isNewSetup = setupSdk(sdk, config.integrationKey, callbacks);
                    registerAndInit(sdk, placements);
                    return { sdk: sdk, isNewSetup: isNewSetup };
                });
        }

        function reset() {
            setupComplete = false;
            currentSdkName = null;
        }

        return {
            waitForSdk: waitForSdk,
            setupSdk: setupSdk,
            registerAndInit: registerAndInit,
            createPlacement: createPlacement,
            createItem: createItem,
            init: init,
            reset: reset
        };
    })();

    var sdkLoading = false;
    var sdkLoaded = false;
    function loadBreadSdk() {
        if (sdkLoading || sdkLoaded) {
            return;
        }

        // First try the dedicated SDK config element (from hyva/js.phtml)
        var configElement = document.querySelector('[data-bread-sdk-config]');
        var config = null;

        if (configElement) {
            try {
                config = JSON.parse(configElement.getAttribute('data-bread-sdk-config'));
            } catch (e) {
                console.error('[Bread SDK] Failed to parse SDK config:', e);
            }
        }

        // Fallback: Try to get config from payment method template (data-bread-config)
        if (!config || !config.jsLocation) {
            var paymentElement = document.querySelector('[data-bread-config]');
            if (paymentElement) {
                try {
                    var paymentConfig = JSON.parse(paymentElement.getAttribute('data-bread-config'));
                    
                    if (paymentConfig.jsLocation) {
                        config = {
                            apiKey: paymentConfig.integrationKey,
                            jsLocation: paymentConfig.jsLocation
                        };
                    }
                } catch (e) {
                    console.error('[Bread SDK] Failed to parse payment config:', e);
                }
            }
        }

        if (!config || !config.jsLocation) {
            // No config found - module is likely disabled, don't load SDK
            return;
        }

        sdkLoading = true;

        var script = document.createElement('script');
        script.async = false;
        if (config.apiKey) {
            script.dataset.apiKey = config.apiKey;
        }
        script.src = config.jsLocation;
        script.onload = function() {
            sdkLoaded = true;
            if (typeof BreadPayments !== 'undefined') {
                BreadPayments.setInitMode('manual');
            } else if (typeof RBCPayPlan !== 'undefined') {
                RBCPayPlan.setInitMode('manual');
            }
        };
        script.onerror = function(e) {
            sdkLoading = false;
            console.error('[Bread SDK] Failed to load SDK:', e);
        };
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadBreadSdk);
    } else {
        loadBreadSdk();
    }
})();
