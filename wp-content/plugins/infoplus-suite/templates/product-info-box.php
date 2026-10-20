<?php
/**
 * Template: product info box (component "product-info-box").
 *
 * Override in a theme: copy to {theme}/infoplus-suite/product-info-box.php.
 * Rendered by Infoplus\Suite\View::render(), called from the ProductInfoBox
 * Elementor widget and from infoplus_suite_product_info_box(). Keep the
 * ip-info-box__* value classes: product-info-box.js writes the SKU and the
 * minimum quantity into them.
 *
 * $args (built by Components::productInfoBox()):
 *   context                string  'elementor' or 'theme'
 *   heading                string
 *   show_sku               bool
 *   sku_label              string
 *   show_min_order         bool
 *   min_order_unit         string
 *   show_delivery          bool
 *   delivery_default_days  int     Used when the product has no delivery meta
 *   delivery_prefix        string
 *   delivery_suffix        string
 *   icon_min_order         string  Icons::svg() name, default 'box'
 *   icon_min_order_color   string  CSS colour
 *   icon_min_order_html    string|null  Ready icon markup (Elementor path); null = use icon_min_order
 *   icon_delivery          string  Icons::svg() name, default 'clock'
 *   icon_delivery_color    string  CSS colour
 *   icon_delivery_html     string|null  Ready icon markup (Elementor path); null = use icon_delivery
 *   product                WC_Product|null
 *   sku                    string
 *   min_qty                int
 *   delivery_days          int
 *
 * Icon markup from either source is printed through Icons::kses().
 *
 * Restyle hooks: root .ip-c-product-info-box[data-ip-component="product-info-box"],
 * parts .ip-c-product-info-box__heading, __rows, __row, __icon, __label (row text),
 * __value (SKU, min. quantity, delivery days).
 *
 * CSS custom properties (theme contract, defaults are the widget control
 * defaults; the plugin CSS does not read them, the theme maps them onto the
 * classes above). Icon colours are inline styles from the args, as in Elementor.
 *   --ip-ib-heading-color   #333
 *   --ip-ib-heading-gap     8px      space below the heading
 *   --ip-ib-text-color      #333
 *   --ip-ib-icon-size       16px
 *   --ip-ib-row-gap         6px
 *   --ip-ib-icon-gap        8px      between icon and text
 *
 * Whitespace below mirrors the former ProductInfoBox::render() output byte for byte.
 *
 * @package Infoplus\Suite
 */

defined('ABSPATH') || exit;

$heading = $args['heading'];

$icon_min_order_html = is_string($args['icon_min_order_html'])
    ? $args['icon_min_order_html']
    : \Infoplus\Suite\Icons::svg($args['icon_min_order'], ['aria-hidden' => 'true']);
$icon_delivery_html = is_string($args['icon_delivery_html'])
    ? $args['icon_delivery_html']
    : \Infoplus\Suite\Icons::svg($args['icon_delivery'], ['aria-hidden' => 'true']);

?>
        <div class="ip-info-box ip-c-product-info-box" data-ip-component="product-info-box">
            <?php if ($heading) : ?>
                <div class="ip-info-box__heading ip-c-product-info-box__heading"><?php echo esc_html($heading); ?></div>
            <?php endif; ?>

            <div class="ip-info-box__rows ip-c-product-info-box__rows">
                <?php if ($args['show_sku']) : ?>
                    <div class="ip-info-box__row ip-info-box__row--sku ip-c-product-info-box__row">
                        <span class="ip-info-box__text ip-c-product-info-box__label">
                            <span class="ip-info-box__sku-label"><?php echo esc_html($args['sku_label']); ?></span>
                            <span class="ip-info-box__sku-value ip-c-product-info-box__value"><?php echo esc_html($args['sku'] ?: '—'); ?></span>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($args['show_min_order']) : ?>
                    <div class="ip-info-box__row ip-info-box__row--min-order ip-c-product-info-box__row">
                        <span class="ip-info-box__icon ip-c-product-info-box__icon" style="color:<?php echo esc_attr($args['icon_min_order_color']); ?>">
                            <?php echo \Infoplus\Suite\Icons::kses($icon_min_order_html); ?>
                        </span>
                        <span class="ip-info-box__text ip-c-product-info-box__label">
                            <span class="ip-info-box__min-qty ip-c-product-info-box__value"><?php echo esc_html($args['min_qty']); ?></span>
                            <?php echo esc_html($args['min_order_unit']); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($args['show_delivery']) : ?>
                    <div class="ip-info-box__row ip-info-box__row--delivery ip-c-product-info-box__row">
                        <span class="ip-info-box__icon ip-c-product-info-box__icon" style="color:<?php echo esc_attr($args['icon_delivery_color']); ?>">
                            <?php echo \Infoplus\Suite\Icons::kses($icon_delivery_html); ?>
                        </span>
                        <span class="ip-info-box__text ip-c-product-info-box__label">
                            <?php echo esc_html($args['delivery_prefix']); ?>
                            <span class="ip-info-box__delivery-days ip-c-product-info-box__value"><?php echo esc_html($args['delivery_days']); ?></span>
                            <?php echo esc_html($args['delivery_suffix']); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
