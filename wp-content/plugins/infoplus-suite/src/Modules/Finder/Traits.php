<?php
/**
 * Finder: what the shop data does not say about a product family.
 *
 * The catalogue stores models, fastenings, formats, prices and Staffelpreise,
 * but not the material, the look or what a family is good for. These values
 * are PLACEHOLDERS, drafted 2026-10-23 from the product titles, the product
 * descriptions and the product images on staging. Infoplus has not checked
 * them yet. Change them here, or per site with the filter
 * infoplus_suite_finder_traits (array $traits, string $product_slug).
 *
 * Keys per family (product slug):
 * - material  metal | acrylic | wood | plastic | writable
 * - look      preview look of the finder plate: alu | atlantis | classic |
 *             clear | wood | chalk | papyrus | laminate | frame | window | xstyle | plain
 * - premium   1 (budget) .. 3 (premium)
 * - robust    1 .. 3 (3 = for workshops, warehouses, daily cleaning)
 * - hygienic  1 .. 3 (3 = smooth, wipeable surface)
 * - uses      any of: staff, event, hospitality, care, industry
 * - contour   true when the family itself has a contour shape (Alucontur)
 * - eco       true for natural material
 * - claim     one sentence for the result card
 *
 * @package Infoplus\Suite\Modules\Finder
 */

namespace Infoplus\Suite\Modules\Finder;

if (!defined('ABSPATH')) exit;

final class Traits
{
    /** Placeholder traits per product slug. */
    private const FAMILIES = [
        'alucontur-namensschild' => [
            'material' => 'metal', 'look' => 'alu', 'premium' => 3, 'robust' => 3, 'hygienic' => 2,
            'uses' => ['staff', 'hospitality', 'industry'], 'contour' => true,
            'claim' => 'Aluminium, auf Wunsch in der Form Ihres Logos.',
        ],
        'aluprofil-namensschilder' => [
            'material' => 'metal', 'look' => 'alu', 'premium' => 3, 'robust' => 3, 'hygienic' => 2,
            'uses' => ['staff', 'hospitality'],
            'claim' => 'Massiver Metallguss, fein geschliffen und eloxiert.',
        ],
        'atlantis-namensschild' => [
            'material' => 'plastic', 'look' => 'atlantis', 'premium' => 2, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff', 'event', 'hospitality', 'care'],
            'claim' => 'Modern geschwungen, angenehm zu tragen, viele Varianten.',
        ],
        'bussiness-namensschilder' => [
            'material' => 'plastic', 'look' => 'classic', 'premium' => 2, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff'],
            'claim' => 'Schlichtes Schild für den Büroalltag.',
        ],
        'chalk-board-namensschilder' => [
            'material' => 'writable', 'look' => 'chalk', 'premium' => 1, 'robust' => 1, 'hygienic' => 1,
            'uses' => ['event', 'hospitality'],
            'claim' => 'Tafel-Optik für Gastronomie und Events.',
        ],
        'classic-namensschilder' => [
            'material' => 'plastic', 'look' => 'classic', 'premium' => 1, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff', 'event'],
            'claim' => 'Der bewährte Klassiker mit Sichtfenster.',
        ],
        'designstudio-namensschilder' => [
            'material' => 'plastic', 'look' => 'papyrus', 'premium' => 3, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['hospitality', 'staff'],
            'claim' => 'Individuelles Design, bedruckt oder graviert.',
        ],
        'folio-namensschilder' => [
            'material' => 'plastic', 'look' => 'window', 'premium' => 1, 'robust' => 1, 'hygienic' => 1,
            'uses' => ['event'],
            'claim' => 'Für kurze Einsätze: großes Sichtfenster, sehr günstig.',
        ],
        'image-namensschilder' => [
            'material' => 'metal', 'look' => 'alu', 'premium' => 2, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff', 'hospitality', 'care'],
            'claim' => 'Fensterschild mit hochwertiger Metalloberfläche.',
        ],
        'laser_contur-namensschilder' => [
            'material' => 'acrylic', 'look' => 'clear', 'premium' => 2, 'robust' => 2, 'hygienic' => 3,
            'uses' => ['staff', 'hospitality'],
            'claim' => 'Acrylglas mit laserpolierten Kanten, der Name im Mittelpunkt.',
        ],
        'materia-namensschilder' => [
            'material' => 'plastic', 'look' => 'laminate', 'premium' => 2, 'robust' => 3, 'hygienic' => 3,
            'uses' => ['industry', 'care', 'staff'],
            'claim' => '3-Schicht-Material, lasergraviert, sehr widerstandsfähig.',
        ],
        'papyrus-namensschilder' => [
            'material' => 'plastic', 'look' => 'papyrus', 'premium' => 2, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['hospitality', 'staff'],
            'claim' => 'Elegant und flexibel, passend auch als Tischschild.',
        ],
        'prestige-namensschilder' => [
            'material' => 'metal', 'look' => 'alu', 'premium' => 3, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['hospitality', 'staff'],
            'claim' => 'Repräsentativ für Empfang und Management.',
        ],
        'profiplexx-namensschilder' => [
            'material' => 'acrylic', 'look' => 'clear', 'premium' => 1, 'robust' => 2, 'hygienic' => 3,
            'uses' => ['event', 'staff', 'care'],
            'claim' => 'Stark gerundet, angenehm zu tragen und kippsicher.',
        ],
        'quadro-namensschilder' => [
            'material' => 'plastic', 'look' => 'classic', 'premium' => 2, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff'],
            'claim' => 'Klare, kantige Form mit Logofläche.',
        ],
        'screen-namensschilder' => [
            'material' => 'plastic', 'look' => 'frame', 'premium' => 1, 'robust' => 2, 'hygienic' => 3,
            'uses' => ['care', 'staff', 'event'],
            'claim' => 'Farbige Rahmen, zum Beispiel für einzelne Bereiche im Haus.',
        ],
        'slim-namensschilder' => [
            'material' => 'acrylic', 'look' => 'clear', 'premium' => 2, 'robust' => 2, 'hygienic' => 3,
            'uses' => ['hospitality', 'staff'],
            'claim' => 'Glänzende Acrylglas-Front, das Logo geschützt hinterdruckt.',
        ],
        'top-namensschilder' => [
            'material' => 'plastic', 'look' => 'classic', 'premium' => 1, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff', 'event'],
            'claim' => 'Logo links oder oben, den Namen drucken Sie selbst.',
        ],
        'translex-namensschilder' => [
            'material' => 'acrylic', 'look' => 'clear', 'premium' => 1, 'robust' => 2, 'hygienic' => 3,
            'uses' => ['staff', 'event'],
            'claim' => 'Logofläche links, transparenter Namensbereich rechts.',
        ],
        'woodline-namensschilder' => [
            'material' => 'wood', 'look' => 'wood', 'premium' => 2, 'robust' => 2, 'hygienic' => 1,
            'uses' => ['hospitality', 'staff'], 'eco' => true,
            'claim' => 'Echtholz: natürlich, warm und nachhaltig.',
        ],
        'x-style-namensschilder' => [
            'material' => 'plastic', 'look' => 'xstyle', 'premium' => 1, 'robust' => 2, 'hygienic' => 2,
            'uses' => ['staff', 'event'],
            'claim' => 'Markantes Design mit Farbstreifen.',
        ],
    ];

    private const DEFAULTS = [
        'material' => 'plastic', 'look' => 'plain', 'premium' => 2, 'robust' => 2, 'hygienic' => 2,
        'uses' => ['staff'], 'contour' => false, 'eco' => false, 'claim' => '',
    ];

    /** Traits of one family, defaults filled in. */
    public static function family(string $product_slug): array
    {
        $traits = array_merge(self::DEFAULTS, self::FAMILIES[$product_slug] ?? []);
        $traits['placeholder'] = !isset(self::FAMILIES[$product_slug]);
        return (array) apply_filters('infoplus_suite_finder_traits', $traits, $product_slug);
    }

    /**
     * The three ways to a name badge (badgepoint calls them labeling options).
     * Every pa_model term belongs to one of them: see optionFor().
     */
    public static function options(): array
    {
        $options = [
            'self' => [
                'label'   => 'Selbst beschriften',
                'short'   => 'Sie gestalten und drucken die Einleger selbst.',
                'fit'     => 'Für Teams, die sich oft ändern, und für Events.',
                'points'  => [
                    'Neue Namen jederzeit selbst drucken',
                    'Vorlagen für Ihren Drucker',
                    'Die günstigste Lösung',
                ],
            ],
            'logo' => [
                'label'   => 'Logo von uns, Namen selbst',
                'short'   => 'Wir drucken Ihr Logo, die Namen tauschen Sie selbst.',
                'fit'     => 'Für Unternehmen mit festem Auftritt und wechselnden Teams.',
                'points'  => [
                    'Logo hochwertig und dauerhaft gedruckt',
                    'Namen schnell selbst ergänzen',
                    'Wiederverwendbar bei Personalwechsel',
                ],
            ],
            'print' => [
                'label'   => 'Fertig personalisiert',
                'short'   => 'Jedes Schild fertig mit Logo und Name, gedruckt oder graviert.',
                'fit'     => 'Für Empfang, Kundenkontakt und Management.',
                'points'  => [
                    'Sofort tragbar, ohne eigenen Aufwand',
                    'Druck oder Lasergravur',
                    'Auf Wunsch in der Form Ihres Logos',
                ],
            ],
        ];
        return (array) apply_filters('infoplus_suite_finder_options', $options);
    }

    /** Option key, flags and a short customer-facing label for a pa_model term slug. */
    public static function optionFor(string $model_slug): array
    {
        $s = str_replace('_', '-', $model_slug);
        $contour = str_contains($s, 'sonderkontur');
        $engraved = str_contains($s, 'lasergrav');
        $series = str_contains($s, 'serienfertigung');

        if ($s === 'komplette-selbstbeschriftung') {
            $option = 'self';
        } elseif (str_contains($s, 'name-zur-selbstbeschriftung')) {
            $option = 'logo';
        } else {
            $option = 'print';
        }

        $labels = [
            'self'  => 'Selbst beschriften',
            'logo'  => $engraved ? 'Logo graviert, Namen selbst' : 'Logo gedruckt, Namen selbst',
            'print' => $engraved ? 'Fertig graviert' : 'Fertig bedruckt',
        ];
        $label = $labels[$option];
        if (str_contains($s, 'logo-oben')) {
            $label = 'Logo oben, Namen selbst';
        } elseif (str_contains($s, 'logo-links')) {
            $label = 'Logo links, Namen selbst';
        } elseif (str_contains($s, 'untereloxal')) {
            $label = 'Fertig bedruckt, kratzfest';
        } elseif (str_contains($s, 'digitaldruck')) {
            $label = 'Fertig bedruckt (Digitaldruck)';
        }
        if ($series) {
            $label = 'Serienfertigung für große Mengen';
        }
        if ($contour) {
            $label .= ', mit Sonderkontur';
        }

        return [
            'option'   => $option,
            'label'    => $label,
            'contour'  => $contour,
            'engraved' => $engraved,
            'series'   => $series,
        ];
    }

    /** magnet | pin | clip for a pa_befestigung term slug ('' when unknown). */
    public static function fastenerKind(string $slug): string
    {
        if (str_contains($slug, 'mag')) {
            return 'magnet';
        }
        if (str_contains($slug, 'clip') || str_contains($slug, 'klemme')) {
            return 'clip';
        }
        if (str_contains($slug, 'nadel')) {
            return 'pin';
        }
        return '';
    }
}
