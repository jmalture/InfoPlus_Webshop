<?php
/*
Plugin Name: Woo Product Variation  Fields
Description: Adds custom fields to WooCommerce products and each variation (backend only).
Version: 1.7.3
Author: Jan Matyas  
*/

//  Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Display the IBE/IFE feilds in a new tab
add_action( 'woocommerce_product_data_tabs', 'add_ibe_ife_tab' );
function add_ibe_ife_tab( $tabs ) {
    $tabs['ibe'] = array(
        'label'    => __( 'IBE/IFE', 'woocommerce' ),
        'target'   => 'ibe_ife_product_data',
        'class'    => array( 'show_if_variable' ),
        'priority' => 21,
    );
    return $tabs;
}
//2. add UUID, rint-API-ID, field in the IBE tab
add_action( 'woocommerce_product_data_panels', 'add_ibe_ife_fields' );
function add_ibe_ife_fields() {
    global $post;

    echo '<div id="ibe_ife_product_data" class="panel woocommerce_options_panel">';

    // UUID field
    woocommerce_wp_text_input( array(
        'id'          => 'ibe_uuid',
        'label'       => __( 'UUID', 'woocommerce' ),
        'description' => __( 'Enter the UUID for this product.', 'woocommerce' ),
        'desc_tip'    => true,
        'value'       => get_post_meta( $post->ID, '_ibe_uuid', true ),
    ) );

    // Print-API-ID field
    woocommerce_wp_text_input( array(
        'id'          => 'ibe_print_api_id',
        'label'       => __( 'Print API ID', 'woocommerce' ),
        'description' => __( 'Enter the Print API ID for this product.', 'woocommerce' ),
        'desc_tip'    => true,
        'value'       => get_post_meta( $post->ID, '_ibe_print_api_id', true ),
    ) );

    //einzigartiger text Name field
    woocommerce_wp_text_input( array(
        'id'          => 'ibe_unique_text_name',
        'label'       => __( 'Unique Text Name', 'woocommerce' ),
        'description' => __( 'Enter the unique text name for this product.', 'woocommerce' ),
        'desc_tip'    => true,
        'value'       => get_post_meta( $post->ID, '_ibe_unique_text_name', true ),
    ) );

    // IFE Image field with WordPress media uploader
    $ife_image = get_post_meta( $post->ID, '_ibe_ife_image', true );
    $image_preview = $ife_image ? '<img src="' . esc_url( $ife_image ) . '" style="max-width:100px;display:block;margin-bottom:10px;" />' : '';
    echo '<p class="form-field"><label for="ibe_ife_image">' . __( 'IFE Image', 'woocommerce' ) . '</label>';
    echo $image_preview;
    echo '<input type="hidden" id="ibe_ife_image" name="ibe_ife_image" value="' . esc_attr( $ife_image ) . '" />';
    echo '<button type="button" class="button ibe-ife-upload">' . __( 'Upload/Add Image', 'woocommerce' ) . '</button> ';
    echo '<button type="button" class="button ibe-ife-remove">' . __( 'Remove Image', 'woocommerce' ) . '</button>';
    echo '<span class="description">' . __( 'Select or upload an image from the media library.', 'woocommerce' ) . '</span></p>';
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        var frame;
        $('.ibe-ife-upload').on('click', function(e){
            e.preventDefault();
            if(frame){ frame.open(); return; }
            frame = wp.media({
                title: '<?php echo esc_js( __( 'Select or Upload IFE Image', 'woocommerce' ) ); ?>',
                button: { text: '<?php echo esc_js( __( 'Use this image', 'woocommerce' ) ); ?>' },
                multiple: false
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $('#ibe_ife_image').val(attachment.url);
                $('.form-field label[for="ibe_ife_image"]').next('img').remove();
                $('<img src="'+attachment.url+'" style="max-width:100px;display:block;margin-bottom:10px;" />').insertAfter('.form-field label[for="ibe_ife_image"]');
            });
            frame.open();
        });
        $('.ibe-ife-remove').on('click', function(e){
            e.preventDefault();
            $('#ibe_ife_image').val('');
            $('.form-field label[for="ibe_ife_image"]').next('img').remove();
        });
    });
    </script>
    <?php
    //  Product background image field with WordPress media uploader
    $product_bg_image = get_post_meta( $post->ID, '_ibe_product_bg_image', true );
    $bg_image_preview = $product_bg_image ? '<img src="' . esc_url($product_bg_image ) . '" style="max-width:100px;display:block;margin-bottom:10px;" />' : '';
    echo '<p class="form-field"><label for="ibe_product_bg_image">' . __( 'Product Background Image', 'woocommerce' ) . '</label>';
    echo $bg_image_preview;
    echo '<input type="hidden" id="ibe_product_bg_image" name="ibe_product_bg_image" value="' . esc_attr( $product_bg_image ) . '" />';
    echo '<button type="button" class="button ibe-product-bg-upload">' . __( 'Upload/Add Background Image', 'woocommerce' ) . '</button> ';
    echo '<button type="button" class="button ibe-product-bg-remove">' . __( '  Remove Background Image', 'woocommerce' ) . '</button>';
    echo '<span class="description">' . __( 'Select or upload an image from the media   library.', 'woocommerce' ) . '</span></p>';
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        var frame;
        $('.ibe-product-bg-upload').on('click', function(e){
            e.preventDefault();
            if(frame){ frame.open(); return; }
            frame = wp.media({
                title: '<?php echo esc_js( __( 'Select or Upload Product Background Image', 'woocommerce' ) ); ?>',
                button: { text: '<?php echo esc_js( __( 'Use this image', 'woocommerce' ) ); ?>' },
                multiple: false
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $('#ibe_product_bg_image').val(attachment.url);
                $('.form-field label[for="ibe_product_bg_image"]').next('img').remove();
                $('<img src="'+attachment.url+'" style="max-width:100px;display:block;margin-bottom:10px;" />').insertAfter('.form-field label[for="ibe_product_bg_image"]');
            });
            frame.open();
        });
        $('.ibe-product-bg-remove').on('click', function(e){
            e.preventDefault();
            $('#ibe_product_bg_image').val('');
            $('.form-field label[for="ibe_product_bg_image"]').next('img').remove();
        });
    });
    </script>
    <?php

    //overlay frame image field with WordPress media uploader
    $overlay_frame_image = get_post_meta( $post->ID, '_ibe_overlay_frame_image', true );
    $overlay_image_preview = $overlay_frame_image ? '<img src="' . esc_url($overlay_frame_image ) . '" style="max-width:100px;display:block;margin-bottom:10px;" />' : '';
    echo '<p class="form-field"><label for="ibe_overlay_frame_image">' . __( 'Overlay Frame Image', 'woocommerce' ) . '</label>';
    echo $overlay_image_preview;
    echo '<input type="hidden" id="ibe_overlay_frame_image" name="ibe_overlay_frame_image" value="' . esc_attr( $overlay_frame_image ) . '" />';
    echo '<button type="button" class="button ibe-overlay-frame-upload">' . __( 'Upload/Add Overlay Frame Image', 'woocommerce' ) . '</button> ';
    echo '<button type="button" class="button ibe-overlay-frame-remove">' . __( 'Remove Overlay Frame Image', 'woocommerce' ) . '</button>';
    echo '<span class="description">' . __( 'Select or upload an image from the media library.', 'woocommerce' ) . '</span></p>';
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        var frame;
        $('.ibe-overlay-frame-upload').on('click', function(e){
            e.preventDefault();
            if(frame){ frame.open(); return; }
            frame = wp.media({
                title: '<?php echo esc_js( __( 'Select or Upload Overlay Frame Image', 'woocommerce' ) ); ?>',
                button: { text: '<?php echo esc_js( __( 'Use this image', 'woocommerce' ) ); ?>' },
                multiple: false
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $('#ibe_overlay_frame_image').val(attachment.url);
                $('.form-field label[for="ibe_overlay_frame_image"]').next('img').remove();
                $('<img src="'+attachment.url+'" style="max-width:100px;display:block;margin-bottom:10px;" />').insertAfter('.form-field label[for="ibe_overlay_frame_image"]');
            });
            frame.open();
        });
        $('.ibe-overlay-frame-remove').on('click', function(e){
            e.preventDefault();
            $('#ibe_overlay_frame_image').val('');
            $('.form-field label[for="ibe_overlay_frame_image"]').next('img').remove();
        });
    });
    </script>
    <?php
    // Display the fields
    echo '</div>';
}

// 3. Save the custom fields
add_action( 'woocommerce_process_product_meta', 'save_ibe_ife_fields' );
function save_ibe_ife_fields( $post_id ) {
    if ( isset( $_POST['ibe_uuid'] ) ) {
        update_post_meta( $post_id, '_ibe_uuid', sanitize_text_field( $_POST['ibe_uuid'] ) );
    }
    if ( isset( $_POST['ibe_print_api_id'] ) ) {
        update_post_meta( $post_id, '_ibe_print_api_id', sanitize_text_field( $_POST['ibe_print_api_id'] ) );
    }
    if ( isset( $_POST['ibe_unique_text_name'] ) ) {
        update_post_meta( $post_id, '_ibe_unique_text_name', sanitize_text_field( $_POST['ibe_unique_text_name'] ) );
    }
    if ( isset( $_POST['ibe_ife_image'] ) ) {
        update_post_meta( $post_id, '_ibe_ife_image', esc_url_raw( $_POST['ibe_ife_image'] ) );
    }
    if ( isset( $_POST['ibe_product_bg_image'] ) ) {
        update_post_meta( $post_id, '_ibe_product_bg_image', esc_url_raw( $_POST['ibe_product_bg_image'] ) );
    }
    if ( isset( $_POST['ibe_overlay_frame_image'] ) ) {
        update_post_meta( $post_id, '_ibe_overlay_frame_image', esc_url_raw( $_POST['ibe_overlay_frame_image'] ) );
    }
}

// 4. make all fields accessible in the REST API
add_filter( 'woocommerce_rest_prepare_product_object', 'add_ibe_ife_fields_to_rest', 10, 3 );
function add_ibe_ife_fields_to_rest( $response, $product, $request ) {
    $fields = array(
        'ibe_uuid' => get_post_meta( $product->get_id(), '_ibe_uuid', true ),
        'ibe_print_api_id' => get_post_meta( $product->get_id(), '_ibe_print_api_id', true ),
        'ibe_unique_text_name' => get_post_meta( $product->get_id(), '_ibe_unique_text_name', true ),
        'ibe_ife_image' => get_post_meta( $product->get_id(), '_ibe_ife_image', true ),
        'ibe_product_bg_image' => get_post_meta( $product->get_id(), '_ibe_product_bg_image', true ),
        'ibe_overlay_frame_image' => get_post_meta( $product->get_id(), '_ibe_overlay_frame_image', true ),
    );

    foreach ( $fields as $key => $value ) {
        if ( ! empty( $value ) ) {
            $response->data[ $key ] = $value;
        }
    }

    return $response;
}


/*Per variation fields*/ 

// Add a URL field on each variation (shows under the pricing block)
add_action( 'woocommerce_variation_options_pricing', function( $loop, $variation_data, $variation ) {
    woocommerce_wp_text_input( [
        'id'            => "_ibe_variation_url[$loop]",
        'name'          => "_ibe_variation_url[$loop]",
        'value'         => get_post_meta( $variation->ID, '_ibe_variation_url', true ),
        'label'         => __( 'Editor URL', 'woocommerce' ),
        'placeholder'   => 'https://example.com/your-editor?foo=bar',
        'wrapper_class' => 'form-row form-row-full',
        'desc_tip'      => true,
        'description'   => __( 'Optional: URL to open when this variation is selected.', 'woocommerce' ),
        'type'          => 'url',
    ] );
}, 10, 3 );


// Save the URL
add_action('woocommerce_save_product_variation', function ($variation_id, $i) {
    if (isset($_POST['_ibe_variation_url'][$i])) {
        update_post_meta($variation_id, '_ibe_variation_url', esc_url_raw($_POST['_ibe_variation_url'][$i]));
    } else {
        delete_post_meta($variation_id, '_ibe_variation_url');
    }
}, 10, 2);


