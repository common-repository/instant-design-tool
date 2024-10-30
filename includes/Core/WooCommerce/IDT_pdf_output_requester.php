<?php

namespace IDT\Core\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

use IDT\Core\Helpers\Exceptions\QuoteReachedException;
use IDT\Core\Helpers\Helper;
use Exception;
use WP_Error;

class IDT_pdf_output_requester
{
    private $order_id;
    private $outputUrls;

    /**
     * IDT_pdf_output_requester constructor.
     *
     * @param int $order_id
     */
    public function __construct($order_id)
    {
        $this->order_id = $order_id;
        $this->outputUrls = Helper::get_output_urls_from_order_id($order_id);
    }

    /**
     * Function to start the process of re-requesting high res output pdf for an order
     *
     * @return bool
     */
    public function fire()
    {
        $allSucceeded = true;

        if (count($this->outputUrls) == 0) {
            return $allSucceeded;
        }

        foreach ($this->outputUrls as $outputUrl) {
            try {
                $this->try_request_output_pdf($outputUrl);
            }
            catch (QuoteReachedException $quoteReachedException) {
                update_option('IDT_pdf_request_quota_reached', true);
                error_log($quoteReachedException->getMessage());
                $allSucceeded = false;
                break;
            }
            catch (Exception $exception) {
                error_log($exception->getMessage());
                error_log($exception->getTraceAsString());
                $allSucceeded = false;
            }
        }

        return $allSucceeded;
    }

    /**
     * Trying to make a post request to the design tool to request
     * the generation of the output pdf.
     *
     * @param $outputUrl
     * @throws QuoteReachedException
     */
    private function try_request_output_pdf($outputUrl)
    {
        $response = $this->post_request($outputUrl, 'outputType=pdf');
        $responseCode = wp_remote_retrieve_response_code($response);

        if ($responseCode === 402) {
            throw new QuoteReachedException("Quote reached. Please upgrade to a higher tier plan.");
        }
    }

    /**
     * @param string $url
     * @param string $body
     * @return array
     * @throws Exception
     */
    private function post_request($url, $body) {
        $response = wp_remote_post($url, ['body' => $body]);

        if (is_wp_error($response)) {
            $errorMessage = json($response->get_error_messages());
            throw new Exception("POST request to $url with body: $body resulted in an error: " . $errorMessage);
        }

        return $response;
    }
}