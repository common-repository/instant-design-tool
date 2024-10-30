<?php

namespace IDT\Core\LoginCookie;

use IDT\Core\Helpers\Helper;

if (!defined("ABSPATH")) {
    exit;
}

class IDT_UserCookie
{
    private $cookie_domain;

    const COOKIE_IDT_TO_SITE = "IDT_TO_SITE"; // Cookie used to pass the login state on to the IDT subdomain.
    const COOKIE_SITE_TO_IDT = "SITE_TO_IDT"; // Cookie set when the user logs in on the IDT subdomain.

    public function __construct()
    {
        if (Helper::settings_synced_already()) {

            $this->cookie_domain = Helper::determine_cookie_domain();

            add_action( 'set_auth_cookie', function ( $cookie ) {
                setcookie( self::COOKIE_SITE_TO_IDT, $cookie, 0, '/', $this->cookie_domain );
                // For safety because some WP plugins / components may rely on this superglobal:
                $logged_in_cookie = is_ssl() ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
                $_COOKIE[ $logged_in_cookie ] = $cookie;
            } );

            add_action( 'set_logged_in_cookie', function ( $cookie ) {
                setcookie( self::COOKIE_SITE_TO_IDT, $cookie, 0, '/', $this->cookie_domain );
                // For safety because some WP plugins / components may rely on this superglobal:
                $_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
            } );

            // This order is important:
            add_action('init', array($this, 'cleanup_site_to_idt_cookie'));
            add_action('init', array($this, 'receive_login_from_idt'));
        }
    }

    private function is_logging_out()
    {
        return isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] === 'logout';
    }

    public function receive_login_from_idt()
    {
        if ( isset( $_COOKIE[ self::COOKIE_IDT_TO_SITE ] ) ) {
            setcookie(self::COOKIE_IDT_TO_SITE, '', 1, '/', $this->cookie_domain );
            if ( !is_user_logged_in() && !$this->is_logging_out() ) {
                $user_id = wp_validate_auth_cookie( $_COOKIE[self::COOKIE_IDT_TO_SITE], 'logged_in' );
                if ( $user_id !== false ) {
                    wp_set_current_user( $user_id );
                    wp_set_auth_cookie( $user_id );
                }
            }
        }
    }

    public function cleanup_site_to_idt_cookie()
    {
        if ( !isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) || $this->is_logging_out() ) {
           setcookie( self::COOKIE_SITE_TO_IDT, '', 1, '/', $this->cookie_domain );
        }
    }
}