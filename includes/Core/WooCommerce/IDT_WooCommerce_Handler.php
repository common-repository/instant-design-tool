<?php

namespace IDT\Core\WooCommerce;

if (!defined("ABSPATH")) {
    exit;
}

class IDT_WooCommerce_Handler
{
    public function __construct()
    {
        add_filter('product_type_selector', array($this, 'add_editable_type'));
        add_action('woocommerce_single_product_summary', array($this, 'editable_product_add_to_cart'), 60);
        add_action('admin_footer', array($this, 'idt_editable_admin_custom_js'));
        add_action('wp_loaded', [$this, 'idt_check_token']);
    }

    function idt_check_token() {
		if (isset($_GET['idt_token'])) {
            $this->handle_stored_cart($_GET['idt_token']);
			wp_redirect(remove_query_arg('idt_token'));
			exit;
        }
    }

    function handle_stored_cart ( $token) {
        global $wpdb;
        $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}idt_snapshot WHERE token = N%s", [$token]);
        $cart = $wpdb->get_row($query);
        $cart = json_decode($cart->cart, true);
        $add_to_cart_contents = $cart['cart_item'];
        $product_id = $cart["product_id"];
        WC()->cart->get_cart(); // Load active cart from session
        WC()->cart->add_to_cart( $product_id, 1, null, null, $add_to_cart_contents );
        $wpdb->delete($wpdb->prefix . 'idt_snapshot', array('token' => $token));
    }

    /**
     * This will add the editable type to the selectbox of currently available types
     *
     * @param $types
     *
     * @return mixed
     */
    public function add_editable_type($types)
    {
        $types['editable'] = _x('Design tool product', 'ADMIN_PANEL', 'IDT');

        return $types;
    }

    /**
     * This will add a tab to the product data metabox
     *
     * @param $tabs
     *
     * @return mixed
     */
    function editable_tab($tabs)
    {
        $tabs['editable'] = array(
            'label' => _x('Editable Product', "ADMIN_PANEL", 'IDT'),
            'target' => 'editable_options',
            'class' => 'show_if_editable',
        );

        return $tabs;
    }

    /**
     * This will supply the tab with content
     */
    function editable_tab_product_tab_content()
    {
        ?>
        <div id='editable_options' class='panel woocommerce_options_panel'>
        <div class='options_group'><?php

            woocommerce_wp_text_input(
                array(
                    'id' => 'editor_external_product_id',
                    'label' => _x('External ProductId', "ADMIN_PANEL", 'IDT'),
                    'placeholder' => '',
                    'desc_tip' => 'true',
                    'description' => _x('Please supply the productId that the product has in Print API', "ADMIN_PANEL", 'IDT'),
                    'type' => 'text'
                )
            );
            ?></div>
        </div><?php
    }

    /**
     * Makes sure the button to display the product is shown
     */
    public function editable_product_add_to_cart()
    {
        global $product;
		global $wpdb;
        // Make sure it's our custom product type
        if ('editable' == $product->get_type()) {
            // Get the custom editor url, otherwise set the default one
            $url = $product->get_meta('idt_editor_url');
            $text = _x('Design this product', "PRODUCT PAGE", "IDT");
            do_action('woocommerce_before_add_to_cart_button'); 
?>

            <p class="cart">
                <a href="<?php echo esc_url($url); ?>" rel="nofollow" class="single_add_to_cart_button button alt">
                    <?php echo $text ?>
                </a>
            </p>

            <?php do_action('woocommerce_after_add_to_cart_button');
        }
    }

    /**
     * Because we are dealing with custom product type, we need to specifically declare which
     * metabox-tabs we want to show. This function will add the javascript necessary to add the price and
     * inventory metaboxes to the create/edit product page for the editable product.
     **/
    function idt_editable_admin_custom_js()
    {
        if ('product' != get_post_type()) {
            return;
        }

        ?>
        <script type='text/javascript'>
            jQuery(document).ready(function () {
                //for Price tab
                jQuery('.product_data_tabs .general_tab').show();
                jQuery('#general_product_data .pricing').addClass('show_if_editable').show();
                jQuery('.inventory_options').addClass('show_if_editable').show();
                jQuery('#inventory_product_data ._manage_stock_field').addClass('show_if_editable').show();
                jQuery('#inventory_product_data ._sold_individually_field').parent().addClass('show_if_editable').show();
                jQuery('#inventory_product_data ._sold_individually_field').addClass('show_if_editable').show();
            });
        </script>
        <?php
    }
}