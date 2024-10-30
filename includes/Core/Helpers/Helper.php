<?php

namespace IDT\Core\Helpers;

use Exception;
use IDT\Core\Helpers\Exceptions\QuoteReachedException;
use IDT\Core\Logger\IDT_Logger;
use WC_Order;
use WC_Order_Item;

class Helper
{
    public static function printapi_keys_are_set()
    {
        $client = get_option('wc_settings_idt_print_api_client_id');
        $secret = get_option('wc_settings_idt_print_api_secret');

        if (empty($client) || empty($secret)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the settings have been synced already
     *
     * @return bool
     */
    public static function settings_synced_already()
    {
        return get_option('wc_settings_idt_settings_synced') === '1';
    }

    /**
     * @return string
     */
    public static function determine_cookie_domain()
    {
        $cookie_domain = get_option('wc_settings_idt_editor_domain');

        //If wc_settings_idt_editor_domain not set, use the HTTP HOST global as main domain
        if (false === $cookie_domain || '' === $cookie_domain) {
            IDT_Logger::info('Cookie domain from editor was not set, using the HTTP HOST global to generate cookie domain');
            return '.' . $_SERVER['HTTP_HOST'];
        }

        $parsed_url = wp_parse_url($cookie_domain);
        $exploded_url = explode('.', $parsed_url['host']);
        $url_without_prefix = join('.', array($exploded_url[1], $exploded_url[2]));

        return '.' . $url_without_prefix;
    }

    /**
     * Counts the editable products on the order
     *
     * @param WC_Order $order
     *
     * @return int
     */
    public static function count_print_api_products(WC_Order $order)
    {
        $count = 0;

        foreach ($order->get_items() as $item) {
            if ($item->get_product()) {
                if (!empty($item->get_product()->get_meta('print_api_external_id'))) {
                    $count += $item->get_quantity();
                }
            }
        }

        return $count;
    }

    /**
     * Check if order has failed output request attempts
     *
     * @param int $order_id
     *
     * @return bool
     */
    public static function order_has_failed_output_request($order_id)
    {
        $output_render_failed_meta = wc_get_order($order_id)->get_meta('IDT_output_render_failed');

        if (in_array($output_render_failed_meta, [1, true, '1'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Get products matching meta idt_product_id
     *
     * @param $product_id
     *
     * @return array|object
     */
    public static function get_product_by_idt_product_id($product_id)
    {
        return wc_get_products(
            array(
                'meta_key' => 'idt_product_id',
                'meta_value' => $product_id,
            )
        );
    }

    /**
     * Generate a new guid
     *
     * @return string
     */
    public static function guid()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', wp_rand(0, 65535), wp_rand(0, 65535), wp_rand(0, 65535), wp_rand(16384, 20479), wp_rand(32768, 49151), wp_rand(0, 65535), wp_rand(0, 65535), wp_rand(0, 65535));
    }

    public static function create_button_for_order_item($order_item_id, $type_of_output, $url_to_bind)
    {
        global $wpdb;
        $link_button = "<a class=\"button\" target=\"_blank\" href=\"$url_to_bind\">$type_of_output output</a>";
        $link_button_query = 'INSERT INTO ' . $wpdb->prefix . "woocommerce_order_itemmeta VALUES (null, $order_item_id, '_IDT_" . $type_of_output . "_highres_link_button', '$link_button')";

        return $wpdb->query($link_button_query);
    }

    /**
     * Gets the job url from an output request url
     *
     * @param $url
     * @param string $type
     * @return mixed
     * @throws QuoteReachedException
     * @throws Exception
     */
    public static function get_job_url_from_output_request_url($url, $type = 'pdf')
    {
        $body = 'outputType=' . $type;
        $response = wp_remote_post($url, ['body' => $body]);

        if (is_wp_error($response)) {
            $error_message = wp_json_encode($response->get_error_messages());
            throw new Exception("POST request to $url with body: $body resulted in an error: " . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if (402 === $response_code) {
            throw new QuoteReachedException('Quote reached. Please upgrade to a higher tier plan.');
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body)->jobUrl;
    }

    /**
     * Gets the output request url from an order item
     *
     * @param WC_Order_Item $order_item
     * @return array|false|mixed|string
     */
    public static function get_output_url_from_order_item(WC_Order_Item $order_item)
    {
        if (!$order_item->meta_exists('_IDT_output_request_url')) {
            return false;
        }

        return $order_item->get_meta('_IDT_output_request_url');
    }

    /**
     * Gets the output request url, and uses that to get the job url
     *
     * @param WC_Order_Item $order_item
     * @param string $type
     * @return mixed
     * @throws QuoteReachedException
     */
    public static function get_job_url_from_order_item(WC_Order_Item $order_item, $type = 'pdf')
    {
        $url = self::get_output_url_from_order_item($order_item);
        return self::get_job_url_from_output_request_url($url, $type);
    }

    /**
     * Check if an order item is done rendering
     *
     * @param WC_Order_Item $order_item
     * @return bool
     */
    public static function order_item_done_rendering(WC_Order_Item $order_item)
    {
        $render_done = (bool)$order_item->get_meta('_IDT_output_done', true);

        if (true === $render_done) {
            return true;
        }

        return false;
    }

    /**
     * @param WC_Order $order
     * @return boolean
     */
    public static function all_items_done(WC_Order $order)
    {
        $all_items_done = true;

        foreach ($order->get_items() as $order_item) {
            if (!$order_item->get_product() || $order_item->get_product()->get_type() !== 'editable') {
                continue;
            }
            if (!self::order_item_done_rendering($order_item)) {
                $all_items_done = false;
            }
        }

        return $all_items_done;
    }

    /**
     * @param $guid
     * @return WC_Order|null
     */
    public static function get_order_by_guid($guid)
    {
        $orders = wc_get_orders(
            array(
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_key' => 'idt_order_guid',
                'meta_compare' => $guid,
            )
        );

        if (empty($orders)) {
            IDT_Logger::error('Tried to match order with guid: ' . $guid . ' but could not find any orders');
            return null;
        }

        return $orders[0];
    }
}
