(function ($) {
    'use strict';

    /**
     * Universal Phone Input Initialization Script
     */

    // Configuration from localized script data
    const config = window.UniversalPhoneData || {
        utilsScript: '',
        geoIpUrl: '',
        geoIpNonce: '',
        defaultCountry: 'us', // Fallback
        overwriteInput: false
    };

    /**
     * Check if an input element should be initialized as a phone input.
     * @param {HTMLInputElement} input 
     * @returns {boolean}
     */
    function shouldInitialize(input) {
        if (input.dataset.itiInitialized) {
            return false;
        }

        // Must be an input element
        if (input.tagName !== 'INPUT') {
            return false;
        }

        const type = input.type.toLowerCase();

        // 1. Check type="tel"
        if (type === 'tel') {
            return true;
        }

        // 2. Check "phone-like" text inputs
        if (type === 'text') {
            const name = (input.name || '').toLowerCase();
            const id = (input.id || '').toLowerCase();
            const autocomplete = (input.autocomplete || '').toLowerCase();
            const inputmode = (input.inputMode || '').toLowerCase();

            // Keywords to match
            const phoneKeywords = ['phone', 'mobile', 'tel', 'whatsapp'];

            // Check if name or id contains keywords
            if (phoneKeywords.some(keyword => name.includes(keyword) || id.includes(keyword))) {
                return true;
            }

            // Check attributes
            if (autocomplete === 'tel' || inputmode === 'tel') {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize intl-tel-input on a specific input element.
     * @param {HTMLInputElement} input 
     */
    function initPhoneInput(input) {
        if (!shouldInitialize(input)) {
            return;
        }

        // Mark as initialized immediately to prevent race conditions
        input.dataset.itiInitialized = '1';

        // Prepare hidden input for E.164 format
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = (input.name || input.id) + '_e164'; // e.g. 'phone_e164'
        hiddenInput.className = 'iti-e164-hidden';

        // Insert hidden input after the visible input
        if (input.parentNode) {
            input.parentNode.insertBefore(hiddenInput, input.nextSibling);
        }

        // Initialize intl-tel-input
        const iti = window.intlTelInput(input, {
            initialCountry: "auto",
            separateDialCode: true,
            nationalMode: false,
            utilsScript: config.utilsScript,
            geoIpLookup: function (success, failure) {
                // GeoIP Lookup via WP REST API
                fetch(config.geoIpUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': config.geoIpNonce
                    }
                })
                    .then(response => {
                        if (response.ok) {
                            return response.json();
                        }
                        throw new Error('GeoIP fetch failed');
                    })
                    .then(data => {
                        const countryCode = (data && data.country_code) ? data.country_code : '';
                        success(countryCode);
                    })
                    .catch(() => {
                        // Fallback to browser locale
                        let countryCode = config.defaultCountry;
                        if (navigator.language) {
                            const parts = navigator.language.split('-');
                            if (parts.length > 1) {
                                countryCode = parts[1].toLowerCase();
                            }
                        }
                        success(countryCode);
                    });
            }
        });

        // Function to update hidden input with E.164 number
        const updateHiddenInput = () => {
            if (iti.isValidNumber()) {
                hiddenInput.value = iti.getNumber();
            } else {
                // If invalid or empty, clear the hidden input or leave it empty
                hiddenInput.value = '';
            }
        };

        // Update on change and blur
        input.addEventListener('change', updateHiddenInput);
        input.addEventListener('blur', updateHiddenInput);
        input.addEventListener('countrychange', updateHiddenInput);

        // Handle Form Submission
        if (input.form) {
            const form = input.form;

            // Listen for submit event on the form
            form.addEventListener('submit', function () {
                updateHiddenInput(); // Ensure latest value is set

                // Optional: Overwrite original input with E.164 value
                if (config.overwriteInput && iti.isValidNumber()) {
                    input.value = iti.getNumber();
                }
            });
        }
    }



    // Initialize on DOM Ready
    $(document).ready(function () {
        // Initial scan of the entire document
        scanForInputs(document.body);

        // Use MutationObserver for dynamic forms (Elementor popups, AJAX loaded content)
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) { // Element node
                        // If the added node is itself an input, check it
                        if (node.tagName === 'INPUT') {
                            initPhoneInput(node);
                        }
                        // Also search for inputs inside the added node
                        scanForInputs(node);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });

    /**
     * Scan a specific container (or document) for inputs to initialize.
     * @param {HTMLElement} container 
     */
    function scanForInputs(container) {
        // Find inputs within the container
        const inputs = container.querySelectorAll('input');
        inputs.forEach(input => {
            initPhoneInput(input);
        });
    }

})(jQuery);
