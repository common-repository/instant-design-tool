<?php

namespace IDT\Admin;

use IDT\Core\Logger\IDT_Logger;

if ( ! defined( "ABSPATH" ) ) {
  exit;
}

class IDT_Button_Post_Type {

  /**
   * IDT_Button_Post_Type constructor.
   */
  public function __construct() {
	add_action( 'init', array( $this, 'create_idt_button_post_type' ), 10 );
	add_action( 'save_post', array( $this, 'run_on_post_publication' ), 10, 2 );
	add_action( 'manage_idt_buttons_posts_columns', array( $this, 'create_shortcode_col' ) );
	add_action( 'manage_idt_buttons_posts_custom_column', array( $this, 'display_shortcodes' ), 10, 2 );
	add_shortcode( 'idt_atc_button', array( $this, 'handle_idt_atc_shortcode' ) );

  }

  /**
   * This function will parse a found shortcode,
   * then get the content it should render,
   * and change the link to match the idt_editor_url meta
   *
   *
   * @param $args
   */
  public function handle_idt_atc_shortcode( $args ) {
	//TODO this could be used to still render a default button, but i need to decide what a good default would be first.
	$placeholder = "";
	if ( array_key_exists( 'id', $args ) && is_product() ) {
	  $buttonContent = get_post( $args['id'] )->post_content;
	  global $post;

	  foreach ( parse_blocks( $buttonContent ) as $blockContent ) {
		if ( $blockContent['blockName'] == "core/buttons" ) {
		  //We have a block row
		  $buttonHTML = $this->getCorrectButtonBlock( $blockContent );
		}
	  }

	  //If we did not find a valid button return false
	  if ( ! isset( $buttonHTML ) ) {
		echo $placeholder;
	  }

	  $product    = wc_get_product( $post );
	  $url        = $product->get_meta( 'idt_editor_url' );
	  $buttonHTML = str_replace( 'IDT', $url, $buttonHTML );
	  echo $buttonHTML;
	} else {
	  echo $placeholder;
	}
  }

  /**
   * This function will create a new custom post type, for containing the newly created buttons.
   */
  public function create_idt_button_post_type() {
	$labels = array(
		'name'               => _x( 'IDT_Buttons', 'Post Type General Name', 'IDT' ),
		'singular_name'      => _x( 'IDT_Button', 'Post Type Singular Name', 'IDT' ),
		'menu_name'          => _x( 'IDT Buttons', 'ADMIN_PANEL' , 'IDT' ),
		'all_items'          => _x( 'All Buttons', "ADMIN_PANEL", 'IDT' ),
		'view_item'          => _x( 'View Button', "ADMIN_PANEL", 'IDT' ),
		'add_new_item'       => _x( 'Add New Button', "ADMIN_PANEL", 'IDT' ),
		'add_new'            => _x( 'Add New', "ADMIN_PANEL", 'IDT' ),
		'edit_item'          => _x( 'Edit Button', "ADMIN_PANEL", 'IDT' ),
		'update_item'        => _x( 'Update Button', "ADMIN_PANEL", 'IDT' ),
		'search_items'       => _x( 'Search Button', "ADMIN_PANEL", 'IDT' ),
		'not_found'          => _x( 'Not found', "ADMIN_PANEL", 'IDT' ),
		'not_found_in_trash' => _x( 'Not found in Trash', "ADMIN_PANEL", 'IDT' ),
	);
	$args   = array(
		'label'               => _x( 'IDT_button', "ADMIN_PANEL", 'IDT' ),
		'description'         => _x( 'Post Type voor IDT_buttons', "ADMIN_PANEL", 'IDT' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor' ),
		'taxonomies'          => array(),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'show_in_rest'        => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-admin-settings',
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => false,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
	);
	register_post_type( 'IDT_Buttons', $args );

  }

  /**
   * This function will run after a post is saved, and determine if the process for
   * saving a new button should be started
   * @hooked post
   *
   * @param $post_id
   */
  public function run_on_post_publication( $post_id ) {
	$post = get_post( $post_id );
	if ( $post->post_type == 'idt_buttons' ) {
	  $this->handle_post_save( $post );
	}
  }

  /**
   * this function will save the created shortcode with the button
   *
   * @param \WP_Post $post
   *
   */
  public function handle_post_save( \WP_Post $post ) {
	if ( ! get_post_meta( $post->ID, 'shortcode' ) ) {
	  add_post_meta( $post->ID, 'shortcode', $this->generate_shortcode( $post->ID ) );
	}
  }

  /**
   * This function will recursively search for a button node containing the IDT href.
   *
   * @param array $buttonBlock
   *
   * @return mixed
   */
  private function getCorrectButtonBlock( array $buttonBlock ) {
	if ( array_key_exists( 'innerBlocks', $buttonBlock ) && count( $buttonBlock['innerBlocks'] ) > 0 ) {
	  foreach ( $buttonBlock['innerBlocks'] as $innerblock ) {
		return $this->getCorrectButtonBlock( $innerblock );
	  }
	} else {
	  //We have reached an end node
	  if ( strpos( $buttonBlock['innerHTML'], 'IDT' ) !== false ) {
		return $buttonBlock['innerHTML'];
	  }
	}
  }

  /**
   * This function will generate the shortcode string for new buttons
   *
   * @param $id
   *
   * @return string
   */
  public function generate_shortcode( $id ) {
	$sc = "idt_atc_button id=" . $id;
	IDT_Logger::error( $sc );

	return $sc;
  }

  /**
   * This function will add an extra column to the idt buttons list, containing the shortcode.
   *
   * @param $columns
   *
   * @return array
   */
  public function create_shortcode_col( $columns ) {
	$columns     = ( is_array( $columns ) ) ? $columns : array();
	$shortcode   = array( 'shortcode' => 'Shortcode' );
	$position    = 2;
	$new_columns = array_slice( $columns, 0, $position, true ) + $shortcode;

	return array_merge( $new_columns, $columns );
  }

  /**
   * This function will display the content in the shortcode column.
   *
   * @param $column
   * @param $post_id
   */
  public function display_shortcodes( $column, $post_id ) {
	$sc = get_post_meta( $post_id, 'shortcode', true );
	echo "<pre>[" . $sc . "]</pre>";
  }

}