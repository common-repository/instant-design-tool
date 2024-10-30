<?php


namespace IDT\Core\Elementor;


class IDT_Add_Elementor_IDT_Category
{
    public function __construct()
    {
        add_action('elementor/elements/categories_registered', [$this, 'add_idt_category_to_elementor']);
    }

    /**
     * Adds the Instant Design Tool category to the elementor designer
     * To house the Editable Product Add To Cart widget.
     * @param $elements_manager
     */
    public function add_idt_category_to_elementor($elements_manager)
    {
        $elements_manager->add_category(
            'idt-elements',
            [
                'title' => _x('Instant Design Tool', 'ELEMENTOR', 'IDT'),
                'icon' => 'fa fa-plug',
            ]
        );
    }
}