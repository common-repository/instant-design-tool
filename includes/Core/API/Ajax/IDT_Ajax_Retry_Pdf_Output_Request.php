<?php

namespace IDT\Core\Api\Ajax;

use Exception;
use IDT\Admin\Templates\IDT_Flash_Notice_Handler;

class IDT_Ajax_Retry_Pdf_Output_Request
{
    public function __construct()
    {
        add_action('wp_ajax_retry_pdf_output_request', array($this, 'retry_output_pdf_request'), 1);
        add_action('wp_ajax_retry_nopriv_pdf_output_request', array($this, 'retry_output_pdf_request_nopriv'));
    }

    /**
     * Callback on the order action "retry request high res pdf output".
     * Will try to request the pdf outputs
     *
     * @updates IDT_output_render_failed on WC_Order when success
     */
    public function retry_output_pdf_request()
    {
        $redirect_url = wp_get_referer()
            ? wp_get_referer()
            : admin_url('edit.php?post_type=shop_order');

        if (!current_user_can('edit_shop_orders') && check_admin_referer('retry_pdf_output_request')) {
            IDT_Flash_Notice_Handler::add_flash_notice('Unauthorized action', 'error');
            wp_safe_redirect($redirect_url);
            exit;
        }

        $order_id = absint(wp_unslash($_GET['order_id']));
        $the_order = wc_get_order($order_id);

        do_action('idt_run_output_binder', $the_order);

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * @throws Exception
     */
    public function retry_output_pdf_request_nopriv()
    {
        throw new Exception('You are not privileged to perform this action');
    }
}
