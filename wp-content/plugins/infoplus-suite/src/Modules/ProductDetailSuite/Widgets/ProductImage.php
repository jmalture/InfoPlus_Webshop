<?php
/**
 * Product Image Elementor Widget
 * 
 * Displays product main image with zoom on hover and fullscreen preview.
 * Updates based on selected variation via infoplusProductState.
 * 
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Widgets
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) exit;

class ProductImage extends Widget_Base
{
    public function get_name(): string { return 'infoplus-product-image'; }
    public function get_title(): string { return __('Product Image', 'infoplus-suite'); }
    public function get_icon(): string { return 'eicon-image-box'; }
    public function get_categories(): array { return ['infoplus-product']; }
    public function get_keywords(): array { return ['product', 'image', 'gallery', 'zoom', 'woocommerce', 'infoplus']; }
    public function get_script_depends(): array { return ['infoplus-product-state', 'infoplus-product-image']; }
    public function get_style_depends(): array { return ['infoplus-product-detail-suite']; }

    protected function register_controls(): void
    {
        // Content Section
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('enable_zoom', [
            'label' => __('Enable Zoom on Hover', 'infoplus-suite'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('zoom_scale', [
            'label' => __('Zoom Scale', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 1.5, 'max' => 4, 'step' => 0.1]],
            'default' => ['size' => 2],
            'condition' => ['enable_zoom' => 'yes'],
        ]);

        $this->add_control('enable_lightbox', [
            'label' => __('Enable Fullscreen Lightbox', 'infoplus-suite'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_gallery', [
            'label' => __('Show Gallery Thumbnails', 'infoplus-suite'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('gallery_position', [
            'label' => __('Gallery Position', 'infoplus-suite'),
            'type' => Controls_Manager::SELECT,
            'default' => 'bottom',
            'options' => [
                'bottom' => __('Bottom', 'infoplus-suite'),
                'left' => __('Left', 'infoplus-suite'),
                'right' => __('Right', 'infoplus-suite'),
            ],
            'condition' => ['show_gallery' => 'yes'],
        ]);

        $this->end_controls_section();

        // Style: Main Image
        $this->start_controls_section('section_style_image', [
            'label' => __('Main Image', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('image_min_height', [
            'label' => __('Min Height', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 200, 'max' => 800],
                'vh' => ['min' => 20, 'max' => 80],
            ],
            'default' => ['unit' => 'px', 'size' => 400],
            'selectors' => [
                '{{WRAPPER}} .infoplus-product-image__main' => 'min-height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('image_border_radius', [
            'label' => __('Border Radius', 'infoplus-suite'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-product-image__main' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .infoplus-product-image__main img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'image_border',
            'selector' => '{{WRAPPER}} .infoplus-product-image__main',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'image_shadow',
            'selector' => '{{WRAPPER}} .infoplus-product-image__main',
        ]);

        $this->add_control('image_background', [
            'label' => __('Background', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#f8f9fa',
            'selectors' => ['{{WRAPPER}} .infoplus-product-image__main' => 'background-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();

        // Style: Gallery
        $this->start_controls_section('section_style_gallery', [
            'label' => __('Gallery Thumbnails', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_gallery' => 'yes'],
        ]);

        $this->add_responsive_control('thumb_size', [
            'label' => __('Thumbnail Size', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 150]],
            'default' => ['unit' => 'px', 'size' => 80],
            'selectors' => [
                '{{WRAPPER}} .infoplus-product-image__thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('thumb_gap', [
            'label' => __('Gap', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 4, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 8],
            'selectors' => ['{{WRAPPER}} .infoplus-product-image__gallery' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('thumb_border_radius', [
            'label' => __('Border Radius', 'infoplus-suite'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .infoplus-product-image__thumb' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('thumb_border_color', [
            'label' => __('Border Color', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#e0e0e0',
            'selectors' => ['{{WRAPPER}} .infoplus-product-image__thumb' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_control('thumb_active_border_color', [
            'label' => __('Active Border Color', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#e91e8c',
            'selectors' => ['{{WRAPPER}} .infoplus-product-image__thumb.is-active' => 'border-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();

        // Style: Lightbox
        $this->start_controls_section('section_style_lightbox', [
            'label' => __('Lightbox', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['enable_lightbox' => 'yes'],
        ]);

        $this->add_control('lightbox_background', [
            'label' => __('Background', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(0, 0, 0, 0.9)',
            'selectors' => ['{{WRAPPER}} .infoplus-lightbox' => 'background-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                $products = wc_get_products(['limit' => 1, 'status' => 'publish']);
                if (!empty($products)) $product = $products[0];
            }
        }

        $enable_zoom = $settings['enable_zoom'] === 'yes';
        $zoom_scale = $settings['zoom_scale']['size'] ?? 2;
        $enable_lightbox = $settings['enable_lightbox'] === 'yes';
        $show_gallery = $settings['show_gallery'] === 'yes';
        $gallery_position = $settings['gallery_position'];

        $wrapper_class = 'infoplus-product-image';
        $wrapper_class .= ' infoplus-product-image--gallery-' . $gallery_position;
        
        // Get images
        $main_image_id = $product ? $product->get_image_id() : 0;
        $main_image_url = $main_image_id ? wp_get_attachment_url($main_image_id) : wc_placeholder_img_src('woocommerce_single');
        $main_image_full = $main_image_id ? wp_get_attachment_image_url($main_image_id, 'full') : $main_image_url;

        $gallery_ids = $product ? $product->get_gallery_image_ids() : [];

        // Get first model's full gallery for initial render
        $first_gallery = $product ? $this->getFirstModelGallery($product) : [];

        // If model has a gallery, use its first image as the main image
        if (!empty($first_gallery)) {
            $main_image_url  = $first_gallery[0]['url'];
            $main_image_full = $first_gallery[0]['url'];
        }
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>"
             data-enable-zoom="<?php echo esc_attr($enable_zoom ? 'yes' : 'no'); ?>"
             data-zoom-scale="<?php echo esc_attr($zoom_scale); ?>"
             data-enable-lightbox="<?php echo esc_attr($enable_lightbox ? 'yes' : 'no'); ?>">

            <div class="infoplus-product-image__wrapper">
                <!-- Main Image -->
                <div class="infoplus-product-image__main" data-full-src="<?php echo esc_url($main_image_full); ?>">
                    <img src="<?php echo esc_url($main_image_url); ?>"
                         alt="<?php echo esc_attr($product ? $product->get_name() : 'Product'); ?>"
                         class="infoplus-product-image__img" />

                    <?php if ($enable_zoom): ?>
                        <div class="infoplus-product-image__zoom-lens"></div>
                    <?php endif; ?>

                    <?php if ($enable_lightbox): ?>
                        <button class="infoplus-product-image__fullscreen" type="button" aria-label="<?php esc_attr_e('View fullscreen', 'infoplus-suite'); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Gallery Thumbnails -->
                <?php if ($show_gallery):
                    // Build initial thumbnail list for the first model (gallery[1..n])
                    // Falls back to WooCommerce gallery for non-model products
                    $initial_thumbs = [];
                    if (!empty($first_gallery) && count($first_gallery) > 1) {
                        // Model gallery: show images after the first (main) one
                        foreach (array_slice($first_gallery, 1) as $img) {
                            $initial_thumbs[] = [
                                'url'           => $img['url'],
                                'thumbnail_url' => $img['thumbnail_url'],
                            ];
                        }
                    } elseif (empty($first_gallery)) {
                        // No model gallery — WooCommerce gallery fallback for non-model products
                        foreach ($gallery_ids as $gid) {
                            $gurl = wp_get_attachment_url($gid);
                            if ($gurl) {
                                $initial_thumbs[] = [
                                    'url'           => $gurl,
                                    'thumbnail_url' => wp_get_attachment_image_url($gid, 'thumbnail') ?: $gurl,
                                ];
                            }
                        }
                    }
                    // Hide the row initially when there are no thumbnails (JS will show it on model change)
                    $gallery_style = empty($initial_thumbs) ? ' style="display:none;"' : '';
                ?>
                    <div class="infoplus-product-image__gallery"<?php echo $gallery_style; ?>>
                        <?php foreach ($initial_thumbs as $img): ?>
                            <button class="infoplus-product-image__thumb"
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
            <div class="infoplus-lightbox" style="display: none;">
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
    }

    /**
     * Get the full gallery for the first available model on this product.
     * Returns an array of [ ['id', 'url', 'thumbnail_url'], … ] from the first model
     * that has at least one gallery image. Returns empty array if no models or no images.
     */
    private function getFirstModelGallery($product): array
    {
        if (!$product || !$product->is_type('variable')) {
            return [];
        }

        $product_id = $product->get_id();

        // Get the first model term assigned to this product — no need to load all variations
        $model_terms = wc_get_product_terms($product_id, 'pa_model', ['fields' => 'slugs']);
        if (empty($model_terms)) {
            return [];
        }

        foreach ($model_terms as $model_slug) {
            $gallery = \Infoplus\Suite\Modules\ProductDetailSuite\Fields\ProductAttributeImages::getTermGalleryUrls(
                $product_id,
                'pa_model',
                $model_slug
            );

            if (!empty($gallery)) {
                return $gallery;
            }
        }

        return [];
    }

    protected function content_template(): void
    {
        ?>
        <#
        var wrapperClass = 'infoplus-product-image infoplus-product-image--gallery-' + settings.gallery_position;
        #>
        <div class="{{{ wrapperClass }}}">
            <div class="infoplus-product-image__wrapper">
                <div class="infoplus-product-image__main">
                    <img src="<?php echo esc_url(wc_placeholder_img_src('woocommerce_single')); ?>" alt="Product" class="infoplus-product-image__img" />
                    <# if (settings.enable_zoom === 'yes') { #>
                        <div class="infoplus-product-image__zoom-lens"></div>
                    <# } #>
                    <# if (settings.enable_lightbox === 'yes') { #>
                        <button class="infoplus-product-image__fullscreen" type="button">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/>
                            </svg>
                        </button>
                    <# } #>
                </div>
                <# if (settings.show_gallery === 'yes') { #>
                    <div class="infoplus-product-image__gallery">
                        <button class="infoplus-product-image__thumb is-active" type="button">
                            <img src="<?php echo esc_url(wc_placeholder_img_src('thumbnail')); ?>" alt="" />
                        </button>
                        <button class="infoplus-product-image__thumb" type="button">
                            <img src="<?php echo esc_url(wc_placeholder_img_src('thumbnail')); ?>" alt="" />
                        </button>
                    </div>
                <# } #>
            </div>
        </div>
        <?php
    }
}
