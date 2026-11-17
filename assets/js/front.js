jQuery( document ).ready( function( $ ) {

	document.addEventListener('wpcf7mailsent', function( event ) {
		var contactform_id = event.detail.contactFormId;
		var redirection_url = event.detail.apiResponse.redirection_url;
		if ( redirection_url != '' && redirection_url != undefined ) {
			window.location = redirection_url;
		}

	} );
    // Create a namespace for our payment handling
    window.CF7PayPal = window.CF7PayPal || {};

    // Must be declared before initializePayPalForm() runs (avoids TDZ / "Cannot access 'r' before initialization")
    const setProcessing = (isProcessing, formId) => {
        const $form = $('form.wpcf7-form').filter(function() {
            return $(this).find('input[name="_wpcf7"]').val() === formId;
        });
        const $overlay = $form.find(".overlay");
        if ($overlay.length) {
            $overlay.css('display', isProcessing ? 'block' : 'none')
                   .toggleClass('hidden', !isProcessing);
        }
    };

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
    };

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
                        var $wpcf7_form = $('form.wpcf7-form');
                        let isValid = true;
                        
                        // Validate required form fields
                        $wpcf7_form.find('input').each(function () {
                            if ($(this).hasClass('wpcf7-validates-as-required') && $(this).val() === '') {
                                isValid = false;
                            }
                        });
                        
                        // Validate PayPal card fields
                        const cardNumberField = $wpcf7_form.find('#card-number-field-container');
                        const cardNameField = $wpcf7_form.find('#card-name-field-container');
                        const cardExpiryField = $wpcf7_form.find('#card-expiry-field-container');
                        const cardCvvField = $wpcf7_form.find('#card-cvv-field-container');
                        
                        if (!cardNumberField.children().length || !cardNameField.children().length || 
                            !cardExpiryField.children().length || !cardCvvField.children().length) {
                            isValid = false;
                            $wpcf7_form.find('.wpcf7-response-output').text('Please fill in all card details.');
                        }
                        
                        if (!isValid) {
                            setProcessing(false, formId);
                            //$wpcf7_form.find('.wpcf7-spinner').css('display', 'none');
                            //$wpcf7_form.find('.wpcf7-response-output').show();
                            reject(new Error('One or more fields have an error.'));
                            return;
                        } else {
                            console.log('createOrder process starting...');
                            // Force spinner to display and hide response output
                            $wpcf7_form.find('.wpcf7-spinner').css({'display': 'inline-flex', 'visibility': 'visible', 'opacity': '1'});
                            $wpcf7_form.find('.wpcf7-response-output').hide();
                        }

                        var amount = 0;
                        const amount_attr = $("input[name='cf7pe_amount']").attr("att-cf7pe-name");
                        if (amount_attr) {
                            var $amountField = $wpcf7_form.find("input[name='" + amount_attr + "']");
                            if (!$amountField.length) {
                                $amountField = $("input[name='" + amount_attr + "']");
                            }
                            if ($amountField.length) {
                                const amountValue = parseFloat($amountField.val());
                                if (!isNaN(amountValue)) {
                                    amount = amountValue;
                                }
                            }
                        }

                        if (amount <= 0) {
                            setProcessing(false, formId);
                            reject(new Error('Invalid amount'));
                            return;
                        }

                        // Create order using AJAX
                        var postData = {
                            action: 'cf7pe_create_order',
                            form_id: formId,
                            amount: amount,
                            payment_source: data.paymentSource,
                            nonce: CF7PE_ajax_object.nonce  
                        };
                
                        $.ajax({
                            url: CF7PE_ajax_object.ajax_url,
                            type: 'POST',
                            data: postData,
                            dataType: 'json'
                        })
                        .then(function(result) {
                            if(result.status == 1 && result.data && result.data.id){
                                resolve(result.data.id);
                                // Keep spinner visible until payment completes
                                $wpcf7_form.find('.wpcf7-spinner').css({'display': 'inline-flex', 'visibility': 'visible', 'opacity': '1'});
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
                        const { orderID } = data;
                        const $form = $('form.wpcf7-form').filter(function() {
                            return $(this).find('input[name="_wpcf7"]').val() === formId;
                        });
                        
                        // Keep spinner visible during processing
                        $form.find('.wpcf7-spinner').css({'display': 'inline-flex', 'visibility': 'visible', 'opacity': '1'});
                        
                        // Add payment reference to form for processing in wpcf7_before_send_mail
                        $form.find('input[name="payment_reference"]').remove();
                        var $paymentRef = $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'payment_reference')
                            .val(orderID);
                        $form.append($paymentRef);
                        
                        setProcessing(false, formId);
                        
                        if (typeof window.wpcf7 !== 'undefined' && typeof window.wpcf7.submit === 'function') {
                            // Let CF7 show the real response. Clear spinner on any CF7 finish (incl. aborted payment).
                            var finished = false;
                            var finish = function(isSuccess) {
                                if (finished) return;
                                finished = true;
                                document.removeEventListener('wpcf7mailsent', onMailSent);
                                document.removeEventListener('wpcf7mailfailed', onDone);
                                document.removeEventListener('wpcf7invalid', onDone);
                                document.removeEventListener('wpcf7spam', onDone);
                                document.removeEventListener('wpcf7submit', onSubmit);
                                clearTimeout(safetyTimer);
                                setProcessing(false, formId);
                                $form.find('.wpcf7-spinner').css('display', 'none');
                                // Was hidden during createOrder — show CF7 success/error message again.
                                $form.find('.wpcf7-response-output').show();
                                if (isSuccess) {
                                    const successUrl = (CF7PE_ajax_object.dynamic_success_return_url && CF7PE_ajax_object.dynamic_success_return_url.trim() !== '')
                                        ? CF7PE_ajax_object.dynamic_success_return_url
                                        : ((CF7PE_ajax_object.success_return_url && CF7PE_ajax_object.success_return_url.trim() !== '')
                                            ? CF7PE_ajax_object.success_return_url
                                            : null);
                                    if (successUrl) {
                                        window.location.href = successUrl;
                                    }
                                }
                                resolve();
                            };
                            var onMailSent = function() { finish(true); };
                            var onDone = function() { finish(false); };
                            var onSubmit = function(event) {
                                // Handles aborted payments (amount mismatch) — CF7 status is often "aborted", not mailfailed.
                                var status = event && event.detail && event.detail.apiResponse
                                    ? event.detail.apiResponse.status
                                    : '';
                                if (status && status !== 'mail_sent') {
                                    finish(false);
                                }
                            };
                            var safetyTimer = setTimeout(function() { finish(false); }, 20000);
                            document.addEventListener('wpcf7mailsent', onMailSent);
                            document.addEventListener('wpcf7mailfailed', onDone);
                            document.addEventListener('wpcf7invalid', onDone);
                            document.addEventListener('wpcf7spam', onDone);
                            document.addEventListener('wpcf7submit', onSubmit);
                            window.wpcf7.submit($form[0]);
                        } else {
                            setProcessing(false, formId);
                            $form.find('.wpcf7-spinner').css('display', 'none');
                            resolve();
                        }
                    });
                },
                onError: function(error) {
                    setProcessing(false, formId);
                    resultMessage('Payment error: ' + error.message, formId);
                    $form.find('.wpcf7-spinner').css('display', 'none');
                },
            });

            // Only proceed if Card Fields are eligible
            if (cardField.isEligible()) {
                try {
                // Render card fields
                const nameField = cardField.NameField({
                    onValidate: function(event) {
                        if (event.isEmpty) {
                            $form.find('.wpcf7-response-output').text('Please enter card holder name').show();
                        }
                    }
                });
                const numberField = cardField.NumberField({
                    onValidate: function(event) {
                        if (event.isEmpty) {
                            $form.find('.wpcf7-response-output').text('Please enter card number').show();
                        }
                    }
                });
                const cvvField = cardField.CVVField({
                    onValidate: function(event) {
                        if (event.isEmpty) {
                            $form.find('.wpcf7-response-output').text('Please enter CVV').show();
                        }
                    }
                });
                const expiryField = cardField.ExpiryField({
                    onValidate: function(event) {
                        if (event.isEmpty) {
                            $form.find('.wpcf7-response-output').text('Please enter expiry date').show();
                        }
                    }
                });

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
                } catch (error) {
                    console.error('Error rendering PayPal card fields:', error);
                    return;
                }

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
                            resultMessage(`Payment error : ${error.message}`, formId);
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
});
