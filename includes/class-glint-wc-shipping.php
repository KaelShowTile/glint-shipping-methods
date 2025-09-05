<?php
defined('ABSPATH') || exit;

class Glint_WC_Shipping {
    public static function init() {
        if (get_option('glint_shipping_enable', 'no') === 'yes') {
            add_filter('woocommerce_shipping_methods', [__CLASS__, 'add_shipping_method']);
            add_action('woocommerce_shipping_init', [__CLASS__, 'shipping_init']);

            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
            add_action('woocommerce_after_shipping_rate', [__CLASS__, 'display_service_options'], 10, 2);
        }
    }
    
    public static function shipping_init() {
        require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-method.php';
    }
    
    public static function add_shipping_method($methods) {
        $methods['glint_shipping'] = 'Glint_WC_Shipping_Method';
        return $methods;
    }

    public static function enqueue_frontend_assets() {
        if (is_checkout()) {
            wp_enqueue_style(
                'glint-shipping-frontend',
                GLINT_WC_SHIPPING_URL . 'assets/css/frontend.css',
                [],
                GLINT_WC_SHIPPING_VERSION
            );
            
            wp_enqueue_script(
                'glint-shipping-frontend',
                GLINT_WC_SHIPPING_URL . 'assets/js/frontend.js',
                ['jquery'],
                GLINT_WC_SHIPPING_VERSION,
                true
            );
        }
    }

    public static function display_service_options($method, $index) {
        if (get_option('glint_shipping_enable', 'no') !== 'yes') {
            return;
        }
        
        $shipping_method = new Glint_WC_Shipping_Method();
        $shipping_method->display_service_options($method);
    }
}