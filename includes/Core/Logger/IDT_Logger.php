<?php

namespace IDT\Core\Logger;

use IDT;

class IDT_Logger
{
    /**
     * Logs message to the database with optional message type
     * @param string $message
     * @param string $type
     */
    public static function log($message, $type = '[ERROR]')
    {
        if (defined('DOING_AJAX')) {
            $message = '[DOING AJAX]: ' . $message;
        }

        if (defined('DOING_CRON')) {
            $message = '[DOING CRON]: ' . $message;
        }

        error_log($message);

        if (get_option('wc_settings_idt_logging_enabled') !== '1') {
            return;
        }

        if (!IDT_Log_Table::exists()) {
            IDT_Log_Table::create();
        }

        IDT_Log_Table::insert($message, $type);
    }

    public static function info($message)
    {
        self::log($message, '[INFO]');
    }

    public static function warning($message)
    {
        self::log($message, '[WARNING]');
    }

    public static function error($message)
    {
        self::log($message);
    }
}
