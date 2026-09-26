<?php
namespace Infoplus\Suite\Modules\CatalogImport;

if (!defined('ABSPATH')) exit;

/**
 * Applies a plan from Planner in small steps, one per AJAX request, so shared hosting
 * never hits a timeout. Progress is kept in an option, so a closed tab can resume.
 */
final class Runner
{
    public const PHASES = ['attributes', 'terms', 'parents', 'variations', 'deletes', 'rules', 'finalize'];
    private const BATCH = ['variations' => 25, 'deletes' => 50, 'rules' => 100];

    private array $plan;
    private array $state;

    public function __construct(array $plan, array $state)
    {
        $this->plan = $plan;
        $this->state = $state + ['phase' => 0, 'cursor' => 0, 'done' => false, 'log' => [], 'errors' => [], 'counts' => []];
    }

    public function state(): array
    {
        return $this->state;
    }

    /** Runs one step and returns the updated state. */
    public function step(): array
    {
        if ($this->state['done']) {
            return $this->state;
        }
        wp_raise_memory_limit('admin');
        if (function_exists('wc_set_time_limit')) {
            wc_set_time_limit(120);
        }
        // Keep product saves from firing heavy cache purges per variation
        wp_defer_term_counting(true);

        $phase = self::PHASES[$this->state['phase']];
        $finished = $this->{'run' . ucfirst($phase)}();

        wp_defer_term_counting(false);

        if ($finished) {
            $this->log("Schritt „{$phase}“ abgeschlossen.");
            $this->state['phase']++;
            $this->state['cursor'] = 0;
            if ($this->state['phase'] >= count(self::PHASES)) {
                $this->state['done'] = true;
                $this->log('Import abgeschlossen.');
            }
        }
        return $this->state;
    }

    public function progress(): array
    {
        $total = [
            'attributes' => 1, 'terms' => 1, 'parents' => 1,
            'variations' => count($this->plan['upserts']),
            'deletes'    => count($this->plan['deletes']),
            'rules'      => count($this->plan['rules_delete']) + count($this->plan['rule_groups']),
            'finalize'   => 1,
        ];
        $all = array_sum($total);
        $done = 0;
        foreach (self::PHASES as $i => $p) {
            if ($i < $this->state['phase']) {
                $done += $total[$p];
            } elseif ($i === $this->state['phase']) {
                $done += min($this->state['cursor'], $total[$p]);
            }
        }
        return [
            'percent' => $this->state['done'] ? 100 : (int) floor(100 * $done / max(1, $all)),
            'phase'   => self::PHASES[min($this->state['phase'], count(self::PHASES) - 1)],
        ];
    }

    // ── Phases ───────────────────────────────────────────────────────────

    private function runAttributes(): bool
    {
        foreach ($this->plan['new_taxonomies'] as $taxonomy) {
            if (wc_attribute_taxonomy_id_by_name($taxonomy)) {
                continue;
            }
            $slug = preg_replace('/^pa_/', '', $taxonomy);
            $id = wc_create_attribute(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'select', 'has_archives' => false]);
            if (is_wp_error($id)) {
                throw new \RuntimeException("Attribut {$taxonomy}: " . $id->get_error_message());
            }
            $this->ensureTaxonomy($taxonomy);
            $this->log("Attribut {$taxonomy} angelegt.");
        }
        return true;
    }

    private function runTerms(): bool
    {
        $created = 0;
        foreach ($this->plan['new_terms'] as $taxonomy => $names) {
            $this->ensureTaxonomy($taxonomy);
            $index = TermIndex::load($taxonomy);
            foreach ($names as $name) {
                if ($index->find($name) === null) {
                    $index->findOrCreate($name);
                    $created++;
                }
            }
        }
        $this->count('terms_created', $created);
        $this->log("{$created} Attributwerte angelegt.");
        return true;
    }

    private function runParents(): bool
    {
        foreach ($this->plan['parents'] as $pid => $p) {
            $product = wc_get_product((int) $pid);
            if (!$product || !$product->is_type('variable')) {
                throw new \RuntimeException("Produkt {$pid} ist kein variables Produkt mehr.");
            }
            $attributes = [];
            $position = 0;
            foreach ($p['attributes'] as $taxonomy) {
                $this->ensureTaxonomy($taxonomy);
                $index = TermIndex::load($taxonomy);
                $termIds = [];
                foreach ($p['terms'][$taxonomy] ?? [] as $name) {
                    $termIds[] = $index->findOrCreate($name)->term_id;
                }
                $attr = new \WC_Product_Attribute();
                $attr->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
                $attr->set_name($taxonomy);
                $attr->set_options($termIds);
                $attr->set_position($position++);
                $attr->set_visible(true);
                $attr->set_variation(true);
                $attributes[$taxonomy] = $attr;
            }
            // Keep informational (non-variation) attributes the editors added by hand
            foreach ($product->get_attributes() as $key => $existing) {
                if (!$existing->get_variation() && !isset($attributes[$key])) {
                    $existing->set_position($position++);
                    $attributes[$key] = $existing;
                }
            }
            $product->set_attributes($attributes);
            $product->save();
        }
        $this->log(count($this->plan['parents']) . ' Produkte: Attribute gesetzt.');
        return true;
    }

    private function runVariations(): bool
    {
        $slice = array_slice($this->plan['upserts'], $this->state['cursor'], self::BATCH['variations']);
        foreach ($slice as $u) {
            try {
                $this->upsert($u);
                $this->count($u['action']);
            } catch (\Throwable $e) {
                $this->error("{$u['sku']}: " . $e->getMessage());
            }
        }
        $this->state['cursor'] += count($slice);
        return $this->state['cursor'] >= count($this->plan['upserts']);
    }

    private function upsert(array $u): void
    {
        $variation = $u['variation_id'] ? wc_get_product($u['variation_id']) : null;
        if (!$variation instanceof \WC_Product_Variation) {
            $variation = new \WC_Product_Variation();
            $variation->set_sku($u['sku']);
        }
        $variation->set_parent_id((int) $u['parent_id']);

        $attributes = [];
        foreach ($u['attributes'] as $taxonomy) {
            $attributes[$taxonomy] = TermIndex::load($taxonomy)->findOrCreate($u['terms'][$taxonomy])->slug;
        }
        $variation->set_attributes($attributes);

        ksort($u['tiers']);
        $variation->set_regular_price((string) reset($u['tiers']));
        $variation->set_sale_price('');
        if (is_numeric($u['weight'])) {
            $variation->set_weight((string) $u['weight']);
        }
        $variation->set_manage_stock(false);
        $variation->set_stock_status('instock');
        $variation->set_status('publish');
        $variation->save();
    }

    private function runDeletes(): bool
    {
        $slice = array_slice($this->plan['deletes'], $this->state['cursor'], self::BATCH['deletes']);
        foreach ($slice as $d) {
            $variation = wc_get_product($d['id']);
            if ($variation instanceof \WC_Product_Variation) {
                $variation->delete(true);
                $this->count('deleted');
            }
        }
        $this->state['cursor'] += count($slice);
        return $this->state['cursor'] >= count($this->plan['deletes']);
    }

    private function runRules(): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wdp_rules';
        $toDelete = $this->plan['rules_delete'];
        $groups = $this->plan['rule_groups'];
        $cursor = $this->state['cursor'];
        $batch = self::BATCH['rules'];

        if ($cursor < count($toDelete)) {
            foreach (array_slice($toDelete, $cursor, $batch) as $r) {
                $wpdb->delete($table, ['id' => $r['id'], 'type' => 'single_item']);
                $this->count('rules_deleted');
            }
            $this->state['cursor'] = min($cursor + $batch, count($toDelete));
            return false;
        }

        $offset = $cursor - count($toDelete);
        $now = current_time('mysql');
        foreach (array_slice($groups, $offset, $batch) as $g) {
            if ($wpdb->insert($table, PriceRules::row($g, $now)) === false) {
                $this->error('Preisregel für ' . $g['skus'][0] . ': ' . $wpdb->last_error);
            } else {
                $this->count('rules_created');
            }
        }
        $this->state['cursor'] = count($toDelete) + min($offset + $batch, count($groups));
        return $this->state['cursor'] >= count($toDelete) + count($groups);
    }

    private function runFinalize(): bool
    {
        global $wpdb;

        // Prices in the CSV are net; the shop shows them incl. VAT
        update_option('woocommerce_prices_include_tax', 'no');
        update_option('woocommerce_tax_display_shop', 'incl');
        update_option('woocommerce_tax_display_cart', 'incl');

        foreach (array_keys($this->plan['parents']) as $pid) {
            $this->fixModelDefaults((int) $pid);
            \WC_Product_Variable::sync((int) $pid);
            wc_delete_product_transients((int) $pid);
        }

        // Bulk price lookups cached by ProductDetailSuite
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_infoplus\\_prices\\_%' OR option_name LIKE '\\_transient\\_timeout\\_infoplus\\_prices\\_%'");
        wp_cache_flush();
        do_action('litespeed_purge_all');

        $this->log('Mehrwertsteuer: Preise werden jetzt netto erfasst und inkl. MwSt. angezeigt.');
        return true;
    }

    /**
     * Per-model default variations (ProductDetailSuite) point at variation IDs.
     * Replace any that were deleted or no longer match their model, and add missing models.
     */
    private function fixModelDefaults(int $pid): void
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, m.meta_value AS model
               FROM {$wpdb->posts} p
               JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'attribute_pa_model'
              WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND p.post_status = 'publish'
              ORDER BY p.menu_order, p.ID",
            $pid
        ), ARRAY_A);
        $firstByModel = [];
        $modelOf = [];
        foreach ($rows as $r) {
            $firstByModel[$r['model']] ??= (int) $r['ID'];
            $modelOf[(int) $r['ID']] = $r['model'];
        }

        $defaults = json_decode((string) get_post_meta($pid, '_infoplus_model_default_variations', true), true) ?: [];
        $fixed = [];
        foreach ($firstByModel as $model => $first) {
            $current = (int) ($defaults[$model] ?? 0);
            $fixed[$model] = ($current && ($modelOf[$current] ?? null) === $model) ? $current : $first;
        }
        if ($fixed) {
            update_post_meta($pid, '_infoplus_model_default_variations', wp_json_encode($fixed));
        } else {
            delete_post_meta($pid, '_infoplus_model_default_variations');
        }

        $legacy = (int) get_post_meta($pid, '_infoplus_default_variation_id', true);
        if ($legacy && !isset($modelOf[$legacy])) {
            delete_post_meta($pid, '_infoplus_default_variation_id');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Attribute taxonomies are registered on init; a new one needs registering for this request. */
    private function ensureTaxonomy(string $taxonomy): void
    {
        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, ['product', 'product_variation'], ['hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false]);
            TermIndex::reset();
        }
    }

    private function count(string $key, int $n = 1): void
    {
        $this->state['counts'][$key] = ($this->state['counts'][$key] ?? 0) + $n;
    }

    private function log(string $message): void
    {
        $this->state['log'][] = current_time('H:i:s') . ' ' . $message;
        $this->state['log'] = array_slice($this->state['log'], -200);
    }

    private function error(string $message): void
    {
        $this->state['errors'][] = $message;
        $this->log('Fehler: ' . $message);
    }
}
