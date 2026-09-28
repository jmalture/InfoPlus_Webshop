<?php

/**
 * Product Order Panel Elementor Widget
 *
 * Unified order widget replacing scattered product ordering elements:
 * - Product info (min quantity, delivery time)
 * - Bulk pricing table (fetched from API by variation SKU)
 * - Quantity selector with live price calculation
 * - Price display (Einzelpreis / Gesamtpreis)
 * - Action buttons (Editor / Anfrage / Cart) driven by order_type
 *
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Widgets
 * @since 1.0.39
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class ProductOrderPanel extends Widget_Base
{
    public function get_name(): string
    {
        return 'infoplus-product-order-panel';
    }
    public function get_title(): string
    {
        return __('Product Order Panel', 'infoplus-suite');
    }
    public function get_icon(): string
    {
        return 'eicon-cart';
    }
    public function get_categories(): array
    {
        return ['infoplus-product'];
    }
    public function get_keywords(): array
    {
        return ['order', 'cart', 'quantity', 'price', 'bulk', 'product', 'woocommerce', 'infoplus'];
    }
    public function get_script_depends(): array
    {
        return ['infoplus-product-state', 'infoplus-product-order-panel'];
    }
    public function get_style_depends(): array
    {
        return ['infoplus-product-detail-suite', 'infoplus-product-order-panel'];
    }

    // ── Controls ──────────────────────────────────────────────────────────────

    protected function register_controls(): void
    {
        // ── Content Tab ───────────────────────────────────────────────────────

        $this->start_controls_section('section_content', [
            'label' => __('Content', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_product_info', [
            'label'        => __('Show Product Info', 'infoplus-suite'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('show_bulk_table', [
            'label'        => __('Show Bulk Price Table', 'infoplus-suite'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('price_suffix_text', [
            'label'       => __('Price Suffix Text', 'infoplus-suite'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('Alle Preise inkl. MwSt., zzgl. Versandkosten', 'infoplus-suite'),
            'placeholder' => __('Alle Preise inkl. MwSt., zzgl. Versandkosten', 'infoplus-suite'),
        ]);

        $this->add_control('editor_btn_text', [
            'label'   => __('Editor Button Text', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Produkt gestalten →', 'infoplus-suite'),
        ]);

        $this->add_control('anfrage_btn_text', [
            'label'   => __('Anfrage Button Text', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Individuelle gewünscht? ↓', 'infoplus-suite'),
        ]);

        $this->add_control('cart_btn_text', [
            'label'   => __('Cart Button Text', 'infoplus-suite'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('In den Warenkorb →', 'infoplus-suite'),
        ]);

        $this->end_controls_section();

        // ── Style Tab ─────────────────────────────────────────────────────────

        $this->start_controls_section('section_style_layout', [
            'label' => __('Layout', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('sections_gap', [
            'label'     => __('Section Gap', 'infoplus-suite'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0, 'max' => 60]],
            'default'   => ['size' => 20, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-order-panel > * + *' => 'margin-top: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('table_columns', [
            'label'   => __('Bulk Table Columns', 'infoplus-suite'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                '2' => __('2 Columns', 'infoplus-suite'),
                '3' => __('3 Columns', 'infoplus-suite'),
            ],
            'default' => '2',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_buttons', [
            'label' => __('Buttons', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('btn_primary_color', [
            'label'     => __('Primary Button Color (Editor / Cart)', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e91e8c',
            'selectors' => [
                '{{WRAPPER}} .infoplus-btn--editor, {{WRAPPER}} .infoplus-btn--cart' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('btn_primary_hover_color', [
            'label'     => __('Primary Button Hover Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#d11a7d',
            'selectors' => [
                '{{WRAPPER}} .infoplus-btn--editor:not(.infoplus-btn--disabled):hover, {{WRAPPER}} .infoplus-btn--cart:not(.infoplus-btn--disabled):hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('btn_secondary_color', [
            'label'     => __('Anfrage Button Border Color', 'infoplus-suite'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e91e8c',
            'selectors' => [
                '{{WRAPPER}} .infoplus-btn--anfrage' => 'border-color: {{VALUE}}; color: {{VALUE}};',
            ],
        ]);

        $this->add_control('btn_radius', [
            'label'     => __('Button Border Radius', 'infoplus-suite'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0, 'max' => 50]],
            'default'   => ['size' => 6, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_qty', [
            'label' => __('Quantity Selector', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('qty_radius', [
            'label'     => __('Input Border Radius', 'infoplus-suite'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0, 'max' => 30]],
            'default'   => ['size' => 6, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-qty-input, {{WRAPPER}} .infoplus-qty-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_typography', [
            'label' => __('Typography', 'infoplus-suite'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('info_font_size', [
            'label'     => __('Info Text Size', 'infoplus-suite'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 10, 'max' => 24]],
            'default'   => ['size' => 14, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-order-panel__info' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('price_font_size', [
            'label'     => __('Price Text Size', 'infoplus-suite'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 12, 'max' => 32]],
            'default'   => ['size' => 16, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-price-value' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('table_font_size', [
            'label'     => __('Bulk Table Text Size', 'infoplus-suite'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 10, 'max' => 20]],
            'default'   => ['size' => 13, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .infoplus-order-panel__bulk' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ── Render ────────────────────────────────────────────────────────────────

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        global $product;

        // In Elementor editor: try to get a product for preview
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        if (!$product || !is_a($product, 'WC_Product')) {
            if ($is_editor) {
                $products = wc_get_products(['type' => 'variable', 'limit' => 1, 'status' => 'publish']);
                $product  = !empty($products) ? $products[0] : null;
            }
        }

        $product_id  = $product ? $product->get_id() : 0;
        $min_qty     = $product_id ? max(1, (int) get_post_meta($product_id, '_infoplus_min_quantity', true)) : 1;
        $delivery    = $product_id ? (int) get_post_meta($product_id, '_infoplus_delivery_days', true) : 0;

        $show_info  = $settings['show_product_info'] === 'yes';
        $show_bulk  = $settings['show_bulk_table']   === 'yes';
        $table_cols = (int) ($settings['table_columns'] ?? 2);

        $price_suffix   = esc_html($settings['price_suffix_text'] ?? '');
        $editor_btn_txt = esc_html($settings['editor_btn_text']   ?? 'Produkt gestalten →');
        $anfrage_btn_txt= esc_html($settings['anfrage_btn_text']  ?? 'Individuelle gewünscht? ↓');
        $cart_btn_txt   = esc_html($settings['cart_btn_text']     ?? 'In den Warenkorb →');

        ?>
        <div class="infoplus-order-panel"
             data-product-id="<?php echo esc_attr($product_id); ?>"
             data-min-qty="<?php echo esc_attr($min_qty); ?>"
             data-table-cols="<?php echo esc_attr($table_cols); ?>">

            <?php if ($show_info && ($min_qty > 1 || $delivery > 0 || $is_editor)): ?>
            <!-- Section 1: Product Info -->
            <div class="infoplus-order-panel__info"
                <?php if (!$is_editor && $min_qty <= 1 && $delivery <= 0): ?>style="display:none;"<?php endif; ?>>

                <?php if ($min_qty > 1 || $is_editor): ?>
                <div class="infoplus-order-panel__info-item infoplus-order-panel__info-item--qty">
                    <span class="infoplus-info-icon infoplus-info-icon--qty"></span>
                    <span>Mindestanzahl: <strong><?php echo esc_html($min_qty); ?> Stück</strong></span>
                </div>
                <?php endif; ?>

                <?php if ($delivery > 0 || $is_editor): ?>
                <div class="infoplus-order-panel__info-item infoplus-order-panel__info-item--delivery">
                    <span class="infoplus-info-icon infoplus-info-icon--delivery"></span>
                    <span>max. <strong><?php echo esc_html($delivery ?: '—'); ?> Arbeitstage</strong></span>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <?php if ($show_bulk): ?>
            <!-- Section 2: Bulk Price Table -->
            <div class="infoplus-order-panel__bulk"
                <?php if (!$is_editor): ?>style="display:none;"<?php endif; ?>>
                <div class="infoplus-order-panel__bulk-loading" style="display:none;">
                    <span class="infoplus-spinner"></span>
                </div>
                <div class="infoplus-order-panel__bulk-grid" data-cols="<?php echo esc_attr($table_cols); ?>">
                    <?php if ($is_editor): ?>
                    <!-- Preview rows for editor -->
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 10 Stück</span><span class="infoplus-bulk-price">38,95 €</span></div>
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 49 Stück</span><span class="infoplus-bulk-price">31,25 €</span></div>
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 99 Stück</span><span class="infoplus-bulk-price">24,45 €</span></div>
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 249 Stück</span><span class="infoplus-bulk-price">18,05 €</span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section 3: Quantity Selector -->
            <div class="infoplus-order-panel__quantity">
                <button type="button" class="infoplus-qty-btn infoplus-qty-minus" aria-label="Menge verringern">−</button>
                <input type="number"
                       class="infoplus-qty-input"
                       value="<?php echo esc_attr($min_qty); ?>"
                       min="<?php echo esc_attr($min_qty); ?>"
                       step="1"
                       aria-label="Menge" />
                <button type="button" class="infoplus-qty-btn infoplus-qty-plus" aria-label="Menge erhöhen">+</button>
            </div>

            <!-- Section 4: Price Display -->
            <div class="infoplus-order-panel__prices"
                <?php if (!$is_editor): ?>style="display:none;"<?php endif; ?>>
                <div class="infoplus-price-row">
                    <span class="infoplus-price-label">Einzelpreis</span>
                    <span class="infoplus-price-value infoplus-price-unit">
                        <?php echo $is_editor ? '38,95 €' : ''; ?>
                    </span>
                </div>
                <div class="infoplus-price-row infoplus-price-row--total">
                    <span class="infoplus-price-label">Gesamtpreis*</span>
                    <span class="infoplus-price-value infoplus-price-total">
                        <?php echo $is_editor ? '389,50 €' : ''; ?>
                    </span>
                </div>
                <?php if ($price_suffix): ?>
                <div class="infoplus-price-suffix"><?php echo $price_suffix; ?></div>
                <?php endif; ?>
            </div>

            <!-- Section 5: Action Buttons -->
            <div class="infoplus-order-panel__actions">

                <!-- Editor button (visible for order_type=editable) -->
                <a href="#"
                   class="infoplus-btn infoplus-btn--editor infoplus-btn--disabled<?php echo $is_editor ? '' : ' infoplus-btn--hidden'; ?>"
                   data-action="editor"
                   aria-disabled="true">
                    <?php echo $editor_btn_txt; ?>
                </a>

                <!-- Anfrage button (visible for editable + requestable) -->
                <a href="#anfrage"
                   class="infoplus-btn infoplus-btn--anfrage<?php echo $is_editor ? '' : ' infoplus-btn--hidden'; ?>"
                   data-action="anfrage">
                    <?php echo $anfrage_btn_txt; ?>
                </a>

                <!-- Cart button (visible for order_type=buyable) -->
                <button type="button"
                        class="infoplus-btn infoplus-btn--cart infoplus-btn--disabled<?php echo $is_editor ? '' : ' infoplus-btn--hidden'; ?>"
                        data-action="cart"
                        disabled>
                    <?php echo $cart_btn_txt; ?>
                </button>

                <div class="infoplus-btn-error" style="display:none;">Kombination nicht verfügbar</div>

            </div>

        </div>
        <?php
    }

    protected function content_template(): void
    {
        ?>
        <div class="infoplus-order-panel" data-product-id="0" data-min-qty="1">

            <# if (settings.show_product_info === 'yes') { #>
            <div class="infoplus-order-panel__info">
                <div class="infoplus-order-panel__info-item">
                    <span class="infoplus-info-icon infoplus-info-icon--qty"></span>
                    <span>Mindestanzahl: <strong>10 Stück</strong></span>
                </div>
                <div class="infoplus-order-panel__info-item">
                    <span class="infoplus-info-icon infoplus-info-icon--delivery"></span>
                    <span>max. <strong>15 Arbeitstage</strong></span>
                </div>
            </div>
            <# } #>

            <# if (settings.show_bulk_table === 'yes') { #>
            <div class="infoplus-order-panel__bulk">
                <div class="infoplus-order-panel__bulk-grid" data-cols="{{ settings.table_columns || 2 }}">
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 10 Stück</span><span class="infoplus-bulk-price">38,95 €</span></div>
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 49 Stück</span><span class="infoplus-bulk-price">31,25 €</span></div>
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 99 Stück</span><span class="infoplus-bulk-price">24,45 €</span></div>
                    <div class="infoplus-bulk-row"><span class="infoplus-bulk-qty">ab 249 Stück</span><span class="infoplus-bulk-price">18,05 €</span></div>
                </div>
            </div>
            <# } #>

            <div class="infoplus-order-panel__quantity">
                <button type="button" class="infoplus-qty-btn infoplus-qty-minus">−</button>
                <input type="number" class="infoplus-qty-input" value="10" min="1" step="1" />
                <button type="button" class="infoplus-qty-btn infoplus-qty-plus">+</button>
            </div>

            <div class="infoplus-order-panel__prices">
                <div class="infoplus-price-row">
                    <span class="infoplus-price-label">Einzelpreis</span>
                    <span class="infoplus-price-value infoplus-price-unit">38,95 €</span>
                </div>
                <div class="infoplus-price-row infoplus-price-row--total">
                    <span class="infoplus-price-label">Gesamtpreis*</span>
                    <span class="infoplus-price-value infoplus-price-total">389,50 €</span>
                </div>
                <# if (settings.price_suffix_text) { #>
                <div class="infoplus-price-suffix">{{ settings.price_suffix_text }}</div>
                <# } #>
            </div>

            <div class="infoplus-order-panel__actions">
                <a href="#" class="infoplus-btn infoplus-btn--editor" data-action="editor">{{ settings.editor_btn_text || 'Produkt gestalten →' }}</a>
                <a href="#anfrage" class="infoplus-btn infoplus-btn--anfrage" data-action="anfrage">{{ settings.anfrage_btn_text || 'Individuelle gewünscht? ↓' }}</a>
                <button type="button" class="infoplus-btn infoplus-btn--cart" data-action="cart">{{ settings.cart_btn_text || 'In den Warenkorb →' }}</button>
            </div>

        </div>
        <?php
    }
}
