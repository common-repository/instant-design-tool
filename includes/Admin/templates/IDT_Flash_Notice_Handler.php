<?php

namespace IDT\Admin\Templates;

if ( ! defined( 'ABSPATH' ) ) {
  exit();
}

class IDT_Flash_Notice_Handler {

  /**
   * IDT_Flash_Notice_Handler constructor.
   */
  public function __construct() {
	if ( is_admin() ) {
	  $notices = get_option( 'IDT_flash_notices' );
	  if ( ! $notices || is_null( $notices ) ) {
		return;
	  }
	  foreach ( $notices as $notice ) {
		Admin_Notice::echoNotice( $notice['message'], $notice['type'] );
	  }
	  self::delete_all_notices();
	}
  }

  public static function delete_all_notices() {
	update_option( 'IDT_flash_notices', [] );
  }

  public static function add_flash_notice( $message, $type = 'success' ) {
	$notices = get_option( 'IDT_flash_notices' );
	if ( ! $notices ) {
	  $notices = [];
	}
	array_push( $notices, array(
		"message" => $message,
		"type"    => $type
	) );
	update_option( 'IDT_flash_notices', $notices );
  }
}