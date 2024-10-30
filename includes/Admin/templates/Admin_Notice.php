<?php

namespace IDT\Admin\Templates;

if ( ! defined( "ABSPATH" ) ) {
  exit;
}

class Admin_Notice {

  public static function returnNotice( $message, $status = "success", $textDomain = "IDT" ) {
	$translated_message = _x( $message, 'ADMIN_PANEL', $textDomain );

	return <<<EOT
        <div class="notice notice-$status is-dismissible">
			<p> $translated_message </p>
        </div>
EOT;
  }

  public static function echoNotice( $message, $status = "success", $textDomain = "IDT" ) {
	$translated_message = _x( $message, 'ADMIN_PANEL', $textDomain );

	echo <<<EOT
        <div class="notice notice-$status is-dismissible">
			<p> $translated_message </p>
        </div>
EOT;
  }

}