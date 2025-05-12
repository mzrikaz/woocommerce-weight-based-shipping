<?php
/*
 * Plugin Name: WooCommerce Weight Based Shipping
 * Description: A simple WooCommerce shipping method for weight-based shipping with configurable base price and additional price per kilogram.
 * Version: 1.1
 * Author: Witsberry
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('woocommerce_shipping_init', 'wbs_shipping_method_init');

    function wbs_shipping_method_init() {
        class WC_Shipping_Weight_Based extends WC_Shipping_Method {
            public function __construct($instance_id = 0) {
                $this->id = 'weight_based_shipping';
                $this->instance_id = absint($instance_id);
                $this->method_title = __('Weight Based Shipping', 'woocommerce');
                $this->method_description = __('Custom shipping method based on cart weight', 'woocommerce');
                $this->supports = array('shipping-zones', 'instance-settings', 'instance-settings-modal');
                $this->init();
            }

            public function init() {
                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->get_option('title');
                $this->base_weight = $this->get_option('base_weight');
                $this->base_price = $this->get_option('base_price');
                $this->additional_price = $this->get_option('additional_price');

                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            public function init_form_fields() {
                $this->instance_form_fields = array(
                    'title' => array(
                        'title' => __('Method Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('Weight Based Shipping', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                    'base_weight' => array(
                        'title' => __('Base Weight (kg)', 'woocommerce'),
                        'type' => 'number',
                        'description' => __('The base weight in kilograms covered by the base price.', 'woocommerce'),
                        'default' => 1,
                        'desc_tip' => true,
                        'custom_attributes' => array('step' => '0.01', 'min' => '0'),
                    ),
                    'base_price' => array(
                        'title' => __('Base Price', 'woocommerce'),
                        'type' => 'number',
                        'description' => __('The base price for the base weight.', 'woocommerce'),
                        'default' => 5,
                        'desc_tip' => true,
                        'custom_attributes' => array('step' => '0.01', 'min' => '0'),
                    ),
                    'additional_price' => array(
                        'title' => __('Additional Price per kg', 'woocommerce'),
                        'type' => 'number',
                        'description' => __('The additional price for every kilogram above the base weight.', 'woocommerce'),
                        'default' => 2,
                        'desc_tip' => true,
                        'custom_attributes' => array('step' => '0.01', 'min' => '0'),
                    ),
                );
            }

            public function calculate_shipping($package = array()) {
                $total_weight = 0;
                foreach ($package['contents'] as $item_id => $values) {
                    $product = $values['data'];
                    $weight = $product->get_weight();
                    $quantity = $values['quantity'];
                    if ($weight) {
                        $total_weight += floatval($weight) * $quantity;
                    }
                }

                // Convert weight to kilograms if WooCommerce is using grams
                if (get_option('woocommerce_weight_unit') === 'g') {
                    $total_weight = $total_weight / 1000;
                }

                // Calculate shipping cost
                $base_weight = floatval($this->base_weight);
                $base_price = floatval($this->base_price);
                $additional_price = floatval($this->additional_price);

                if ($total_weight <= $base_weight) {
                    $cost = $base_price;
                } else {
                    $extra_weight = $total_weight - $base_weight;
                    $cost = $base_price + ($extra_weight * $additional_price);
                }

                $this->add_rate(array(
                    'id' => $this->get_rate_id(),
                    'label' => $this->title,
                    'cost' => $cost,
                ));
            }
        }
    }

    add_filter('woocommerce_shipping_methods', 'add_wbs_shipping_method');
    function add_wbs_shipping_method($methods) {
        $methods['weight_based_shipping'] = 'WC_Shipping_Weight_Based';
        return $methods;
    }
}