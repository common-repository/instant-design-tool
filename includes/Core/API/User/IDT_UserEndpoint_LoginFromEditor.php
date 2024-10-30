<?php

namespace IDT\Core\Api\User;

if ( ! defined( "ABSPATH" ) ) {
  exit;
}

use WP_Error;
use WP_REST_Request;
use WP_User;

class IDT_UserEndpoint_LoginFromEditor {

  /**
   * IDT_UserEndpoint_LoginFromEditor constructor.
   */
  public function __construct() {
	add_action( 'rest_api_init', array( $this, 'create_external_login_endpoint' ) );

  }

  /**
   * Create the endpoint
   */
  public function create_external_login_endpoint() {
	register_rest_route( 'idt/v1', '/extlogin/', array(
		'methods'  => 'POST',
		'callback' => array( $this, 'log_user_in_or_return_error' ),
		'permission_callback' => '__return_true'

	) );
  }

  /**
   * @param WP_REST_Request $request
   *
   * @return array
   * @throws \Exception
   */
  public function log_user_in_or_return_error( WP_REST_Request $request ) {
	$api_key = get_option( 'wc_settings_idt_api_key' );
	if ( $request->get_param( 'apiKey' ) == $api_key ) {

	  $data                         = $request->get_body_params();
	  $credentials['user_login']    = $data['email'];
	  $credentials['user_password'] = $data['password'];

	  $status = wp_signon( $credentials );

	  if ( $status instanceof WP_USER && ! ( $status instanceof WP_Error ) ) {
		$returnValue = [
			"status" => "success"
		];
	  } else {
		$returnValue = [
			'status' => 'InvalidCredentials'
		];
	  }

	  return $returnValue;
	} else {
	  throw new \Exception( 'ApiKey Mismatch' );
	}
  }

}