jQuery( document ).ready( function( $ ) {

	document.addEventListener('wpcf7mailsent', function( event ) {
		var contactform_id = event.detail.contactFormId;
		var redirection_url = event.detail.apiResponse.redirection_url;
		if ( redirection_url != '' && redirection_url != undefined ) {
			window.location = redirection_url;
		}

	} );
} );

// Create the Card Fields Component and define callbacks
jQuery(document).ready(function($) {
    // Create a namespace for our payment handling
    window.CF7PayPal = window.CF7PayPal || {};
    // Only initialize once per form
    function initializePayPalForm($form) {
        const formId = $form.find('input[name="_wpcf7"]').val();
        if (window.CF7PayPal[formId]) return;
        // Check if PayPal SDK is loaded
        if (typeof paypal === 'undefined') {
            console.error('PayPal SDK not loaded. Please check your PayPal configuration.');
            return;
        }
        // Initialize PayPal Card Fields
        try {
            const cardField = paypal.CardFields({
                createOrder: function(data) {
                    return new Promise((resolve, reject) => {
                        setProcessing(true, formId);
                        
                        // Get form data to calculate amount
                        let amount = 10; // Default amount
                        const amountField = $form.find('input[name="amount"], input[name="price"], input[name="total"]').first();
                        if (amountField.length) {
                            const amountValue = parseFloat(amountField.val());
                            if (!isNaN(amountValue) && amountValue > 0) {
                                amount = amountValue;
                            }
                        }

                        // Create order using AJAX
                        var postData = {
                            action: 'cf7pe_create_order',
                            form_id: formId,
                            amount: amount,
                            payment_source: data.paymentSource
                        };
                
                        $.ajax({
                            url: CF7PAP_ajax_object.ajax_url,
                            type: 'POST',
                            data: postData,
                            dataType: 'json'
                        })
                        .then(function(result) {
                            setProcessing(false, formId);
                            if(result.status == 1 && result.data && result.data.id){
                                resolve(result.data.id);
                            } else {
                                const error = result.msg || 'Failed to create order';
                                resultMessage(error, formId);
                                reject(new Error(error));
                            }
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            setProcessing(false, formId);
                            const error = 'Error creating order: ' + errorThrown;
                            resultMessage(error, formId);
                            reject(new Error(error));
                        });
                    });
                },
                onApprove: function(data) {
                    return new Promise((resolve, reject) => {
                        setProcessing(true, formId);
                
                        const { orderID } = data;
                        
                        // Add payment reference to form for processing in wpcf7_before_send_mail
                        $form.find('input[name="payment_reference"]').remove();
                        var $paymentRef = $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'payment_reference')
                            .val(orderID);
                        $form.append($paymentRef);
                        
                        setProcessing(false, formId);
                        
                        // Submit form using CF7's API
                        if (typeof window.wpcf7 !== 'undefined' && typeof window.wpcf7.submit === 'function') {
                            window.wpcf7.submit($form[0]);
                        } 
                        resolve();
                    });
                },
                onError: function(error) {
                    setProcessing(false, formId);
                    resultMessage('Payment error: ' + error.message, formId);
                },
            });

            // Only proceed if Card Fields are eligible
            if (cardField.isEligible()) {
                // Remove any existing card fields first
                $form.find('#card-name-field-container, #card-number-field-container, #card-cvv-field-container, #card-expiry-field-container').empty();
                // Render card fields
                const nameField = cardField.NameField();
                const numberField = cardField.NumberField();
                const cvvField = cardField.CVVField();
                const expiryField = cardField.ExpiryField();

                // Check if containers exist before rendering
                const containers = {
                    name: $form.find("#card-name-field-container")[0],
                    number: $form.find("#card-number-field-container")[0],
                    cvv: $form.find("#card-cvv-field-container")[0],
                    expiry: $form.find("#card-expiry-field-container")[0]
                };

                if (containers.name) nameField.render("#" + containers.name.id);
                if (containers.number) numberField.render("#" + containers.number.id);
                if (containers.cvv) cvvField.render("#" + containers.cvv.id);
                if (containers.expiry) expiryField.render("#" + containers.expiry.id);

                // Remove any existing submit handlers
                $form.off('submit.cf7paypal wpcf7submit.cf7paypal');

                // Handle form submission
                $form.on('submit.cf7paypal', function(e) {
                    const hasPaymentFields = $(this).find('#card-number-field-container').length > 0;
                    if (!hasPaymentFields) return true;

                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $submitButton = $(this).find('input[type="submit"]');
                    setProcessing(true, formId);
                    
                    cardField.submit()
                        .catch((error) => {
                            setProcessing(false, formId);
                            resultMessage(`Payment error: ${error.message}`, formId);
                        });
                    
                    return false;
                });

                // Handle CF7 submission event
                $form.on('wpcf7submit.cf7paypal', function(e) {
                    const hasPaymentFields = $(this).find('#card-number-field-container').length > 0;
                    if (!hasPaymentFields) return true;

                    // If no payment reference, prevent submission
                    if (!$(this).find('input[name="payment_reference"]').length) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                });

                // Mark this form as initialized
                window.CF7PayPal[formId] = true;
            } else {
                resultMessage('Card fields are not available for your browser/device', formId);
            }
        } catch (error) {
            console.error('Error initializing PayPal Card Fields:', error);
            resultMessage('Failed to initialize payment form. Please check your configuration.', formId);
        }
    }

    // Function to initialize forms
    function initializeForms() {
        $('form.wpcf7-form').each(function() {
            const $form = $(this);
            if ($form.find('#card-number-field-container').length > 0) {
                initializePayPalForm($form);
            }
        });
    }

    // Initialize forms on page load
    initializeForms();

    // Handle CF7 form initialization
    $(document).on('wpcf7:init', function() {
        initializeForms();
    });

    // Handle CF7 form reset
    $(document).on('wpcf7:reset', function(e) {
        const $form = $(e.target);
        const formId = $form.find('input[name="_wpcf7"]').val();
        if (formId && window.CF7PayPal[formId]) {
            delete window.CF7PayPal[formId];
            initializePayPalForm($form);
        }
    });
    
    // Show a loader on payment form processing
    const setProcessing = (isProcessing, formId) => {
        const $form = $('form.wpcf7-form').filter(function() {
            return $(this).find('input[name="_wpcf7"]').val() === formId;
        });
        const $overlay = $form.find(".overlay");
        if ($overlay.length) {
            $overlay.css('display', isProcessing ? 'block' : 'none')
                   .toggleClass('hidden', !isProcessing);
        }
    }
    
    // Display status message
    const resultMessage = (msg_txt, formId) => {
        const $form = $('form.wpcf7-form').filter(function() {
            return $(this).find('input[name="_wpcf7"]').val() === formId;
        });
        const $messageContainer = $form.find("#paymentResponse");
        if ($messageContainer.length) {
            $messageContainer.removeClass('hidden')
                           .text(msg_txt);
            
            setTimeout(function () {
                $messageContainer.addClass('hidden')
                               .text('');
            }, 5000);
        } else {
            console.log('Payment message:', msg_txt);
        }
    }
});
