<?php

namespace IDT\Core\Api\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use IDT\Core\Helpers\Exceptions\QuoteReachedException;
use IDT\Core\Helpers\Helper;
use IDT\Core\Logger\IDT_Logger;
use stdClass;
use WC_Order;
use WC_Order_Item;
use WP_Error;

class IDT_Render_Manager
{
    public function __construct()
    {
        add_action('idt_run_output_binder', [$this, 'idt_bind_output_urls'], 10, 1);
        add_action('idt_run_output_binder_by_guid', [$this, 'idt_bind_output_urls_by_guid'], 10, 1);
    }

    /**
     * Wrapper to access the functionality in this class by guid instead of order ID.
     *
     * @param string $guid
     */
    public function idt_bind_output_urls_by_guid($guid)
    {
        $this->idt_bind_output_urls(Helper::get_order_by_guid($guid));
    }

    /**
     * For each item on the order, check if the outputs have been set, and bind the outputs if not.
     *
     * @param WC_Order $order
     * @return bool
     */
    public function idt_bind_output_urls(WC_Order $order)
    {
        if (get_option('IDT_pdf_request_quota_reached')) {
            $this->maybe_reschedule($order, 7200);
            IDT_Logger::warning('Tried to render order ' . $order->get_id() . ' but pdf quotum was reached');
            return false;
        }

        foreach ($order->get_items() as $order_item) {
            if ('editable' !== $order_item->get_product()->get_type()) {
                continue;
            }

            if (Helper::order_item_done_rendering($order_item)) {
                $this->remove_output_request_url_from_order_item($order_item);
                continue;
            }

            try {
                $job_url = Helper::get_job_url_from_order_item($order_item);
                /*
                * If the job_url is still empty at this point, something serious is wrong and this order needs attention.
                * Because of that we give the order_render failed status to the order, so it is visible from the Orders screen
                * That something is wrong.
                */
                if ('' === $job_url) {
                    IDT_Logger::error('Job url was empty for order item ' . $order_item->get_id());
                    $order->add_meta_data('IDT_output_render_failed', true);
                    return false;
                }

                $this->handle_job_url_output($job_url, $order_item, $order);
            } catch (QuoteReachedException $exception) {
                $order_item->update_meta_data('_quotum_reached', true);
                /*
                 * We could return here, since we are probably not going to render anything anymore because the quota has been reached.
                 * But we do this to make sure all items that have failed due to the quota reached get the new quotum reached meta so
                 * it is clear why they have no output button yet.
                 */
                continue;
            } catch (Exception $exception) {
                $this->maybe_reschedule($order);
            }
        }

        //loop through all order items again, this time all should have a button
        $this->remove_failed_status_if_all_items_are_done($order);
        return true;
    }

    /**
     * @param WC_Order_Item $order_item
     */
    private function remove_output_request_url_from_order_item($order_item)
    {
        $order_item->delete_meta_data('_IDT_output_request_url');
        $order_item->save_meta_data();
    }

    /**
     * Create output buttons for both the content and, if set, the cover
     *
     * @param int $order_item_id
     * @param stdClass $output
     */
    private function create_buttons_for_order_item($order_item_id, $output)
    {
        Helper::create_button_for_order_item($order_item_id, 'pdf', $output->fileUrls->content);
        if (isset($output->fileUrls->cover)) {
            Helper::create_button_for_order_item($order_item_id, 'pdf_cover', $output->fileUrls->cover);
        }
    }

    /**
     * @param string $job_url
     * @return mixed|null
     */
    private function get_pdf_output_from($job_url)
    {
        try {
            $response = wp_remote_retrieve_body(wp_remote_get($job_url));

            if ($response instanceof WP_Error) {
                throw new Exception(wp_json_encode($response->get_error_messages()));
            }

            return json_decode($response);
        } catch (Exception $exception) {
            IDT_Logger::error($exception->getMessage());
            IDT_Logger::error($exception->getTraceAsString());

            return null;
        }
    }

    /**
     * @param WC_Order $order
     * @param int $time
     */
    private function reschedule_job_for_order(WC_Order $order, $time = 300)
    {
        wp_schedule_single_event(time() + $time, 'idt_run_output_binder', ['order' => $order]);
        $order->update_meta_data('IDT_output_request_retry_count', $this->get_order_retry_count($order) + 1);
        $order->save_meta_data();
    }

    /**
     * @param WC_Order $order
     * @return int
     */
    private function get_order_retry_count(WC_Order $order)
    {
        $retry_count = $order->get_meta('IDT_output_request_retry_count', true);
        if (!isset($retry_count) || '' === $retry_count) {
            return 0;
        } else {
            return (int)$retry_count;
        }
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    private function should_reschedule_for_order(WC_Order $order)
    {
        if (Helper::all_items_done($order)) {
            return false;
        }

        $retry_count = $this->get_order_retry_count($order);

        if ($retry_count < 10) {
            return true;
        }

        $order->add_order_note(_x('Order is not yet ready and has been retried a lot. Please check output url.', 'ADMIN_PANEL', 'IDT'), false);
        $order->add_meta_data('IDT_output_render_failed', true);
        $order->delete_meta_data('IDT_output_request_retry_count');
        $order->save_meta_data();

        return false;
    }

    /**
     * Complete the order item when status is ready, fail the order item
     * when status is unavailable and reschedule in other cases
     *
     * @param string $job_url
     * @param WC_Order_Item $order_item
     * @param WC_Order $order
     */
    private function handle_job_url_output($job_url, $order_item, $order)
    {
        $output = $this->get_pdf_output_from($job_url);

        if (!$output || 'pending' === $output->status) {
            $this->maybe_reschedule($order);
            return;
        }

        if ('unavailable' === $output->status) {
            $order_item->update_meta_data('_IDT_output_done', false);
            $order->add_meta_data('IDT_output_render_failed', true);
            $order_item->save_meta_data();
            $order->save_meta_data();
            return;
        }

        $this->create_buttons_for_order_item($order_item->get_id(), $output);
        $this->remove_output_request_url_from_order_item($order_item);
        $order_item->update_meta_data('_IDT_output_done', true);
        $order_item->save_meta_data();
    }

    /**
     * Checks if all items in an order are done rendering, and if so, remove the failed status from the order.
     *
     * @param WC_Order $order
     */
    private function remove_failed_status_if_all_items_are_done(WC_Order $order)
    {
        if (true !== Helper::all_items_done($order)) {
            IDT_Logger::error('Not all items are done yet for order ' . $order->get_order_number());
            return;
        }

        $order->update_meta_data('IDT_output_render_failed', false);
        $order->save_meta_data();
    }

    /**
     * Reschedules the job when necessary.
     *
     * @param WC_Order $order
     * @param int $time
     */
    private function maybe_reschedule(WC_Order $order, $time = 300)
    {
        if ($this->should_reschedule_for_order($order)) {
            $this->reschedule_job_for_order($order, $time);
        }
    }

    /**
     * Sends the request to the rendering server, requesting the high res pdf output of the design
     *
     * @param string $url
     */
    public static function request_pdf_output($url)
    {
        $response = wp_remote_post($url, ['body' => 'outputType=pdf']);

        if (is_wp_error($response)) {
            self::log_request_pdf_output_error(sprintf('Failed to request pdf output: %s', wp_json_encode($response->get_error_messages())));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_message = wp_remote_retrieve_response_message($response);

        if (402 === $response_code) {
            update_option('IDT_pdf_request_quota_reached', true);
            self::log_request_pdf_output_error($response_message);
            // TODO we could throw a QuoteReachedException here.
        } elseif (201 !== $response_code) {
            self::log_request_pdf_output_error($response_message);
        }

        // We should not arrive here unless status code is 201
        update_option('IDT_pdf_request_quota_reached', false);
    }

    /**
     * @param string $response_message
     * @return void
     */
    private static function log_request_pdf_output_error($response_message)
    {
        IDT_Logger::error('There has been an error while requesting the high-res pdf.');
        IDT_Logger::error($response_message);
    }
}
