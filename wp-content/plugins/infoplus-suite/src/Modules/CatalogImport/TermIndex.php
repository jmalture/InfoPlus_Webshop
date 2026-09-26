<?php
namespace Infoplus\Suite\Modules\CatalogImport;

if (!defined('ABSPATH')) exit;

/**
 * Finds attribute terms by name the way a person would read them, so the import reuses
 * "60×25 mm" for "60x25 mm" and "Druck &amp; Sonderkontur" for "Druck & Sonderkontur"
 * instead of creating near-duplicates. Falls back to the slug.
 */
final class TermIndex
{
    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<string, \WP_Term> normalised name => term */
    private array $byName = [];
    /** @var array<string, \WP_Term> slug => term */
    private array $bySlug = [];

    private function __construct(private string $taxonomy) {}

    public static function load(string $taxonomy): self
    {
        if (!isset(self::$cache[$taxonomy])) {
            $index = new self($taxonomy);
            if (taxonomy_exists($taxonomy)) {
                $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                foreach (is_array($terms) ? $terms : [] as $term) {
                    $index->add($term);
                }
            }
            self::$cache[$taxonomy] = $index;
        }
        return self::$cache[$taxonomy];
    }

    public static function reset(): void
    {
        self::$cache = [];
    }

    public static function normalise(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = str_replace(['×', "\u{00A0}"], ['x', ' '], $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return mb_strtolower($name, 'UTF-8');
    }

    public function find(string $name): ?\WP_Term
    {
        return $this->byName[self::normalise($name)] ?? $this->bySlug[sanitize_title($name)] ?? null;
    }

    /** Existing term, or a newly created one. */
    public function findOrCreate(string $name): \WP_Term
    {
        $term = $this->find($name);
        if ($term) {
            return $term;
        }
        $result = wp_insert_term($name, $this->taxonomy);
        if (is_wp_error($result)) {
            // "term_exists" carries the ID of a clashing slug
            $existingId = $result->get_error_data('term_exists');
            if (!$existingId) {
                throw new \RuntimeException("Begriff \"{$name}\" in {$this->taxonomy}: " . $result->get_error_message());
            }
            $result = ['term_id' => (int) $existingId];
        }
        $term = get_term((int) $result['term_id'], $this->taxonomy);
        if (!$term instanceof \WP_Term) {
            throw new \RuntimeException("Begriff \"{$name}\" in {$this->taxonomy} nicht gefunden.");
        }
        $this->add($term);
        return $term;
    }

    private function add(\WP_Term $term): void
    {
        $this->byName[self::normalise($term->name)] = $term;
        $this->bySlug[$term->slug] = $term;
    }
}
