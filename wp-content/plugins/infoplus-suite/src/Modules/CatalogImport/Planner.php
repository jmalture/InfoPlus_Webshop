<?php
namespace Infoplus\Suite\Modules\CatalogImport;

if (!defined('ABSPATH')) exit;

/**
 * Dry run: compares the CSV with the site and writes down every change the import would make.
 * Reads only — nothing on the site changes here.
 */
final class Planner
{
    /** @return array{plan: ?array, errors: string[]} */
    public static function build(array $rows): array
    {
        global $wpdb;
        $errors = [];

        // ── Parent products ──────────────────────────────────────────────
        $parents = [];
        foreach ($rows as $row) {
            $pid = $row['parent_id'];
            if (!isset($parents[$pid])) {
                $product = wc_get_product($pid);
                if (!$product || !$product->is_type('variable')) {
                    $errors[] = "Produkt {$pid} ({$row['parent_title']}) existiert nicht oder ist kein variables Produkt.";
                    $parents[$pid] = null;
                    continue;
                }
                $parents[$pid] = [
                    'id'         => $pid,
                    'title'      => $product->get_name(),
                    'status'     => $product->get_status(),
                    'attributes' => $row['attributes'],
                    'terms'      => [],
                    'rows'       => 0,
                ];
            }
            if ($parents[$pid] === null) {
                continue;
            }
            if ($parents[$pid]['attributes'] !== $row['attributes']) {
                $errors[] = "{$row['sku']}: andere Attributliste als der Rest von Produkt {$pid}.";
            }
            foreach ($row['terms'] as $tax => $name) {
                $parents[$pid]['terms'][$tax][$name] = true;
            }
            $parents[$pid]['rows']++;
        }
        if ($errors) {
            return ['plan' => null, 'errors' => $errors];
        }
        $parentIds = array_keys($parents);

        // ── Existing variations of those products (with and without SKU) ──
        $in = implode(',', array_map('intval', $parentIds));
        $existing = $wpdb->get_results(
            "SELECT p.ID, p.post_parent, COALESCE(m.meta_value, '') AS sku
               FROM {$wpdb->posts} p
               LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sku'
              WHERE p.post_type = 'product_variation' AND p.post_parent IN ({$in})",
            ARRAY_A
        );
        $bySku = [];
        $managedSkus = [];
        foreach ($existing as $e) {
            if ($e['sku'] !== '') {
                $bySku[$e['sku']] = $e;
                $managedSkus[$e['sku']] = true;
            }
        }

        // SKUs in the CSV that live on some other product
        $csvSkus = array_column($rows, 'sku');
        $foreign = [];
        foreach (array_chunk($csvSkus, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%s'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $found = $wpdb->get_results($wpdb->prepare(
                "SELECT m.meta_value AS sku, p.ID, p.post_type, p.post_parent
                   FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
                  WHERE m.meta_key = '_sku' AND m.meta_value IN ({$placeholders})",
                $chunk
            ), ARRAY_A);
            foreach ($found as $f) {
                if (isset($bySku[$f['sku']]) && (int) $bySku[$f['sku']]['ID'] === (int) $f['ID']) {
                    continue;
                }
                $foreign[$f['sku']] = $f;
            }
        }

        $upserts = [];
        $counts = ['create' => 0, 'update' => 0, 'move' => 0];
        foreach ($rows as $row) {
            $sku = $row['sku'];
            $managedSkus[$sku] = true;
            $variationId = 0;
            if (isset($bySku[$sku])) {
                $variationId = (int) $bySku[$sku]['ID'];
                $action = ((int) $bySku[$sku]['post_parent'] === $row['parent_id']) ? 'update' : 'move';
            } elseif (isset($foreign[$sku])) {
                $f = $foreign[$sku];
                if ($f['post_type'] !== 'product_variation') {
                    $errors[] = "{$sku}: SKU gehört bereits zu Produkt {$f['ID']} ({$f['post_type']}).";
                    continue;
                }
                $variationId = (int) $f['ID'];
                $action = 'move';
            } else {
                $action = 'create';
            }
            $counts[$action]++;
            $upserts[] = ['action' => $action, 'variation_id' => $variationId] + $row;
        }

        $deletes = [];
        $csvSkuSet = array_fill_keys($csvSkus, true);
        foreach ($existing as $e) {
            if ($e['sku'] === '' || !isset($csvSkuSet[$e['sku']])) {
                $deletes[] = ['id' => (int) $e['ID'], 'sku' => $e['sku'], 'parent_id' => (int) $e['post_parent']];
            }
        }

        // ── Attribute taxonomies and terms ────────────────────────────────
        $taxonomies = [];
        $newTerms = [];
        foreach ($parents as $p) {
            foreach ($p['terms'] as $tax => $names) {
                $taxonomies[$tax] = true;
                $index = TermIndex::load($tax);
                foreach (array_keys($names) as $name) {
                    if ($index->find($name) === null) {
                        $newTerms[$tax][$name] = true;
                    }
                }
            }
        }
        $newTaxonomies = [];
        foreach (array_keys($taxonomies) as $tax) {
            if (!wc_attribute_taxonomy_id_by_name($tax)) {
                $newTaxonomies[] = $tax;
            }
        }

        // ── Price rules ───────────────────────────────────────────────────
        $table = $wpdb->prefix . 'wdp_rules';
        $rulesToDelete = [];
        $rulesKept = [];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            $errors[] = 'Tabelle ' . $table . ' fehlt – ist "Advanced Dynamic Pricing" aktiv?';
        } else {
            $allSkus = self::allSkusOnSite();
            $rules = $wpdb->get_results("SELECT id, title, type, filters, conditions, role_discounts FROM {$table} WHERE deleted = 0", ARRAY_A);
            foreach ($rules as $rule) {
                if ($rule['type'] !== 'single_item') {
                    $rulesKept[] = ['id' => (int) $rule['id'], 'title' => $rule['title']];
                    continue;
                }
                // SKUs that exist nowhere on the site count as catalog SKUs (stale rule entries)
                $scope = $managedSkus;
                $filters = @unserialize((string) $rule['filters']);
                foreach ((array) $filters as $f) {
                    foreach ((array) ($f['value'] ?? []) as $v) {
                        if (($f['type'] ?? '') === 'product_sku' && !isset($allSkus[(string) $v])) {
                            $scope[(string) $v] = true;
                        }
                    }
                }
                if (PriceRules::isReplaceable($rule, $scope)) {
                    $rulesToDelete[] = ['id' => (int) $rule['id'], 'title' => $rule['title']];
                } else {
                    $rulesKept[] = ['id' => (int) $rule['id'], 'title' => $rule['title']];
                }
            }
        }
        $ruleGroups = PriceRules::group($rows);

        if ($errors) {
            return ['plan' => null, 'errors' => $errors];
        }

        $deletedByParent = [];
        foreach ($deletes as $d) {
            $deletedByParent[$d['parent_id']] = ($deletedByParent[$d['parent_id']] ?? 0) + 1;
        }
        foreach ($parents as $pid => &$p) {
            $p['deletes'] = $deletedByParent[$pid] ?? 0;
            $p['terms'] = array_map('array_keys', $p['terms']);
        }
        unset($p);

        $plan = [
            'created'        => current_time('mysql'),
            'parents'        => $parents,
            'new_taxonomies' => $newTaxonomies,
            'new_terms'      => array_map('array_keys', $newTerms),
            'upserts'        => $upserts,
            'deletes'        => $deletes,
            'rules_delete'   => $rulesToDelete,
            'rules_kept'     => $rulesKept,
            'rule_groups'    => $ruleGroups,
            'summary'        => [
                'rows'              => count($rows),
                'products'          => count($parents),
                'create'            => $counts['create'],
                'update'            => $counts['update'],
                'move'              => $counts['move'],
                'delete'            => count($deletes),
                'delete_without_sku'=> count(array_filter($deletes, fn($d) => $d['sku'] === '')),
                'new_taxonomies'    => count($newTaxonomies),
                'new_terms'         => array_sum(array_map('count', $newTerms)),
                'rules_delete'      => count($rulesToDelete),
                'rules_create'      => count($ruleGroups),
                'rules_kept'        => count($rulesKept),
                'prices_include_tax'=> get_option('woocommerce_prices_include_tax'),
            ],
        ];

        return ['plan' => $plan, 'errors' => []];
    }

    /** sku => true for every SKU on the site. */
    private static function allSkusOnSite(): array
    {
        global $wpdb;
        $skus = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value <> ''");
        return array_fill_keys(array_map('strval', $skus), true);
    }
}
