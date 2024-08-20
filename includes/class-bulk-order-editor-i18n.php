<?php
class Bulk_Order_Editor_i18n
{
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'bulk-order-editor',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
