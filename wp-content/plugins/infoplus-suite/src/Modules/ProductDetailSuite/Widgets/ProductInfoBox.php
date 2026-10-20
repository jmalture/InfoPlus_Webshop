<?php

/**
 * Product Info Box — Elementor Widget
 *
 * Displays dynamic product information rows:
 *   - SKU (from selected variation)
 *   - Minimum order quantity (from bulk price table / product meta)
 *   - Delivery time (from product meta, default 15 days)
 *
 * Each row has a configurable icon (color, size) and text (color, size).
 * Values update reactively via infoplusProductState events.
 *
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Widgets
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Infoplus\Suite\View;
use Infoplus\Suite\Modules\ProductDetailSuite\Components;

if (!defined('ABSPATH')) exit;

class ProductInfoBox extends Widget_Base
{
    public function get_name(): string { return 'infoplus-product-info-box'; }
    public function get_title(): string { return __('Product Info Box', 'infoplus-suite'); }
    public function get_icon(): string { return 'eicon-info-box'; }
    public function get_categories(): array { return ['infoplus-product']; }
    public function get_keywords(): array { return ['product', 'info', 'sku', 'delivery', 'order', 'infoplus']; }

    public function get_script_depends(): array
    {
        return ['infoplus-product-state', 'infoplus-product-info-box'];
    }

    public function get_style_depends(): array
    {
        return ['infoplus-product-detail-suite'];
    }

    // ── Controls ───────────────────────────────────────────────────────────────

    protected function register_controls(): void
    {
        // ── Content ──────────────────────────────────────────────────────────

        $this->start_controls_section('section_content', [
            'label' => __('Content', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('heading', [
            'label'   => __('Heading', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Informationen:',
        ]);

        $this->add_control('show_sku', [
            'label'   => __('Show SKU', 'infoplus-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('sku_label', [
            'label'     => __('SKU Label', 'infoplus-suite'),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'SKU:',
            'condition' => ['show_sku' => 'yes'],
        ]);

        $this->add_control('show_min_order', [
            'label'   => __('Show Min. Order', 'infoplus-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('min_order_unit', [
            'label'     => __('Unit Label', 'infoplus-suite'),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'Stück',
            'condition' => ['show_min_order' => 'yes'],
        ]);

        $this->add_control('show_delivery', [
            'label'   => __('Show Delivery Time', 'infoplus-suite'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('delivery_default_days', [
            'label'     => __('Default Days', 'infoplus-suite'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 15,
            'min'       => 1,
            'condition' => ['show_delivery' => 'yes'],
        ]);

        $this->add_control('delivery_prefix', [
            'label'     => __('Prefix', 'infoplus-suite'),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'max.',
            'condition' => ['show_delivery' => 'yes'],
        ]);

        $this->add_control('delivery_suffix', [
            'label'     => __('Suffix', 'infoplus-suite'),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'Arbeitstage',
            'condition' => ['show_delivery' => 'yes'],
        ]);

        $this->end_controls_section();

        // ── Icons ────────────────────────────────────────────────────────────

        $this->start_controls_section('section_icons', [
            'label' => __('Icons', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('icon_min_order', [
            'label'     => __('Min. Order Icon', 'infoplus-suite'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-box', 'library' => 'fa-solid'],
            'condition' => ['show_min_order' => 'yes'],
        ]);

        $this->add_control('icon_min_order_color', [
            'label'     => __('Min. Order Icon Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#22c55e',
            'condition' => ['show_min_order' => 'yes'],
        ]);

        $this->add_control('icon_delivery', [
            'label'     => __('Delivery Icon', 'infoplus-suite'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-clock', 'library' => 'fa-solid'],
            'condition' => ['show_delivery' => 'yes'],
        ]);

        $this->add_control('icon_delivery_color', [
            'label'     => __('Delivery Icon Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#f59e0b',
            'condition' => ['show_delivery' => 'yes'],
        ]);

        $this->end_controls_section();

        // ── Style: Heading ───────────────────────────────────────────────────

        $this->start_controls_section('section_heading_style', [
            'label' => __('Heading', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('heading_color', [
            'label'     => __('Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [
                '{{WRAPPER}} .ip-info-box__heading' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'heading_typography',
            'selector' => '{{WRAPPER}} .ip-info-box__heading',
        ]);

        $this->add_responsive_control('heading_spacing', [
            'label'      => __('Bottom Spacing', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'default'    => ['size' => 8, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-info-box__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Style: Rows ──────────────────────────────────────────────────────

        $this->start_controls_section('section_row_style', [
            'label' => __('Rows', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('text_color', [
            'label'     => __('Text Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [
                '{{WRAPPER}} .ip-info-box__text' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'text_typography',
            'selector' => '{{WRAPPER}} .ip-info-box__text',
        ]);

        $this->add_responsive_control('icon_size', [
            'label'      => __('Icon Size', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 40]],
            'default'    => ['size' => 16, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-info-box__icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .ip-info-box__icon svg'  => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('row_gap', [
            'label'      => __('Row Gap', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 20]],
            'default'    => ['size' => 6, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-info-box__rows' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_gap', [
            'label'      => __('Icon-Text Gap', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 20]],
            'default'    => ['size' => 8, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-info-box__row' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    /**
     * Render widget output on the frontend.
     * Markup lives in templates/product-info-box.php (View::render).
     */
    protected function render(): void
    {
        $s = $this->get_settings_for_display();

        global $product;
        $p = $product;

        // Editor fallback (local only, the global is not changed)
        if ((!$p || !is_a($p, 'WC_Product')) && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $products = wc_get_products(['type' => 'variable', 'limit' => 1, 'status' => 'publish']);
            $p = !empty($products) ? $products[0] : null;
        }

        View::render('product-info-box', Components::productInfoBox($p, [
            'context'               => View::CONTEXT_ELEMENTOR,
            'heading'               => $s['heading'] ?? '',
            'show_sku'              => $s['show_sku'],
            'sku_label'             => $s['sku_label'],
            'show_min_order'        => $s['show_min_order'],
            'min_order_unit'        => $s['min_order_unit'],
            'show_delivery'         => $s['show_delivery'],
            'delivery_default_days' => $s['delivery_default_days'] ?? 15,
            'delivery_prefix'       => $s['delivery_prefix'],
            'delivery_suffix'       => $s['delivery_suffix'],
            'icon_min_order_color'  => $s['icon_min_order_color'],
            'icon_delivery_color'   => $s['icon_delivery_color'],
            // Elementor icon markup, rendered only for visible rows, as before
            // (Icons_Manager output may depend on what it rendered earlier).
            'icon_min_order_html'   => $s['show_min_order'] === 'yes' ? $this->iconMarkup($s['icon_min_order']) : '',
            'icon_delivery_html'    => $s['show_delivery'] === 'yes' ? $this->iconMarkup($s['icon_delivery']) : '',
        ]));
    }

    /**
     * Markup of an Elementor ICONS control value, as Icons_Manager prints it.
     */
    private function iconMarkup($icon): string
    {
        if (!is_array($icon)) {
            return '';
        }
        ob_start();
        \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
        return (string) ob_get_clean();
    }

    protected function content_template(): void
    {
        ?>
        <div class="ip-info-box">
            <# if (settings.heading) { #>
                <div class="ip-info-box__heading">{{{ settings.heading }}}</div>
            <# } #>

            <div class="ip-info-box__rows">
                <# if (settings.show_sku === 'yes') { #>
                    <div class="ip-info-box__row ip-info-box__row--sku">
                        <span class="ip-info-box__text">
                            <span class="ip-info-box__sku-label">{{{ settings.sku_label }}}</span>
                            <span class="ip-info-box__sku-value">DEMO-SKU</span>
                        </span>
                    </div>
                <# } #>

                <# if (settings.show_min_order === 'yes') { #>
                    <div class="ip-info-box__row ip-info-box__row--min-order">
                        <span class="ip-info-box__icon" style="color:{{{ settings.icon_min_order_color }}}">
                            <# var minIcon = elementor.helpers.renderIcon(view, settings.icon_min_order, {'aria-hidden':'true'}, 'i', 'object');
                               if (minIcon && minIcon.value) { #>{{{ minIcon.value }}}<# } #>
                        </span>
                        <span class="ip-info-box__text">1 {{{ settings.min_order_unit }}}</span>
                    </div>
                <# } #>

                <# if (settings.show_delivery === 'yes') { #>
                    <div class="ip-info-box__row ip-info-box__row--delivery">
                        <span class="ip-info-box__icon" style="color:{{{ settings.icon_delivery_color }}}">
                            <# var delIcon = elementor.helpers.renderIcon(view, settings.icon_delivery, {'aria-hidden':'true'}, 'i', 'object');
                               if (delIcon && delIcon.value) { #>{{{ delIcon.value }}}<# } #>
                        </span>
                        <span class="ip-info-box__text">{{{ settings.delivery_prefix }}} {{{ settings.delivery_default_days }}} {{{ settings.delivery_suffix }}}</span>
                    </div>
                <# } #>
            </div>
        </div>
        <?php
    }
}
