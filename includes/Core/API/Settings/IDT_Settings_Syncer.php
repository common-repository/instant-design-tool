<?php

namespace IDT\Core\Api\Settings;

use Exception;
use IDT\Admin\Templates\Admin_Notice;
use IDT\Core\Logger\IDT_Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Settings_Syncer
{
    private $sync_token;
    private $cart_url;
    private $registration_url;
    private $site_name;
    private $default_currency;
    private $login_page_url;
    private $logged_in_cookie;
    private $idt_api_key;
    private $post_data;
    private $post_url;
    private $route_permalink;

    public function __construct()
    {
        $this->check_wp_json_or_rest_route();

        $this->sync_token = get_option('wc_settings_idt_connect_code');
        $this->cart_url = wc_get_cart_url();
        $this->registration_url = wp_registration_url();
        $this->site_name = get_bloginfo('name');
        $this->default_currency = get_woocommerce_currency();
        $this->login_page_url = wp_login_url();
        $this->logged_in_cookie = 'IDT_USER';

        $this->idt_api_key = get_option('wc_settings_idt_api_key');

        if (is_admin()) {
            $this->post_data = $this->build_the_post_request();

            $this->post_url = $this->get_post_url();

            if (!in_array($this->post_url, [false, '', null], true)) {
                $this->send_the_post_request();
            }
        }
    }

    /**
     * Get data to include in post
     *
     * @return string
     */
    private function get_login_user_endpoint()
    {
        return site_url() . $this->route_permalink . '/idt/v1/extlogin';
    }

    /**
     * Get data to include in post
     *
     * @return string
     */
    private function get_retrieve_user_endpoint()
    {
        return site_url() . $this->route_permalink . '/idt/v1/getcustomer';
    }

    /**
     * Get data to include in post
     *
     * @return string
     */
    private function get_push_snapshot_endpoint()
    {
        return site_url() . $this->route_permalink . '/idt/v1/pushthesnapshot';
    }

    /**
     * Get data to include in post
     *
     * @return string
     */
    private function get_output_ready_endpoint()
    {
        return site_url() . $this->route_permalink . '/idt/v1/outputready';
    }

    private function get_main_site_register_page()
    {
        if (!(strpos($this->registration_url, '?'))) {
            return $this->registration_url . '?idt_ref=';
        } else {
            return $this->registration_url . '&idt_ref=';
        }
    }

    /**
     * Get formatted data for POST
     *
     * @return array
     */
    private function build_the_post_request()
    {
        return array(
            'Token' => $this->sync_token,
            'EndpointUrls' => [
                'Loginuser' => $this->get_login_user_endpoint(),
                'RetrieveUser' => $this->get_retrieve_user_endpoint(),
                'PushSnapshot' => $this->get_push_snapshot_endpoint(),
                'OutputFileReady' => $this->get_output_ready_endpoint(),
            ],
            'StaticUrls' => [
                'ShoppingCartRedirectionUrl' => $this->cart_url . (str_contains($this->cart_url, "?") ? "&" : "?") ."idt_token=",
                'RegistrationUrl' => $this->get_main_site_register_page(),
                'ContactPage' => site_url() . '/contact',
                'LoginPage' => $this->login_page_url . '?idt_ref=',
                'ForgotPassword' => wp_lostpassword_url(),
            ],
            'Settings' => [
                'ApplicationName' => $this->site_name,
                'DefaultLanguage' => 'NL',
                'DefaultCurrency' => $this->default_currency,
                'ApiKey' => $this->idt_api_key,
                'CookieName' => 'SITE_TO_IDT',
                'UseImpersonator' => false
            ],
        );
    }

    /**
     * @return void
     */
    public function send_the_post_request()
    {
        try {
            $res = wp_remote_post(
                $this->post_url,
                [
                    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                    'data_format' => 'body',
                    'body' => wp_json_encode($this->post_data, JSON_UNESCAPED_SLASHES)
                ]
            );

            if ($res instanceof WP_Error) {
                IDT_Logger::error(serialize($res));
                $this->display_admin_notice(-1);

                return;
            }

            $response_code = wp_remote_retrieve_response_code($res);

            if (200 !== $response_code) {
                $this->display_admin_notice(-1);
                return;
            }

            if ($this->response_has_errors($res)) {
                $this->display_admin_notice(-1);
                return;
            }

            $this->display_admin_notice(1);
            update_option('wc_settings_idt_connect_code', null);
            update_option('wc_settings_idt_settings_synced', true);
            update_option('wc_settings_idt_editor_domain', json_decode(wp_remote_retrieve_body($res))->url);
        } catch (Exception $exception) {
            IDT_Logger::error($exception->getCode());
            IDT_Logger::error($exception->getMessage());
        }
    }

    /**
     * Checks the url to use for the endpoints.
     *
     * @return void
     */
    public function check_wp_json_or_rest_route()
    {
        if (substr_count(get_rest_url(), 'rest_route') > 0) {
            $this->route_permalink = '?rest_route=';
        } elseif (substr_count(get_rest_url(), 'wp-json') > 0) {
            $this->route_permalink = '/wp-json';
        } else {
            //Default to wp-json since it's the most common
            $this->route_permalink = '/wp-json';
        }
    }

    private function get_post_url()
    {
        return 'https://www.instantdesigntool.com/api/plugins/configure';
    }

    private function response_has_errors($response)
    {
        if (isset(json_decode($response['body'])->error) && json_decode($response['body'])->error === true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Displays a notice indicating if a settings sync request was successful or if it failed
     *
     * @param $status String Represents the status of the response
     */
    private function display_admin_notice($status)
    {
        if (1 === $status) {
            add_action(
                'admin_notices',
                function () {
                    Admin_Notice::echoNotice('The settings have successfully been synced');
                }
            );
        } elseif (-1 === $status) {
            add_action(
                'admin_notices',
                function () {
                    Admin_Notice::echoNotice('Your settings have not been synced', 'warning');
                }
            );
            add_action(
                'admin_notices',
                function () {
                    Admin_Notice::echoNotice('There was a problem connecting to IDT, please make sure you are using the right token and url, or contact support', 'error');
                }
            );
        }
    }
}
