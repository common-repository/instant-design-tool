<?php

namespace IDT\Core\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class IDT_Show_Pid_On_Product_Page {

  /**
   * IDT_Show_Pid_On_Product_Page constructor.
   */
  public function __construct() {
	add_action( 'add_meta_boxes', array( $this, 'IDT_add_metabox_for_pid' ) );
  }

  /**
   * This will be called on hook woocommerce_product_options_shipping
   * Will show the product_id of the currenly viewed product in a metabox
   */
  public function IDT_add_metabox_for_pid() {
	add_meta_box( 'IDT_show_pid', _x( 'Product ID', 'ADMIN_PANEL', 'IDT' ), array(
		$this,
		'IDT_pid_metabox_content'
	), 'product', 'side', 'core' );
  }

  /**
   *    Content for the metabox
   */
  public function IDT_pid_metabox_content() {
	$id = the_ID();

	return "<p style='font-size: 36px'>$id</p>";
  }

}