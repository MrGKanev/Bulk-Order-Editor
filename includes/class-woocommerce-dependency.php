<?php
class Bulk_Order_Editor_WooCommerce_Dependency
{
    private $plugin_file;

    public function __construct($plugin_file)
    {
        $this->plugin_file = $plugin_file;
        add_action('admin_notices', array($this, 'woocommerce_inactive_notice'));
        add_action('admin_init', array($this, 'deactivate_self'));
    }

    public function woocommerce_inactive_notice()
    {
        echo '<div class="notice notice-error is-dismissible">
            <p>"Bulk Order Editor" requires WooCommerce to be active. Please activate WooCommerce first.</p>
        </div>';
    }

    public function deactivate_self()
    {
        deactivate_plugins(plugin_basename($this->plugin_file));
    }
}
