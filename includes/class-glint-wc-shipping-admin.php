<?php
class Glint_WC_Shipping_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_glint_save_shipping_methods', [__CLASS__, 'save_shipping_methods']);
    }

    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'CHT Shipping Settings',
            'CHT Shipping',
            'manage_options',
            'glint-wc-shipping',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_glint-wc-shipping') return;
        
        // Enqueue code editor for formulas
        wp_enqueue_code_editor(['type' => 'javascript']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
        
        // Custom assets
        wp_enqueue_script(
            'glint-shipping-admin',
            GLINT_WC_SHIPPING_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            GLINT_WC_SHIPPING_VERSION,
            true
        );
        
        wp_enqueue_style(
            'glint-shipping-admin',
            GLINT_WC_SHIPPING_URL . 'assets/css/admin.css',
            [],
            GLINT_WC_SHIPPING_VERSION
        );

        // Add nonce for AJAX security
        wp_localize_script('glint-shipping-admin', 'glintShippingAdmin', [
            'nonce' => wp_create_nonce('glint_shipping_nonce')
        ]);
    }

    public static function render_settings_page() {
        $is_enabled = get_option('glint_shipping_enable', 'no') === 'yes';

        $methods = Glint_WC_Shipping_DB::get_all_methods();
        ?>
        <div class="wrap glint-shipping-settings">
            <h1>Shipping Settings</h1>
            <form id="glint-shipping-form">

                <div class="enable-section">
                    <label>
                        <input type="checkbox" name="enable_shipping" <?php checked($is_enabled, true); ?> value="1">
                        Enable CHT Shipping Methods
                    </label>
                    <p class="description"> After enabled, setup the plugin shipping method for all avaliable shipping zone in WooCommerce shipping setting page.</p>
                </div>

                <div id="shipping-methods-repeater">
                    <?php foreach ($methods as $index => $method): ?>
                        <div class="method-row" data-index="<?php echo $index; ?>">
                            <div class="name-section">
                                <h2>Zone:</h2>
                                <input type="text" name="methods[<?php echo $index; ?>][setting_name]" 
                                    value="<?php echo esc_attr($method['setting_name']); ?>" 
                                    placeholder="e.g. Local Delivery">
                            </div>
                            
                            <div class="postcode-section">
                                <label>Postcodes (one per line):</label>
                                <textarea name="methods[<?php echo $index; ?>][postcode]" rows="10"><?php echo esc_textarea($method['postcode']); ?></textarea>
                            </div>
                            
                            <div class="method-section">
                                <label>Shipping Method:</label>
                                <select name="methods[<?php echo $index; ?>][method_name]" class="method-select">
                                    <option value="custom_formula" <?php selected($method['method_name'], 'custom_formula'); ?>>Custom Formula</option>
                                    <option value="mrl" <?php selected($method['method_name'], 'mrl'); ?>>MRL</option>
                                    <option value="sydney_delivery" <?php selected($method['method_name'], 'sydney_delivery'); ?>>Sydney Delivery</option>
                                </select>
                            </div>
                            
                            <div class="settings-section section-<?php echo $index; ?>">
                                <?php if ($method['method_name'] === 'custom_formula'): ?>
                                    <!-- Formula -->
                                    <label>Formula (JavaScript):</label>
                                    <textarea name="methods[<?php echo $index; ?>][method_setting][formula]" class="formula-editor editor-<?php echo $index; ?>"><?php echo esc_textarea($method['method_setting']['formula'] ?? ''); ?></textarea>

                                <?php elseif ($method['method_name'] === 'mrl'): ?>
                                    <div class="row">
                                        <div class="method-option">
                                            <!-- Account Name -->                                   
                                            <label>Account Name:</label>
                                            <input type="text" name="methods[<?php echo $index; ?>][method_setting][account]" value="<?php echo esc_attr($method['method_setting']['account'] ?? ''); ?>">
                                        </div>
                                        <div class="method-option">
                                        <!-- Password -->
                                            <label>Password:</label>
                                            <input type="password" name="methods[<?php echo $index; ?>][method_setting][password]" value="<?php echo esc_attr($method['method_setting']['password'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="method-option">
                                            <!-- Tail Lift Pickup -->
                                            <label>Tail Lift Pickup:</label>
                                            <select name="methods[<?php echo $index; ?>][method_setting][tailLiftPickup]" class="setting-select">
                                                <option value="yes" <?php selected($method['method_setting']['tailLiftPickup'], 'yes'); ?>>Yes</option>
                                                <option value="no" <?php selected($method['method_setting']['tailLiftPickup'], 'no'); ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="method-option">
                                            <!-- Tail Lift Pickup -->
                                            <label>Tail Lift Delivery:</label>
                                            <select name="methods[<?php echo $index; ?>][method_setting][tailLiftDelivery]" class="setting-select">
                                                <option value="yes" <?php selected($method['method_setting']['tailLiftDelivery'], 'yes'); ?>>Yes</option>
                                                <option value="no" <?php selected($method['method_setting']['tailLiftDelivery'], 'no'); ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="method-option">
                                            <!-- Hand Unload -->
                                            <label>Hand Unload:</label>
                                            <select name="methods[<?php echo $index; ?>][method_setting][handUnload]" class="setting-select">
                                                <option value="yes" <?php selected($method['method_setting']['handUnload'], 'yes'); ?>>Yes</option>
                                                <option value="no" <?php selected($method['method_setting']['handUnload'], 'no'); ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="method-option">
                                            <!-- Customer can select -->
                                            <label>Customer Can Choose Delivery Methods?</label>
                                            <select name="methods[<?php echo $index; ?>][method_setting][customer_choice_enabled]" class="setting-select">
                                                <option value="yes" <?php selected($method['method_setting']['customer_choice_enabled'], 'yes'); ?>>Yes</option>
                                                <option value="no" <?php selected($method['method_setting']['customer_choice_enabled'], 'no'); ?>>No</option>
                                            </select>
                                        </div>
                                    </div>

                                <?php elseif ($method['method_name'] === 'sydney_delivery'): ?>
                                    <!-- Price/Pallet -->
                                    <label>Price Per Pallet:</label>
                                    <input type="text" name="methods[<?php echo $index; ?>][method_setting][price]" value="<?php echo esc_attr($method['method_setting']['price'] ?? ''); ?>">
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" class="remove-method">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-new-method" class="button">Add New Method</button>
                <button type="submit" id="save-methods" class="button-primary">Save Settings</button>
            </form>
            
            <!-- Template for new method rows -->
            <script type="text/template" id="method-row-template">
                <div class="method-row" data-index="{{index}}">
                    <div class="name-section">
                        <h2>Setting Name:</h2>
                        <input type="text" name="methods[{{index}}][setting_name]" 
                            placeholder="e.g. Local Delivery">
                    </div>
                    
                    <div class="postcode-section">
                        <label>Postcodes (one per line):</label>
                        <textarea name="methods[{{index}}][postcode]" rows="10"></textarea>
                    </div>
                    
                    <div class="method-section">
                        <label>Shipping Method:</label>
                        <select name="methods[{{index}}][method_name]" class="method-select">
                            <option value="custom_formula">Custom Formula</option>
                            <option value="mrl">MRL</option>
                            <option value="sydney_delivery">Sydney Delivery</option>
                        </select>
                    </div>
                    
                    <div class="settings-section section-{{index}}">
                        <label>Formula (JavaScript):</label>
                        <textarea name="methods[{{index}}][method_setting][formula]" class="formula-editor editor-{{index}}"></textarea>
                    </div>
                    
                    <button type="button" class="remove-method">Remove</button>
                </div>
            </script>
        </div>
        <?php
    }

    public static function save_shipping_methods() {
        check_ajax_referer('glint_shipping_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Save enable/disable setting
        $is_enabled = isset($_POST['enable_shipping']) && $_POST['enable_shipping'] === '1';
        update_option('glint_shipping_enable', $is_enabled ? 'yes' : 'no');
        
        $methods = isset($_POST['methods']) ? $_POST['methods'] : [];
        $sanitized_methods = [];
        
        foreach ($methods as $method) {
            $sanitized = [
                'setting_name' => sanitize_text_field($method['setting_name']),
                'postcode' => sanitize_textarea_field($method['postcode']),
                'method_name' => sanitize_text_field($method['method_name']),
                'method_setting' => []
            ];
            
            if ($sanitized['method_name'] === 'custom_formula') {
                $sanitized['method_setting']['formula'] = isset($method['method_setting']['formula']) ? 
                    sanitize_textarea_field($method['method_setting']['formula']) : '';
            } 
            elseif ($sanitized['method_name'] === 'mrl') {
                $sanitized['method_setting']['account'] = isset($method['method_setting']['account']) ? 
                    sanitize_text_field($method['method_setting']['account']) : '';
                $sanitized['method_setting']['password'] = isset($method['method_setting']['password']) ? 
                    sanitize_text_field($method['method_setting']['password']) : '';
                $sanitized['method_setting']['tailLiftPickup'] = isset($method['method_setting']['tailLiftPickup']) ? 
                    sanitize_text_field($method['method_setting']['tailLiftPickup']) : '';
                $sanitized['method_setting']['tailLiftDelivery'] = isset($method['method_setting']['tailLiftDelivery']) ? 
                    sanitize_text_field($method['method_setting']['tailLiftDelivery']) : '';
                $sanitized['method_setting']['handUnload'] = isset($method['method_setting']['handUnload']) ? 
                    sanitize_text_field($method['method_setting']['handUnload']) : '';
                $sanitized['method_setting']['customerChoose'] = isset($method['method_setting']['customerChoose']) ? 
                    sanitize_text_field($method['method_setting']['customerChoose']) : '';
            }
            elseif ($sanitized['method_name'] === 'sydney_delivery') {
                $sanitized['method_setting']['price'] = isset($method['method_setting']['price']) ? 
                    sanitize_text_field($method['method_setting']['price']) : '';
            }
            
            $sanitized_methods[] = $sanitized;
        }
        
        Glint_WC_Shipping_DB::save_methods($sanitized_methods);
        wp_send_json_success('Settings saved');
    }
}