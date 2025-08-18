<?php
/*
Plugin Name: CHT Shipping
Description: A customized WooCommerce shipping plugin for CHT 
Version: 1.0.0
Author: Kael
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('GLINT_WC_SHIPPING_VERSION', '1.0');
define('GLINT_WC_SHIPPING_PLUGIN_FILE', __FILE__);
define('GLINT_WC_SHIPPING_PATH', plugin_dir_path(__FILE__));
define('GLINT_WC_SHIPPING_URL', plugin_dir_url(__FILE__));

// Initialize plugin after WooCommerce is loaded
add_action('woocommerce_loaded', function() {
    // Include required files
    require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-db.php';
    require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-admin.php';
    require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-product.php';
    require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping.php';

    // Initialize components
    Glint_WC_Shipping_DB::init();
    Glint_WC_Shipping_Admin::init();
    Glint_WC_Shipping_Product::init();
    Glint_WC_Shipping::init();
});

// Add settings link to plugin actions
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=glint-shipping-methods') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, function() {
    add_option('glint_shipping_enable', 'no');

    require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-db.php';
    Glint_WC_Shipping_DB::create_table();
    //Glint_WC_Shipping_DB::upgrade_table();
});

register_deactivation_hook(__FILE__, function() {
    require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-db.php';
    //Glint_WC_Shipping_DB::cleanup();
});