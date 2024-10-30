<?php

namespace IDT\Core\WooCommerce;

use IDT\Core\Logger\IDT_Logger;
use IDT\Core\PrintApi\IDT_PrintApi_Products_Dropdown;

if (!defined("ABSPATH")) {
    exit;
}

class IDT_WooCommerce_ProductFields
{

    /**
     * IDT_WooCommerce_ProductFields constructor.
     */
    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', array($this, 'IDT_custom_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_field'));
    }

    /**
     * Add custom fields to the Product type, so we can supply the specific data for Print API
     */
    public function IDT_custom_fields()
    {

        $fields = [];
        $options = IDT_PrintApi_Products_Dropdown::editor_products_for_dd();

        $fields['external_id_select'] = array(
            'id' => 'idt_product_id',
            'label' => _x('Link to Design tool product', 'ADMIN_PANEL', 'IDT'),
            'type' => 'select',
            'placeholder' => _x('productid', "ADMIN_PANEL", 'IDT'),
            'options' => $options
        );

        $fields['price_per_page'] = array(
            'id' => 'idt_price_per_page',
            'label' => _x('Price per page', 'ADMIN_PANEL', 'IDT'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
            ),
            'description' => _x('The default value is 0.50', "ADMIN_PANEL", "IDT"),
            'placeholder' => _x('Only supply if the product has multiple pages', "ADMIN_PANEL", 'IDT')
        );

        $fields['default_amount_of_pages'] = array(
            'id' => 'idt_default_amount_of_pages',
            'label' => _x('Default amount of pages', 'ADMIN_PANEL', 'IDT'),
            'type' => 'number',
            'description' => _x('The amount after which the page\'s are considered "extra"', "ADMIN_PANEL", "IDT"),
            'placeholder' => _x('1-50', "ADMIN_PANEL", 'IDT')
        );

        $fields['external_id_overwrite'] = array(
            'id' => 'idt_external_id_overwrite',
            'label' => _x('Overwrite Print Api product', 'ADMIN_PANEL', 'IDT'),
            'type' => 'text',
            'description' => _x('Overwrite with a custom PrintAPI product ID', "ADMIN_PANEL", "IDT"),
            'placeholder' => _x('Only supply a known PrintAPI ID', "ADMIN_PANEL", 'IDT')
        );

        echo "<div class='options_group show_if_editable'>";
        woocommerce_wp_select($fields['external_id_select']);
        woocommerce_wp_text_input($fields['price_per_page']);
        woocommerce_wp_text_input($fields['default_amount_of_pages']);
        woocommerce_wp_text_input($fields['external_id_overwrite']);
        echo "</div>";

    }

    /**
     * Enables us to save the data from the custom fields above
     *
     * @param $post_id
     *
     * @return bool
     */
    public function save_custom_field($post_id)
    {
        $allProducts = IDT_PrintApi_Products_Dropdown::get_all_products_from_editor();
        $idt_product_id = sanitize_text_field(isset($_POST['idt_product_id']) ? $_POST['idt_product_id'] : '');
        $idt_external_id_overwrite = sanitize_text_field(isset($_POST['idt_external_id_overwrite']) ? $_POST['idt_external_id_overwrite'] : '');
        $pricePerPage = sanitize_text_field(isset($_POST['idt_price_per_page']) ? $_POST['idt_price_per_page'] : '');
        $defaultAmountOfPages = sanitize_text_field(isset($_POST['idt_default_amount_of_pages']) ? $_POST['idt_default_amount_of_pages'] : '');

        if ("" == $pricePerPage && "" == $idt_product_id) {
            //Faulty input has been detected in either field
            return false;
        }

        $pidsFromEditor = array();
        foreach ($allProducts as $product) {
            $pidsFromEditor[] = $product->id;
        }
        // If the submitted does not match one of product id's from the api, it's a fishy situation and we do not want to continue
        // Otherwise this is a legit situation
        if (!in_array($idt_product_id, $pidsFromEditor)) {
            IDT_Logger::error('An error occured. ProductId mismatch');

            return false;
        }

        $selected_product_data = IDT_PrintApi_Products_Dropdown::get_data_for_id($idt_product_id);
        $print_api_external_id = $selected_product_data['printApiId'];
        $idt_editor_url = $selected_product_data['url'];

        $product = wc_get_product($post_id);

        if ("editable" !== sanitize_text_field($_POST['product-type'])) {
            $product->delete_meta_data('idt_product_id');
            $product->delete_meta_data('idt_external_id_overwrite');
            $product->delete_meta_data('print_api_external_id');
            $product->delete_meta_data('idt_editor_url');
            $product->save();

            return false;
        }

        $product->update_meta_data('idt_product_id', $idt_product_id);
        $product->update_meta_data('idt_external_id_overwrite', $idt_external_id_overwrite);
        $product->update_meta_data('print_api_external_id', strlen($idt_external_id_overwrite) > 0 ? $idt_external_id_overwrite : $print_api_external_id);
        $product->update_meta_data('idt_editor_url', $idt_editor_url);

        if (isset($pricePerPage) && !in_array($pricePerPage, [null, '', ' '])) {
            if (is_numeric($pricePerPage) && $pricePerPage >= 0) {
                $product->update_meta_data('idt_price_per_page', $pricePerPage);
            } else {
                IDT_Logger::error('Text value supplied in number field in edit product page');

                return false;
            }
        }

        if (isset($defaultAmountOfPages) && !in_array($defaultAmountOfPages, [null, '', ' '])) {
            if (is_numeric($defaultAmountOfPages) && $defaultAmountOfPages >= 0) {
                $product->update_meta_data('idt_default_amount_of_pages', $defaultAmountOfPages);
            } else {
                IDT_Logger::error('Text value supplied in number field in edit product page');

                return false;
            }
        }

        $product->save();
    }

}
