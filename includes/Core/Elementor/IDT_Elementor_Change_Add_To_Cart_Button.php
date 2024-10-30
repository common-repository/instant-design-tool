<?php

namespace IDT\Core\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class IDT_Elementor_Change_Add_To_Cart_Button {

  /**
   * IDT_Elementor_Change_Add_To_Cart_Button constructor.
   */
  public function __construct() {
	add_action( 'elementor/widget/render_content', array(
		$this,
		'change_add_to_cart_button_for_editable_product'
	), 10, 2 );

  }

  /**
   * Clone all the attributes on elem onto elemToCopyTo
   *
   * @param DOMElement $elem
   * @param DOMElement $elemToCopyTo
   *
   * @return DOMElement
   */
  private function cloneAllAttributes( DOMElement $elem, DOMElement $elemToCopyTo ) {
	if ( $elem->hasAttributes() ) {
	  foreach ( $elem->attributes as $attr ) {
		$elemToCopyTo->setAttribute( $attr->nodeName, $attr->nodeValue );
	  }
	}

	return $elemToCopyTo;
  }

  /**
   * Remove the form from the content string
   *
   * @param DOMXPath $xpath
   */
  private function removeForm( DOMXPath $xpath ) {
	$form = $xpath->query( '//form' )->item( 0 );
	if ( $form ) {
	  $form->parentNode->removeChild( $form );
	}
  }

  /**
   * Search the Add to cart button in the DOM.
   *
   * @param DOMXPath $xpath
   *
   * @return bool|DOMElement
   */
  private function getAddToCartButton( DOMXPath $xpath ) {
	$atcButton = $xpath->query( "//*[contains(@class, 'single_add_to_cart_button')]" )->item( 0 );
	if ( $atcButton !== null ) {
	  return $atcButton;
	} else {
	  return false;
	}
  }

  /**
   * Swap the add to cart button from elementor with custom one which will support links
   *
   * @param $content
   * @param $product
   *
   * @return string
   */
  private function swapATCButtonWithAnchor( $content, $product ) {
	$dd = new DOMDocument;
	$dd->loadHTML( $content );
	$xpath     = new DOMXPath( $dd );
	$atcButton = getAddToCartButton( $xpath );

	if ( false === $atcButton ) {
	  $textFromDB = get_option( 'wc_settings_idt_button_text_single' );
	  if ( $textFromDB ) {
		$text = $textFromDB;
	  } else {
		$text = __( 'Start Designing', 'IDT' );
	  }

	  $message   = apply_filters( 'idt_add_to_cart_button_text', $text );
	  $atcButton = $dd->createElement( 'a', $message );

	  $classes = apply_filters( 'idt_add_to_cart_button_classes', 'single_add_to_cart_button button alt' );
	  $atcButton->setAttribute( 'class', $classes );
	}

	$custom_url = apply_filters( 'idt_add_to_cart_button_link', $product->get_meta( 'idt_editor_url' ) );
	$atcButton->setAttribute( 'href', $custom_url );
	$atcButton->setAttribute( 'type', null );

	removeForm( $xpath );

	$dd->appendChild( $atcButton );
	$dd->removeChild( $dd->doctype );

	return $dd->saveHTML();
  }

  /**
   * This is the callback function that will be run after rendering the content for an elementor widget
   * Because it is more resource intensive due to running every render, we exclude the largest portions of conditions like is_product and product type first.
   *
   * @param $content
   * @param Widget_Base $widget
   *
   * @return mixed|void
   */
  public function change_add_to_cart_button_for_editable_product( $content, Widget_Base $widget ) {
	if ( is_product() ) {
	  global $product;
	  if ( get_class( $product ) == "WC_Product_Editable" ) {
		if ( $widget instanceof ElementorPro\Modules\Woocommerce\Widgets\Product_Add_To_Cart ) {
		  $content = swapATCButtonWithAnchor( $content, $product );
		  $content = str_replace( '<html><body>', '', $content );
		  $content = str_replace( '</body></html>', '', $content );
		}
	  }
	}

	return apply_filters( 'idt_add_to_cart_button_content', $content );

  }
}