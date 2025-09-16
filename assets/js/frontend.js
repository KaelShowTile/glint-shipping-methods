jQuery(function($) {
    // Initialize service toggles
    function initServiceToggles() {
        $('.glint-service-toggle input').on('change', function() {
            // Trigger shipping method update
            $('body').trigger('update_checkout');
            
            // Show loading indicator
            $(document.body).trigger('wc_update_cart');
        });
    }
    
    // Initialize on page load
    initServiceToggles();
    
    // Re-initialize after AJAX updates
    $(document).on('updated_checkout', function() {
        initServiceToggles();
    });


    //changing the text of "Free" on subtotal if the shipping fee is 0
    var checkShippingFee = setInterval(function() {
        var shippingFeeElement = $('.wc-block-components-totals-item__value strong');
        if (shippingFeeElement.length && shippingFeeElement.text().trim() === "Free") {
            shippingFeeElement.text("");
            clearInterval(checkShippingFee); // Stop the interval once done
        }
    }, 200); 


});

