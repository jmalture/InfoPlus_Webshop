<?php

/**
 * Bulk Price Table Elementor Widget
 *
 * Standalone price-tier grid widget for WooCommerce variable product pages.
 * Fetches prices from the REST API keyed by variation SKU, renders a responsive
 * grid with configurable columns (rows auto-calculated from API data), animated
 * loading skeleton, active-tier highlighting driven by current quantity, and
 * click-to-set-quantity behaviour.
 *
 * Communicates with other widgets via infoplusProductState:
 *   - Listens: variation:found, variation:reset, variation:notfound, qty:changed
 *   - Fires:   qty:changed (source='external') on cell click
 *
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Widgets
 * @since 1.0.53
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Infoplus\Suite\View;
use Infoplus\Suite\Modules\ProductDetailSuite\Components;

if (!defined('ABSPATH')) exit;

class BulkPriceTable extends Widget_Base
{
    public function get_name(): string
    {
        return 'infoplus-bulk-price-table';
    }

    public function get_title(): string
    {
        return __('Bulk Price Table', 'infoplus-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-price-table';
    }

    public function get_categories(): array
    {
        return ['infoplus-product'];
    }

    public function get_keywords(): array
    {
        return ['bulk', 'price', 'table', 'quantity', 'discount', 'staffelpreis', 'infoplus'];
    }

    public function get_script_depends(): array
    {
        return ['infoplus-product-state', 'infoplus-bulk-price-table'];
    }

    public function get_style_depends(): array
    {
        return ['infoplus-product-detail-suite', 'infoplus-bulk-price-table'];
    }

    // ── Controls ───────────────────────────────────────────────────────────────

    protected function register_controls(): void
    {
        // ── Content Tab ───────────────────────────────────────────────────────

        $this->start_controls_section('section_layout', [
            'label' => __('Layout', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('columns', [
            'label'       => __('Columns per Row', 'infoplus-suite'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 2,
            'min'         => 1,
            'max'         => 4,
            'description' => __('Number of price-tier cells per row. Rows are calculated automatically from the API data.', 'infoplus-suite'),
            'selectors'   => [
                '{{WRAPPER}} .ibpt-grid'     => 'grid-template-columns: repeat({{VALUE}}, 1fr)',
                '{{WRAPPER}} .ibpt-skeleton' => 'grid-template-columns: repeat({{VALUE}}, 1fr)',
            ],
        ]);

        $this->add_control('skeleton_rows', [
            'label'       => __('Skeleton Rows (loading state)', 'infoplus-suite'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 3,
            'min'         => 1,
            'max'         => 6,
            'description' => __('How many placeholder rows to show while prices are loading.', 'infoplus-suite'),
        ]);

        $this->add_control('ab_label', [
            'label'   => __('Prefix Label', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => 'ab',
        ]);

        $this->add_control('stueck_label', [
            'label'   => __('Unit Label', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Stück',
        ]);

        $this->end_controls_section();

        // ── Style Tab: Spacing ────────────────────────────────────────────────

        $this->start_controls_section('section_style_spacing', [
            'label' => __('Spacing', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('col_gap', [
            'label'      => __('Column Gap', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['size' => 10, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ibpt-grid, {{WRAPPER}} .ibpt-skeleton' => 'column-gap: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $this->add_control('row_gap', [
            'label'      => __('Row Gap', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['size' => 8, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ibpt-grid, {{WRAPPER}} .ibpt-skeleton' => 'row-gap: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $this->end_controls_section();

        // ── Style Tab: Cell ───────────────────────────────────────────────────

        $this->start_controls_section('section_style_cell', [
            'label' => __('Cell', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('cell_bg_color', [
            'label'     => __('Background', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => ['{{WRAPPER}} .ibpt-cell' => 'background-color: {{VALUE}}'],
        ]);

        $this->add_responsive_control('cell_padding', [
            'label'      => __('Padding', 'infoplus-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default'    => [
                'top' => '10', 'right' => '14', 'bottom' => '10', 'left' => '14',
                'unit' => 'px', 'isLinked' => false,
            ],
            'selectors' => ['{{WRAPPER}} .ibpt-cell' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}'],
        ]);

        $this->add_control('cell_border_radius', [
            'label'      => __('Border Radius', 'infoplus-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default'    => [
                'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6',
                'unit' => 'px', 'isLinked' => true,
            ],
            'selectors' => ['{{WRAPPER}} .ibpt-cell' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'cell_border',
            'selector' => '{{WRAPPER}} .ibpt-cell',
        ]);

        $this->end_controls_section();

        // ── Style Tab: Labels ("ab" / "Stück") ────────────────────────────────

        $this->start_controls_section('section_style_labels', [
            'label' => __('Labels ("ab" / "Stück")', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('label_color', [
            'label'     => __('Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#666666',
            'selectors' => ['{{WRAPPER}} .ibpt-cell__label' => 'color: {{VALUE}}'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .ibpt-cell__label',
        ]);

        $this->end_controls_section();

        // ── Style Tab: Quantity Number ─────────────────────────────────────────

        $this->start_controls_section('section_style_qty', [
            'label' => __('Quantity Number', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('qty_color', [
            'label'     => __('Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#222222',
            'selectors' => ['{{WRAPPER}} .ibpt-cell__qty' => 'color: {{VALUE}}'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'qty_typography',
            'selector' => '{{WRAPPER}} .ibpt-cell__qty',
        ]);

        $this->end_controls_section();

        // ── Style Tab: Price ──────────────────────────────────────────────────

        $this->start_controls_section('section_style_price', [
            'label' => __('Price', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('price_color', [
            'label'     => __('Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e91e8c',
            'selectors' => ['{{WRAPPER}} .ibpt-cell__price' => 'color: {{VALUE}}'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'price_typography',
            'selector' => '{{WRAPPER}} .ibpt-cell__price',
        ]);

        $this->end_controls_section();

        // ── Style Tab: Active Cell ────────────────────────────────────────────

        $this->start_controls_section('section_style_active', [
            'label' => __('Active Cell (matching quantity)', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('active_bg_color', [
            'label'     => __('Background', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#f7f7f7',
            'selectors' => ['{{WRAPPER}} .ibpt-cell.ibpt-cell--active' => 'background-color: {{VALUE}}'],
        ]);

        $this->add_control('active_qty_color', [
            'label'     => __('Quantity Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e91e8c',
            'selectors' => ['{{WRAPPER}} .ibpt-cell.ibpt-cell--active .ibpt-cell__qty' => 'color: {{VALUE}}'],
        ]);

        $this->add_control('active_price_color', [
            'label'     => __('Price Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e91e8c',
            'selectors' => ['{{WRAPPER}} .ibpt-cell.ibpt-cell--active .ibpt-cell__price' => 'color: {{VALUE}}'],
        ]);

        $this->add_control('active_label_color', [
            'label'     => __('Label Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .ibpt-cell.ibpt-cell--active .ibpt-cell__label' => 'color: {{VALUE}}'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'active_border',
            'selector' => '{{WRAPPER}} .ibpt-cell.ibpt-cell--active',
        ]);

        $this->end_controls_section();
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Markup lives in templates/bulk-price-table.php (View::render).
        // In the Elementor editor the template shows static demo tiers.
        View::render('bulk-price-table', Components::bulkPriceTable(null, [
            'context'       => View::CONTEXT_ELEMENTOR,
            'columns'       => $settings['columns'] ?? 2,
            'skeleton_rows' => $settings['skeleton_rows'] ?? 3,
            'ab_label'      => $settings['ab_label'] ?? 'ab',
            'stueck_label'  => $settings['stueck_label'] ?? 'Stück',
            'is_preview'    => \Elementor\Plugin::$instance->editor->is_edit_mode(),
        ]));
    }
}
