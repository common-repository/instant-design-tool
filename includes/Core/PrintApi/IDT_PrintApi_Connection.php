<?php

namespace IDT\Core\PrintApi;

use IDT\Admin\Templates\Admin_Notice;
use IDT\Core\Logger\IDT_Logger;

if (!defined('ABSPATH')) {
    exit();
}

class IDT_PrintApi_Connection
{
    public $connection;

    public function __construct($poll = false, $client = null, $secret = null)
    {
        $usage = get_option('wc_settings_idt_use_printapi');
        if ('never' === $usage) {
            return false;
        }
        try {
            $environment = get_option('wc_settings_idt_printapi_environment');

            if (isset($client)) {
                $print_api_client_id = $client;
            } else {
                $print_api_client_id = get_option('wc_settings_idt_print_api_client_id');
            }

            if (isset($secret)) {
                $print_api_secret = $secret;
            } else {
                $print_api_secret = get_option('wc_settings_idt_print_api_secret');
            }

            if ('live' === $environment) {
                $this->connection = PrintApi::authenticate($print_api_client_id, $print_api_secret, 'live');
            } else {
                $this->connection = PrintApi::authenticate($print_api_client_id, $print_api_secret);
            }

            if (true === $poll) {
                if (null !== $this->connection && false !== $this->connection) {
                    $this->poll_connection();
                } else {
                    $this->delete_keys();
                }
            }

            return true;

        } catch (PrintApiResponseException $e) {
            IDT_Logger::error($e->getMessage());
            $this->delete_keys_on_error($e);
            $this->show_response_error();
        } catch (PrintApiException $e) {
            IDT_Logger::error($e->getMessage());
            $this->delete_keys_on_error($e);
            $this->show_printapi_error();
        }
        return false;
    }

    private function delete_keys_on_error($e)
    {
        if ('invalid_client' === json_decode($e->getMessage())->error) {
            $this->delete_keys();
            $this->show_response_error();
        }
    }

    private function delete_keys()
    {
        update_option('wc_settings_idt_print_api_client_id', 'invalid key');
        update_option('wc_settings_idt_print_api_secret', 'invalid key');
        update_option('wc_settings_idt_print_api_status', 'error');
        update_option('wc_settings_idt_use_printapi', 'optionally');
    }

    /**
     * @return bool
     */
    public static function is_status_connected()
    {
        return get_option('wc_settings_idt_print_api_status') === 'connected';
    }

    private function show_response_error()
    {
        add_action(
            'admin_notices',
            function () {
                Admin_Notice::echoNotice('The received response from Print API was not valid, please submit valid credentials or contact support', 'error');
            }
        );
    }

    private function show_printapi_error()
    {
        add_action(
            'admin_notices',
            function () {
                Admin_Notice::echoNotice('Print API encountered an error, please check your credentials or contact support', 'error');
            }
        );
    }

    /**
     * @return mixed|bool
     */
    public static function get_env()
    {
        return get_option('wc_settings_idt_printapi_environment');
    }

    /**
     * @return bool
     * @throws PrintApiException
     * @throws PrintApiResponseException
     */
    public function poll_connection()
    {
        $response = $this->connection->get('products?offset=1&limit=1');
        $products = $response->results;

        if (isset($response->message) && 'Authorization has been denied for this request.' === $response->message) {
            update_option('wc_settings_idt_print_api_status', 'error');

            return false;
        } elseif (is_string($products[0]->id)) {
            update_option('wc_settings_idt_print_api_status', 'connected');

            return true;
        } else {
            update_option('wc_settings_idt_print_api_status', 'error');

            return false;
        }
    }
}
