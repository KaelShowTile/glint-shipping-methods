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
});