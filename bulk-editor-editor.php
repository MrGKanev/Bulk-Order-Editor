<?php

/**
 * Plugin Name: Bulk Order Editor
 * Plugin URI: https://github.com/MrGKanev/Bulk-Order-Editor/
 * Description: Bulk Order Editor is a simple plugin that allows you to change the status of multiple WooCommerce orders at once.
 * Version: 0.0.5
 * Author: Gabriel Kanev
 * Author URI: https://gkanev.com
 * License: MIT
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.1.4
 */

defined('ABSPATH') || exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woocommerce-dependency.php';
    new Bulk_Order_Editor_WooCommerce_Dependency(__FILE__);
    return;
}

define('BULK_ORDER_EDITOR_PLUGIN_FILE', __FILE__);
define('BULK_ORDER_EDITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'includes/class-bulk-order-editor.php';

function run_bulk_order_editor()
{
    $plugin = new Bulk_Order_Editor();
    $plugin->run();
}

run_bulk_order_editor();
