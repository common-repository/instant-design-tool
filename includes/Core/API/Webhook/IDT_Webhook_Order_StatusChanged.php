<?php

namespace IDT\Core\Api\Webhook;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Webhook_Order_StatusChanged
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'create_order_status_changed_webhook'));
    }

    /**
     * Create the endpoint
     *
     */
    public function create_order_status_changed_webhook()
    {
        register_rest_route(
            'idt/v1',
            '/orderstatuschanged',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_order_status_changed'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * This function will launch an action so we can hook into it and get an order_id param with it
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_order_status_changed(WP_REST_Request $request)
    {
        $order_id = $request['orderId'];
        do_action('idt_order_status_updated', $order_id);

        return new WP_REST_Response(array(), 200);
    }

}
