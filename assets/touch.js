jQuery(document).ready(function(){
    jQuery('label[for=billing_city]').html('Suburb / Town <abbr class="required" title="required">*</abbr>');

    jQuery(document.body).on('change', 'input[name="payment_method"]', function() {
        jQuery('body').trigger('update_checkout');
    });
});
