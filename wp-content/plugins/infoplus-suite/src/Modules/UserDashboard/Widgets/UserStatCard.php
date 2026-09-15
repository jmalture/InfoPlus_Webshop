<?php

/**
 * User Stat Card Grid — Elementor Widget
 *
 * Displays a configurable grid of stat cards, each with its own icon, label,
 * and dynamic value pulled from user/WooCommerce data.
 *
 * @package Infoplus\Suite\Modules\UserDashboard\Widgets
 */

namespace Infoplus\Suite\Modules\UserDashboard\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) exit;

class UserStatCard extends Widget_Base
{
    public function get_name(): string
    {
        return 'infoplus-user-stat-card';
    }

    public function get_title(): string
    {
        return __('User Stat Cards', 'infoplus-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-number-field';
    }

    public function get_categories(): array
    {
        return ['infoplus-product'];
    }

    public function get_style_depends(): array
    {
        return ['infoplus-user-stat-card'];
    }

    public function get_keywords(): array
    {
        return ['stat', 'card', 'dashboard', 'user', 'orders', 'count', 'infoplus', 'grid'];
    }

    // ── Controls ───────────────────────────────────────────────────────────────

    protected function register_controls(): void
    {
        // ── Cards (Repeater) ────────────────────────────────────────────────

        $this->start_controls_section('section_cards', [
            'label' => __('Cards', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $repeater = new Repeater();

        $repeater->add_control('data_source', [
            'label'   => __('Data Source', 'infoplus-suite'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'orders_total',
            'options' => [
                'orders_total'      => __('Bestellungen gesamt', 'infoplus-suite'),
                'orders_processing' => __('Bestellungen in Bearbeitung', 'infoplus-suite'),
                'orders_completed'  => __('Bestellungen abgeschlossen', 'infoplus-suite'),
                'total_spent'       => __('Gesamtausgaben', 'infoplus-suite'),
                'projects_total'    => __('Projekte gesamt (Platzhalter)', 'infoplus-suite'),
                'shared_projects'   => __('Geteilte Projekte (Platzhalter)', 'infoplus-suite'),
                'custom'            => __('Benutzerdefiniert', 'infoplus-suite'),
            ],
        ]);

        $repeater->add_control('custom_value', [
            'label'     => __('Value', 'infoplus-suite'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '0',
            'condition' => ['data_source' => 'custom'],
        ]);

        $repeater->add_control('label', [
            'label'   => __('Label', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Bestellungen gesamt',
        ]);

        $repeater->add_control('selected_icon', [
            'label'   => __('Icon', 'infoplus-suite'),
            'type'    => Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-shopping-cart',
                'library' => 'fa-solid',
            ],
        ]);

        $repeater->add_control('icon_color', [
            'label'   => __('Icon Color', 'infoplus-suite'),
            'type'    => Controls_Manager::COLOR,
            'default' => '#e91e8c',
        ]);

        $repeater->add_control('icon_bg_color', [
            'label'   => __('Icon Background', 'infoplus-suite'),
            'type'    => Controls_Manager::COLOR,
            'default' => 'rgba(233, 30, 140, 0.08)',
        ]);

        $repeater->add_control('icon_border_color', [
            'label'   => __('Icon Border Color', 'infoplus-suite'),
            'type'    => Controls_Manager::COLOR,
            'default' => 'transparent',
        ]);

        $this->add_control('cards', [
            'label'       => __('Stat Cards', 'infoplus-suite'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [
                [
                    'data_source' => 'orders_total',
                    'label'       => 'Bestellungen gesamt',
                    'selected_icon' => ['value' => 'fas fa-shopping-cart', 'library' => 'fa-solid'],
                    'icon_color'  => '#e91e8c',
                    'icon_bg_color' => 'rgba(233, 30, 140, 0.08)',
                ],
                [
                    'data_source' => 'orders_processing',
                    'label'       => 'In Bearbeitung',
                    'selected_icon' => ['value' => 'fas fa-spinner', 'library' => 'fa-solid'],
                    'icon_color'  => '#f59e0b',
                    'icon_bg_color' => 'rgba(245, 158, 11, 0.08)',
                ],
                [
                    'data_source' => 'orders_completed',
                    'label'       => 'Abgeschlossen',
                    'selected_icon' => ['value' => 'fas fa-check-circle', 'library' => 'fa-solid'],
                    'icon_color'  => '#10b981',
                    'icon_bg_color' => 'rgba(16, 185, 129, 0.08)',
                ],
                [
                    'data_source' => 'total_spent',
                    'label'       => 'Gesamtausgaben',
                    'selected_icon' => ['value' => 'fas fa-euro-sign', 'library' => 'fa-solid'],
                    'icon_color'  => '#6366f1',
                    'icon_bg_color' => 'rgba(99, 102, 241, 0.08)',
                ],
            ],
            'title_field' => '{{{ label }}}',
        ]);

        $this->end_controls_section();

        // ── Grid Layout ─────────────────────────────────────────────────────

        $this->start_controls_section('section_grid', [
            'label' => __('Grid Layout', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_responsive_control('grid_columns', [
            'label'   => __('Columns', 'infoplus-suite'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 4,
            'min'     => 1,
            'max'     => 6,
            'tablet_default' => 2,
            'mobile_default' => 1,
            'selectors' => [
                '{{WRAPPER}} .ip-stat-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);

        $this->add_responsive_control('grid_rows', [
            'label'       => __('Rows', 'infoplus-suite'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 0,
            'min'         => 0,
            'max'         => 10,
            'description' => __('0 = auto (as many rows as needed)', 'infoplus-suite'),
        ]);

        $this->add_responsive_control('grid_gap', [
            'label'      => __('Gap', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['size' => 16, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-stat-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Icon Style ──────────────────────────────────────────────────────

        $this->start_controls_section('section_icon_style', [
            'label' => __('Icon', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('icon_size', [
            'label'      => __('Icon Size', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 12, 'max' => 64]],
            'default'    => ['size' => 24, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-stat-card__icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .ip-stat-card__icon svg'  => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_box_size', [
            'label'      => __('Box Size', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 32, 'max' => 96]],
            'default'    => ['size' => 52, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-stat-card__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('icon_border_radius', [
            'label'      => __('Border Radius', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 0, 'max' => 50],
                '%'  => ['min' => 0, 'max' => 50],
            ],
            'default'    => ['size' => 14, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-stat-card__icon' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Label Style ─────────────────────────────────────────────────────

        $this->start_controls_section('section_label_style', [
            'label' => __('Label', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('label_color', [
            'label'     => __('Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#888',
            'selectors' => [
                '{{WRAPPER}} .ip-stat-card__label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .ip-stat-card__label',
        ]);

        $this->end_controls_section();

        // ── Value Style ─────────────────────────────────────────────────────

        $this->start_controls_section('section_value_style', [
            'label' => __('Value', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('value_color', [
            'label'     => __('Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [
                '{{WRAPPER}} .ip-stat-card__value' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typography',
            'selector' => '{{WRAPPER}} .ip-stat-card__value',
        ]);

        $this->end_controls_section();

        // ── Card Style ──────────────────────────────────────────────────────

        $this->start_controls_section('section_card_style', [
            'label' => __('Card', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg_color', [
            'label'     => __('Background', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .ip-stat-card' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_border_color', [
            'label'     => __('Border Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#eee',
            'selectors' => [
                '{{WRAPPER}} .ip-stat-card' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_border_radius', [
            'label'      => __('Border Radius', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'default'    => ['size' => 12, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-stat-card' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => __('Padding', 'infoplus-suite'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default'    => [
                'top' => '20', 'right' => '24', 'bottom' => '20', 'left' => '24',
                'unit' => 'px', 'isLinked' => false,
            ],
            'selectors' => [
                '{{WRAPPER}} .ip-stat-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_inner_gap', [
            'label'      => __('Gap (icon to text)', 'infoplus-suite'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['size' => 16, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .ip-stat-card' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ── Data fetching ──────────────────────────────────────────────────────────

    private function getStatValue(string $source): string
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return $this->getDemoValue($source);
        }

        switch ($source) {
            case 'orders_total':
                return (string) $this->getOrderCount($user_id);

            case 'orders_processing':
                return (string) $this->getOrderCount($user_id, 'processing');

            case 'orders_completed':
                return (string) $this->getOrderCount($user_id, 'completed');

            case 'total_spent':
                if (function_exists('wc_get_customer_total_spent')) {
                    $spent = wc_get_customer_total_spent($user_id);
                    return number_format((float) $spent, 2, ',', '.') . ' EUR';
                }
                return '0,00 EUR';

            case 'projects_total':
            case 'shared_projects':
                return '0';

            case 'custom':
            default:
                return '';
        }
    }

    private function getOrderCount(int $user_id, ?string $status = null): int
    {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $args = [
            'customer_id' => $user_id,
            'limit'       => -1,
            'return'      => 'ids',
        ];

        if ($status) {
            $args['status'] = 'wc-' . $status;
        } else {
            $args['status'] = ['wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed'];
        }

        return count(wc_get_orders($args));
    }

    private function getDemoValue(string $source): string
    {
        $demos = [
            'orders_total'      => '28',
            'orders_processing' => '3',
            'orders_completed'  => '25',
            'total_spent'       => '4.250,00 EUR',
            'projects_total'    => '12',
            'shared_projects'   => '5',
            'custom'            => '0',
        ];
        return $demos[$source] ?? '0';
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $cards    = $settings['cards'] ?? [];

        if (empty($cards)) return;

        $rows = (int) ($settings['grid_rows'] ?? 0);
        $rows_style = $rows > 0 ? ' style="grid-template-rows:repeat(' . $rows . ',1fr);"' : '';
        ?>
        <div class="ip-stat-grid"<?php echo $rows_style; ?>>
            <?php foreach ($cards as $card) :
                $source = $card['data_source'] ?? 'orders_total';
                $label  = $card['label'] ?? '';
                $value  = ($source === 'custom')
                    ? ($card['custom_value'] ?? '0')
                    : $this->getStatValue($source);

                $icon_color  = $card['icon_color'] ?? '#e91e8c';
                $icon_bg     = $card['icon_bg_color'] ?? 'rgba(233,30,140,0.08)';
                $icon_border = $card['icon_border_color'] ?? 'transparent';
            ?>
                <div class="ip-stat-card">
                    <div class="ip-stat-card__icon" style="color:<?php echo esc_attr($icon_color); ?>;background-color:<?php echo esc_attr($icon_bg); ?>;border-color:<?php echo esc_attr($icon_border); ?>;">
                        <?php \Elementor\Icons_Manager::render_icon($card['selected_icon'], ['aria-hidden' => 'true']); ?>
                    </div>
                    <div class="ip-stat-card__body">
                        <span class="ip-stat-card__label"><?php echo esc_html($label); ?></span>
                        <span class="ip-stat-card__value"><?php echo esc_html($value); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    protected function content_template(): void
    {
        ?>
        <# var rowsVal = parseInt(settings.grid_rows) || 0;
           var rowsStyle = rowsVal > 0 ? 'grid-template-rows:repeat(' + rowsVal + ',1fr);' : ''; #>
        <div class="ip-stat-grid" style="{{{ rowsStyle }}}">
            <# _.each(settings.cards, function(card) { #>
                <div class="ip-stat-card">
                    <div class="ip-stat-card__icon" style="color:{{{ card.icon_color }}};background-color:{{{ card.icon_bg_color }}};border-color:{{{ card.icon_border_color }}};">
                        <# var iconHTML = elementor.helpers.renderIcon(view, card.selected_icon, { 'aria-hidden': 'true' }, 'i', 'object');
                           if (iconHTML && iconHTML.value) { #>
                            {{{ iconHTML.value }}}
                        <# } #>
                    </div>
                    <div class="ip-stat-card__body">
                        <span class="ip-stat-card__label">{{{ card.label }}}</span>
                        <span class="ip-stat-card__value">
                            <# if (card.data_source === 'custom') { #>
                                {{{ card.custom_value }}}
                            <# } else { #>
                                --
                            <# } #>
                        </span>
                    </div>
                </div>
            <# }); #>
        </div>
        <?php
    }
}
