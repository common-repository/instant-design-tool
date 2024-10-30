<?php

namespace IDT\Core\Api\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use IDT\Core\Helpers\Helper;
use IDT\Core\Logger\IDT_Logger;
use WC_Product_Editable;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_User;

class IDT_SnapshotEndpoint_Push
{
    private $request_data;
    private $namespace;
    private $product;

    public function __construct()
    {
        $this->namespace = 'idt/v1';
        add_filter('woocommerce_is_rest_api_request', array($this, 'simulate_as_not_rest'));
        add_action('rest_api_init', array($this, 'create_snapshot_push_endpoint'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_custom_data_to_product'), 10, 4);
        add_filter('woocommerce_get_item_data', array($this, 'display_custom_product_meta'), 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', array($this, 'custom_new_product_image'), 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_item_meta_in_order'), 10, 4);
        add_action('woocommerce_before_calculate_totals', array($this, 'change_cart_items_prices'), 10, 1);
    }

    /**
     * Creates an endpoint which will receive calls from the design tool.
     */
    public function create_snapshot_push_endpoint()
    {
        register_rest_route(
            $this->namespace,
            '/pushthesnapshot/',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_product_to_cart_from_snapshot_info'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * We have to tell WC that this should not be handled as a REST request.
     * Otherwise we can't use the product loop template contents properly.
     * Since WooCommerce 3.6
     *
     * @param bool $is_rest_api_request
     *
     * @return bool
     */
    public function simulate_as_not_rest($is_rest_api_request)
    {
        if (!$_SERVER['REQUEST_URI'] || stripos($_SERVER['REQUEST_URI'], $this->namespace) === false) {
            return $is_rest_api_request;
        }

        return false;
    }

    /**
     * Server-to-server API.
     *
     * Saves a snapshot received from the design tool and returns a token. This token is
     * appended to the redirect URL. When the user returns to WooCommerce, we use the
     * token in the URL to look up the saved snapshot and add it to the current cart.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_HTTP_Response
     */
    public function add_product_to_cart_from_snapshot_info($request)
    {
        global $woocommerce;
		global $wpdb;
        try {
            $this->request_data = $request;
            $idt_product_id = $request->get_param('productId');
            $uid = $request->get_param('userId');
            $snapshot_id = $request->get_param('snapshotId');
            $remote_api_key = $request->get_param('apiKey');
            $local_api_key = get_option('wc_settings_idt_api_key');

            if (!$remote_api_key || !$local_api_key || $local_api_key !== $remote_api_key) {
                throw new Exception('Design tool API key mismatch. Please sync settings again or contact support', 401);
            }

            if (get_option('wc_settings_idt_guest_mode') !== '1') {
                if (is_null($uid)) {
                    throw new Exception('User is not logged in but should be, because Guest Mode is disabled in IDT settings.', 403);
                }
                wp_set_current_user($uid);
            }

            if ($idt_product_id) {
                $this->product = new WC_Product_Editable($idt_product_id);
            }

            $woocommerce_product_id = $this->get_product_id_from_idt_product_id($idt_product_id);

            if (!$woocommerce_product_id) {
                throw new Exception('No such product available', 400);
            }
			
			$cart_item_data = $this->add_custom_data_to_product([], $woocommerce_product_id);

            $token = $snapshot_id; // Snapshot ID is a cryptographically random token

            $wpdb->insert(
                $wpdb->prefix . 'idt_snapshot',
                array(
                    'token' => $token,
                    'cart' => json_encode(["cart_item"=> $cart_item_data, "product_id" => $woocommerce_product_id]),
                    'created_at' => date('Y-m-d H:i:s')
                ),
                array(
                    '%s',
                    '%s',
                    '%d'
                )
            );

            return new WP_HTTP_Response(['token' => $token], 200);
			
        } catch (Exception $exception) {
            $message = !empty($exception->getMessage())
                ? $exception->getMessage()
                : 'Something went wrong adding the product to the shopping cart';

            $status_code = !empty($exception->getCode())
                ? $exception->getCode()
                : 500;

            IDT_Logger::error($message);
            IDT_Logger::error($exception->getTraceAsString());

            return new WP_HTTP_Response(['message' => $message], $status_code);
        }
    }

    /**
     * Find product_id with idt_product_id that matches the one from the request
     *
     * @param $idt_product_id
     *
     * @return int|null
     */
    private function get_product_id_from_idt_product_id($idt_product_id)
    {
        $found_products = Helper::get_product_by_idt_product_id($idt_product_id);
        $product_count = count($found_products);

        if (0 === $product_count) {
            IDT_Logger::error('No products found with IDT product ID: ' . $idt_product_id);
            return null;
        }

        if ($product_count >= 2) {
            IDT_Logger::error('More than 1 product found for IDT product ID: ' . $idt_product_id . ". You might have some duplicate products defined. We'll only return the first one.");
        }

        return $found_products[0]->get_id();
    }

    /**
     * Add custom product data to cart item.
     *
     * @param array $cart_item_data
     * @param int $product_id
     *
     * @return array
     */
    public function add_custom_data_to_product($cart_item_data, $product_id)
    {
        if ($this->product instanceof WC_Product_Editable) {
            $hidden_meta_data = [];

            if ($this->request_data->get_param('pageCount') > 1 && !is_null($this->request_data->get_param('pageCount'))) {
                $hidden_meta_data['_IDT_pageCount'] = $this->request_data->get_param('pageCount');
                $hidden_meta_data['_IDT_book_price'] = $this->generate_book_total($product_id);
            }

            $cart_item_data['IDT_title'] = $this->request_data->get_param('title');
            $cart_item_data['IDT_edit_link'] = $this->request_data->get_param('editUrl');
            $hidden_meta_data['_IDT_snapshotId'] = $this->request_data->get_param('snapshotId');
            $hidden_meta_data['_IDT_thumbnailUrl'] = $this->request_data->get_param('thumbnailUrl');
            $hidden_meta_data['_IDT_output_request_url'] = $this->request_data->get_param('outputRequestUrl');

            $cart_item_data['IDT_hidden'] = serialize($hidden_meta_data);
        }

        return $cart_item_data;
    }

    /**
     * Generates the total for pageable products
     *
     * @param $product_id
     *
     * @return float
     */
    private function generate_book_total($product_id)
    {
        $page_count = $this->request_data->get_param('pageCount');
        $product = wc_get_product($product_id);
        $product_price = (float)$product->get_price();
        $amount_per_extra_page = get_post_meta($product_id, 'idt_price_per_page', true);
        $default_amount_of_pages = get_post_meta($product_id, 'idt_default_amount_of_pages', true);

        if ($page_count <= $default_amount_of_pages ||
            !isset($default_amount_of_pages) ||
            in_array($default_amount_of_pages, [null, false, '', ' '], true)) {
            return $product_price;
        }

        if (!isset($amount_per_extra_page) || in_array($amount_per_extra_page, [null, '', false, 0], true)) {
            $amount_per_extra_page = 0.50;
        }

        $price_for_extra_pages = (float)($page_count - $default_amount_of_pages) * $amount_per_extra_page;

        return (float)($product_price + $price_for_extra_pages);
    }

    /**
     * Display custom product data in the cart.
     *
     * @param array $item_data
     * @param array $cart_item
     *
     * @return array
     */
    public function display_custom_product_meta($item_data, $cart_item)
    {
        if (empty($cart_item['IDT_edit_link']) && empty($cart_item['_IDT_pageCount']) && empty($cart_item['IDT_title'])) {
            return $item_data;
        }

        $opt = get_option('wc_settings_idt_display_project_details', 'yes');

        if (is_cart() && isset($cart_item['IDT_hidden']) && 'yes' === $opt) {
            $hidden_meta_data = unserialize($cart_item['IDT_hidden']);
            $item_data[] = array(
                'key' => _x('Edit your product here', 'CART METADATA', 'IDT'),
                'value' => wc_clean($cart_item['IDT_edit_link']),
                'display' => '<a  class="button" href="' . $cart_item['IDT_edit_link'] . '">' . _x('Editor', 'WEBSITE', 'IDT') . '</a>',
            );
            $item_data[] = array(
                'key' => _x('Project Title', 'CART METADATA', 'IDT'),
                'value' => wc_clean($cart_item['IDT_title']),
                'display' => '',
            );

            if (isset($hidden_meta_data['_IDT_pageCount']) && !is_null($hidden_meta_data['_IDT_pageCount']) && $hidden_meta_data['_IDT_pageCount'] > 1) {
                $item_data[] = array(
                    'key' => _x('Amount of pages', 'CART METADATA', 'IDT'),
                    'value' => wc_clean($hidden_meta_data['_IDT_pageCount']),
                    'display' => '',
                );
            }
        }

        return $item_data;
    }

    /**
     * Change the product image to show the custom product thumbnail
     *
     * @param $product_image
     * @param $cart_item
     *
     * @return string
     */
    public function custom_new_product_image($product_image, $cart_item)
    {
        if (!isset($cart_item['IDT_hidden'])) {
            return $product_image;
        }

        $data = unserialize($cart_item['IDT_hidden']);

        if (empty($data['_IDT_thumbnailUrl'])) {
            return $product_image;
        }

        return '<img src="' . esc_url($data['_IDT_thumbnailUrl']) . '"/>';
    }

    /**
     * This function will save the custom item meta on the order.
     *
     * @param $item
     * @param $item_id
     * @param $values
     * @param $order
     */
    public function save_item_meta_in_order($item, $item_id, $values, $order)
    {
        if (isset($values['IDT_hidden'])) {
            $hidden_data = unserialize($values['IDT_hidden']);

            if (isset($values['IDT_title'])) {
                $item->update_meta_data(_x('Project Title', 'WEBSITE', 'IDT'), $values['IDT_title']);
            }

            if (isset($hidden_data['_IDT_pageCount'])) {
                $item->update_meta_data('_IDT_pageCount', $hidden_data['_IDT_pageCount']);
            }

            if (isset($hidden_data['_IDT_output_request_url'])) {
                $output_request_url = $hidden_data['_IDT_output_request_url'];
                $item->update_meta_data('_IDT_output_request_url', $output_request_url);
                IDT_Render_Manager::request_pdf_output($output_request_url);

                /*
                 * We use the guid here because at this point, the order is not yet a completed order, thus it has no official
                 * order_id yet. We need to relate back to the order in the render manager, and this guid allows us to do so.
                 */

                $guid = Helper::guid();
                $order->update_meta_data('idt_order_guid', $guid);

                /*
                 * We schedule an event, which will check in 2 hours if the output request has been set successfully.
                 * If not, we can assume the webhook was not hit, or the render failed completely.
                 * If the webhook was not hit, we will bind the output manually.
                 */
                wp_schedule_single_event(time() + 7200, 'idt_run_output_binder_by_guid', ['guid' => $guid]);
            }

            if (isset($hidden_data)) {
                $item->update_meta_data('_IDT_hidden_meta', $hidden_data);
            }
        }
    }

    /**
     * Check if the custom book price has been set, which will only be set if the product
     * has multiple pages. This price defaults to 0.50 cents per page.
     *
     * @param $cart
     */
    public function change_cart_items_prices($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!did_action('wp_loaded')) {
            IDT_Logger::error('wp_loaded action has not yet been performed while already trying to access cart');
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['IDT_hidden'])) {
                $hidden_meta_data = unserialize($cart_item['IDT_hidden']);
                if (isset($hidden_meta_data['_IDT_book_price'])) {
                    $cart_item['data']->set_price($hidden_meta_data['_IDT_book_price']);
                }
            }
        }
    }
}
