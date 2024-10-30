<?php

namespace IDT\Core\Api\Snapshot;

use IDT\Admin\Templates\Admin_Notice;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Check_Output_Pdf_Quota
{
    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        add_action('init', array($this, 'idt_check_if_quota_reached'));
        add_action('idt_display_quote_reached_for_order_message', array($this, 'display_quote_reached_notice'));
    }

    public function idt_check_if_quota_reached()
    {
        if (!wc_current_user_has_role('editor')) {
            return;
        }

        if (get_option('IDT_pdf_request_quota_reached')) {
            $url = get_option('wc_settings_idt_editor_domain');

            /** @noinspection HtmlUnknownTarget because this will be a cross domain call */
            $add_credit_link = sprintf('<a href="%s/manage/credit/addcredit">add credit</a>', $url);
            $message = 'It seems like your PDF quota has been reached! Please ' . $add_credit_link . ' to your Instant Design Tool dashboard or upgrade your plan to keep generating output PDFs';

            add_action(
                'admin_notices',
                function () use ($message) {
                    Admin_Notice::echoNotice($message, 'error');
                }
            );
        }
    }

    public function display_quote_reached_notice(WC_Order $order)
    {
        $url = get_option('wc_settings_idt_editor_domain');
        $add_credit_link = "<a href=\"$url/manage/credit/addcredit\">add credit</a>";
        $message = 'Order ' . $order->get_order_number() . '/ ' . $order->get_id() . ' has not been forwarded because it seems like your PDF quota has been reached. Please ' . $add_credit_link . ' to your Instant Design Tool dashboard or upgrade your plan to keep generating output PDFs';

        add_action('admin_notices', function () use ($message) {
            Admin_Notice::echoNotice($message);
        });
    }
}
