<?php

function get_woocommerce_order_statuses()
{
    return wc_get_order_statuses();
}

function your_plugin_woocommerce_inactive_notice()
{
    echo '<div class="notice notice-error is-dismissible">
        <p>"Bulk Order Editor" requires WooCommerce to be active. Please activate WooCommerce first.</p>
    </div>';
}

function your_plugin_deactivate_self()
{
    deactivate_plugins(plugin_basename(__FILE__));
}
