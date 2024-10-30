<?php

namespace IDT\Core\WooCommerce;

if ( ! defined( "ABSPATH" ) ) {
  exit;
}

class IDT_Change_Order_Page_Thumbnail {

  /**
   * IDT_Change_Order_Page_Thumbnail constructor.
   */
  public function __construct() {
	add_filter( 'woocommerce_admin_order_item_thumbnail', array( $this, 'change_thumbnail_url' ), 10, 3 );
  }

  /**
   * Changing thumbnail on the order page
   *
   *
   * @param $item_id
   * @param $item
   *
   * @return string
   */
  public function change_thumbnail_url( $current_image, $item_id, $item ) {
	if ( isset( $item['_IDT_hidden_meta'] ) ) {
	  return ( "<img src='" . $item['_IDT_hidden_meta']['_IDT_thumbnailUrl'] . "'/>" );
	} else {
	  return $current_image;
	}
  }

}