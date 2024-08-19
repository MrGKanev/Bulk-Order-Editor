<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue admin scripts and styles
 */
function boe_enqueue_admin_scripts($hook)
{
    // Only enqueue on the Bulk Order Editor page
    if ('woocommerce_page_bulk-order-editor' !== $hook) {
        return;
    }

    wp_enqueue_script('boe-admin-script', BULK_ORDER_EDITOR_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), BULK_ORDER_EDITOR_VERSION, true);
    wp_localize_script('boe-admin-script', 'boeAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bulk_order_editor_nonce')
    ));

    wp_enqueue_style('boe-admin-style', BULK_ORDER_EDITOR_PLUGIN_URL . 'assets/css/admin.css', array(), BULK_ORDER_EDITOR_VERSION);
}
add_action('admin_enqueue_scripts', 'boe_enqueue_admin_scripts');

/**
 * Add Bulk Order Editor submenu to WooCommerce menu
 */
function boe_add_admin_menu()
{
    add_submenu_page(
        'woocommerce',
        __('Bulk Order Editor', 'bulk-order-editor'),
        __('Bulk Order Editor', 'bulk-order-editor'),
        'manage_woocommerce',
        'bulk-order-editor',
        'boe_admin_page_content'
    );
}
add_action('admin_menu', 'boe_add_admin_menu');

/**
 * Register plugin settings
 */
function boe_register_settings()
{
    register_setting('boe_options_group', 'boe_default_status');
    register_setting('boe_options_group', 'boe_orders_per_page');
}
add_action('admin_init', 'boe_register_settings');

/**
 * Add settings link to plugin page
 */
function boe_add_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=bulk-order-editor">' . __('Settings', 'bulk-order-editor') . '</a>';
    array_push($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'boe_add_settings_link');

/**
 * Add custom bulk actions to WooCommerce orders list
 */
function boe_add_bulk_actions($bulk_actions)
{
    $bulk_actions['bulk_edit_orders'] = __('Bulk Edit', 'bulk-order-editor');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-shop_order', 'boe_add_bulk_actions');

/**
 * Handle custom bulk action
 */
function boe_handle_bulk_action($redirect_to, $action, $post_ids)
{
    if ($action !== 'bulk_edit_orders') {
        return $redirect_to;
    }

    $redirect_to = add_query_arg('bulk_edit_orders', count($post_ids), $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-shop_order', 'boe_handle_bulk_action', 10, 3);

/**
 * Display admin notices
 */
function boe_admin_notices()
{
    if (!isset($_REQUEST['bulk_edit_orders'])) {
        return;
    }

    $count = intval($_REQUEST['bulk_edit_orders']);
    $message = sprintf(
        _n(
            'Bulk edited %s order.',
            'Bulk edited %s orders.',
            $count,
            'bulk-order-editor'
        ),
        number_format_i18n($count)
    );
    echo "<div class='updated'><p>{$message}</p></div>";
}
add_action('admin_notices', 'boe_admin_notices');

/**
 * Initialize the plugin
 */
function boe_init()
{
    load_plugin_textdomain('bulk-order-editor', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'boe_init');
