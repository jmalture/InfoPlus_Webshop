<?php
namespace Infoplus\Suite\Modules\CatalogImport;

/**
 * Reads the catalog import CSV produced by import-mapping/build_import.py.
 *
 * Format: UTF-8, ";" separated, header row. Required columns:
 *   sku, parent_id, attributes (comma list of pa_* taxonomies in dropdown order),
 *   attr_<name> for every taxonomy listed in "attributes", net_1 … net_500.
 *
 * Pure PHP (no WordPress calls) so it can be unit tested from the CLI.
 */
final class CsvReader
{
    public const TIERS = [1, 5, 10, 20, 50, 100, 250, 500];

    /**
     * @return array{rows: array<int, array>, errors: string[]}
     *   Each row: sku, parent_id, parent_title, attributes (string[]), terms (taxonomy => name),
     *   weight (string), tiers (qty => float), description.
     */
    public static function read(string $path): array
    {
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return ['rows' => [], 'errors' => ['Datei konnte nicht gelesen werden.']];
        }

        $header = fgetcsv($fh, 0, ';', '"', '');
        if (!$header) {
            fclose($fh);
            return ['rows' => [], 'errors' => ['Die Datei ist leer.']];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip BOM

        $missing = array_diff(
            array_merge(['sku', 'parent_id', 'attributes'], array_map(fn($t) => 'net_' . $t, self::TIERS)),
            $header
        );
        if ($missing) {
            fclose($fh);
            return ['rows' => [], 'errors' => ['Fehlende Spalten: ' . implode(', ', $missing)]];
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;
        while (($cells = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || $cells === ['']) {
                continue;
            }
            if (count($cells) !== count($header)) {
                $errors[] = "Zeile {$line}: " . count($cells) . ' Spalten statt ' . count($header);
                continue;
            }
            $r = array_combine($header, $cells);
            $row = self::parseRow($r, $line, $errors);
            if ($row === null) {
                continue;
            }
            if (isset($seen[$row['sku']])) {
                $errors[] = "Zeile {$line}: SKU {$row['sku']} doppelt (auch Zeile {$seen[$row['sku']]})";
                continue;
            }
            $seen[$row['sku']] = $line;
            $rows[] = $row;
        }
        fclose($fh);

        return ['rows' => $rows, 'errors' => $errors];
    }

    private static function parseRow(array $r, int $line, array &$errors): ?array
    {
        $sku = trim($r['sku']);
        $parent = (int) $r['parent_id'];
        if ($sku === '' || $parent <= 0) {
            $errors[] = "Zeile {$line}: SKU oder parent_id fehlt";
            return null;
        }

        $attributes = array_values(array_filter(array_map('trim', explode(',', $r['attributes']))));
        $terms = [];
        foreach ($attributes as $taxonomy) {
            $col = 'attr_' . preg_replace('/^pa_/', '', $taxonomy);
            $value = trim($r[$col] ?? '');
            if ($value === '') {
                $errors[] = "Zeile {$line}: {$sku} hat keinen Wert für {$taxonomy}";
                return null;
            }
            $terms[$taxonomy] = $value;
        }

        $tiers = [];
        foreach (self::TIERS as $qty) {
            $raw = str_replace(',', '.', trim($r['net_' . $qty]));
            if ($raw === '') {
                continue;
            }
            if (!is_numeric($raw) || (float) $raw <= 0) {
                $errors[] = "Zeile {$line}: {$sku} ungültiger Preis ab {$qty} Stk. ({$r['net_' . $qty]})";
                return null;
            }
            $tiers[$qty] = round((float) $raw, 4);
        }
        if (!$tiers) {
            $errors[] = "Zeile {$line}: {$sku} hat keinen Preis";
            return null;
        }

        return [
            'sku'          => $sku,
            'parent_id'    => $parent,
            'parent_title' => trim($r['parent_title'] ?? ''),
            'attributes'   => $attributes,
            'terms'        => $terms,
            'weight'       => trim($r['weight_kg'] ?? ''),
            'tiers'        => $tiers,
            'description'  => trim($r['description'] ?? ''),
        ];
    }
}
