<?php
class Bulk_Order_Editor
{
    public function run()
    {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
    }

    private function load_dependencies()
    {
        require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'includes/class-bulk-order-editor-loader.php';
        require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'includes/class-bulk-order-editor-i18n.php';
        require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'admin/class-bulk-order-editor-admin.php';
    }

    private function set_locale()
    {
        $plugin_i18n = new Bulk_Order_Editor_i18n();
        add_action('plugins_loaded', array($plugin_i18n, 'load_plugin_textdomain'));
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new Bulk_Order_Editor_Admin();
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
    }
}
