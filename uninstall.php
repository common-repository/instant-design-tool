<?php

namespace IDT;

require_once(__DIR__ . "/includes/Core/Logger/IDT_Logger.php");
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  Core\Logger\IDT_Logger::error( "WP_UNINSTALL_PLUGIN not defined" );
  exit;
}

Core\Logger\IDT_Logger::info( "uninstall process started of Instant Design Tool" );
delete_option( 'wc_settings_idt_api_key' );
delete_option( 'idt_firstrun_completed' );
delete_option( 'wc_settings_idt_connect_code' );
delete_option( 'wc_settings_idt_settings_sync_url' );
delete_option( 'wc_settings_idt_button_text_single' );
delete_option( 'wc_settings_idt_button_text' );
delete_option( 'wc_settings_idt_print_api_client_id' );
delete_option( 'wc_settings_idt_print_api_secret' );
delete_option( 'wc_settings_idt_use_printapi' );
delete_option( 'wc_settings_idt_printapi_environment' );
delete_option( 'wc_settings_idt_settings_synced' );
delete_option( 'wc_settings_idt_print_api_status' );
delete_option( 'wc_settings_idt_editor_domain' );
delete_option( 'wc_settings_idt_use_custom_texts' );
delete_option( 'IDT_pdf_request_quota_reached' );
delete_option( 'IDT_elementor_mode' );
delete_option( 'wc_settings_idt_guest_mode' );
delete_option( 'wc_settings_idt_only_paid_orders' );
delete_option( 'idt_version' );
delete_option( 'wc_settings_idt_display_project_details' );

//TODO Remove Cookies

$cd = get_option( 'wc_settings_idt_editor_domain' );

if ( false === $cd ) {
  $cd = "." . $_SERVER['HTTP_HOST'];
} else {
  $parsedUrl        = parse_url( $cd );
  $explodedURL      = explode( '.', $parsedUrl['host'] );
  $urlWithoutPrefix = join( '.', array( $explodedURL[1], $explodedURL[2] ) );
  $urlWithPrefix    = "." . $urlWithoutPrefix;

  $cd = $urlWithPrefix;
}

unset( $_COOKIE['IDT_USER'] );
setcookie( 'IDT_USER', 'null', time() - 2000, '/' );
setcookie( 'IDT_USER', 'null', time() - 2000, '/', $cd );

