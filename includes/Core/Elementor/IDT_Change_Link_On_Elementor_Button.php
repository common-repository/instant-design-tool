<?php

namespace IDT\Core\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
  exit();
}

class IDT_Change_Link_On_Elementor_Button {

  /**
   * IDT_Change_Link_On_Elementor_Button constructor.
   */
  public function __construct() {
	add_filter( 'elementor/frontend/the_content', array( $this, 'handle_idt_elementor_atc_shortcode' ) );
  }

  /**
   * On elementor page load, check to see if we have an IDT ref and swap href on button.
   *
   * @param $content
   *
   * @return mixed
   */
  public function handle_idt_elementor_atc_shortcode( $content ) {

	if ( ! is_product() ) {
	  return $content;
	}
	global $post;
	$product = wc_get_product( $post->ID );
	$url     = $product->get_meta( 'idt_editor_url' );

	if ( empty( $url ) || ! $product->is_type( 'editable' ) ) {
	  $content = str_replace( 'href="IDT"', 'href=""', $content );

	  return $content;
	}

	if ( strpos( $content, 'href="http://IDT' ) ) {
	  $content = str_replace( 'href="http://IDT"', 'href="' . $url . '"', $content );
	} else if ( strpos( $content, 'href="https://IDT' ) ) {
	  $content = str_replace( 'href="https://IDT"', 'href="' . $url . '"', $content );
	} else {
	  $content = str_replace( 'href="IDT"', 'href="' . $url . '"', $content );
	}

	apply_filters( 'idt_elementor_atc_button_content', $content );

	return $content;
  }

}