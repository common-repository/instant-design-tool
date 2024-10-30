<?php

namespace IDT\Core\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

class IDT_Register_Order_Statuses
{
    public function __construct()
    {
        add_action('init', array($this, 'register_printapi_shipped_order_status'));
        add_action('init', array($this, 'register_printapi_cancelled_order_status'));
        add_filter('wc_order_statuses', array($this, 'register_new_order_statuses_in_wc'));
    }

    public function register_printapi_shipped_order_status()
    {
        register_post_status(
            'wc-idt-shipped',
            array(
                'label' => _x('Print API Shipped', 'ADMIN_PANEL', 'IDT'),
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                //This one needs to be translated using _n_noop because woocommerce loads it that way. Otherwise an error will be thrown
                /* translators: the amount of orders that have this status */
                'label_count' => _n_noop('Print API Shipped  (%s)', 'Print API Shipped (%s)', 'IDT'),
            )
        );
    }

    public function register_printapi_cancelled_order_status()
    {
        register_post_status(
            'wc-idt-cancelled',
            array(
                'label' => _x('Print API Cancelled', 'ADMIN_PANEL', 'IDT'),
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                //This one needs to be translated using _n_noop because woocommerce loads it that way. Otherwise an error will be thrown
                /* translators: the amount of orders that have this status */
                'label_count' => _n_noop('Print API Cancelled (%s)', 'Print API Cancelled (%s)', 'IDT'),
            )
        );
    }

    /**
     * Add the new order status to the woocommerce status listing
     *
     * @param $order_statuses
     * @return mixed
     */
    public function register_new_order_statuses_in_wc($order_statuses)
    {
        $order_statuses['wc-idt-shipped'] = _x('Print API Shipped', 'ADMIN_PANEL', 'IDT');
        $order_statuses['wc-idt-cancelled'] = _x('Print API Cancelled', 'ADMIN_PANEL', 'IDT');

        return $order_statuses;
    }
}
