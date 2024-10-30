<?php

namespace IDT\Core\Logger;

class IDT_Log_Table
{
    public static function create()
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}idt_logs";
        $charset_collate = $wpdb->get_charset_collate();
        //TODO UNIQUE contraint on message_created_at
        $sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		status varchar(45) NOT NULL,
		message text NOT NULL,
		created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY  (id)
	    ) $charset_collate;";

        //Require the dbDelta function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        return dbDelta($sql);
    }

    public static function truncate()
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}idt_logs";
        $wpdb->query('DELETE FROM ' . $table_name);
    }

    public static function insert($message, $type = '[ERROR]')
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}idt_logs";

        return $wpdb->insert(
            $table_name,
            array(
                'status' => $type,
                'message' => $message,
                'created_at' => gmdate('Y-m-d H:i:s'),
            )
        );
    }

    public static function get_log_entries()
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}idt_logs";

        return $wpdb->get_results('SELECT * FROM ' . $table_name);
    }

    public static function exists()
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}idt_logs";

        return !empty($wpdb->get_results($wpdb->prepare('show tables like %s', $table_name)));
    }


    /**
     * This function is used to get the contents from the log table, send them to the output buffer and send the
     * output buffer in text/plain format with idt_log.txt as an attachment.
     */
    public static function export()
    {
        ob_get_clean();

        header('Content-type: text/plain');
        header("Content-Disposition: attachment;filename= 'idt_log.txt'");
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');
        header('Expires: 0');

        foreach (self::get_log_entries() as $log_entry) {
            echo esc_html(sprintf('%s: %s %s', $log_entry->created_at, $log_entry->status, $log_entry->message) . PHP_EOL);
        }

        ob_end_flush();
        exit;
    }
}
