( function($) {
	"use strict";

	function cf7pe_sandbox_validate() {
		if ( jQuery( '.cf7pe-settings #cf7pe_use_paypal' ).prop( 'checked' ) == true && jQuery( '.cf7pe-settings #cf7pe_mode_sandbox' ).prop( 'checked' ) != true ) {
			jQuery( '.cf7pe-settings #cf7pe_live_client_id, .cf7pe-settings #cf7pe_live_client_secret' ).prop( 'required', true );
		} else {
			jQuery( '.cf7pe-settings #cf7pe_live_client_id, .cf7pe-settings #cf7pe_live_client_secret' ).removeAttr( 'required' );
		}
	}

	function cf7pe_live_validate() {
		if ( jQuery( '.cf7pe-settings #cf7pe_use_paypal' ).prop( 'checked' ) == true && jQuery( '.cf7pe-settings #cf7pe_mode_sandbox' ).prop( 'checked' ) == true ) {
			jQuery( '.cf7pe-settings #cf7pe_sandbox_client_id, .cf7pe-settings #cf7pe_sandbox_client_secret' ).prop( 'required', true );
		} else {
			jQuery( '.cf7pe-settings #cf7pe_sandbox_client_id, .cf7pe-settings #cf7pe_sandbox_client_secret' ).removeAttr( 'required' );
		}
	}

	jQuery( document ).on( 'change', '.cf7pe-settings .enable_required', function() {
		if ( jQuery( this ).prop( 'checked' ) == true ) {
			jQuery( '.cf7pe-settings #cf7pe_amount' ).prop( 'required', true );
			
		} else {
			jQuery( '.cf7pe-settings #cf7pe_amount' ).removeAttr( 'required' );
		}

		cf7pe_live_validate();
		cf7pe_sandbox_validate();

	} );

	jQuery( document ).on( 'change', '.cf7pe-settings #cf7pe_mode_sandbox', function() {
		cf7pe_live_validate();
		cf7pe_sandbox_validate();
	} );



	jQuery( document ).on( 'input', '.cf7pe-settings .required', function() {
		cf7pe_live_validate();
		cf7pe_sandbox_validate();
	} );


	function check_paypal_field_validation(){		

		jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );

		if ( jQuery( '.cf7pe-settings #cf7pe_mode_sandbox' ).prop( 'checked' ) == true ) {

			if(
				jQuery( '.cf7pe-settings #cf7pe_sandbox_client_id' ).val() == '' ||
				jQuery( '.cf7pe-settings #cf7pe_sandbox_client_secret').val() == ''
			){
				jQuery("#paypal-extension-tab .ui-tabs-anchor").find('span').remove();
				jQuery("#paypal-extension-tab .ui-tabs-anchor").append('<span class="icon-in-circle" aria-hidden="true">!</span>');
			}else{
				jQuery("#paypal-extension-tab .ui-tabs-anchor").find('span').remove();
			}
		}

		if ( jQuery( '.cf7pe-settings #cf7pe_mode_sandbox' ).prop( 'checked' ) != true ) {
			if(
				jQuery('.cf7pe-settings #cf7pe_live_client_id' ).val() == '' ||
				jQuery('.cf7pe-settings #cf7pe_live_client_secret').val() == ''
			){
				jQuery("#paypal-extension-tab .ui-tabs-anchor").find('span').remove();
				jQuery("#paypal-extension-tab .ui-tabs-anchor").append('<span class="icon-in-circle" aria-hidden="true">!</span>');
			}else{
				jQuery("#paypal-extension-tab .ui-tabs-anchor").find('span').remove();
			}
		}


		if( jQuery( '.cf7pe-settings #cf7pe_use_paypal' ).prop( 'checked' ) == true ){
				
			jQuery('.cf7pe-settings .form-required-fields').each(function() {
				if (jQuery.trim(jQuery(this).val()) == '') {
				  jQuery("#paypal-extension-tab .ui-tabs-anchor").find('span').remove();
				  jQuery("#paypal-extension-tab .ui-tabs-anchor").append('<span class="icon-in-circle" aria-hidden="true">!</span>');
			   }
			});
		   
	   }else{
		   jQuery("#paypal-extension-tab .ui-tabs-anchor").find('span').remove();
	   }
				
	}

	jQuery( document ).ready( function() { check_paypal_field_validation() });
	jQuery( document ).on('click',".ui-state-default",function() { check_paypal_field_validation() });
	
} )( jQuery );

/*start Refund payment */
jQuery(document).ready(function(){
	jQuery(".pap-refund-payment").on("click", function () {
        jQuery("#pap-refund-payment-loader").text('');
        var entry_id = jQuery("#entry_id").val();
        var contact_form_id = jQuery("#contact_form_id").val();
		var transaction_id = jQuery("#transaction_id").val();
        var refund_message=confirm("Are you sure to payment refund?");
         if(!refund_message) {
             return false;
         }
		refund_payment(entry_id,contact_form_id,transaction_id);
	});
});
function refund_payment(entry_id,contact_form_id,transaction_id) {
    var str = 'action=action__refund_payment_free';
    var contact_form_id;
    var entry_id;
	var transaction_id;
    var url1=window.location.href;
    var redirect=url1.split('wp-admin')[0]+'wp-admin/post.php?post='+entry_id+'&action=edit';
    if (contact_form_id != "") {
        str += '&contact_form_id=' + contact_form_id;
    }
    if (entry_id != "") {
        str += '&entry_id=' + entry_id;
    }
	if (transaction_id != "") {
        str += '&transaction_id=' + transaction_id;
    }
    jQuery.ajax({
            url: admin_ajax_url.admin_URL,
            type: "POST",
            data: str,
        beforeSend: function () {
            jQuery("#pap-refund-payment-loader").addClass("pap-refund-payment-loader");
        },
        success: function (data) {
            jQuery("#pap-refund-payment-loader").removeClass("pap-refund-payment-loader");
            console.log(data);
            jQuery("#pap-refund-payment-loader").text(data);

            setTimeout(function() {
                jQuery('#pap-refund-payment-loader').fadeOut("slow");
                window.location.href=redirect;
            }, 3000 );
           
        }, error: function () {
            console.log('ajax error');
        }
    });
}
/*End Refund payment */