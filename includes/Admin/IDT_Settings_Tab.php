<?php

namespace IDT\Admin;

use IDT\Core\Helpers\Helper;
use IDT\Core\Logger\IDT_Log_Table;
use IDT\Core\Logger\IDT_Logger;
use IDT\Core\PrintApi\IDT_PrintApi_Connection;

if (!defined("ABSPATH")) {
    exit;
}

class IDT_Settings_Tab
{
    public static function init()
    {
        add_filter('woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50);
        add_action('woocommerce_settings_tabs_settings_idt', __CLASS__ . '::settings_tab');
        add_action('woocommerce_update_options_settings_idt', __CLASS__ . '::update_settings');
        add_action('wc_settings_idt_updated', __CLASS__ . '::reload_settings_page');
        add_filter('pre_update_option_wc_settings_idt_print_api_client_id', __CLASS__ . '::check_printapi_connection_client', 25, 2);
        add_filter('pre_update_option_wc_settings_idt_print_api_secret', __CLASS__ . '::check_printapi_connection_secret', 25, 2);

        //Logger specific actions
        add_action('woocommerce_after_settings_settings_idt', __CLASS__ . '::display_logger_buttons');
        add_action('admin_post_clear_idt_log', __CLASS__ . '::clear_log');
        add_action('admin_post_download_idt_log', __CLASS__ . '::download_log');
    }

    /**
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels
     *
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels
     */
    public static function add_settings_tab($settings_tabs)
    {
        $settings_tabs['settings_idt'] = _x('IDT Settings', 'ADMIN_PANEL', 'IDT');

        return $settings_tabs;
    }

    /**
     * Uses the WooCommerce admin fields API to output settings via the woocommerce_admin_fields() function.
     */
    public static function settings_tab()
    {
        woocommerce_admin_fields(self::get_settings());
    }

    /**
     * Uses the WooCommerce options API to save settings via the woocommerce_update_options() function.
     */
    public static function update_settings()
    {
        woocommerce_update_options(self::get_settings());
        do_action('wc_settings_idt_updated');
    }

    /**
     * Get all the settings for this plugin for @return array Array of settings.
     * @see woocommerce_admin_fields() function.
     *
     */
    public static function get_settings()
    {
        $settings = array(
            'section_title' => array(
                'name' => _x('Instant Design Tool general settings', "ADMIN_PANEL", 'IDT'),
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_settings_idt_section_title'
            ),
            'api_key' => array(
                'name' => _x('IDT API key', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'desc' => _x('This is automatically generated.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_api_key',
                'custom_attributes' => array(
                    'disabled' => true
                )
            ),
            'IDT_connect_code' => array(
                'name' => _x('IDT connect code', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'desc' => _x('You can generate this code in your Design Tool management panel. The plugin will not work without this code.', "ADMIN_PANEL", "IDT"),
                'id' => 'wc_settings_idt_connect_code'
            ),
            'idt_setting_synced_status' => array(
                'name' => _x('Connection Status', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'desc' => _x('This indicates whether the connection with Instant Design Tool has been made.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_settings_synced',
                'custom_attributes' => array(
                    'disabled' => true
                )
            ),
            'IDT_guest_mode' => array(
                'name' => _x('Guest checkout mode', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => _x('Do you want your customers to be able to proceed to checkout without logging in?', "ADMIN_PANEL", "IDT"),
                'id' => 'wc_settings_idt_guest_mode',
                'options' => array(
                    true => _x('Yes', "ADMIN_PANEL", "IDT"),
                    false => _x('No', "ADMIN_PANEL", "IDT"),
                ),
            ),
            'idt_show_product_details_in_cart' => array(
                'name' => _x('Show product details in cart?', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => _x('This option will determine if the project details will be shown in the cart.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_display_project_details',
                'options' => array(
                    'yes' => _x('Yes', "ADMIN_PANEL", "IDT"),
                    'no' => _x('No', "ADMIN_PANEL", "IDT"),
                ),
            ),
            'idt_show_product_details_in_cart' => array(
                'name' => _x('Enable logging?', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => _x('If set to true, the plugin will log errors to the database.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_logging_enabled',
                'options' => array(
                    true => _x('Yes', "ADMIN_PANEL", "IDT"),
                    false => _x('No', "ADMIN_PANEL", "IDT"),
                ),
            ),
            'idt_use_editable_product_button_texts' => array(
                'name' => _x('Use custom texts for buttons?', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => "Please note that this functionality has been replaced by translation and will be removed in later releases.",
                'id' => 'wc_settings_idt_use_custom_texts',
                'options' => array(
                    'yes' => _x('Yes', "ADMIN_PANEL", "IDT"),
                    'no' => _x('No', "ADMIN_PANEL", "IDT"),
                ),
            ),

            'editable_product_button_text' => array(
                'name' => _x('Editable product button text', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'value' => _x('View product', "ADMIN_PANEL", 'IDT'),
                'desc' => "Please note that this functionality has been replaced by translation and will be removed in later releases.",
                'id' => 'wc_settings_idt_button_text',
                'custom_attributes' => [
                    "disabled" => true
                ]
            ),
            'editable_product_button_text_single' => array(
                'name' => _x('Editable product button text on the product page', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'value' => _x('Start design', "ADMIN_PANEL", 'IDT'),
                'desc' => "Please note that this functionality has been replaced by translation and will be removed in later releases.",
                'id' => 'wc_settings_idt_button_text_single',
                'custom_attributes' => [
                    "disabled" => true
                ]
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_settings_idt_section_end'
            ),

            'section_title_2' => array(
                'name' => _x('Print API settings', "ADMIN_PANEL", 'IDT'),
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_settings_idt_print_api_section_title'
            ),
            'use_print_api' => array(
                'name' => _x('Use Print API?', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => _x('Would you like to make use of the Print API?', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_use_printapi',
                'options' => array(
                    'never' => _x('Never', "ADMIN_PANEL", 'IDT'),
                    'optionally' => _x('Optionally', "ADMIN_PANEL", 'IDT'),
                    'always' => _x('Always', "ADMIN_PANEL", 'IDT')
                )
            ),
            'only_paid_orders' => array(
                'name' => _x('Allow unpaid orders to be forwarded?', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => _x('If you have selected "Always" as usage option. Would you also like unpaid orders to be forwarded?', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_only_paid_orders',
                'default' => 'paid',
                'options' => array(
                    'unpaid' => _x('Allow unpaid orders to be forwarded', "ADMIN_PANEL", 'IDT'),
                    'paid' => _x('Don\'t allow unpaid orders to be forwarded', "ADMIN_PANEL", 'IDT'),
                )
            ),
            'print_api_client_id' => array(
                'name' => _x('Print API client id', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'desc' => _x('Please provide the Print API client id. Note: this is not necessary if you are not using Print API.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_print_api_client_id',
            ),
            'print_api_secret' => array(
                'name' => _x('Print API secret', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'desc' => _x('Please provide the Print API secret, note that this is not necessary if you are not using the Print API.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_print_api_secret',
            ),
            'printapi_environment' => array(
                'name' => _x('Print API environment', "ADMIN_PANEL", 'IDT'),
                'type' => 'select',
                'desc' => _x('In which environment should the Print API be used? Please note that selecting live will result in orders being fulfilled.
			 The system will automatically reset your environment to test if it detects a live environment with test keys.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_printapi_environment',
                'options' => array(
                    'test' => _x('Test', "ADMIN_PANEL", 'IDT'),
                    'live' => _x('Live', "ADMIN_PANEL", 'IDT')
                )
            ),
            'print_api_status' => array(
                'name' => _x('Connection status', "ADMIN_PANEL", 'IDT'),
                'type' => 'text',
                'desc' => _x('This indicates whether the Print API is connected or not.', "ADMIN_PANEL", 'IDT'),
                'id' => 'wc_settings_idt_print_api_status',
                'custom_attributes' => array(
                    'disabled' => true
                )
            ),
            'section_end_2' => array(
                'type' => 'sectionend',
                'id' => 'wc_settings_idt_print_api_section_end'
            ),
        );

        if (!Helper::settings_synced_already()) {
            $settings['print_api_client_id']['custom_attributes'] = ['disabled' => 'true'];
            $settings['print_api_secret']['custom_attributes'] = ['disabled' => 'true'];
            $settings['printapi_environment']['custom_attributes'] = ['disabled' => 'true'];
            $settings['use_print_api']['custom_attributes'] = ['disabled' => 'true'];
            $settings['only_paid_orders']['custom_attributes'] = ['disabled' => 'true'];
            $settings['section_title_2']['desc'] = _x('This section will be enabled after the inital connection to Instant Design Tool has been made.', "ADMIN_PANEL", 'IDT');
        }

        if (get_option('wc_settings_idt_settings_synced')) {
            $settings['idt_setting_synced_status']['value'] = "connected";
        } else {
            $settings['idt_setting_synced_status']['value'] = "not connected";
        }

        return $settings;
    }

    /**
     * This function will run after reloading the admin page.
     */
    public static function reload_settings_page()
    {
        header('Location: ' . admin_url("admin.php?page=wc-settings&tab=settings_idt"));
    }

    /**
     * @param $new_val
     * @param $old_val
     *
     * @return mixed
     */
    public static function check_printapi_connection_client($new_val, $old_val)
    {
        if ($new_val !== $old_val && $new_val !== 'invalid key') {
            new IDT_PrintApi_Connection(true, $new_val);
        }

        return $new_val;
    }

    /**
     * @param $new_val
     * @param $old_val
     *
     * @return mixed
     */
    public static function check_printapi_connection_secret($new_val, $old_val)
    {
        if ($new_val !== $old_val && $new_val !== 'invalid key') {
            new IDT_PrintApi_Connection(true, null, $new_val);
        }

        return $new_val;
    }

    public static function display_logger_buttons()
    {
        ?>
        <form method="POST" action="<?= esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('download_log', 'download_idt_log_nonce')
            ?>
            <input type="hidden" name="action" value="download_idt_log">
            <input id="test-settings" type="submit" value="Download IDT Log" class="button">

        </form>
        <form method="POST" action="<?= esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
            <?php wp_nonce_field('clear_log', 'clear_idt_log_nonce')
            ?>
            <input name="action" type="hidden" value="clear_idt_log">
            <input type="submit" value="Clear IDT Log" class="button">
        </form>
        <?php
    }

    /**
     * @return void
     */
    public static function clear_log()
    {
        if (!is_user_logged_in() || !in_array('administrator', wp_get_current_user()->roles, true)) {
            return;
        }

        if (!wp_verify_nonce($_REQUEST['clear_idt_log_nonce'], 'clear_log')) {
            return;
        }

        IDT_Log_Table::truncate();
        wp_safe_redirect($_REQUEST['_wp_http_referer']);
    }

    /**
     * @return void
     */
    public static function download_log()
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        if (!wp_verify_nonce($_REQUEST['download_idt_log_nonce'], 'download_log')) {
            return;
        }

        IDT_Log_Table::export();
    }
}

