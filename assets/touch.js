jQuery(document).ready(function(){
    jQuery(document.body).on('change', 'input[name="payment_method"]', function() {
        jQuery('body').trigger('update_checkout');
    });
});
