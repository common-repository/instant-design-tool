<?php

namespace IDT\Core\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Display_output_request_status
{
    public function __construct()
    {
        add_filter('woocommerce_admin_order_actions', array($this, 'add_custom_order_status_actions_button'), 100, 2);
        add_action('admin_head', array($this, 'add_custom_order_status_actions_button_css'));
    }

    /**
     * Adds a new custom order action to the orders screen
     *
     * @param $actions
     * @param $order
     *
     * @return mixed
     */
    public function add_custom_order_status_actions_button($actions, $order)
    {
        $the_order = wc_get_order($order->get_id());
        $output_render_status = $the_order->get_meta('IDT_output_render_failed');
        $print_api_forward_status = (bool)$the_order->get_meta('IDT_order_forwarded');

        if (!in_array($output_render_status, ["", null, 0, "0", false], true)
            && $output_render_status == true && $print_api_forward_status !== true) {
            $action_slug = 'retry-pdf';
            $order_id = method_exists($order, 'get_id')
                ? $order->get_id()
                : $order->id;

            // Set the action button
            $actions[$action_slug] = array(
                'url' => wp_nonce_url(admin_url('admin-ajax.php?action=retry_pdf_output_request&order_id=' . $order_id), 'retry_pdf_output_request'),
                'name' => _x('Press this button to retry the high-res output pdf generation. Please note that this might take some time', 'ADMIN_PANEL', 'IDT'),
                'action' => $action_slug,
            );
        }

        return $actions;
    }

    public function add_custom_order_status_actions_button_css()
    {
        $action_slug = "retry-pdf"; // The key slug defined for your action button
        echo '<style>.wc-action-button-' . $action_slug . '::after { font-family: woocommerce !important; content: "\e031" !important; color: red; }</style>';
    }
}