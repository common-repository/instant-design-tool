<?php

namespace IDT\Core\Api\Webhook;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use IDT\Core\Helpers\Exceptions\NotFoundException;
use IDT\Core\Logger\IDT_Logger;
use WP_Error;
use IDT\Core\Helpers\Helper;
use WP_HTTP_Response;
use WP_REST_Request;

class IDT_Webhook_ListenToOutputReady
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'create_output_ready_webhook'));
    }

    /**
     * Listen to webhook requests from the Design Tool.
     */
    public function create_output_ready_webhook()
    {
        register_rest_route(
            'idt/v1',
            '/outputready/',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'validate_api_key_and_trigger_binding_process'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_HTTP_Response
     * @throws Exception
     */
    public function validate_api_key_and_trigger_binding_process($request)
    {
        if ($request->get_param('apiKey') !== get_option('wc_settings_idt_api_key')) {
            return new WP_HTTP_Response(['message' => 'ApiKey Mismatch'], 403);
        }

        $this->trigger_output_binding($request->get_param('url'));

        return new WP_HTTP_Response(null, 200);
    }

    /**
     * Calls job url, finds order_item matching the snapshot_id, get order_id by that order_item,
     * Use order_id to trigger output binding process for that order
     *
     * @param string $job_url The route to retrieve the status data from.
     * @throws Exception
     */
    public function trigger_output_binding($job_url)
    {
        $response = wp_remote_retrieve_body(wp_remote_get($job_url));
        if ($response instanceof WP_Error) {
            throw new Exception(wp_json_encode($response->get_error_messages()));
        }

        $content = json_decode($response);
        if ($content && strtolower($content->type) === 'pdf') {
            $order_item_id = $this->get_order_item_id_by_snapshot_id($content->snapshotId);
            $order_id = wc_get_order_id_by_order_item_id($order_item_id);
            $order = wc_get_order($order_id);

            do_action('idt_run_output_binder', $order);
        }
    }

    /**
     * Get order id by finding the matching snapshot id in the item meta
     *
     * @param $snapshot_id
     *
     * @return int
     * @throws Exception
     */
    private function get_order_item_id_by_snapshot_id($snapshot_id)
    {
        global $wpdb;

        try {
            $output = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT order_item_id FROM ' . $wpdb->prefix . 'woocommerce_order_itemmeta  WHERE meta_value LIKE %s',
                    '%' . $snapshot_id . '%'
                )
            );

            if (empty($output)) {
                throw new NotFoundException("No order item matched with meta value: $snapshot_id");
            }

            return (int)$output[0]->order_item_id;

        } catch (Exception $exception) {
            IDT_Logger::error($exception->getMessage());
            IDT_Logger::error($exception->getTraceAsString());
        }

        return 0;
    }
}
