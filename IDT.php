<?php
/*
 * Plugin name: Instant Design Tool
 * Plugin URI: https://www.instantdesigntool.com
 * Description: Connect WooCommerce to your Instant Design Tool.
 * Version: 3.0.5
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 5.0
 * WC tested up to: 7.9.0
 * Text Domain: IDT
 */

require('vendor/autoload.php');

class IDT
{
    private $PLUGIN_BASE;

    public function __construct()
    {
        if (!defined("ABSPATH")) {
            exit;
        } // Exit if accessed directly

        if (!defined('IDT_VERSION')) {
            define('IDT_VERSION', '1.4.0');
        }

        $this->PLUGIN_BASE = plugin_basename(__FILE__);
        register_activation_hook(__FILE__, array($this, 'add_activator'));
        register_deactivation_hook(__FILE__, array($this, 'add_deactivator'));

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            new \IDT\Core\Initializer();
        } else {
            unset($_GET['activate']);
            add_action('admin_notices', array($this, "display_woocommerce_missing_notice"));
            \IDT\Core\IDT_Deactivator::deactivate($this->PLUGIN_BASE);
        }
    }

    public function display_woocommerce_missing_notice()
    {
        \IDT\Admin\Templates\Admin_Notice::echoNotice("Please make sure WooCommerce is activated", "error");
    }

    public function add_activator()
    {
        \IDT\Core\IDT_Activator::activate();
    }

    public function add_deactivator()
    {
        \IDT\Core\IDT_Deactivator::deactivate();
    }
}

new IDT();
