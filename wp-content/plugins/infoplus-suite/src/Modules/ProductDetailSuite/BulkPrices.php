<?php
/**
 * Bulk price tiers (Staffelpreise) from the Advanced Dynamic Pricing rules table.
 *
 * Moved out of ProductDetailSuite\Module (interceptBulkPricesRoute) so the REST route
 * GET /infoplus/v1/products/prices and the server-side minimum quantity check read
 * the same tiers from the same cache. Behaviour and cache key are unchanged.
 *
 * @package Infoplus\Suite\Modules\ProductDetailSuite
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite;

if (!defined('ABSPATH')) exit;

final class BulkPrices
{
    /**
     * Tiers for one SKU as [{ from, to, value }], values in display prices.
     * Cached 24 h per SKU in the transient infoplus_prices_{md5(sku)} (no invalidation, R-14).
     */
    public static function forSku(string $sku): array
    {
        if ($sku === '') {
            return [];
        }
        $cache_key = 'infoplus_prices_' . md5($sku);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
        $prices = self::query($sku);
        set_transient($cache_key, $prices, DAY_IN_SECONDS);
        return $prices;
    }

    /**
     * Lowest "from" over all tiers, 0 when the SKU has no tiers. The product page uses
     * the same value as its minimum (getMinBulkQty in
     * WooSingleProductAddons/Assets/elementor-bulk-calculator.js).
     */
    public static function lowestFrom(string $sku): int
    {
        $froms = [];
        foreach (self::forSku($sku) as $tier) {
            $from = (string) ($tier['from'] ?? '');
            if ($from !== '' && is_numeric($from)) {
                $froms[] = (int) $from;
            }
        }
        return $froms ? max(0, min($froms)) : 0;
    }

    /**
     * Query the WDP rules table for bulk price tiers matching the given SKU.
     *
     * Matches against:
     *   1. The SKU string itself  (old-style rules filtered by SKU attribute)
     *   2. The variation post ID  (new-style rules filtered by "Products")
     *   3. The parent product ID  (rules applied at parent-product level)
     *
     * Returns an array of { from, to, value } objects ordered as stored in WDP.
     * Open-ended "to" values (≥ 99 000) are normalised to an empty string so the
     * frontend can render "500+ Stück" labels correctly.
     */
    private static function query(string $sku): array
    {
        global $wpdb;

        // Resolve WooCommerce IDs from the SKU
        $variation_id = (int) wc_get_product_id_by_sku($sku);
        $parent_id    = 0;

        if ($variation_id > 0) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->get_parent_id()) {
                $parent_id = (int) $variation->get_parent_id();
            }
        }

        $table = $wpdb->prefix . 'wdp_rules';

        // Guard: table might not exist when the WDP plugin is inactive
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rules = $wpdb->get_results(
            "SELECT filters, bulk_adjustments FROM {$table} WHERE enabled = 1 AND deleted = 0 ORDER BY priority ASC, id ASC"
        );

        foreach ($rules as $rule) {
            $filters = maybe_unserialize($rule->filters);
            if (!is_array($filters)) {
                continue;
            }

            foreach ($filters as $filter) {
                $values = $filter['value'] ?? [];
                if (!is_array($values)) {
                    continue;
                }

                // Cast values to strings for reliable in_array comparison
                $str_values = array_map('strval', $values);

                $matched = in_array($sku, $str_values, true)
                    || ($variation_id > 0 && in_array((string) $variation_id, $str_values, true))
                    || ($parent_id    > 0 && in_array((string) $parent_id,    $str_values, true));

                if ($matched) {
                    return self::toDisplayPrices(
                        self::parseWdpRanges($rule->bulk_adjustments),
                        $variation_id > 0 ? wc_get_product($variation_id) : null
                    );
                }
            }
        }

        return [];
    }

    /**
     * WDP stores tier prices as entered (net when prices are entered excl. tax).
     * Convert them to what the shop shows, so the tier table and order panel match
     * the cart (incl. MwSt. when "display prices in the shop" is incl. tax).
     */
    private static function toDisplayPrices(array $ranges, $product): array
    {
        if (!$product instanceof \WC_Product || !function_exists('wc_get_price_to_display')) {
            return $ranges;
        }
        foreach ($ranges as &$range) {
            if ($range['value'] !== '' && is_numeric($range['value'])) {
                $display = wc_get_price_to_display($product, ['price' => (float) $range['value'], 'qty' => 1]);
                $range['value'] = (string) round((float) $display, wc_get_price_decimals());
            }
        }
        unset($range);
        return $ranges;
    }

    /**
     * Deserialise a WDP bulk_adjustments blob into the { from, to, value } shape
     * expected by the BulkPriceTable frontend widget.
     */
    private static function parseWdpRanges(string $raw): array
    {
        $bulk   = maybe_unserialize($raw);
        $ranges = $bulk['ranges'] ?? [];
        $result = [];

        foreach ($ranges as $range) {
            $from  = (string) ($range['from']  ?? '');
            $to    = (string) ($range['to']    ?? '');
            $value = (string) ($range['value'] ?? '');

            // Normalise open-ended upper bounds (WDP uses 99999 as "unlimited")
            if ($to !== '' && (int) $to >= 99000) {
                $to = '';
            }

            $result[] = [
                'from'  => $from,
                'to'    => $to,
                'value' => $value,
            ];
        }

        return $result;
    }
}
