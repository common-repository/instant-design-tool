<?php

namespace IDT\Core;

if (!defined('ABSPATH')) {
    exit;
}

use IDT\Admin\IDT_Button_Post_Type;
use IDT\Admin\IDT_Forward_On_Paid;
use IDT\Admin\IDT_Settings_Tab;
use IDT\Admin\IDT_Update_Manager;
use IDT\Admin\Templates\Admin_Notice;
use IDT\Admin\Templates\IDT_Flash_Notice_Handler;
use IDT\Core\Api\Ajax\IDT_Ajax_Retry_Pdf_Output_Request;
use IDT\Core\Api\Settings\IDT_Settings_Syncer;
use IDT\Core\Api\Snapshot\IDT_Check_Output_Pdf_Quota;
use IDT\Core\Api\Snapshot\IDT_Render_Manager;
use IDT\Core\Api\Snapshot\IDT_SnapshotEndpoint_Push;
use IDT\Core\Api\User\IDT_UserEndpoint_Identify;
use IDT\Core\Api\User\IDT_UserEndpoint_LoginFromEditor;
use IDT\Core\Api\User\IDT_UserEndpoint_Redirect;
use IDT\Core\Api\Webhook\IDT_Webhook_ListenToOutputReady;
use IDT\Core\Api\Webhook\IDT_Webhook_Order_StatusChanged;
use IDT\Core\Elementor\IDT_Add_Elementor_IDT_Category;
use IDT\Core\Elementor\IDT_Change_Link_On_Elementor_Button;
use IDT\Core\Elementor\IDT_Check_Elementor_Active;
use IDT\Core\Elementor\IDT_Display_Elementor_Notice;
use IDT\Core\Elementor\IDT_Register_Editable_Product_ATC_Widget;
use IDT\Core\Helpers\Helper;
use IDT\Core\Logger\IDT_Logger;
use IDT\Core\LoginCookie\IDT_UserCookie;
use IDT\Core\PrintApi\IDT_PrintApi;
use IDT\Core\PrintApi\IDT_PrintApi_Connection;
use IDT\Core\WooCommerce\IDT_Cart_Session;
use IDT\Core\WooCommerce\IDT_Change_Order_Page_Thumbnail;
use IDT\Core\WooCommerce\IDT_Display_output_request_status;
use IDT\Core\WooCommerce\IDT_Display_PrintApi_Forward_Status;
use IDT\Core\WooCommerce\IDT_Register_Order_Statuses;
use IDT\Core\WooCommerce\IDT_Show_Pid_On_Product_Page;
use IDT\Core\WooCommerce\IDT_Update_Order_Status;
use IDT\Core\WooCommerce\IDT_WooCommerce_Handler;
use IDT\Core\WooCommerce\IDT_WooCommerce_ProductFields;

class Initializer
{
    public function __construct()
    {
        add_action(
            'init',
            function () {
                load_plugin_textdomain('IDT');
            }
        );

        //This is called statically because the menu pages are added statically
        IDT_Settings_Tab::init();

        add_action(
            'admin_notices',
            function () {
                new IDT_Flash_Notice_Handler();
            }
        );

        require_once 'WooCommerce/Register_Editable_product.php';

        //This will handle the woocommerce editable product type registration
        new IDT_WooCommerce_Handler();
        new IDT_WooCommerce_ProductFields();

        if ($this->check_if_first_run()) {
            $this->do_first_run();
        }

        if (!$this->api_key_is_set()) {
            $this->generate_api_key();
        }

        if (!$this->settings_sync_token_set() && !Helper::settings_synced_already()) {
            $this->prompt_for_settings_sync();
        }

        if ($this->settings_sync_token_set()) {
            add_action('init', array($this, 'run_settings_syncer'));
        }

        if (!$this->printapi_is_connected() && Helper::printapi_keys_are_set()) {
            new IDT_PrintApi_Connection(true);
        } elseif (!Helper::printapi_keys_are_set()) {
            update_option('wc_settings_idt_print_api_status', 'error');
        }

        $this->admin_check_printapi_environment();

        if (is_admin() && $this->plugin_was_updated()) {
            new IDT_Update_Manager();
        }

        /*if ($this->settings_sync_token_set() && $this->is_login_cookie_update_pending()) {
            $this->apply_login_cookie_update();
        } */

        if ($this->is_login_cookie_update_pending()) {
            $this->apply_login_cookie_update();
        }

        new IDT_UserCookie();
        new IDT_Cart_Session();
        new IDT_UserEndpoint_Identify();
        new IDT_UserEndpoint_LoginFromEditor();
        new IDT_UserEndpoint_Redirect();
        new IDT_SnapshotEndpoint_Push();
        new IDT_Webhook_ListenToOutputReady();
        new IDT_Webhook_Order_StatusChanged();
        new IDT_Change_Order_Page_Thumbnail();
        new IDT_PrintApi();
        new IDT_Register_Order_Statuses();
        new IDT_Update_Order_Status();
        new IDT_Show_Pid_On_Product_Page();
        new IDT_Check_Output_Pdf_Quota();
        new IDT_Display_PrintApi_Forward_Status();
        new IDT_Display_output_request_status();
        new IDT_Check_Elementor_Active();
        new IDT_Button_Post_Type();
        new IDT_Display_Elementor_Notice();
        new IDT_Forward_On_Paid();
        new IDT_Render_Manager();
        new IDT_Ajax_Retry_Pdf_Output_Request();

        if (get_option('IDT_elementor_mode') === '1') {
            new IDT_Change_Link_On_Elementor_Button();
            new IDT_Add_Elementor_IDT_Category();
            new IDT_Register_Editable_Product_ATC_Widget();
        }
    }

    /**
     * Checks if it's the first time the plugin runs
     * @return boolean
     */
    public function check_if_first_run()
    {
        return !(get_option('idt_firstrun_completed') === '1');
    }

    /**
     * Runs the settings syncer, which will sync the settings to the editor
     */
    public function run_settings_syncer()
    {
        flush_rewrite_rules();
        new IDT_Settings_Syncer();
    }

    /**
     * Checks if the API key is set
     * @return bool
     */
    public function api_key_is_set()
    {
        return !in_array(get_option('wc_settings_idt_api_key'), [false, ''], true);
    }

    /**
     * Checks if the sync token is set
     * @return bool
     */
    public function settings_sync_token_set()
    {
        return !in_array(get_option('wc_settings_idt_connect_code'), ['', false, null], true);
    }

    /**
     * This will activate the prompt to the admin to supply a settings sync token
     */
    public function prompt_for_settings_sync()
    {
        $this->add_display_idt_api_key_admin_notice('settings_sync');
    }

    /**
     * Handles all the things that only need to happen once, on first run
     */
    public function do_first_run()
    {
        add_option('idt_firstrun_completed', true);
        add_option('wc_settings_idt_login_cookie_updated', true);
    }

    /**
     * This will activate the prompt to the admin to supply an API key
     */
    public function generate_api_key()
    {
        add_option('wc_settings_idt_api_key', md5(microtime()));
    }

    /**
     * This will display a message to the admin, which tells the admin to fill in his API key
     */
    public function add_display_idt_api_key_admin_notice($type)
    {
        if ('api_key' === $type) {
            add_action('admin_notices', array($this, 'display_idt_api_key_admin_notice'));
        } elseif ('settings_sync' === $type) {
            add_action('admin_notices', array($this, 'display_idt_settings_sync_admin_notice'));
        }
    }

    /**
     *  This is the callback function to display the notice
     */
    public function display_idt_api_key_admin_notice()
    {
        Admin_Notice::echoNotice('Please Fill in your API Key for the IDT Plugin key in the <a href=' . admin_url('admin.php?page=wc-settings&tab=settings_IDT') . '>settings menu</a>', 'warning');
    }

    /**
     *  This is the callback function to display the notice
     */
    public function display_idt_settings_sync_admin_notice()
    {
        //TODO translate this notice
        Admin_Notice::echoNotice('Please supply the IDT settings sync token in the <a href=' . admin_url('admin.php?page=wc-settings&tab=settings_idt') . '>settings menu</a>', 'warning');
    }

    /**
     * The login integration of this plugin was revamped in version 3.0.0.
     *
     * This revamp requires some changes to IDT settings, which we've automated through this API call to
     * prevent existing installations from breaking.
     */
    private function apply_login_cookie_update()
    {
        $url = get_option('wc_settings_idt_editor_domain');

        if ($url) {

            $url .= '/api/plugin/migrate';

            $apiKey = get_option('wc_settings_idt_api_key');
            $body = json_encode(['apiKey' => $apiKey]);

            $response = wp_remote_post($url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (!is_wp_error($response)) {

                $response_code = wp_remote_retrieve_response_code($response);

                if ($response_code === 200) {
                    add_option('wc_settings_idt_login_cookie_updated', true);
                }
            }
        }
    }

    private function is_login_cookie_update_pending()
    {
        return !get_option('wc_settings_idt_login_cookie_updated');
    }

    private function printapi_is_connected()
    {
        $status = get_option('wc_settings_idt_print_api_status');

        return 'connected' === $status;
    }

    private function admin_check_printapi_environment()
    {
        if (!is_admin()) {
            return;
        }

        if ('live' !== IDT_PrintApi_Connection::get_env()) {
            return;
        }

        $client_key = get_option('wc_settings_idt_print_api_client_id');
        $secret_key = get_option('wc_settings_idt_print_api_secret');

        if (strpos($client_key, 'test_') !== false || strpos($secret_key, 'test_') !== false) {
            //if we have testkeys and a live environment, revert environment back to test to prevent unwanted orders:
            update_option('wc_settings_idt_printapi_environment', 'test');
            IDT_Logger::info('env changed');
        }
    }

    /**
     * @return bool
     */
    private function plugin_was_updated()
    {
        return IDT_VERSION !== get_option('idt_version') || in_array(get_option('idt_version'), [false, null, ''], true);
    }
}
