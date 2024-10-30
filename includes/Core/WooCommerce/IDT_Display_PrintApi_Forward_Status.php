<?php

namespace IDT\Core\WooCommerce;

use IDT\Core\Helpers\Helper;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Display_PrintApi_Forward_Status
{
    public function __construct()
    {
        if (get_option('wc_settings_idt_use_printapi') === 'never') {
            return;
        }

        add_filter('manage_edit-shop_order_columns', array($this, 'shop_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'shop_order_posts_custom_column'));
    }

    /**
     * Create custom column in the orders table
     *
     * @param $columns
     *
     * @return array
     */
    public function shop_order_columns($columns)
    {
        $columns = (is_array($columns)) ? $columns : array();
        $printapi_forward_status = array('printapi_forward_status' => 'Print Api Forward Status');
        $position = 5;
        $new_columns = array_slice($columns, 0, $position, true) + $printapi_forward_status;

        return array_merge($new_columns, $columns);
    }

    /**
     * Generate content for the orders table custom column
     *
     * @param $column
     */
    public function shop_order_posts_custom_column($column)
    {
        global $post, $the_order;

        if (empty($the_order) || $the_order->get_id() !== $post->ID) {
            $the_order = wc_get_order($post->ID);
        }

        if ('printapi_forward_status' === $column) {
            echo wp_kses(
                $this->determine_order_printapi_forward_status($the_order),
                [
                    'button' => [
                        'class' => 'button button-secondary',
                        'onclick' => 'event.preventDefault()',
                    ],
                ]
            );
        }
    }

    /**
     * Determine status to display for specific order
     *
     * @param WC_Order $order
     * @return string
     */
    private function determine_order_printapi_forward_status(WC_Order $order)
    {
        $failed = (bool)$order->get_meta('IDT_order_forward_failed', true);
        $forwarded = (bool)$order->get_meta('IDT_order_forwarded', true);
        $output_failed = (bool)$order->get_meta('IDT_output_render_failed', true);

        if (!Helper::count_print_api_products($order) > 0) {
            $message = _x('No design tool Products', 'ADMIN_PANEL', 'IDT');

            return sprintf("<button class='button button-secondary' onclick='event.preventDefault()'>%s</button>", $message);
        }

        if ($forwarded && !$failed) {
            $style = 'button-primary';
            $message = _x('Success', 'ADMIN_PANEL', 'IDT');
        } elseif ($output_failed) {
            $style = 'button-secondary';
            $message = 'Output request failed';
        } elseif (!$failed && !$forwarded) {
            $style = 'button-secondary';
            $message = _x('Not forwarded', 'ADMIN_PANEL', 'IDT');
        } elseif ($forwarded && $failed) {
            $style = 'button';
            $message = _x('Error', 'ADMIN_PANEL', 'IDT');
        } else {
            $style = 'button-secondary';
            $message = _x('Failed', 'ADMIN_PANEL', 'IDT');
        }

        return "<button class='button $style' onclick='event.preventDefault()'>$message</button>";
    }
}

