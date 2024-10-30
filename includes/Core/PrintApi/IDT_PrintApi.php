<?php

namespace IDT\Core\PrintApi;

use Exception;
use IDT\Admin\Templates\Admin_Notice;
use IDT\Core\Helpers\Helper;
use IDT\Core\Logger\IDT_Logger;
use WC_Order;

if (!defined('ABSPATH')) {
    exit();
}

class IDT_PrintApi
{
    private $api;
    private $order;

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'idt_add_meta_boxes']);
        add_action('save_post', [$this, 'idt_save_wc_order_other_fields'], 10, 1);
    }

    /**
     * Add the Print API metabox to the order page
     *
     * @return bool
     */
    public function idt_add_meta_boxes()
    {
        global $post;
        if ('shop_order' !== $post->post_type || 'never' === get_option('wc_settings_idt_use_printapi') || Helper::order_has_failed_output_request($post->ID)) {
            return false;
        }
        $order_id = $post->ID;
        $order = wc_get_order($order_id);

        $this->order = $order;
        if (Helper::count_print_api_products($order) > 0) {
            add_meta_box(
                'IDT_other_fields',
                _x('Print API ', 'ADMIN_PANEL', 'IDT'),
                array(
                    $this,
                    'idt_printapi_metabox_content',
                ),
                'shop_order',
                'side',
                'core'
            );
        }

        if (null !== $order->get_meta('idt_track_n_trace_url') && '' !== $order->get_meta('idt_track_n_trace_url')) {
            add_meta_box(
                'IDT_tracking_url',
                _x('Tracking link:', 'ADMIN_PANEL', 'IDT'),
                array(
                    $this,
                    'idt_trackntrace_metabox_content',
                ),
                'shop_order',
                'side',
                'core'
            );
        }

        return true;
    }

    /**
     * Display the track n trace link on the track n trace metabox
     *
     * @return void
     */
    public function idt_trackntrace_metabox_content()
    {
        ?>
        <a href="<?php echo esc_attr($this->order->get_meta('idt_track_n_trace_url')); ?>">
            <?php echo esc_url($this->order->get_meta('idt_track_n_trace_url')); ?></a>
        <?php
    }

    /**
     * Echoes the contents for the Print API metabox
     *
     * @return bool
     */
    public function idt_printapi_metabox_content()
    {
        global $post;
        if ('shop_order' !== $post->post_type) {
            return false;
        }

        $order_id = $post->ID;
        $forwarded = get_post_meta($order_id, 'IDT_order_forwarded', true);
        $failed = get_post_meta($order_id, 'IDT_order_forward_failed', true);

        if (!IDT_PrintApi_Connection::is_status_connected()) {
            echo esc_html_x('Print API is not connected', 'ADMIN_PANEL', 'IDT');
        } else {
            $messages = $this->get_metabox_content_based_on_products_and_state(wc_get_order($order_id), $forwarded, $failed);

            wp_nonce_field('retry_printapi_forward', 'idt_forward_order_nonce');
            if ($messages[0]) {
                echo esc_html($messages[0]);
            }
            if ($messages[1]) :
                ?>
                <p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
                    <input type="submit" class="button" style="width:250px;" name="idt_forward_order_button"
                            value="<?php echo esc_attr($messages[1]); ?>">
                </p>
                <?php
            endif;
        }
        return true;
    }

    /**
     * Callback function hooked to post save. Used to start the process of forwarding the order manually.
     *
     * @param $post_id
     * @return integer
     */
    public function idt_save_wc_order_other_fields($post_id)
    {
        // Check if our nonce is set.
        if (!isset($_POST['idt_forward_order_nonce'])) {
            return $post_id;
        }

        //Verify that the nonce is valid.
        if (!wp_verify_nonce($_REQUEST['idt_forward_order_nonce'], 'retry_printapi_forward')) {
            return $post_id;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Check the user's permissions.
        if ('page' === $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        }

        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        if (isset($_POST['idt_forward_order_button'])) {
            $this->handle_printapi_order($post_id);
        }

        return $post_id;
    }

    /**
     * Handle forwarding the order to Print API.
     *
     * @param $post_id
     * @return bool
     */
    public function handle_printapi_order($post_id)
    {
        $order = wc_get_order($post_id);

        if (!$this->order_has_editable_product($order)) {
            return false;
        }

        if (!IDT_PrintApi_Connection::is_status_connected()) {
            $this->display_printapi_not_connected_notice();
            return false;
        }

        $client = new IDT_PrintApi_Connection();
        $this->api = $client->connection;

        //Then we gather the complete editable products their data
        $editable_products = $this->gather_editable_product_data($order);
        list($address, $email) = $this->get_shipping_data($order);
        //We merge these parts of data
        $data_for_api = $this->get_data_for_order_forward($order, $email, $editable_products, $address, $post_id);

        //We try to send the request
        try {
            $this->api->post('/orders', $data_for_api);
        } catch (Exception  $exception) {
            $this->handle_printapi_post_error($exception, $post_id);

            return false;
        }

        $response = $this->api->getResponse()['response'];

        if (201 !== $response['code']) {
            $exception = new Exception(json_decode($response['body'])->message);
            $this->handle_printapi_post_error($exception, $post_id);

            return false;
        }

        $order->add_order_note('Printapi set the state of the order to: ' . $response['message']);
        //We use update here because we do not want duplicate values, but rather want to overwrite any existing meta

        $order->update_meta_data('IDT_order_forwarded', true);
        $order->update_meta_data('IDT_order_forward_failed', false);

        $order->save_meta_data();

        return true;
    }

    /**
     * Will add all the error messages from PrintAPI to the orders notification log
     *
     * @called when failing the order forward
     * @param WC_Order $order
     * @return void
     */
    public function add_all_error_notices_to_order_notes(WC_Order $order)
    {
        foreach ($this->get_errors_from_api_client() as $error_message) {
            /* translators: %s the error received from printapi */
            $order->add_order_note(sprintf(__('Warning! Print API is reporting the following: %s', 'IDT'), $error_message), false, 'Print API');
        }
    }

    /**
     * Checks if there's editable products on the order
     *
     * @param WC_Order $order
     *
     * @return bool
     */
    public function order_has_editable_product(WC_Order $order)
    {
        foreach ($order->get_items() as $item) {
            if ('' !== $item->get_meta('_IDT_hidden_meta') && !empty($item->get_meta('_IDT_hidden_meta'))) {
                return true;
            }
        }

        return false;
    }

    /**
     *  Loop through items on the order, and get the editable product data
     *
     * @param $order
     *
     * @return array
     */
    public function gather_editable_product_data($order)
    {
        $items_to_forward = array();
        foreach ($order->get_items() as $item) {
            $item->print_api_external_id = get_post_meta($item->get_data()['product_id'], 'print_api_external_id', true);
            //If the item has hidden meta, we want to add it to the items to forward.
            if ('' !== $item->get_meta('_IDT_hidden_meta') && !empty($item->get_meta('_IDT_hidden_meta'))) {
                $items_to_forward[] = $item;
            }
        }

        return $items_to_forward;
    }

    /**
     * Handles the exception on the post to Print API
     *
     * @param Exception $exception
     * @param $post_id
     */
    private function handle_printapi_post_error(Exception $exception, $post_id)
    {
        $this->add_all_error_notices_to_order_notes(wc_get_order($post_id));
        IDT_Logger::error('order forwarding failed');
        IDT_Logger::error('error message is:');
        IDT_Logger::error($exception->getMessage());

        update_post_meta($post_id, 'IDT_order_forwarded', false);
        update_post_meta($post_id, 'IDT_order_forward_failed', true);
    }

    /**
     * Sets the editable item data in the required format
     *
     * @param $editable_products
     *
     * @return array
     */
    public function get_formatted_item_data($editable_products)
    {
        $items = array();
        foreach ($editable_products as $editable_product) {
            $item = array();
            $item['productId'] = $editable_product->print_api_external_id;
            $item['quantity'] = $editable_product->get_quantity();
            $hidden_meta = $editable_product->get_meta('_IDT_hidden_meta');
            if (isset($hidden_meta['_IDT_pageCount'])) {
                $item['pageCount'] = $hidden_meta['_IDT_pageCount'];
            } else {
                $item['pageCount'] = 1;
            }
            $item['files'] = array(
                'output' => $hidden_meta['_IDT_output_request_url'],
            );
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Sets the address data in the required format
     *
     * @param $address
     *
     * @return mixed
     */
    public function get_formatted_address($address)
    {
        if (isset($address['first_name']) && isset($address['last_name'])) {
            $address['name'] = $address['first_name'] . ' ' . $address['last_name'];
            unset($address['first_name']);
            unset($address['last_name']);
        }

        if (isset($address['address_1'])) {
            $address['line1'] = $address['address_1'];
            unset($address['address_1']);
        }

        if (isset($address['address_2'])) {
            $address['line2'] = $address['address_2'];
            unset($address['address_2']);
        }

        if (isset($address['postcode'])) {
            $address['postCode'] = $address['postcode'];
            unset($address['postcode']);
        }

        if (isset($address['email'])) {
            unset($address['email']);
        }

        if (isset($address['state'])) {
            /**
             * If the state is included, check if it is greater than 3 chars.If it is, the state
             * is not a valid ISO 3166-2 code, and Print API does not accept it.
             * Because of that, the state key is popped off to prevent errors.
             */
            if (strlen($address['state'] > 3)) {
                unset($address['state']);
            }
        }

        return $address;
    }

    /**
     * @return void
     */
    private function display_printapi_not_connected_notice()
    {
        add_action(
            'admin_notices',
            function () {
                Admin_Notice::echoNotice('Print API is not connected', 'error');
            }
        );
    }

    /**
     * Builds up the data for api array
     *
     * @param WC_Order $order
     * @param $email
     * @param array $editable_products
     * @param $address
     * @param $post_id
     * @return array
     */
    private function get_data_for_order_forward(WC_Order $order, $email, array $editable_products, $address, $post_id)
    {
        return array(
            'id' => $order->get_order_number(),
            'email' => $email,
            'items' => $this->get_formatted_item_data($editable_products),
            'shipping' => array(
                'address' => $address,
            ),
            'metadata' => wp_json_encode(
                array(
                    'post_id' => $post_id,
                )
            ),
        );
    }

    /**
     * @param WC_Order $order
     * @return array
     */
    private function get_shipping_data(WC_Order $order)
    {
        //If Formatted shipping address is empty, we use the billing address, otherwise we use shipping
        $address = '' === $order->get_formatted_shipping_address() ? $order->get_address() : $order->get_address('shipping');
        $email = $order->get_billing_email();
        $address = $this->get_formatted_address($address);
        return array($address, $email);
    }

    private function get_errors_from_api_client()
    {
        $response_code = wp_remote_retrieve_response_code($this->api->getResponse());
        if ($response_code >= 200 && $response_code <= 300) {
            return [];
        }

        $error_messages = [];
        //response has errors
        $body = json_decode(wp_remote_retrieve_body($this->api->getResponse()));
        foreach ($body->fields as $error_object) {
            $error_object_string = $error_object->key;
            foreach ($error_object->errors as $error_message) {
                $error_object_string = $error_object_string . ' : ' . $error_message . ', ';
            }
            $error_messages[] = $error_object_string;
        }
        return $error_messages;
    }


    private function get_metabox_content_based_on_products_and_state(WC_Order $order, $forwarded, $failed)
    {
        $count_of_items = $order->get_item_count();
        $count_of_print_api_items = Helper::count_print_api_products($order);
        $messages = [
            /* translators: %s the number of items that have been forwarded */
            'forwarded' => sprintf(_x('%s item(s) has/have already been forwarded', 'ADMIN_PANEL', 'IDT'), $count_of_print_api_items),
            /* translators: %%1$s the number of items failed while forwarding
            /* translators: %2$s the number of items that the order has */
            'failed' => sprintf(_x('Forwarding of %1$s of %2$s items was unsuccessful.', 'ADMIN_PANEL', 'IDT'), $count_of_print_api_items, $count_of_items),
            /* translators: %%1$s the number of items failed while forwarding
            /* translators: %2$s the number of items that the order has */
            'not_forwarded' => sprintf(_x('%1$s out of %2$s items can be forwarded', 'ADMIN_PANEL', 'IDT'), $count_of_print_api_items, $count_of_items),
            'has_no_print_api_items' => null,
        ];

        $button_messages = [
            'forwarded' => null,
            'not_forwarded' => _x('Send Order', 'ADMIN_PANEL', 'IDT'),
            'failed' => _x('Retry?', 'ADMIN_PANEL', 'IDT'),
            'has_no_print_api_items' => null,
        ];

        if ($count_of_print_api_items < 1) {
            return [$messages['has_no_print_api_items'], $button_messages['has_no_print_api_items']];
        }

        if (!$forwarded && !$failed) {
            return [$messages['not_forwarded'], $button_messages['not_forwarded']];
        }

        if ($forwarded) {
            return [$messages['forwarded'], $button_messages['forwarded']];
        }

        if ($failed) {
            return [$messages['failed'], $button_messages['failed']];
        }

        return [$messages['not_forwarded'], $button_messages['not_forwarded']];
    }
}
