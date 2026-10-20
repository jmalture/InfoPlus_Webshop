<?php
/**
 * Template: product image (component "product-image").
 *
 * Override in a theme: copy to {theme}/infoplus-suite/product-image.php.
 * Rendered by Infoplus\Suite\View::render(), called from the ProductImage
 * Elementor widget and from infoplus_suite_product_image(). Keep the
 * infoplus-* classes and data-* attributes, and keep the lightbox as the
 * next sibling of the root element: product-image.js depends on them.
 *
 * $args (built by Components::productImage()):
 *   context           string  'elementor' or 'theme'
 *   enable_zoom       bool    Zoom on hover
 *   zoom_scale        float|int|string
 *   enable_lightbox   bool    Fullscreen lightbox
 *   show_gallery      bool    Thumbnail row
 *   gallery_position  string  'bottom', 'left' or 'right'
 *   product           WC_Product|null
 *   product_name      string  Alt text of the main image
 *   main_image_url    string
 *   main_image_full   string
 *   initial_thumbs    array   url, thumbnail_url
 *
 * Restyle hooks: root .ip-c-product-image[data-ip-component="product-image"],
 * parts .ip-c-product-image__main, __image (main img), __fullscreen,
 * __gallery, __thumb, __lightbox.
 *
 * CSS custom properties (theme contract, defaults are the widget control
 * defaults; the plugin CSS does not read them, the theme maps them onto the
 * classes above). In Elementor the same values come from the widget style controls.
 *   --ip-pi-min-height           400px    main image area
 *   --ip-pi-radius               8px      main image
 *   --ip-pi-bg                   #f8f9fa  main image background
 *   --ip-pi-thumb-size           80px
 *   --ip-pi-thumb-gap            8px
 *   --ip-pi-thumb-radius         4px
 *   --ip-pi-thumb-border         #e0e0e0
 *   --ip-pi-thumb-border-active  #e91e8c
 *   --ip-pi-lightbox-bg          rgba(0, 0, 0, 0.9)
 *
 * Whitespace below mirrors the former ProductImage::render() output byte for byte.
 *
 * @package Infoplus\Suite
 */

defined('ABSPATH') || exit;

$enable_zoom     = $args['enable_zoom'];
$zoom_scale      = $args['zoom_scale'];
$enable_lightbox = $args['enable_lightbox'];
$show_gallery    = $args['show_gallery'];

$wrapper_class = 'infoplus-product-image';
$wrapper_class .= ' infoplus-product-image--gallery-' . $args['gallery_position'];
$wrapper_class .= ' ip-c-product-image';

?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" data-ip-component="product-image"
             data-enable-zoom="<?php echo esc_attr($enable_zoom ? 'yes' : 'no'); ?>"
             data-zoom-scale="<?php echo esc_attr($zoom_scale); ?>"
             data-enable-lightbox="<?php echo esc_attr($enable_lightbox ? 'yes' : 'no'); ?>">

            <div class="infoplus-product-image__wrapper">
                <!-- Main Image -->
                <div class="infoplus-product-image__main ip-c-product-image__main" data-full-src="<?php echo esc_url($args['main_image_full']); ?>">
                    <img src="<?php echo esc_url($args['main_image_url']); ?>"
                         alt="<?php echo esc_attr($args['product_name']); ?>"
                         class="infoplus-product-image__img ip-c-product-image__image" />

                    <?php if ($enable_zoom): ?>
                        <div class="infoplus-product-image__zoom-lens"></div>
                    <?php endif; ?>

                    <?php if ($enable_lightbox): ?>
                        <button class="infoplus-product-image__fullscreen ip-c-product-image__fullscreen" type="button" aria-label="<?php esc_attr_e('View fullscreen', 'infoplus-suite'); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Gallery Thumbnails -->
                <?php if ($show_gallery):
                    $initial_thumbs = $args['initial_thumbs'];
                ?>
                    <div class="infoplus-product-image__gallery ip-c-product-image__gallery"<?php if (empty($initial_thumbs)) : ?> style="display:none;"<?php endif; ?>>
                        <?php foreach ($initial_thumbs as $img): ?>
                            <button class="infoplus-product-image__thumb ip-c-product-image__thumb"
                                    type="button"
                                    data-image-url="<?php echo esc_url($img['url']); ?>"
                                    data-full-url="<?php echo esc_url($img['url']); ?>">
                                <img src="<?php echo esc_url($img['thumbnail_url'] ?: $img['url']); ?>"
                                     alt="" loading="lazy" />
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lightbox Template -->
        <?php if ($enable_lightbox): ?>
            <div class="infoplus-lightbox ip-c-product-image__lightbox" style="display: none;">
                <div class="infoplus-lightbox__backdrop"></div>
                <div class="infoplus-lightbox__content">
                    <button class="infoplus-lightbox__close" type="button" aria-label="<?php esc_attr_e('Close', 'infoplus-suite'); ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <img src="" alt="" class="infoplus-lightbox__image" />
                    <div class="infoplus-lightbox__nav">
                        <button class="infoplus-lightbox__prev" type="button" aria-label="<?php esc_attr_e('Previous', 'infoplus-suite'); ?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <button class="infoplus-lightbox__next" type="button" aria-label="<?php esc_attr_e('Next', 'infoplus-suite'); ?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php
