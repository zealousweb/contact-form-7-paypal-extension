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
    // Prevent multiple initializations
    if (window.cf7papCardFieldsInitialized) return;
    window.cf7papCardFieldsInitialized = true;

    const cardField = paypal.CardFields({
        createOrder: function(data) {
            setProcessing(true);
            
            var postData = {
                action: 'cf7pap_action_multiple_method_create_order_onsite_payments',
                payment_source: data.paymentSource,
                form_id: $('input[name="_wpcf7"]').val()
            };
    
            return $.ajax({
                url: CF7PAP_ajax_object.ajax_url,
                type: 'POST',
                data: postData,
                dataType: 'json'
            })
            .then(function(result) {
                setProcessing(false);
                if(result.status == 1){
                    return result.data.id;
                }else{
                    resultMessage(result.msg);
                    return false;
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                setProcessing(false);
                resultMessage('Error creating order: ' + errorThrown);
                return false;
            });
        },
        onApprove: function(data) {
            setProcessing(true);
    
            const { orderID } = data;
            var postData = {
                action: 'cf7pap_action_multiple_method_capture_order_onsite_payments',
                order_id: orderID,
                form_id: $('input[name="_wpcf7"]').val()
            };
    
            return $.ajax({
                url: CF7PAP_ajax_object.ajax_url,
                type: 'POST',
                data: postData,
                dataType: 'json'
            })
            .then(function(result) {
                setProcessing(false);
                if(result.status == 1){
                    // Add payment reference to form
                    var $form = $('form.wpcf7-form');
                    $form.find('input[name="payment_reference"]').remove();
                    var $paymentRef = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'payment_reference')
                        .val(orderID);
                    $form.append($paymentRef);
                    
                    // Submit the Contact Form 7 form
                    $form.off('submit').submit();
                }else{
                    resultMessage(result.msg);
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                setProcessing(false);
                resultMessage('Error capturing payment: ' + errorThrown);
            });
        },
        onError: function(error) {
            setProcessing(false);
            resultMessage('Payment error: ' + error.message);
        },
    });
    
    // Render each field after checking for eligibility
    if (cardField.isEligible()) {
        const nameField = cardField.NameField();
        nameField.render("#card-name-field-container");
    
        const numberField = cardField.NumberField();
        numberField.render("#card-number-field-container");
    
        const cvvField = cardField.CVVField();
        cvvField.render("#card-cvv-field-container");
    
        const expiryField = cardField.ExpiryField();
        expiryField.render("#card-expiry-field-container");
    
        // Handle form submission
        $('form.wpcf7-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Disable submit button and show processing state
            $submitButton.prop('disabled', true);
            setProcessing(true);
            
            // Process payment
            cardField.submit()
                .then(() => {
                    // Payment processing started successfully
                })
                .catch((error) => {
                    setProcessing(false);
                    $submitButton.prop('disabled', false);
                    resultMessage(`Payment error: ${error.message}`);
                });
        });
    } else {
        resultMessage('Card fields are not available for your browser/device');
    }
    
    // Show a loader on payment form processing
    const setProcessing = (isProcessing) => {
        const overlay = document.querySelector(".overlay");
        if (overlay) {
            if (isProcessing) {
                overlay.classList.remove("hidden");
            } else {
                overlay.classList.add("hidden");
            }
        }
    }
    
    // Display status message
    const resultMessage = (msg_txt) => {
        const messageContainer = document.querySelector("#paymentResponse");
        if (messageContainer) {
            messageContainer.classList.remove("hidden");
            messageContainer.textContent = msg_txt;
            
            setTimeout(function () {
                messageContainer.classList.add("hidden");
                messageContainer.textContent = "";
            }, 5000);
        }
    }
});
