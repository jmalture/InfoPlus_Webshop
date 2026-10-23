<?php
/**
 * Server-side minimum order quantity (Mindestbestellmenge).
 *
 * Replaces the BeRocket "Min/Max Quantities" plugin (deleted on staging 2026-10-23,
 * it had no rules configured). Until now the minimum was only enforced in the browser
 * by elementor-bulk-calculator.js. This class applies the same rule on the server:
 *
 *   minimum = max(
 *       meta _infoplus_min_quantity (variation, else parent product),
 *       meta mindestanzahl (parent product, the ACF field the calculator reads),
 *       lowest "from" of the Staffelpreise tiers for the variation SKU (BulkPrices),
 *       1
 *   )
 *
 * Checked when a line is added (theme buy form and every other request that runs
 * woocommerce_add_to_cart_validation, plus the old admin-ajax handler), when the
 * quantity is changed in the Warenkorb, and on the cart and checkout pages
 * (woocommerce_check_cart_items, which blocks the order while a line is too small).
 *
 * The Warenkorb and Kasse pages on staging are the WooCommerce blocks (Store API).
 * For them the minimum is also passed to woocommerce_store_api_product_quantity_minimum,
 * so the quantity selector of the Cart block cannot go below it.
 *
 * Lines that came from the online editor (cart item data project_id, set by the
 * InfoplusAPI route cart/updateCart) are skipped: the editor prices and counts its
 * own projects.
 *
 * Filter infoplus_suite_min_quantity (int $min, int $product_id, int $variation_id)
 * can change the minimum, for example per customer role.
 *
 * @package Infoplus\Suite\Modules\WooSingleProductAddons
 */

namespace Infoplus\Suite\Modules\WooSingleProductAddons;

use Infoplus\Suite\Modules\ProductDetailSuite\BulkPrices;

if (!defined('ABSPATH')) exit;

final class MinQuantity
{
    public function register(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAdd'], 25, 5);
        add_filter('woocommerce_update_cart_validation', [$this, 'validateUpdate'], 10, 4);
        add_action('woocommerce_check_cart_items', [$this, 'checkCart']);
        add_filter('woocommerce_store_api_product_quantity_minimum', [$this, 'storeApiMinimum'], 10, 3);
    }

    /** Store API (Cart and Checkout blocks): lowest quantity the quantity selector allows. */
    public function storeApiMinimum($value, $product, $cart_item = null)
    {
        if (!$product instanceof \WC_Product || (is_array($cart_item) && self::isEditorLine($cart_item))) {
            return $value;
        }
        $variation_id = $product->is_type('variation') ? (int) $product->get_id() : 0;
        $product_id   = $variation_id ? (int) $product->get_parent_id() : (int) $product->get_id();
        return max((int) $value, self::forProduct($product_id, $variation_id));
    }

    /** Minimum quantity for one product or variation, at least 1. */
    public static function forProduct(int $product_id, int $variation_id = 0): int
    {
        $min = 1;

        $own = $variation_id ? (int) get_post_meta($variation_id, '_infoplus_min_quantity', true) : 0;
        if ($own < 1) {
            $own = (int) get_post_meta($product_id, '_infoplus_min_quantity', true);
        }
        $min = max($min, $own);
        $min = max($min, (int) get_post_meta($product_id, 'mindestanzahl', true));

        $sku_product = wc_get_product($variation_id ?: $product_id);
        if ($sku_product) {
            $sku = (string) $sku_product->get_sku();
            if ($sku !== '' && class_exists(BulkPrices::class)) {
                $min = max($min, BulkPrices::lowestFrom($sku));
            }
        }

        return max(1, (int) apply_filters('infoplus_suite_min_quantity', $min, $product_id, $variation_id));
    }

    /** True for a Warenkorb line that the online editor added (InfoplusAPI cart/updateCart). */
    public static function isEditorLine(array $item): bool
    {
        return !empty($item['project_id']);
    }

    /** Quantity of shop lines (not editor lines) of this variation already in the Warenkorb. */
    private function quantityInCart(int $product_id, int $variation_id, string $skip_key = ''): int
    {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }
        $qty = 0;
        foreach (WC()->cart->get_cart() as $key => $item) {
            if ($key === $skip_key || self::isEditorLine($item)) {
                continue;
            }
            if ((int) $item['product_id'] === $product_id && (int) ($item['variation_id'] ?? 0) === $variation_id) {
                $qty += (int) $item['quantity'];
            }
        }
        return $qty;
    }

    private static function notice(int $product_id, int $variation_id, int $min): string
    {
        $product = wc_get_product($variation_id ?: $product_id);
        $name = $product ? wp_strip_all_tags($product->get_name()) : '';
        return sprintf(
            /* translators: 1: product name, 2: minimum quantity */
            __('Die Mindestbestellmenge für „%1$s“ beträgt %2$d Stück.', 'infoplus-suite'),
            $name,
            $min
        );
    }

    /** woocommerce_add_to_cart_validation: the new line plus what is already in the Warenkorb. */
    public function validateAdd($passed, $product_id, $quantity, $variation_id = 0, $variations = [])
    {
        if (!$passed) {
            return $passed;
        }
        $product_id   = (int) $product_id;
        $variation_id = (int) $variation_id;
        $min = self::forProduct($product_id, $variation_id);
        if ($min <= 1) {
            return $passed;
        }
        $total = (int) $quantity + $this->quantityInCart($product_id, $variation_id);
        if ($total < $min) {
            wc_add_notice(self::notice($product_id, $variation_id, $min), 'error');
            return false;
        }
        return $passed;
    }

    /** woocommerce_update_cart_validation: quantity changed in the Warenkorb (0 removes the line). */
    public function validateUpdate($passed, $cart_item_key, $values, $quantity)
    {
        if (!$passed || (int) $quantity === 0 || !is_array($values) || self::isEditorLine($values)) {
            return $passed;
        }
        $product_id   = (int) ($values['product_id'] ?? 0);
        $variation_id = (int) ($values['variation_id'] ?? 0);
        $min = self::forProduct($product_id, $variation_id);
        $total = (int) $quantity + $this->quantityInCart($product_id, $variation_id, (string) $cart_item_key);
        if ($total < $min) {
            wc_add_notice(self::notice($product_id, $variation_id, $min), 'error');
            return false;
        }
        return $passed;
    }

    /** woocommerce_check_cart_items: runs on the cart and checkout pages and before an order is placed. */
    public function checkCart(): void
    {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }
        $seen = [];
        foreach (WC()->cart->get_cart() as $item) {
            if (self::isEditorLine($item)) {
                continue;
            }
            $product_id   = (int) $item['product_id'];
            $variation_id = (int) ($item['variation_id'] ?? 0);
            $pair = $product_id . ':' . $variation_id;
            if (isset($seen[$pair])) {
                continue;
            }
            $seen[$pair] = true;
            $min = self::forProduct($product_id, $variation_id);
            if ($min > 1 && $this->quantityInCart($product_id, $variation_id) < $min) {
                wc_add_notice(self::notice($product_id, $variation_id, $min), 'error');
            }
        }
    }
}
