<?php
/**
 * Finder: the candidate list. One candidate per product family and model
 * (pa_model term), with what the finder page needs to filter, score and show
 * it, read live from the shop:
 *
 * - option (self / logo / print) and flags from the model slug (Traits::optionFor)
 * - order type of the model (Attribute Overrides tab, editable when unset)
 * - fastenings offered by the model's variations (magnet / pin / clip)
 * - base price: the lowest variation price, as the shop displays it
 * - Staffelpreise of one variation of the model (ProductDetailSuite\BulkPrices)
 * - minimum quantity (WooSingleProductAddons\MinQuantity)
 * - production days (product meta fertigungszeit)
 * - image: first model gallery image, else the product image
 * - placeholder traits of the family (Traits::family)
 *
 * Built once and cached for 12 hours in the transient
 * infoplus_finder_{Plugin::VERSION}. Saving a product clears it.
 *
 * @package Infoplus\Suite\Modules\Finder
 */

namespace Infoplus\Suite\Modules\Finder;

use Infoplus\Suite\Plugin;
use Infoplus\Suite\Modules\ProductDetailSuite\BulkPrices;
use Infoplus\Suite\Modules\ProductDetailSuite\Fields\ProductAttributeImages;
use Infoplus\Suite\Modules\WooSingleProductAddons\MinQuantity;

if (!defined('ABSPATH')) exit;

final class Catalog
{
    public static function cacheKey(): string
    {
        return 'infoplus_finder_' . str_replace('.', '_', Plugin::VERSION);
    }

    public static function flush(): void
    {
        delete_transient(self::cacheKey());
    }

    /** ['candidates' => [...], 'options' => [...], 'meta' => [...]] */
    public static function get(): array
    {
        $cached = get_transient(self::cacheKey());
        if (is_array($cached) && isset($cached['candidates'])) {
            return $cached;
        }
        $data = self::build();
        set_transient(self::cacheKey(), $data, 12 * HOUR_IN_SECONDS);
        return $data;
    }

    private static function build(): array
    {
        global $wpdb;

        $ids = wc_get_products([
            'status' => 'publish',
            'type'   => 'variable',
            'limit'  => -1,
            'return' => 'ids',
        ]);

        $candidates = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) {
                continue;
            }
            $terms = wc_get_product_terms($pid, 'pa_model', ['fields' => 'all']);
            if (!$terms || is_wp_error($terms)) {
                continue;
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT v.ID, pr.meta_value AS price, mo.meta_value AS model, be.meta_value AS fastener, sk.meta_value AS sku
                 FROM {$wpdb->posts} v
                 LEFT JOIN {$wpdb->postmeta} pr ON pr.post_id = v.ID AND pr.meta_key = '_price'
                 LEFT JOIN {$wpdb->postmeta} mo ON mo.post_id = v.ID AND mo.meta_key = 'attribute_pa_model'
                 LEFT JOIN {$wpdb->postmeta} be ON be.post_id = v.ID AND be.meta_key = 'attribute_pa_befestigung'
                 LEFT JOIN {$wpdb->postmeta} sk ON sk.post_id = v.ID AND sk.meta_key = '_sku'
                 WHERE v.post_parent = %d AND v.post_type = 'product_variation' AND v.post_status = 'publish'",
                $pid
            ));

            $by_model = [];
            foreach ($rows as $row) {
                $slug = (string) $row->model;
                if ($slug === '') {
                    continue;
                }
                $m = $by_model[$slug] ?? ['min' => PHP_FLOAT_MAX, 'min_id' => 0, 'sku' => '', 'sku_id' => 0, 'fasteners' => [], 'count' => 0];
                $price = (float) $row->price;
                if ($price > 0 && $price < $m['min']) {
                    $m['min'] = $price;
                    $m['min_id'] = (int) $row->ID;
                    if ((string) $row->sku !== '') {
                        $m['sku'] = (string) $row->sku;
                        $m['sku_id'] = (int) $row->ID;
                    }
                }
                if ($m['sku'] === '' && (string) $row->sku !== '') {
                    $m['sku'] = (string) $row->sku;
                    $m['sku_id'] = (int) $row->ID;
                }
                if ((string) $row->fastener !== '') {
                    $m['fasteners'][(string) $row->fastener] = true;
                }
                $m['count']++;
                $by_model[$slug] = $m;
            }

            $slug_p  = $product->get_slug();
            $traits  = Traits::family($slug_p);
            $days    = (int) get_post_meta($pid, 'fertigungszeit', true);
            $family  = trim(preg_replace('/\s*Namens(schild|schilder)\s*$/u', '', $product->get_name()));
            $fallback_img = (string) wp_get_attachment_image_url((int) $product->get_image_id(), 'woocommerce_thumbnail');

            foreach ($terms as $term) {
                $m = $by_model[$term->slug] ?? null;
                if (!$m || $m['min_id'] === 0) {
                    continue;
                }
                $opt = Traits::optionFor($term->slug);

                $min_var = wc_get_product($m['min_id']);
                $base = $min_var ? (float) wc_get_price_to_display($min_var) : $m['min'];

                $tiers = [];
                if ($m['sku'] !== '' && class_exists(BulkPrices::class)) {
                    foreach (BulkPrices::forSku($m['sku']) as $t) {
                        if (!is_numeric($t['value'] ?? '') || !is_numeric($t['from'] ?? '')) {
                            continue;
                        }
                        $tiers[] = [
                            'from'  => (int) $t['from'],
                            'to'    => is_numeric($t['to'] ?? '') ? (int) $t['to'] : 0,
                            'price' => (float) $t['value'],
                        ];
                    }
                }

                $min_qty = class_exists(MinQuantity::class)
                    ? MinQuantity::forProduct($pid, $m['sku_id'] ?: $m['min_id'])
                    : 1;

                $kinds = [];
                $fastener_names = [];
                foreach (array_keys($m['fasteners']) as $f_slug) {
                    $kind = Traits::fastenerKind($f_slug);
                    if ($kind !== '') {
                        $kinds[$kind] = true;
                    }
                    $f_term = get_term_by('slug', $f_slug, 'pa_befestigung');
                    if ($f_term) {
                        $fastener_names[] = html_entity_decode($f_term->name, ENT_QUOTES, 'UTF-8');
                    }
                }

                $image = '';
                if (class_exists(ProductAttributeImages::class)) {
                    $gallery = ProductAttributeImages::getTermGalleryUrls($pid, 'pa_model', $term->slug);
                    $image = $gallery ? (string) ($gallery[0]['url'] ?? '') : '';
                    $order_type = ProductAttributeImages::getTermOrderType($pid, 'pa_model', $term->slug);
                } else {
                    $order_type = 'editable';
                }

                $candidates[] = [
                    'id'         => $pid . ':' . $term->slug,
                    'pid'        => $pid,
                    'family'     => $family,
                    'product'    => html_entity_decode($product->get_name(), ENT_QUOTES, 'UTF-8'),
                    'model'      => $term->slug,
                    'modelName'  => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
                    'label'      => $opt['label'],
                    'option'     => $opt['option'],
                    'contour'    => $opt['contour'] || !empty($traits['contour']),
                    'ownContour' => $opt['contour'],
                    'engraved'   => $opt['engraved'],
                    'series'     => $opt['series'],
                    'orderType'  => $order_type,
                    'fasteners'  => array_keys($kinds),
                    'fastenerNames' => array_values(array_unique($fastener_names)),
                    'variants'   => $m['count'],
                    'base'       => round($base, 2),
                    'tiers'      => $tiers,
                    'minQty'     => max(1, (int) $min_qty),
                    'days'       => $days > 0 ? $days : 0,
                    'image'      => $image !== '' ? $image : $fallback_img,
                    'url'        => add_query_arg('modell', $term->slug, get_permalink($pid)),
                    'traits'     => $traits,
                ];
            }
        }

        return [
            'candidates' => $candidates,
            'options'    => self::summarise($candidates),
            'meta'       => [
                'built'    => time(),
                'currency' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '€',
                'tax'      => get_option('woocommerce_tax_display_shop') === 'incl' ? 'inkl. MwSt.' : 'zzgl. MwSt.',
            ],
        ];
    }

    /** Lowest unit price of a candidate: base price or the cheapest tier. */
    public static function lowestPrice(array $c): float
    {
        $prices = [(float) $c['base']];
        foreach ($c['tiers'] as $t) {
            if ($t['price'] > 0) {
                $prices[] = (float) $t['price'];
            }
        }
        $prices = array_filter($prices, function ($p) { return $p > 0; });
        return $prices ? (float) min($prices) : 0.0;
    }

    /** Per option: label and copy (Traits::options), number of models and families, "ab" price. */
    private static function summarise(array $candidates): array
    {
        $out = [];
        foreach (Traits::options() as $key => $def) {
            $mine = array_filter($candidates, function ($c) use ($key) {
                return $c['option'] === $key && !$c['series'];
            });
            $prices = array_filter(array_map([self::class, 'lowestPrice'], $mine));
            $out[$key] = $def + [
                'models'   => count($mine),
                'families' => count(array_unique(array_column($mine, 'pid'))),
                'from'     => $prices ? round(min($prices), 2) : 0,
                'days'     => $mine ? (int) min(array_filter(array_column($mine, 'days')) ?: [0]) : 0,
            ];
        }
        return $out;
    }
}
