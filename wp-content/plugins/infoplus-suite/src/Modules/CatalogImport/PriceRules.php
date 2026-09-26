<?php
namespace Infoplus\Suite\Modules\CatalogImport;

/**
 * Builds Advanced Dynamic Pricing (WDP) "single_item" bulk rules from the CSV price tiers.
 *
 * One rule per distinct tier set, filtered by SKU list, fixed price per quantity range —
 * the same shape as the hand-made "… - Bulk" rules already on the site, so both the WDP
 * cart pricing and ProductDetailSuite::getBulkPricesBySku() read them unchanged.
 *
 * Priority stays 0 like the existing bulk rules, so role rules with a higher priority
 * number (e.g. "Händlerrabatt", priority 15) still apply on top of the tier price.
 *
 * Pure PHP apart from the optional current-time argument, so it can be tested from the CLI.
 */
final class PriceRules
{
    public const TITLE_PREFIX = '[Katalog] ';
    public const OPEN_END = 99999;

    /** qty => price  ->  [[from, to, value], …] */
    public static function ranges(array $tiers): array
    {
        ksort($tiers);
        $qtys = array_keys($tiers);
        $ranges = [];
        foreach ($qtys as $i => $from) {
            $to = isset($qtys[$i + 1]) ? $qtys[$i + 1] - 1 : self::OPEN_END;
            $ranges[] = ['from' => (float) $from, 'to' => (float) $to, 'value' => (float) $tiers[$from]];
        }
        return $ranges;
    }

    /**
     * Group SKUs that share identical ranges.
     * @param array<int, array{sku: string, tiers: array}> $rows
     * @return array<int, array{skus: string[], ranges: array}>
     */
    public static function group(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $ranges = self::ranges($row['tiers']);
            $key = json_encode($ranges);
            $groups[$key] ??= ['skus' => [], 'ranges' => $ranges];
            $groups[$key]['skus'][] = $row['sku'];
        }
        return array_values($groups);
    }

    /** Column => value for one wp_wdp_rules row. */
    public static function row(array $group, string $now): array
    {
        $skus = $group['skus'];
        $title = self::TITLE_PREFIX . $skus[0] . (count($skus) > 1 ? ' +' . (count($skus) - 1) : '');
        $filters = [[
            'qty'             => '1',
            'type'            => 'product_sku',
            'method'          => 'in_list',
            'value'           => array_values($skus),
            'product_exclude' => ['on_wc_sale' => '', 'already_affected' => '', 'backorder' => ''],
            'excludes'        => [],
        ]];
        $bulk = [
            'type'          => 'bulk',
            'qty_based'     => 'not',
            'measurement'   => 'qty',
            'discount_type' => 'price__fixed',
            'ranges'        => $group['ranges'],
            'table_message' => '',
        ];

        return [
            'deleted'                  => 0,
            'enabled'                  => 1,
            'exclusive'                => 0,
            'rule_type'                => 'common',
            'type'                     => 'single_item',
            'title'                    => $title,
            'priority'                 => 0,
            'options'                  => serialize(['apply_to' => 'appeared', 'repeat' => '-1']),
            'additional'               => serialize([
                'conditions_relationship' => '', 'date_from' => '', 'date_to' => '', 'trigger_coupon_code' => '',
                'blocks' => [], 'is_replace' => '', 'replace_name' => '',
                'is_replace_free_products_with_discount' => '', 'free_products_replace_name' => '',
                'is_replace_auto_add_products_with_discount' => '', 'auto_add_products_replace_name' => '',
                'sortable_apply_mode' => 'consistently', 'auto_add_cant_be_removed_from_cart' => false,
                'auto_add_show_as_recommended_product' => false,
            ]),
            'advertising'              => serialize(['discount_message' => '', 'discount_message_cart_item' => '', 'long_discount_message' => '', 'sale_badge' => '']),
            'conditions'               => serialize([]),
            'filters'                  => serialize($filters),
            'limits'                   => serialize([]),
            'product_adjustments'      => serialize(['type' => 'total', 'total' => ['value' => ''], 'split' => [['value' => '']], 'max_discount_sum' => '']),
            'sortable_blocks_priority' => serialize(['roles', 'bulk-adjustments']),
            'bulk_adjustments'         => serialize($bulk),
            'role_discounts'           => serialize([]),
            'cart_adjustments'         => serialize([]),
            'get_products'             => serialize(['repeat' => '-1', 'repeat_subtotal' => '', 'max_amount_for_gifts' => '']),
            'auto_add_products'        => serialize(['repeat' => '-1', 'repeat_subtotal' => '']),
            'condition_message'        => serialize([]),
            'summary'                  => $title,
            'hash'                     => md5(serialize($filters) . serialize($bulk)),
            'date'                     => $now,
        ];
    }

    /**
     * True when an existing rule is a bulk price rule for catalog SKUs only, and may be replaced.
     * Rules with conditions, role discounts, or any non-SKU filter are never touched.
     */
    public static function isReplaceable(array $rule, array $managedSkus): bool
    {
        if (($rule['type'] ?? '') !== 'single_item') {
            return false;
        }
        if (strpos((string) ($rule['title'] ?? ''), self::TITLE_PREFIX) === 0) {
            return true;
        }
        $roles = @unserialize((string) ($rule['role_discounts'] ?? ''));
        $conditions = @unserialize((string) ($rule['conditions'] ?? ''));
        if (!empty($roles) || !empty($conditions)) {
            return false;
        }
        $filters = @unserialize((string) ($rule['filters'] ?? ''));
        if (!is_array($filters) || !$filters) {
            return false;
        }
        foreach ($filters as $f) {
            if (($f['type'] ?? '') !== 'product_sku' || !is_array($f['value'] ?? null) || !$f['value']) {
                return false;
            }
            foreach ($f['value'] as $sku) {
                if (!isset($managedSkus[(string) $sku])) {
                    return false;
                }
            }
        }
        return true;
    }
}
