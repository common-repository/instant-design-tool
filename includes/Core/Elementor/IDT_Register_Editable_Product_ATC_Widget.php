<?php


namespace IDT\Core\Elementor;

use Elementor\Plugin;

class IDT_Register_Editable_Product_ATC_Widget
{
    public function __construct()
    {
        add_action(
            'init',
            function () {
                Plugin::instance()->widgets_manager->register_widget_type(new IDT_Widget());
            }
        );
    }
}
