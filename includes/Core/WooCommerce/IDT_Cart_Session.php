<?php

namespace IDT\Core\WooCommerce;

use IDT\Core\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class IDT_Cart_Session {
  /**
   * IDT_Cart_Session constructor.
   */
  public function __construct()
  {
      if (!get_option( 'idt_snapshot_created' ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'idt_snapshot';

            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            token nvarchar(64) NOT NULL,
            cart text NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            update_option( 'idt_snapshot_created', true );
        }
    }
}