<?php

namespace IDT\Core\Api\User;

if ( ! defined( "ABSPATH" ) ) {
  exit;
}

use WP_REST_Request;

class IDT_UserEndpoint_Identify {

  /**
   * IDT_UserEndpoint_Identify constructor.
   */
  public function __construct() {
	add_action( 'rest_api_init', array( $this, 'create_user_info_endpoint' ) );
  }

  public function create_user_info_endpoint() {
	register_rest_route( 'idt/v1', '/getcustomer/', array(
		'methods'  => 'POST',
		'callback' => array( $this, 'get_user_id_from_cookie' ),
		'permission_callback' => '__return_true'
	) );
  }

  /**
   * @param WP_REST_Request $request
   *
   * @return false|mixed|string|void
   * @throws \Exception
   */
  public function get_user_id_from_cookie( WP_REST_Request $request ) {

	$api_key = get_option( 'wc_settings_idt_api_key' );

	if ( $request->get_param( 'apiKey' ) !== $api_key ) {
	  throw new \Exception( 'API key mismatch' );
	}

    if ( isset( $_COOKIE[ 'IDT_AUTH' ] ) ) {
        $user_id = wp_validate_auth_cookie( $_COOKIE[ 'IDT_AUTH' ], 'logged_in' );
        if ( is_numeric( $user_id ) ) {
          return [ 'userId' => $user_id ];
        }
    }

    return null; // Means no user logged in.
  }
}