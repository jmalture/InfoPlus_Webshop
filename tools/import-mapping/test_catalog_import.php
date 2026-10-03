<?php
// CLI tests for the WordPress-free parts of CatalogImport.
require __DIR__ . '/../_repo/src/Modules/CatalogImport/CsvReader.php';
require __DIR__ . '/../_repo/src/Modules/CatalogImport/PriceRules.php';
use Infoplus\Suite\Modules\CatalogImport\{CsvReader, PriceRules};
$fail = 0;
function check($cond, $msg) { global $fail; echo ($cond ? "ok   " : "FAIL ") . $msg . "\n"; if (!$cond) $fail++; }

$csv = __DIR__ . '/import.csv';
$r = CsvReader::read($csv);
check(!$r['errors'], 'real import.csv parses without errors (' . count($r['errors']) . ')');
check(count($r['rows']) === 2322, 'real import.csv has 2322 rows (' . count($r['rows']) . ')');
$byS = array_column($r['rows'], null, 'sku');
$a = $byS['NIA401C12-D1-A-12D'] ?? null;
check($a && $a['parent_id'] === 3338, 'NIA401C12-D1-A-12D -> Atlantis 3338');
check($a && $a['terms']['pa_fenster'] === '12 mm durchgehend', '… Fenster 12 mm durchgehend');
check($a && $a['terms']['pa_model'] === 'Logo gedruckt / Name zur Selbstbeschriftung', '… Model Classic');
check($a && !isset($a['tiers'][1]) && $a['tiers'][10] > 0, '… first tier is 10 pcs');
check($a && array_keys($a['terms']) === $a['attributes'], '… one term per attribute, in order');

// ranges
$ranges = PriceRules::ranges([10 => 9.7, 20 => 7.3, 500 => 4.04, 50 => 6.95]);
check($ranges[0] == ['from' => 10.0, 'to' => 19.0, 'value' => 9.7], 'range 10-19');
check($ranges[2] == ['from' => 50.0, 'to' => 499.0, 'value' => 6.95], 'range 50-499 (sorted, gap filled)');
check(end($ranges)['to'] == 99999.0, 'last range open-ended 99999');

// grouping covers every SKU exactly once
$groups = PriceRules::group($r['rows']);
$all = array_merge(...array_column($groups, 'skus'));
check(count($all) === 2322 && count(array_unique($all)) === 2322, 'every SKU in exactly one price rule (' . count($groups) . ' rules)');

// rule row round-trips
$row = PriceRules::row($groups[0], '2026-09-26 12:00:00');
$f = unserialize($row['filters']); $b = unserialize($row['bulk_adjustments']);
check($f[0]['type'] === 'product_sku' && $f[0]['value'] === $groups[0]['skus'], 'rule filters = SKU list');
check($b['discount_type'] === 'price__fixed' && $b['ranges'] === $groups[0]['ranges'], 'rule bulk = fixed price ranges');
check($row['priority'] === 0 && $row['type'] === 'single_item' && strpos($row['title'], '[Katalog] ') === 0, 'priority 0, single_item, [Katalog] title');

// replaceable detection
$managed = ['A' => true, 'B' => true];
$mk = fn($type, $skus, $extra = []) => $extra + ['type' => $type, 'title' => 'x', 'filters' => serialize([['type' => 'product_sku', 'value' => $skus]]), 'conditions' => serialize([]), 'role_discounts' => serialize([])];
check(PriceRules::isReplaceable($mk('single_item', ['A', 'B']), $managed), 'SKU rule with catalog SKUs -> replace');
check(!PriceRules::isReplaceable($mk('single_item', ['A', 'Z']), $managed), 'SKU rule with foreign SKU -> keep');
check(!PriceRules::isReplaceable($mk('common', ['A']), $managed), 'common rule -> keep');
check(!PriceRules::isReplaceable($mk('single_item', ['A'], ['role_discounts' => serialize(['rows' => [1]])]), $managed), 'rule with role discount -> keep');
$handler = ['type' => 'common', 'title' => 'Händlerrabatt', 'filters' => 'a:0:{}', 'conditions' => 'a:0:{}', 'role_discounts' => 'a:1:{s:4:"rows";a:0:{}}'];
check(!PriceRules::isReplaceable($handler, $managed), 'Händlerrabatt -> keep');

// bad CSV rows are reported, not imported
$tmp = tempnam(sys_get_temp_dir(), 'ipci');
file_put_contents($tmp, "sku;parent_id;attributes;attr_model;net_1;net_5;net_10;net_20;net_50;net_100;net_250;net_500\n" .
    "X1;5;pa_model;Druck;1,5;;;;;;;\nX1;5;pa_model;Druck;2;;;;;;;\nX2;5;pa_model;;2;;;;;;;\nX3;5;pa_model;Druck;;;;;;;;\n");
$bad = CsvReader::read($tmp);
check(count($bad['rows']) === 1 && $bad['rows'][0]['tiers'] === [1 => 1.5], 'decimal comma accepted');
check(count($bad['errors']) === 3, 'duplicate SKU, missing term, missing price reported (' . implode(' | ', $bad['errors']) . ')');
echo $fail ? "\n{$fail} FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
