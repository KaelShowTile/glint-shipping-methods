<?php
defined('ABSPATH') || exit;

class Glint_WC_Shipping_Method extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        
        $this->id = 'glint_shipping';
        $this->method_title = __('CHT Shipping', 'glint-wc-shipping');
        $this->method_description = __('Custom shipping calculation based on postcodes and methods', 'glint-wc-shipping');
        
        $this->supports = [
            'shipping-zones',
            'instance-settings',
        ];
        
        $this->init();
    }
    
    public function init() {
        // Load form fields
        $this->init_form_fields();
        
        // Load settings
        $this->init_settings();
        
        // Define user settings
        $this->title = $this->get_option('title', $this->method_title);
        
        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }
    
    public function init_form_fields() {
        $this->form_fields = [
            'title' => [
                'title' => __('Title', 'glint-wc-shipping'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'glint-wc-shipping'),
                'default' => __('CHT Shipping', 'glint-wc-shipping'),
                'desc_tip' => true,
            ]
        ];
    }
    
    public function calculate_shipping($package = []) {
        // Check if postcode exists
        if (empty($package['destination']['postcode'])) {
            return;
        }
        
        $postcode = strtoupper(str_replace(' ', '', $package['destination']['postcode']));
        $methods = Glint_WC_Shipping_DB::get_all_methods();
        
        $found_method = null;
        $no_service_method = null;
        
        // Find matching method by postcode
        foreach ($methods as $method) {
            // First, check if this is the no_shipping_service method
            if ($method['method_name'] === 'no_shipping_service') {
                $no_service_method = $method;
                continue;
            }
            
            // Then check for postcode matches in regular methods
            $postcodes = array_map('trim', explode("\n", $method['postcode']));
            $normalized_postcodes = array_map(function($pc) {
                return strtoupper(str_replace(' ', '', $pc));
            }, $postcodes);
            
            if (in_array($postcode, $normalized_postcodes)) {
                if($this->calculate_method_cost($found_method, $package) !== false){
                    $found_method = $method;
                    break;
                }
            }
        }

        // If no regular method found, use the no_shipping_service method
        if (!$found_method && $no_service_method) {
            // Add a special rate with the custom message
            $rate = [
                'id' => $this->id . '_no_shipping_service',
                'label' => $no_service_method['method_setting']['no_shipping_method_notice'], 
                //'cost' => 0, // Or you could set a special cost if needed
                'package' => $package,
                'meta_data' => [
                    'no_shipping_service' => true, // Custom flag for identification
                    'custom_label' => $no_service_method['method_setting']['no_shipping_method_notice']
                ]
            ];
            
            $this->add_rate($rate);
            return;
        }
        
        // If no method found, don't add a rate
        if (!$found_method) {
            return;
        }

        // Calculate shipping based on method type
        $cost = $this->calculate_method_cost($found_method, $package);
        
        // Add shipping rate
        $rate = [
            'id' => $this->id . '_' . $found_method['method_id'],
            'label' => $found_method['setting_name'],
            'cost' => $cost,
            //'calc_tax' => 'per_item',
            'package' => $package,
        ];
        
        $this->add_rate($rate);
    }

    
    private function calculate_method_cost($method, $package) {
        switch ($method['method_name']) {
            case 'custom_formula':
                return $this->calculate_custom_formula($method, $package);
            case 'mrl':
                return $this->calculate_mrl($method, $package);
            case 'sydney_delivery':
                return $this->calculate_sydney_delivery($method, $package);
        }
    }
    
    private function calculate_custom_formula($method, $package) {
        // Get store address
        $store_country = WC()->countries->get_base_country();
        $store_postcode = WC()->countries->get_base_postcode();
        $store_city = WC()->countries->get_base_city();
        
        // Get destination address
        $destination = $package['destination'];
        $to_suburb = $destination['city'];
        $to_postcode = $destination['postcode'];
        
        // Prepare items array
        $items = [];
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $qty = $item['quantity'];
            $dimensions = $this->get_product_dimensions($product);
            $length = $this->convert_dimension_to_cm($dimensions['length'], $dimensions['dimension_unit']);
            $width = $this->convert_dimension_to_cm($dimensions['width'], $dimensions['dimension_unit']);
            $height = $this->convert_dimension_to_cm($dimensions['height'], $dimensions['dimension_unit']);
            $weight = $this->convert_weight_to_kg($dimensions['weight'], $dimensions['weight_unit']);
            
            // Get dimensions - convert to cm if needed
            $length = wc_get_dimension((float) $product->get_length(), 'cm');
            $width = wc_get_dimension((float) $product->get_width(), 'cm');
            $height = wc_get_dimension((float) $product->get_height(), 'cm');
            $weight = wc_get_weight((float) $product->get_weight(), 'kg');
            
            $items[] = [
                'width' => max(1, $width),  // Ensure minimum 1cm
                'length' => max(1, $length),
                'height' => max(1, $height),
                'weight' => max(0.1, $weight), // Ensure minimum 0.1kg
                'qty' => $qty
            ];
        }
        
        // Prepare services array
        $services = [[
            'account' => $method['method_setting']['account'] ?? '',
            'service' => 'CPX' // Default service
        ]];
        
        // Prepare API request
        $api_url = 'https://api.ezishipping.com/customers/81/shipping/';
        $request_body = [
            'fromSuburb' => $store_city,
            'fromPostcode' => $store_postcode,
            'toSuburb' => $to_suburb,
            'toPostcode' => $to_postcode,
            'tailLiftPickup' => $this->convert_yesno($method['method_setting']['tailLiftPickup'] ?? 'no'),
            'tailLiftDelivery' => $this->convert_yesno($method['method_setting']['tailLiftDelivery'] ?? 'no'),
            'handUnload' => $this->convert_yesno($method['method_setting']['handUnload'] ?? 'no'),
            'services' => $services,
            'items' => $items
        ];
        
        // Make API request
        $response = wp_remote_post($api_url, [
            'body' => wp_json_encode($request_body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode('2656699showtile:Welcome123!')
            ],
            'timeout' => 10
        ]);
        
        // Handle response
        if (is_wp_error($response)) {
            error_log('MRL API Error: ' . $response->get_error_message());
            return 0; // Fallback to free shipping on error
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = "MRL API Error: Status $status_code";
            if (isset($data['error']['message'])) {
                $error_message .= " - " . $data['error']['message'];
            }
            error_log($error_message);
            return 0;
        }

        if (!isset($data['success']) || !$data['success']) {
            $error = $data['error'] ?? ['code' => 'unknown', 'message' => 'Unknown error'];
            error_log("MRL API Error: {$error['code']} - {$error['message']}");
            return 0;
        }
        
        // Find the first valid quote
        foreach ($data['response'] as $quote) {
            if (isset($quote['TotalInc'])) {
                return (float) $quote['TotalInc'];
            }
        }
        
        error_log('MRL API Error: No valid quote found');
        return 0; // Fallback to free shipping
    }
    
    private function get_customer_service_choices() {
        $choices = [
            'tailLiftPickup' => 'no',
            'tailLiftDelivery' => 'no',
            'handUnload' => 'no'
        ];
        
        // Get from session if available
        if (isset(WC()->session) && WC()->session->get('glint_mrl_services')) {
            $session_choices = WC()->session->get('glint_mrl_services');
            foreach ($choices as $key => $value) {
                if (isset($session_choices[$key])) {
                    $choices[$key] = $session_choices[$key];
                }
            }
        }
        
        return $choices;
    }

    private function save_customer_service_choices() {
        if (!isset(WC()->session) || !isset($_POST['post_data'])) {
            return;
        }
        
        parse_str($_POST['post_data'], $post_data);
        
        $services = [
            'tailLiftPickup' => isset($post_data['glint_tailLiftPickup']) ? 'yes' : 'no',
            'tailLiftDelivery' => isset($post_data['glint_tailLiftDelivery']) ? 'yes' : 'no',
            'handUnload' => isset($post_data['glint_handUnload']) ? 'yes' : 'no'
        ];
        
        WC()->session->set('glint_mrl_services', $services);
    }

    private function calculate_mrl($method, $package) {
        $this->save_customer_service_choices();

        // Get service choices (customer or default)
        $customer_choice_enabled = $method['method_setting']['customer_choice_enabled'] ?? 'no';
        
        if ($customer_choice_enabled === 'yes') {
            $service_choices = $this->get_customer_service_choices();
        } else {
            $service_choices = [
                'tailLiftPickup' => $method['method_setting']['tailLiftPickup'] ?? 'no',
                'tailLiftDelivery' => $method['method_setting']['tailLiftDelivery'] ?? 'no',
                'handUnload' => $method['method_setting']['handUnload'] ?? 'no'
            ];
        }

        // If no items, return 0
        if (empty($package['contents'])) {
            return 0;
        }
        
        // Get store address
        $store_country = WC()->countries->get_base_country();
        $store_postcode = WC()->countries->get_base_postcode();
        $store_city = WC()->countries->get_base_city();
        
        // Get destination address
        $destination = $package['destination'];
        $to_suburb = $destination['city'];
        $to_postcode = $destination['postcode'];
        
        // Prepare items array
        $items = [];
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $qty = $item['quantity'];
            
            // Get dimensions
            $dimensions = $this->get_product_dimensions($product);
            $length = $this->convert_dimension_to_cm($dimensions['length'], $dimensions['dimension_unit']);
            $width = $this->convert_dimension_to_cm($dimensions['width'], $dimensions['dimension_unit']);
            $height = $this->convert_dimension_to_cm($dimensions['height'], $dimensions['dimension_unit']);
            $weight = $this->convert_weight_to_kg($dimensions['weight'], $dimensions['weight_unit']);
            
            // Ensure minimum values
            $items[] = [
                'width' => max(1, $width),
                'length' => max(1, $length),
                'height' => max(1, $height),
                'weight' => max(0.1, $weight),
                'qty' => $qty
            ];
        }
        
        // Prepare services array
        $services = [[
            'account' => $method['method_setting']['account'] ?? '',
            'service' => 'CPX' // Default service
        ]];
        
        // Prepare API request
        $api_url = 'https://api.ezishipping.com/customers/81/shipping/';
        $request_body = [
            'fromSuburb' => $store_city,
            'fromPostcode' => $store_postcode,
            'toSuburb' => $to_suburb,
            'toPostcode' => $to_postcode,
            'tailLiftPickup' => $this->convert_yesno($service_choices['tailLiftPickup']),
            'tailLiftDelivery' => $this->convert_yesno($service_choices['tailLiftDelivery']),
            'handUnload' => $this->convert_yesno($service_choices['handUnload']),
            'services' => $services,
            'items' => $items
        ];
        
        // Make API request
        $response = wp_remote_post($api_url, [
            'body' => wp_json_encode($request_body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode('2656699showtile:Welcome123!')
            ],
            'timeout' => 15,
            'sslverify' => false // Only for testing, remove in production
        ]);
        
        // Handle response
        if (is_wp_error($response)) {
            error_log('MRL API Error: ' . $response->get_error_message());
            return 0;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = "MRL API Error: Status $status_code";
            if (isset($data['error']['message'])) {
                $error_message .= " - " . $data['error']['message'];
            }
            error_log($error_message);
            return 0;
        }
        
        if (!isset($data['success']) || !$data['success']) {
            $error = $data['error'] ?? ['code' => 'unknown', 'message' => 'Unknown error'];
            error_log("MRL API Error: {$error['code']} - {$error['message']}");
            return 0;
        }
        
        // Find the first valid quote
        foreach ($data['response'] as $quote) {
            if (isset($quote['TotalInc'])) {
                return (float) $quote['TotalInc'];
            }
        }
        
        error_log('MRL API Error: No valid quote found');
        return 0;
        return 20;
    }
    
    private function calculate_sydney_delivery($method, $package) {
        $destination = $package['destination'];
        $to_postcode = $destination['postcode'];
        $total_weight = 0;

        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $dimensions = $this->get_product_dimensions($product);
            $weight = $this->convert_weight_to_kg($dimensions['weight'], $dimensions['weight_unit']);

            //if forget to setup weight, use 1200kg as default
            if(!$weight || $weight==0){
                $weight = 1200;
            }
            
            //convert into kg
            $weight = wc_get_weight((float) $product->get_weight(), 'kg');
            $total_weight = $total_weight + $weight;
        }

        //formula
        if($method['method_setting']['1200kg'] && $total_weight <= 1200){
            return $method['method_setting']['1200kg'];
        }elseif($method['method_setting']['2400kg'] && $total_weight <= 2400){
            return $method['method_setting']['2400kg'];
        }elseif($method['method_setting']['3600kg'] && $total_weight <= 3600){
            return $method['method_setting']['3600kg'];
        }elseif($method['method_setting']['4800kg'] && $total_weight <= 2400){
            return $method['method_setting']['4800kg'];
        }elseif($method['method_setting']['6000kg'] && $total_weight <= 2400){
            return $method['method_setting']['6000kg'];
        }elseif($method['method_setting']['7200kg'] && $total_weight <= 2400){
            return $method['method_setting']['7200kg'];
        }elseif($method['method_setting']['8400kg'] && $total_weight <= 2400){
            return $method['method_setting']['8400kg'];
        }elseif($method['method_setting']['12000kg'] && $total_weight <= 2400){
            return $method['method_setting']['12000kg'];
        }else{
            //other condition
            return false; 
        }
    }

    // Convert yes/no to Y/N
    private function convert_yesno($value) {
        return strtoupper($value) === 'YES' ? 'Y' : 'N';
    }

    // Get product dimensions in consistent units 
    private function get_product_dimensions($product) {
        return [
            'length' => (float) $product->get_length(),
            'width' => (float) $product->get_width(),
            'height' => (float) $product->get_height(),
            'weight' => (float) $product->get_weight(),
            'dimension_unit' => get_option('woocommerce_dimension_unit'),
            'weight_unit' => get_option('woocommerce_weight_unit')
        ];
    }

    private function convert_dimension_to_cm($value, $from_unit) {
        $conversions = [
            'm' => 100,
            'cm' => 1,
            'mm' => 0.1,
            'in' => 2.54,
            'yd' => 91.44
        ];
        
        return $value * ($conversions[$from_unit] ?? 1);
    }

    private function convert_weight_to_kg($value, $from_unit) {
        $conversions = [
            'kg' => 1,
            'g' => 0.001,
            'lbs' => 0.453592,
            'oz' => 0.0283495
        ];
        
        return $value * ($conversions[$from_unit] ?? 1);
    }

    public function display_service_options($method) {
        // Only show for MRL method with customer choice enabled
        if ($method->method_id !== 'glint_shipping') {
            return;
        }
        
        // Get method settings
        $method_id = str_replace('glint_shipping_', '', $method->get_id());
        $method_settings = Glint_WC_Shipping_DB::get_method_by_id($method_id);
        
        if (!$method_settings || $method_settings['method_name'] !== 'mrl') {
            return;
        }
        
        $customer_choice_enabled = $method_settings['method_setting']['customer_choice_enabled'] ?? 'no';
        
        if ($customer_choice_enabled !== 'yes') {
            return;
        }
        
        // Get current choices
        $current_choices = $this->get_customer_service_choices();
        
        // Display service options
        echo '<div class="glint-mrl-services">';
        echo '<p>Additional Services</p>';
        
        $services = [
            'tailLiftPickup' => [
                'label' => 'Tail Lift Pickup',
                'description' => 'Required if you need a tail lift for pickup'
            ],
            'tailLiftDelivery' => [
                'label' => 'Tail Lift Delivery',
                'description' => 'Required if you need a tail lift for delivery'
            ],
            'handUnload' => [
                'label' => 'Hand Unload',
                'description' => 'Required for manual unloading'
            ]
        ];
        
        foreach ($services as $key => $service) {
            $checked = $current_choices[$key] === 'yes' ? 'checked' : '';
            
            echo '<div class="glint-service-option">';
            echo '<div class="glint-service-label">';
            echo '<span>' . esc_html($service['label']) . '</span>';
            echo '<div class="glint-service-description">' . esc_html($service['description']) . '</div>';
            echo '</div>';
            echo '<label class="glint-service-toggle">';
            echo '<input type="checkbox" name="glint_' . esc_attr($key) . '" ' . $checked . '>';
            echo '<span class="glint-service-toggle-slider"></span>';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    }

}