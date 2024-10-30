<?php

if (!defined("ABSPATH")) {
    exit;
}

add_action('init', 'register_editable_product_type');

/**
 * This will register the product class, so it is available to woocommerce
 */

if (!function_exists('register_editable_product_type')) {
    function register_editable_product_type()
    {
        class WC_Product_Editable extends WC_Product
        {
            /**
             * @var string
             */
            private $product_type;

            public function __construct($product)
            {
                $this->product_type = 'editable';
                parent::__construct($product);
            }

            /**
             * Get internal type.
             *
             * @return string
             */
            public function get_type()
            {
                return 'editable';
            }

            /**
             * Get the add to cart url used mainly in loops.
             *
             * @return string
             */
            public function add_to_cart_url()
            {
                $url = $this->get_permalink($this->id);

                return apply_filters('woocommerce_product_add_to_cart_url', $url, $this);
            }

            /**
             * Get the add to cart button text.
             *
             * @return string
             */
            public function add_to_cart_text()
            {
                $text = _x("Read More", "WEBSITE", "IDT");

                return apply_filters('woocommerce_product_add_to_cart_text', $text, $this);
            }

            /**
             * Get the add to cart button text description - used in aria tags.
             *
             * @return string
             * @since 3.3.0
             */
            public function add_to_cart_description()
            {
                $text = $this->is_purchasable() && $this->is_in_stock()
                    /* translators: %s: Product title */
                    ? _x('Add "%s" to your cart', 'WEBSITE', 'IDT')
                    : _x('Read more about "%s"', 'WEBSITE', 'IDT');

                return apply_filters('woocommerce_product_add_to_cart_description', sprintf($text, $this->get_name()), $this);
            }
        }
    }
}