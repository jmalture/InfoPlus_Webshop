<?php
/**
 * Model Selector Elementor Widget
 * 
 * Displays product model variants in a scrollable card layout.
 * Each card shows: Image, Model Name, "from €X.XX" price, Info button.
 * 
 * Communicates with other widgets via infoplusProductState.
 * 
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Widgets
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;
use Infoplus\Suite\View;
use Infoplus\Suite\Modules\ProductDetailSuite\Components;

if (!defined('ABSPATH')) exit;

class ModelSelector extends Widget_Base
{
    /**
     * Get widget name
     */
    public function get_name(): string
    {
        return 'infoplus-model-selector';
    }

    /**
     * Get widget title
     */
    public function get_title(): string
    {
        return __('Model Selector', 'infoplus-suite');
    }

    /**
     * Get widget icon
     */
    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    /**
     * Get widget categories
     */
    public function get_categories(): array
    {
        return ['infoplus-product'];
    }

    /**
     * Get widget keywords
     */
    public function get_keywords(): array
    {
        return ['model', 'variant', 'selector', 'product', 'woocommerce', 'infoplus'];
    }

    /**
     * Get script dependencies
     */
    public function get_script_depends(): array
    {
        return ['infoplus-product-state', 'infoplus-model-selector'];
    }

    /**
     * Get style dependencies
     */
    public function get_style_depends(): array
    {
        return ['infoplus-product-detail-suite'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls(): void
    {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Section Title', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Variant', 'infoplus-suite'),
                'placeholder' => __('Enter title', 'infoplus-suite'),
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'infoplus-suite'),
                'label_off' => __('Hide', 'infoplus-suite'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Show "from" Price', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'infoplus-suite'),
                'label_off' => __('Hide', 'infoplus-suite'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_info_button',
            [
                'label' => __('Show Info Button', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'infoplus-suite'),
                'label_off' => __('Hide', 'infoplus-suite'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'info_button_behavior',
            [
                'label' => __('Info Button Behavior', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'default' => 'modal',
                'options' => [
                    'tooltip' => __('Tooltip (on hover)', 'infoplus-suite'),
                    'modal' => __('Modal (on click)', 'infoplus-suite'),
                ],
                'condition' => [
                    'show_info_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'sync_product_image',
            [
                'label' => __('Sync Product Image', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'infoplus-suite'),
                'label_off' => __('No', 'infoplus-suite'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Update the Product Image widget when a model is selected.', 'infoplus-suite'),
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'default' => 'vertical',
                'options' => [
                    'vertical' => __('Vertical (Scrollable)', 'infoplus-suite'),
                    'horizontal' => __('Horizontal (Grid)', 'infoplus-suite'),
                ],
            ]
        );

        $this->add_responsive_control(
            'max_height',
            [
                'label' => __('Max Height', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => [
                        'min' => 200,
                        'max' => 1000,
                        'step' => 10,
                    ],
                    'vh' => [
                        'min' => 20,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 500,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__list' => 'max-height: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'layout' => 'vertical',
                ],
            ]
        );

        $this->end_controls_section();

        // Style: Card Section
        $this->start_controls_section(
            'section_style_card',
            [
                'label' => __('Card', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'card_padding',
            [
                'label' => __('Padding', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => '12',
                    'right' => '12',
                    'bottom' => '12',
                    'left' => '12',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'card_gap',
            [
                'label' => __('Gap Between Cards', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 40,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => __('Background', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'card_background_active',
            [
                'label' => __('Background (Active)', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8f9fa',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card.is-active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .infoplus-model-selector__card',
                'fields_options' => [
                    'border' => [
                        'default' => 'solid',
                    ],
                    'width' => [
                        'default' => [
                            'top' => '1',
                            'right' => '1',
                            'bottom' => '1',
                            'left' => '1',
                            'isLinked' => true,
                        ],
                    ],
                    'color' => [
                        'default' => '#e0e0e0',
                    ],
                ],
            ]
        );

        $this->add_control(
            'card_border_color_active',
            [
                'label' => __('Border Color (Active)', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0066cc',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card.is-active' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'card_border_radius',
            [
                'label' => __('Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => '8',
                    'right' => '8',
                    'bottom' => '8',
                    'left' => '8',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .infoplus-model-selector__card',
            ]
        );

        $this->end_controls_section();

        // Style: Image Section
        $this->start_controls_section(
            'section_style_image',
            [
                'label' => __('Image', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'image_width',
            [
                'label' => __('Width', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 400,
                    ],
                    '%' => [
                        'min' => 20,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__image' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_min_height',
            [
                'label' => __('Min Height', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 60,
                        'max' => 300,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 120,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__image' => 'min-height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .infoplus-model-selector__image img' => 'max-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_border_radius',
            [
                'label' => __('Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => '6',
                    'right' => '6',
                    'bottom' => '6',
                    'left' => '6',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .infoplus-model-selector__image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style: Typography Section
        $this->start_controls_section(
            'section_style_typography',
            [
                'label' => __('Typography', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'heading_title_style',
            [
                'label' => __('Section Title', 'infoplus-suite'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .infoplus-model-selector__title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'heading_model_name_style',
            [
                'label' => __('Model Name', 'infoplus-suite'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'model_name_typography',
                'selector' => '{{WRAPPER}} .infoplus-model-selector__name',
            ]
        );

        $this->add_control(
            'model_name_color',
            [
                'label' => __('Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__name' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'heading_price_style',
            [
                'label' => __('Price', 'infoplus-suite'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .infoplus-model-selector__price',
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __('Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__price' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style: Active Indicator
        $this->start_controls_section(
            'section_style_active_indicator',
            [
                'label' => __('Active Indicator', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'active_indicator_color',
            [
                'label' => __('Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0066cc',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card.is-active::before' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'active_indicator_width',
            [
                'label' => __('Width', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 2,
                        'max' => 10,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-model-selector__card.is-active::before' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     * Markup lives in templates/model-selector.php (View::render).
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Check if we're on a product page
        global $product;

        // For editor preview, try to get a sample product (sets the global, as before)
        if (!$product || !is_a($product, 'WC_Product')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                // Try to get any variable product for preview
                $products = wc_get_products([
                    'type' => 'variable',
                    'limit' => 1,
                    'status' => 'publish',
                ]);

                if (!empty($products)) {
                    $product = $products[0];
                }
            }
        }

        View::render('model-selector', Components::modelSelector($product, [
            'context'              => View::CONTEXT_ELEMENTOR,
            'title'                => $settings['title'],
            'show_title'           => $settings['show_title'],
            'show_price'           => $settings['show_price'],
            'show_info_button'     => $settings['show_info_button'],
            'info_button_behavior' => $settings['info_button_behavior'],
            'sync_product_image'   => $settings['sync_product_image'],
            'layout'               => $settings['layout'],
        ]));
    }

    /**
     * Render widget output in the editor (JavaScript template)
     */
    protected function content_template(): void
    {
        ?>
        <#
        var wrapperClass = 'infoplus-model-selector infoplus-model-selector--' + settings.layout;
        #>
        <div class="{{{ wrapperClass }}}">
            <# if (settings.show_title === 'yes' && settings.title) { #>
                <h3 class="infoplus-model-selector__title">{{{ settings.title }}}</h3>
            <# } #>
            
            <div class="infoplus-model-selector__list">
                <!-- Preview placeholder in editor -->
                <div class="infoplus-model-selector__card is-active">
                    <div class="infoplus-model-selector__image">
                        <img src="<?php echo esc_url(wc_placeholder_img_src('woocommerce_thumbnail')); ?>" alt="Sample" />
                        <# if (settings.show_info_button === 'yes') { #>
                            <button class="infoplus-model-selector__info-btn" type="button"><span>i</span></button>
                        <# } #>
                    </div>
                    <div class="infoplus-model-selector__content">
                        <span class="infoplus-model-selector__name"><?php esc_html_e('Print on paper', 'infoplus-suite'); ?></span>
                        <# if (settings.show_price === 'yes') { #>
                            <span class="infoplus-model-selector__price"><?php esc_html_e('from', 'infoplus-suite'); ?> €3.52</span>
                        <# } #>
                    </div>
                </div>
                
                <div class="infoplus-model-selector__card">
                    <div class="infoplus-model-selector__image">
                        <img src="<?php echo esc_url(wc_placeholder_img_src('woocommerce_thumbnail')); ?>" alt="Sample" />
                        <# if (settings.show_info_button === 'yes') { #>
                            <button class="infoplus-model-selector__info-btn" type="button"><span>i</span></button>
                        <# } #>
                    </div>
                    <div class="infoplus-model-selector__content">
                        <span class="infoplus-model-selector__name"><?php esc_html_e('Logo on front panel', 'infoplus-suite'); ?></span>
                        <# if (settings.show_price === 'yes') { #>
                            <span class="infoplus-model-selector__price"><?php esc_html_e('from', 'infoplus-suite'); ?> €6.88</span>
                        <# } #>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
