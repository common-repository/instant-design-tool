<?php

/**
 * A simple Print API REST client.
 *
 * This small utility simplifies using our REST API from PHP. Print API offers a flexible and
 * secure REST API that lets you print and ship your PDF or image files as a wide range of
 * products, like hardcover books, softcover books, wood or aluminium prints and much more.
 *
 * Read more at: https://www.printapi.nl/services/rest-api
 *
 * @package Print API
 * @version 2.0.0
 * @copyright 2017 Print API
 */

namespace IDT\Core\PrintApi;

final class PrintApi
{
    const LIVE_BASE_URI = 'https://live.printapi.nl/v2/';
    const TEST_BASE_URI = 'https://test.printapi.nl/v2/';
    const USER_AGENT = 'Print API PHP Client v2.0.0';
    /**
     * @var array|\WP_Error
     */
    private $response;

    /**
     * Call this to obtain an authenticated Print API client.
     *
     * The client ID and secret can be obtained by creating a free Print API account at:
     * https://portal.printapi.nl/test/account/register
     *
     * @param string $clientId The client ID assigned to your application.
     * @param string $secret The secret assigned to your application.
     * @param string $environment One of "test" or "live".
     *
     * @return PrintApi An authenticated Print API client.
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    static public function authenticate($clientId, $secret, $environment = 'test')
    {
        // Construct OAuth 2.0 token endpoint URL:

        $baseUri = self::_getBaseUri($environment);
        $oAuthUri = $baseUri . 'oauth';

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'User-Agent: ' . self::USER_AGENT;

        $oAuthParameters = self::_formatOAuthParameters($clientId, $secret);

        //Create args for post
        $args = array(
            'body' => $oAuthParameters,
            'timeout' => '15',
            'redirection' => '5',
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array()
        );

        $wp_result = wp_remote_post($oAuthUri, $args);
        $responseObj = json_decode(wp_remote_retrieve_body($wp_result));

        if (isset($responseObj->error) && !isset ($responseObj->access_token)) {
            return false;
        }

        $token = json_decode(wp_remote_retrieve_body($wp_result))->access_token;
        self::_throwExceptionForFailure($wp_result);

        return new PrintApi($baseUri, $token);
    }

    // ==============
    // Static helpers
    // ==============

    /**
     * Returns the base URI of the specified Print API environment.
     *
     * @param string $environment One of "test" or "live".
     *
     * @return string The base URI of the specified Print API environment.
     *
     * @throws PrintApiException If the environment is unknown.
     */
    static private function _getBaseUri($environment)
    {
        if ($environment === 'test') {
            return self::TEST_BASE_URI;
        }

        if ($environment === 'live') {
            return self::LIVE_BASE_URI;
        }

        throw new PrintApiException('Unknown environment: ' . $environment . '. Must be one of '
            . '"test" or "live".');
    }

    /**
     * Returns formatted parameters for the OAuth token endpoint.
     *
     * @param string $clientId The client ID credential.
     * @param string $secret The client secret credential.
     *
     * @return string Formatted parameters for the Print API OAuth token endpoint.
     */
    static private function _formatOAuthParameters($clientId, $secret)
    {
        return 'grant_type=client_credentials'
            . '&client_id=' . urlencode($clientId)
            . '&client_secret=' . urlencode($secret);
    }

    /**
     * Throws an exception if the wp_remote_post failed
     *
     * @param mixed $result The result of wp_remote_post
     *
     * @throws PrintApiException         If the request failed.
     * @throws PrintApiResponseException If the API returned an error report.
     */
    static private function _throwExceptionForFailure($result)
    {
        if ($result instanceof \WP_Error) {
            $error = $result->get_error_message();
            //On error, throw exception anyway
            throw new PrintApiException("wp remote post error: $error");
        }

        // Check for API response codes indicating failure:
        $statusCode = wp_remote_retrieve_response_code($result);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new PrintApiResponseException("Status code was not between 200 and 300. Serialized Dump: " . serialize($result['body']));
        }
    }

    // ================
    // Instance members
    // ================

    /** @var string */
    private $baseUri;
    /** @var string */
    private $token;
    /** @var int */
    private $timeout = 90;

    /**
     * Private constructor, call {@link authenticate()} to obtain an instance of this class.
     *
     * @param string $baseUri The base URI of the Print API environment.
     * @param string $token An OAuth access token.
     */
    private function __construct($baseUri, $token)
    {
        $this->baseUri = $baseUri;
        $this->token = $token;
    }

    /**
     * Sends an HTTP POST request to Print API.
     *
     * @param string $uri The destination URI. Can be absolute or relative.
     * @param array $content The request body as an associative array.
     * @param array $parameters The query parameters as an associative array.
     *
     * @return string
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    public function post($uri, $content, $parameters = array())
    {
        $uri = $this->_constructApiUri($uri, $parameters);
        $content = $content !== null ? json_encode($content) : null;

        $response = wp_remote_post($uri, [
            'headers' => [
                'Authorization' => " Bearer " . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => $content
        ]);

        $this->response = $response;
        self::_throwExceptionForFailure($response);
        return wp_remote_retrieve_body($response);
    }

    /**
     * Sends an HTTP GET request to Print API.
     *
     * @param string $uri The destination URI. Can be absolute or relative.
     * @param array $parameters The query parameters as an associative array.
     *
     * @return object The decoded API response.
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    public function get($uri, $parameters = array())
    {
        $uri = $this->_constructApiUri($uri, $parameters);
        $response = wp_remote_get($uri, [
            'headers' => [
                'Authorization' => " Bearer " . $this->token,
                'Accept' => 'application/json'
            ]
        ]);
        self::_throwExceptionForFailure($response);

        return json_decode(wp_remote_retrieve_body($response));
    }

    /**
     * Uploads a file to Print API.
     *
     * @param string $uri The destination URI. Can be absolute or relative.
     * @param string $fileName The name of the file to upload.
     * @param string $mediaType One of "application/pdf", "image/jpeg" or "image/png".
     *
     * @return object The decoded API response.
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    public function upload($uri, $fileName, $mediaType)
    {
        $uri = $this->_constructApiUri($uri);
        $content = wp_remote_retrieve_body(wp_remote_get($fileName));

        return $this->_request('POST', $uri, $content, $mediaType);
    }

    /**
     * Gets the request timeout in seconds. 0 if timeout is disabled.
     *
     * @return int The request timeout in seconds.
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the request timeout in seconds. Specify 0 to disable timeout.
     *
     * @param int $timeout The request timeout in seconds.
     *
     * @throws PrintApiException If the specified timeout is not an integer.
     */
    public function setTimeout($timeout)
    {
        if (!is_int($timeout)) {
            throw new PrintApiException('Argument $timeout must be an integer.');
        }

        $this->timeout = $timeout;
    }

    // ===============
    // Private helpers
    // ===============

    /**
     * Generates a fully qualified URI for the API.
     *
     * @param string $uri The destination URI. Can be absolute or relative.
     * @param array $parameters The query parameters as an associative array.
     *
     * @return string A fully qualified API URI.
     */
    private function _constructApiUri($uri, $parameters = array())
    {
        $uri = trim($uri, '/');

        if (strpos($uri, $this->baseUri) === false) {
            $uri = $this->baseUri . $uri;
        }

        if (!empty($parameters)) {
            $uri .= '?' . http_build_query($parameters);
        }

        return $uri;
    }

    /**
     * Sends a custom HTTP request to the API.
     *
     * @param string $method The HTTP verb to use for the request.
     * @param string $uri The destination URI (absolute).
     * @param mixed $content The request body, e.g. a JSON string.
     * @param null|string $contentType The Content-Type HTTP header value.
     *
     * @return object The decoded API response.
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    private function _request($method, $uri, $content = null, $contentType = null)
    {

        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Accept-Encoding' => 'gzip',
            'Authorization' => "Bearer $this->token",
            'Accept' => 'application/json',
            'Content-Length' => 425,
            'Content-Type' => 'application/json',
            'Keep-Alive' => "timeout: 3"

        ];
        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $args = [
            "headers" => $headers,
            "method" => $method
        ];

        if ($content !== null) {
            $args['body'] = $content;
        }

        $response = wp_remote_request($uri, $args);
        self::_throwExceptionForFailure($response);
        $result = wp_remote_retrieve_body($response);

        return json_decode($result);
    }

    /**
     * @return array|\WP_Error
     */
    public function getResponse()
    {
        return $this->response;
    }
}



