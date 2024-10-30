<?php
/**
 * Created by PhpStorm.
 * User: melle
 * Date: 11/05/2020
 * Time: 14:16
 */

namespace IDT\Core\Elementor;

use IDT\Admin\Templates\Admin_Notice;

class IDT_Display_Elementor_Notice {

  /**
   * IDT_Display_Elementor_Notice constructor.
   */
  public function __construct() {
	add_action( 'admin_init', array( $this, 'idt_check_notice_dismissed_param' ) );

	$this->maybe_show_notice();
  }

  public function maybe_show_notice() {
	if ( ! get_option( 'idt_elementor_notice_displayed' ) ) {
	  if ( get_option( 'idt_elementor_mode' ) ) {
		add_action( 'admin_notices', function () {
		  $href = '//www.instantdesigntool.com/elementor';
		  $msg  = "To properly set up Instant Design Tool with Elementor, please <a href='$href'>read our guide</a> thoroughly";
		  Admin_Notice::echoNotice( $msg . ' <a class="button" href="?idt_elementor_notice_dismissed">Dismiss</a>', "info" );
		} );
	  }
	}
  }

  public function idt_check_notice_dismissed_param() {
	if ( is_admin() ) {
	  if ( isset( $_GET['idt_elementor_notice_dismissed'] ) ) {
		update_option( 'idt_elementor_notice_displayed', true );
	  }
	}
  }

}