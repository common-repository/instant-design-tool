<?php

namespace IDT\Admin;

class IDT_Update_Manager
{
    private $options = [
        'wc_settings_idt_only_paid_orders' => 'paid',
        'wc_settings_idt_display_project_details' => 'yes',
        'wc_settings_idt_logging_enabled' => false,
    ];

    /**
     * IDT_Update_Manager constructor.
     */
    public function __construct()
    {
        $this->handle_options();
        $this->maybe_set_default_values_for_buttons();
        $this->update_db_version_number();
    }

    /**
     * Handles all the options from the options field
     */
    private function handle_options()
    {
        foreach ($this->options as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    /*
     * Updates the version number to correspond with the one in the database
     */
    private function update_db_version_number()
    {
        update_option('idt_version', IDT_VERSION);
    }

    private function maybe_set_default_values_for_buttons()
    {
        $version_number = $this->parse_version_number();
        if ('0' === $version_number[1] && $version_number[2] >= 7) {
            update_option('wc_settings_idt_button_text', 'View product');
            update_option('wc_settings_idt_button_text_single', 'Start design');
        }
    }

    private function parse_version_number()
    {
        return explode('.', IDT_VERSION);
    }
}
