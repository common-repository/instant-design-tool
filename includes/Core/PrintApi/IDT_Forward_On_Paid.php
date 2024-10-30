<?php

namespace IDT\Admin;

use Exception;
use IDT\Admin\Templates\Admin_Notice;
use IDT\Admin\Templates\IDT_Flash_Notice_Handler;
use IDT\Core\Logger\IDT_Logger;
use IDT\Core\PrintApi\IDT_PrintApi;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Forward_On_Paid
{

    /**
     * IDT_Forward_On_Paid constructor.
     */
    public function __construct()
    {
        add_action('woocommerce_order_status_processing', [$this, 'idt_forward_order_if_paid'], 10, 1);
        add_action('woocommerce_order_status_on-hold', [$this, 'idt_forward_order_if_paid'], 10, 1);
    }

    /**
     * @param $order_id
     * @return boolean
     */
    public function idt_forward_order_if_paid($order_id)
    {
        $order = wc_get_order($order_id);
        $printapi = new IDT_PrintApi();
        $usage_option = get_option('wc_settings_idt_use_printapi');
        $paid_orders = get_option('wc_settings_idt_only_paid_orders', 'paid') === 'paid';
        $quota_reached = get_option('IDT_pdf_request_quota_reached');

        if ('1' === $quota_reached) {
            $url = get_option('wc_settings_idt_editor_domain');
            $add_credit_link = "<a href=\"$url/manage/credit/addcredit\">add credit</a>";
            $message = 'Order ' . $order->get_order_number() . '/ ' . $order->get_id() . ' has not been forwarded because it seems like your PDF quota has been reached. Please ' . $add_credit_link . ' to your Instant Design Tool dashboard or upgrade your plan to keep generating output PDF\'s';

            add_action(
                'admin_notices',
                function () use ($message) {
                    Admin_Notice::echoNotice($message);
                }
            );

            return false;
        }

        //get setting to see if we can forward
        if (!isset($order_id) || 'always' !== $usage_option) {
            return false;
        }

        if ($paid_orders) {
            if (!$order->is_paid()) {
                IDT_Flash_Notice_Handler::add_flash_notice('Order ' . $order->get_id() . '/ #' . $order->get_order_number() . ' not forwarded because the order was not paid for yet.', 'warning');
                return false;
            }
        }

        try {
            $printapi->handle_printapi_order($order_id);
            return true;
        } catch (Exception $e) {
            IDT_Logger::error($e->getCode());
            IDT_Logger::error($e->getMessage());
            return false;
        }
    }
}
