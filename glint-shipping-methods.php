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

// Include required files
require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-db.php';
require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-admin.php';
require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    Glint_WC_Shipping_DB::init();
    Glint_WC_Shipping_Admin::init();
    Glint_WC_Shipping::init();
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, ['Glint_WC_Shipping_DB', 'create_table']);
//register_deactivation_hook(__FILE__, ['Glint_WC_Shipping_DB', 'cleanup']);