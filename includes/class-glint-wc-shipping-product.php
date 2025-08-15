<?php
class Glint_WC_Shipping_Product {
    public static function init() {
        add_action('woocommerce_product_options_shipping', [__CLASS__, 'add_shipping_fields']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_shipping_fields']);
    }
    
    public static function add_shipping_fields() {
        global $product_object;
        
        woocommerce_wp_text_input([
            'id'          => 'unitperpallet',
            'label'       => __('Units Per Pallet', 'glint-wc-shipping'),
            'description' => __('Number of units that fit on a standard pallet', 'glint-wc-shipping'),
            'desc_tip'    => true,
            'type'        => 'number',
            'value'       => $product_object->get_meta('unitperpallet', true),
            'custom_attributes' => [
                'step' => '1',
                'min'  => '0'
            ]
        ]);
    }
    
    public static function save_shipping_fields($product) {
        if (isset($_POST['unitperpallet'])) {
            $value = wc_clean($_POST['unitperpallet']);
            $product->update_meta_data('unitperpallet', $value);
        }
    }
}