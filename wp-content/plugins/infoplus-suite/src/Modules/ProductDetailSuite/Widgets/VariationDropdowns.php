<?php

/**
 * Variation Dropdowns Elementor Widget
 * 
 * Displays product variation attribute selectors (Format, Befestigung, etc.)
 * with custom styling and optional images from global attribute terms.
 * 
 * Communicates with Model Selector and other widgets via infoplusProductState.
 * 
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Widgets
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Infoplus\Suite\View;
use Infoplus\Suite\Modules\ProductDetailSuite\Components;

if (!defined('ABSPATH')) exit;

class VariationDropdowns extends Widget_Base
{
    public function get_name(): string
    {
        return 'infoplus-variation-dropdowns';
    }
    public function get_title(): string
    {
        return __('Variation Dropdowns', 'infoplus-suite');
    }
    public function get_icon(): string
    {
        return 'eicon-select';
    }
    public function get_categories(): array
    {
        return ['infoplus-product'];
    }
    public function get_keywords(): array
    {
        return ['variation', 'dropdown', 'select', 'attribute', 'product', 'woocommerce', 'infoplus'];
    }
    public function get_script_depends(): array
    {
        return ['infoplus-product-state', 'infoplus-variation-dropdowns'];
    }
    public function get_style_depends(): array
    {
        return ['infoplus-product-detail-suite'];
    }

    protected function register_controls(): void
    {
        // Content Section
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('attributes_to_show', [
            'label' => __('Attributes to Display', 'infoplus-suite'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'options' => $this->getAvailableAttributes(),
            'default' => ['pa_format', 'pa_befestigung'],
            'description' => __('Select which attributes to show. Leave empty to show all except Model.', 'infoplus-suite'),
        ]);

        $this->add_control('exclude_model', [
            'label' => __('Exclude Model Attribute', 'infoplus-suite'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_info_button', [
            'label' => __('Show Info Button', 'infoplus-suite'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('info_button_behavior', [
            'label' => __('Info Button Behavior', 'infoplus-suite'),
            'type' => Controls_Manager::SELECT,
            'default' => 'tooltip',
            'options' => [
                'tooltip' => __('Tooltip (on hover)', 'infoplus-suite'),
                'modal' => __('Modal (on click)', 'infoplus-suite'),
            ],
            'condition' => ['show_info_button' => 'yes'],
        ]);

        $this->add_control('placeholder_text', [
            'label' => __('Placeholder Text', 'infoplus-suite'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Wähle eine Option', 'infoplus-suite'),
        ]);

        $this->end_controls_section();

        // Style: Dropdown
        $this->start_controls_section('section_style_dropdown', [
            'label' => __('Dropdown', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('dropdown_gap', [
            'label' => __('Gap Between Dropdowns', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => ['{{WRAPPER}} .infoplus-variation-dropdowns' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('dropdown_background', [
            'label' => __('Background', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .infoplus-dropdown__trigger' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .infoplus-dropdown__options' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'dropdown_border',
            'selector' => '{{WRAPPER}} .infoplus-dropdown__trigger, {{WRAPPER}} .infoplus-dropdown__options',
        ]);

        $this->add_responsive_control('dropdown_border_radius', [
            'label' => __('Border Radius', 'infoplus-suite'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-dropdown__trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .infoplus-dropdown__options' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('dropdown_padding', [
            'label' => __('Padding', 'infoplus-suite'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '10', 'right' => '14', 'bottom' => '10', 'left' => '14', 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .infoplus-dropdown__trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // Style: Label
        $this->start_controls_section('section_style_label', [
            'label' => __('Label', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'label_typography',
            'selector' => '{{WRAPPER}} .infoplus-dropdown__label',
        ]);

        $this->add_control('label_color', [
            'label' => __('Color', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#666666',
            'selectors' => ['{{WRAPPER}} .infoplus-dropdown__label' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();

        // Style: Options
        $this->start_controls_section('section_style_option', [
            'label' => __('Options', 'infoplus-suite'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'option_typography',
            'selector' => '{{WRAPPER}} .infoplus-dropdown__option, {{WRAPPER}} .infoplus-dropdown__selected-text',
        ]);

        $this->add_control('option_color', [
            'label' => __('Text Color', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#333333',
            'selectors' => [
                '{{WRAPPER}} .infoplus-dropdown__option' => 'color: {{VALUE}};',
                '{{WRAPPER}} .infoplus-dropdown__selected-text' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('option_hover_background', [
            'label' => __('Hover Background', 'infoplus-suite'),
            'type' => Controls_Manager::COLOR,
            'default' => '#f5f5f5',
            'selectors' => ['{{WRAPPER}} .infoplus-dropdown__option:hover' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('option_image_height', [
            'label' => __('Image Height', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 20, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 32],
            'selectors' => [
                '{{WRAPPER}} .infoplus-variation-dropdowns' => '--infoplus-dropdown-image-height: {{SIZE}}{{UNIT}}; --infoplus-dropdown-trigger-height: calc({{SIZE}}{{UNIT}} + 20px);',
                '{{WRAPPER}} .infoplus-dropdown__option-image' => 'height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .infoplus-dropdown__selected-image' => 'height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .infoplus-dropdown[data-has-images="yes"] .infoplus-dropdown__trigger' => 'min-height: calc({{SIZE}}{{UNIT}} + 20px);',
                '{{WRAPPER}} .infoplus-dropdown[data-has-images="yes"] .infoplus-dropdown__selected' => 'min-height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('option_image_width', [
            'label' => __('Image Width', 'infoplus-suite'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 20, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 42],
            'selectors' => [
                '{{WRAPPER}} .infoplus-variation-dropdowns' => '--infoplus-dropdown-image-width: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .infoplus-dropdown__option-image' => 'width: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .infoplus-dropdown__selected-image' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function getAvailableAttributes(): array
    {
        $attributes = [];
        $taxonomies = wc_get_attribute_taxonomies();
        foreach ($taxonomies as $taxonomy) {
            $attributes['pa_' . $taxonomy->attribute_name] = $taxonomy->attribute_label;
        }
        return $attributes;
    }

    /**
     * Render widget output on the frontend.
     * Markup lives in templates/variation-dropdowns.php (View::render).
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        global $product;

        // Editor preview: use any variable product (sets the global, as before)
        if (!$product || !is_a($product, 'WC_Product')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                $products = wc_get_products(['type' => 'variable', 'limit' => 1, 'status' => 'publish']);
                if (!empty($products)) {
                    $product = $products[0];
                }
            }
        }

        View::render('variation-dropdowns', Components::variationDropdowns($product, [
            'context'              => View::CONTEXT_ELEMENTOR,
            'attributes_to_show'   => $settings['attributes_to_show'] ?? [],
            'exclude_model'        => $settings['exclude_model'],
            'show_info_button'     => $settings['show_info_button'],
            'info_button_behavior' => $settings['info_button_behavior'],
            'placeholder_text'     => $settings['placeholder_text'],
        ]));
    }

    protected function content_template(): void
    {
        ?>
        <div class="infoplus-variation-dropdowns">
            <div class="infoplus-dropdown">
                <label class="infoplus-dropdown__label">Format</label>
                <div class="infoplus-dropdown__trigger">
                    <span class="infoplus-dropdown__selected">
                        <span class="infoplus-dropdown__selected-text">{{{ settings.placeholder_text }}}</span>
                    </span>
                    <span class="infoplus-dropdown__arrow">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <div class="infoplus-dropdown">
                <label class="infoplus-dropdown__label">Befestigung</label>
                <div class="infoplus-dropdown__trigger">
                    <span class="infoplus-dropdown__selected">
                        <span class="infoplus-dropdown__selected-text">{{{ settings.placeholder_text }}}</span>
                    </span>
                    <span class="infoplus-dropdown__arrow">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                </div>
            </div>
        </div>
<?php
    }
}
