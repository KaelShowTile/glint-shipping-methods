<?php
defined('ABSPATH') || exit;

class Glint_WC_Shipping {
    public static function init() {
        add_filter('woocommerce_shipping_methods', [__CLASS__, 'add_shipping_method']);
        add_action('woocommerce_shipping_init', [__CLASS__, 'shipping_init']);
    }
    
    public static function shipping_init() {
        require_once GLINT_WC_SHIPPING_PATH . 'includes/class-glint-wc-shipping-method.php';
    }
    
    public static function add_shipping_method($methods) {
        $methods['glint_shipping'] = 'Glint_WC_Shipping_Method';
        return $methods;
    }
}