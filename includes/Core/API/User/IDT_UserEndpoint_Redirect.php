<?php

namespace IDT\Core\Api\User;

use IDT\Core\Helpers\Helper;

if (!defined("ABSPATH")) {
    exit;
}

class IDT_UserEndpoint_Redirect
{
    public function __construct()
    {
        add_action('init', [$this, 'set_idt_ref_cookie_when_idt_ref_query_string_set']);
        add_action('user_register', [$this, 'redirect_user_after_login']);
        add_action('wp_login', [$this, 'redirect_user_after_login']);
    }

    /**
     * When we find a $_GET['idt_ref'] parameter, set a cookie with the value.
     */
    public function set_idt_ref_cookie_when_idt_ref_query_string_set()
    {
        if (isset($_GET['idt_ref'])) {
            setcookie('idt_ref', $_GET['idt_ref'], time() + 300, '/', Helper::determine_cookie_domain());
        }
    }

    /**
     * If a user registers or logs in, and we have a cookie named idt_ref, we want to redirect to the url in the value
     */
    public function redirect_user_after_login()
    {
        if (isset($_COOKIE['idt_ref'])) {
            header('Location: ' . $_COOKIE['idt_ref']);
            unset($_COOKIE['idt_ref']);
            setcookie('idt_ref', '', 1, '/', Helper::determine_cookie_domain());
            //We have to exit, because otherwise the location header will get overridden.
            exit();
        }
    }
}