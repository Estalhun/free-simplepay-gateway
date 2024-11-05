<?php

/**
 * Plugin Name:       Free OTP SimplePay Gateway for WooCommerce
 * Plugin URI:        https://github.com/Estalhun/free-simplepay-gateway
 * Description:       Free OTP SimplePay payment gateway integration for WooCommerce.
 * Version:           2.9.7
 * Author:            Estalhun & Cyone64
 * License:           GPL-3.0
 * License URI:       https://opensource.org/license/gpl-3-0
 * Text Domain:       free-simplepay
 * Domain Path:       /languages/
 * Requires at least: 5.2
 * Tested up to:      6.4.1
 * Requires PHP:      7.2
 * WC tested up to:   8.3.1
 * HPOS Compatible: true
 */

// Pull in the autoloader
require_once __DIR__.'/autoload.php';

// Register the activation and the deactivation hooks
register_activation_hook(__FILE__, [FSG\SimplePay\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [FSG\SimplePay\Plugin::class, 'deactivate']);

// Declare HPOS & Checkout Block compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Boot the plugin
FSG\SimplePay\Plugin::boot();
