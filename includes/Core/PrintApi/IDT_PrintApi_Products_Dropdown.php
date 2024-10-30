<?php

namespace IDT\Core\PrintApi;

use Exception;
use IDT\Core\Helpers\Helper;
use WP_Error;
use IDT\Core\Logger\IDT_Logger;

if (!defined('ABSPATH')) {
    exit();
}

class IDT_PrintApi_Products_Dropdown
{
    /**
     * Retrieves all the possible products from the clients Design Tool.
     *
     * @return array (Empty if an error occurred)
     */
    public static function get_all_products_from_editor()
    {
        $opt = get_option('wc_settings_idt_editor_domain');
        $products = [];

        if (!$opt) {
            return $products;
        }

        try {
            $opt .= '/api/products/all';
            $response = wp_remote_retrieve_body(wp_remote_get($opt));

            if (!isset($response)) {
                throw new Exception('Error on product dropdown. Result from API was null');
            }

            if ($response instanceof WP_Error) {
                throw new Exception('Error on product dropdown. Message: ' . $response->get_error_message());
            }

            $products = json_decode($response, false)->products;
            if(!is_array($products)) {
                throw new Exception('Error on product dropdown. Result from API did not contain products.');
            }
        } catch (Exception $exception) {
            IDT_Logger::error($exception->getMessage());

            $products = [];
        }

        return $products;
    }

    /**
     * Gets the products for the dropdown
     */
    public static function editor_products_for_dd()
    {
        global $post_id;
        $products_from_editor = self::get_all_products_from_editor();
        $products = [];

        if (empty($products_from_editor)) {
            $products[] = _x('You have not yet linked any products.', 'ADMIN_PANEL', 'IDT');

            return $products;
        }

        foreach ($products_from_editor as $product) {
            $matched = Helper::get_product_by_idt_product_id($product->id);
            if (!$matched || $matched[0]->get_id() === $post_id) {
                $products[$product->id] = $product->name;
            }
        }

        if (empty($products)) {
            $products[null] = _x('All products in your catalogue have been linked already', 'ADMIN_PANEL', 'IDT');
        }

        return $products;
    }

    public static function get_data_for_id($editor_id)
    {
        $products_from_editor = self::get_all_products_from_editor();
        $products = [];

        if (empty($products_from_editor)) {
            return $products;
        }

        foreach ($products_from_editor as $product) {
            if ($product->id === $editor_id) {
                $products['url'] = $product->url;
                $products['printApiId'] = $product->printApiId;
                break;
            }
        }

        return $products;
    }
}
