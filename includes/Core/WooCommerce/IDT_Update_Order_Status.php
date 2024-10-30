<?php

namespace IDT\Core\WooCommerce;

use Exception;
use IDT\Admin\Templates\Admin_Notice;
use IDT\Core\Logger\IDT_Logger;
use IDT\Core\PrintApi\IDT_PrintApi_Connection;
use WC_Order;
use WC_Order_Refund;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Update_Order_Status
{
    public function __construct()
    {
        add_action('idt_order_status_updated', array($this, 'handle_status_update_from_webhook'), 10, 1);
    }

    /**
     * This will handle the requesting of the current status and updating it.
     *
     * @param $order_id
     * @return bool
     */
    public function handle_status_update_from_webhook($order_id)
    {
        //Get the current status from Print API
        if (!IDT_PrintApi_Connection::is_status_connected()) {
            $this->show_admin_notice();
            return false;
        }

        try {
            $client = $this->get_client();
            $order = $client->get('/orders/' . $order_id);
            $wc_order = $this->get_correct_wc_order_object_from_printapi_order($order_id, $order);
            $status = $this->determine_status_slug($order->status);

            if (!$wc_order instanceof WC_Order) {
                throw new Exception("Order was not of class WC_Order, which probably means it has not been found. Order id was $order_id");
            }

            if ('wc-idt-shipped' === $status) {
                $this->set_tracking_url_on_order($order, $wc_order);
            }

            $wc_order->update_status($status, _x('Print API updated the status:', 'ADMIN_PANEL', 'IDT'));
        } catch (Exception $exception) {
            IDT_Logger::error($exception->getCode());
            IDT_Logger::error($exception->getMessage());
        }
        return true;
    }

    /**
     * Determines the right slug for order status
     *
     * @param $order_status
     *
     * @return string
     */
    public function determine_status_slug($order_status)
    {
        $statuses = array(
            'Processing' => 'processing',
            'Created' => 'processing',
            'Shipped' => 'wc-idt-shipped',
            'Cancelled' => 'wc-idt-cancelled',
        );

        return $statuses[$order_status];
    }

    private function get_client()
    {
        $client = new IDT_PrintApi_Connection();
        return $client->connection;
    }

    private function show_admin_notice()
    {
        add_action(
            'admin_notices',
            function () {
                Admin_Notice::echoNotice('Print API encountered an error, please check your credentials or contact support', 'error');
            }
        );
    }

    /**
     * @param $order_id
     * @param object $order
     * @return bool|WC_Order|WC_Order_Refund
     */
    private function get_correct_wc_order_object_from_printapi_order($order_id, $order)
    {
        /*
        * Search order by post_id received from the meta sent by Print API.
        * If meta is set, we have a new order which is relatable back to by meta,
        * If not, the order is an order that is done the old way, and the ID will be the post id
        * Because WooCommerce does not support custom order numbers natively, we cannot "guess" for an order number
        * That is different than the post_id.
        */
        if (isset($order->metadata)) {
            $decoded_data = json_decode($order->metadata);
            if ($decoded_data && isset($decoded_data->post_id)) {
                $order_id = $decoded_data->post_id;
            }
        }

        return wc_get_order($order_id);
    }

    /**
     * @param object $order
     * @param WC_Order $wc_order
     */
    private function set_tracking_url_on_order($order, WC_Order $wc_order)
    {
        /* Property comes in this format, because of that it is not in valid snake case. */
        if (isset($order->trackingUrl)) {
            $wc_order->update_meta_data('idt_track_n_trace_url', $order->trackingUrl);
        }
    }
}
