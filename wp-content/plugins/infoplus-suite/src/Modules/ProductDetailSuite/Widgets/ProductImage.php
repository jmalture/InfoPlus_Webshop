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
use Infoplus\Suite\View;
use Infoplus\Suite\Modules\ProductDetailSuite\Components;

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

    /**
     * Render widget output on the frontend.
     * Markup lives in templates/product-image.php (View::render).
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        global $product;

        // Editor preview: use any product (sets the global, as before)
        if (!$product || !is_a($product, 'WC_Product')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                $products = wc_get_products(['limit' => 1, 'status' => 'publish']);
                if (!empty($products)) $product = $products[0];
            }
        }

        View::render('product-image', Components::productImage($product, [
            'context'          => View::CONTEXT_ELEMENTOR,
            'enable_zoom'      => $settings['enable_zoom'],
            'zoom_scale'       => $settings['zoom_scale']['size'] ?? 2,
            'enable_lightbox'  => $settings['enable_lightbox'],
            'show_gallery'     => $settings['show_gallery'],
            'gallery_position' => $settings['gallery_position'],
        ]));
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
