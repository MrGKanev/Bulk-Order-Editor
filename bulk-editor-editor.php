<?php

/**
 * Plugin Name:             Bulk Order Editor
 * Plugin URI:              https://github.com/MrGKanev/Bulk-Order-Editor/
 * Description:             Bulk Order Editor is a simple plugin that allows you to change the status of multiple WooCommerce orders at once.
 * Version:                 0.0.3
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 MIT
 * Requires at least:       6.4
 * Requires PHP:            7.4
 * WC requires at least:    6.0
 * WC tested up to:         9.1.2
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    define('__FP_FILE__', __FILE__);

    // Include necessary files in the correct order
    require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
    require_once plugin_dir_path(__FILE__) . 'includes/hooks.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
    require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';

    // Register hooks
    add_action('admin_enqueue_scripts', 'enqueue_custom_admin_script');
    add_action('admin_enqueue_scripts', 'enqueue_plugin_styles');
    add_action('admin_menu', 'register_custom_woocommerce_menu_page');
    add_action('wp_ajax_update_single_order', 'handle_update_single_order_ajax');
} else {
    add_action('admin_notices', 'your_plugin_woocommerce_inactive_notice');
    add_action('admin_init', 'your_plugin_deactivate_self');
}
