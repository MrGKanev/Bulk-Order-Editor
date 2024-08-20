<?php
class Bulk_Order_Editor_Main {
    private $admin;

    public function __construct() {
        $this->load_dependencies();
        $this->admin = new Bulk_Order_Editor_Admin();
    }

    public function run() {
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_ajax_hooks();
    }

    private function load_dependencies() {
        require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'includes/class-bulk-order-editor-i18n.php';
        require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'admin/class-bulk-order-editor-admin.php';
    }

    private function set_locale() {
        $plugin_i18n = new Bulk_Order_Editor_i18n();
        add_action('plugins_loaded', array($plugin_i18n, 'load_plugin_textdomain'));
    }

    private function define_admin_hooks() {
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
        add_action('admin_menu', array($this->admin, 'add_plugin_admin_menu'));
    }

    private function define_ajax_hooks() {
        add_action('wp_ajax_batch_update_orders', array($this->admin, 'handle_batch_update_orders'));
        add_action('wp_ajax_update_single_order', array($this->admin, 'handle_update_single_order_ajax'));
    }
}