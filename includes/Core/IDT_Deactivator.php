<?php

namespace IDT\Core;

if ( ! defined( "ABSPATH" ) ) {
  exit;
}

class IDT_Deactivator {

  /**
   * Function will be run at plugin deactivation
   *
   * @param null $url
   */
  public static function deactivate( $url = null ) {
	try {
	  require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	  if ( ! is_null( $url ) ) {
		deactivate_plugins( $url );
	  }
	} catch ( \Exception $e ) {
	  header( admin_url( 'plugins.php' ) );
	}

  }
}