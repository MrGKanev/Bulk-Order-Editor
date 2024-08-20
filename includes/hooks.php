<?php

function enqueue_custom_admin_script()
{
    wp_enqueue_script('bulk-order-editor-js', plugin_dir_url(__FP_FILE__) . 'js/bulk-order-editor.js', array('jquery'), '1.0', true);
    wp_localize_script('bulk-order-editor-js', 'bulkOrderEditor', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bulk_order_editor_nonce')
    ));
}

function enqueue_plugin_styles()
{
    $screen = get_current_screen();
    if ($screen->id === 'woocommerce_page_order-status-changer') {
        wp_enqueue_style('bulk-order-editor-css', plugin_dir_url(__FILE__) . '../css/style.css', array(), '1.0', 'all');
    }
}

function register_custom_woocommerce_menu_page()
{
    add_submenu_page(
        'woocommerce',
        'Bulk Order Editor',
        'Bulk Order Editor',
        'manage_woocommerce',
        'order-status-changer',
        'order_status_editor_page_content'
    );
}
