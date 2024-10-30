<?php

namespace IDT\Core\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class IDT_Check_Elementor_Active {

  /**
   * IDT_Check_Elementor_Active constructor.
   */
  public function __construct() {
	$activePlugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
	// Check only on admin requests to save some resources.
	if ( is_admin() ) {
	  if ( ! in_array( 'elementor/elementor.php', $activePlugins ) || ! in_array( 'elementor-pro/elementor-pro.php', $activePlugins ) ) {
		update_option( 'IDT_elementor_mode', false );
	  } else {
		//If elementor and elementor pro have both been found
		update_option( 'IDT_elementor_mode', true );
	  }
	}
  }
}