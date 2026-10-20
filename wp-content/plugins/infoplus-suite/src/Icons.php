<?php
/**
 * Inline SVG icons for Suite templates.
 *
 * Replaces Elementor's Icons_Manager in the theme path. The built-in shapes
 * are the Font Awesome Free 5 solid icons the Elementor widgets use by
 * default, so the theme path shows the same pictogram.
 * Font Awesome Free by @fontawesome, https://fontawesome.com, icons licensed CC BY 4.0.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite;

if (!defined('ABSPATH')) exit;

final class Icons
{
    /**
     * Built-in icons: name => [viewBox, path data].
     */
    private const ICONS = [
        // fa-solid "box"
        'box' => [
            '0 0 512 512',
            'M509.5 184.6L458.9 32.8C452.4 13.2 434.1 0 413.4 0H272v192h238.7c-.4-2.5-.4-5-1.2-7.4zM240 0H98.6c-20.7 0-39 13.2-45.5 32.8L2.5 184.6c-.8 2.4-.8 4.9-1.2 7.4H240V0zM0 224v240c0 26.5 21.5 48 48 48h416c26.5 0 48-21.5 48-48V224H0z',
        ],
        // fa-solid "clock"
        'clock' => [
            '0 0 512 512',
            'M256,8C119,8,8,119,8,256S119,504,256,504,504,393,504,256,393,8,256,8Zm92.49,313h0l-20,25a16,16,0,0,1-22.49,2.5h0l-67-49.72a40,40,0,0,1-15-31.23V112a16,16,0,0,1,16-16h32a16,16,0,0,1,16,16V256l58,42.5A16,16,0,0,1,348.49,321Z',
        ],
    ];

    /**
     * Return the markup of a built-in icon, or '' for an unknown name.
     *
     * @param string $name  Icon name, see ICONS. Filter `infoplus_suite_icons` adds more.
     * @param array  $attrs Extra attributes for the <svg> element (for example aria-hidden).
     */
    public static function svg(string $name, array $attrs = []): string
    {
        /**
         * Filter the icon set: name => [viewBox, path data].
         *
         * @param array $icons Icons.
         */
        $icons = (array) apply_filters('infoplus_suite_icons', self::ICONS);

        if ($name === '' || !isset($icons[$name]) || !is_array($icons[$name]) || count($icons[$name]) < 2) {
            return '';
        }

        [$viewBox, $path] = array_values($icons[$name]);

        $attrs = array_merge([
            'class'   => 'ip-icon ip-icon--' . $name,
            'viewBox' => (string) $viewBox,
            'xmlns'   => 'http://www.w3.org/2000/svg',
            'fill'    => 'currentColor',
        ], $attrs);

        $html = '<svg';
        foreach ($attrs as $key => $value) {
            $html .= ' ' . esc_attr((string) $key) . '="' . esc_attr((string) $value) . '"';
        }
        $html .= '><path d="' . esc_attr((string) $path) . '"></path></svg>';

        return $html;
    }

    /**
     * Sanitize icon markup (built-in SVG or Elementor Icons_Manager output).
     * Allows <i> font icons and plain inline SVG, nothing else.
     */
    public static function kses(string $html): string
    {
        return wp_kses($html, self::allowedHtml());
    }

    /**
     * wp_kses() allow list for icon markup.
     */
    public static function allowedHtml(): array
    {
        $common = [
            'class'        => true,
            'id'           => true,
            'style'        => true,
            'aria-hidden'  => true,
            'aria-label'   => true,
            'role'         => true,
            'focusable'    => true,
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'transform'    => true,
        ];

        $shape = $common + [
            'd'                 => true,
            'cx'                => true,
            'cy'                => true,
            'r'                 => true,
            'rx'                => true,
            'ry'                => true,
            'x'                 => true,
            'y'                 => true,
            'x1'                => true,
            'x2'                => true,
            'y1'                => true,
            'y2'                => true,
            'width'             => true,
            'height'            => true,
            'points'            => true,
            'fill-rule'         => true,
            'clip-rule'         => true,
            'stroke-linecap'    => true,
            'stroke-linejoin'   => true,
            'opacity'           => true,
            'fill-opacity'      => true,
        ];

        return [
            'i'        => $common,
            'span'     => $common,
            'svg'      => $common + [
                'xmlns'       => true,
                'xmlns:xlink' => true,
                'viewbox'     => true,
                'width'       => true,
                'height'      => true,
                'version'     => true,
                'x'           => true,
                'y'           => true,
                'preserveaspectratio' => true,
            ],
            'g'        => $common,
            'title'    => [],
            'defs'     => [],
            'symbol'   => $common + ['viewbox' => true],
            'use'      => $common + ['href' => true, 'xlink:href' => true, 'x' => true, 'y' => true],
            'path'     => $shape,
            'circle'   => $shape,
            'ellipse'  => $shape,
            'rect'     => $shape,
            'line'     => $shape,
            'polyline' => $shape,
            'polygon'  => $shape,
        ];
    }
}
