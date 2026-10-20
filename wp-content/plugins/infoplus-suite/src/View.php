<?php
/**
 * View renderer for Suite components.
 *
 * One entry point for rendering the markup of a Suite component, used by the
 * Elementor widgets and by classic PHP themes alike. Works like
 * wc_get_template(): the active theme can override a component by shipping
 * `infoplus-suite/{component}.php`; otherwise the plugin's
 * `templates/{component}.php` is used.
 *
 * Templates receive exactly one variable, `$args` (no extract()), and must
 * escape everything they print.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite;

if (!defined('ABSPATH')) exit;

final class View
{
    /** Folder inside the (child) theme that holds template overrides. */
    public const THEME_DIR = 'infoplus-suite';

    /** Render contexts passed to templates as $args['context']. */
    public const CONTEXT_THEME     = 'theme';
    public const CONTEXT_ELEMENTOR = 'elementor';

    /**
     * Asset handles per component.
     *
     * @var array<string, array{scripts: string[], styles: string[]}>
     */
    private static array $assets = [];

    /**
     * Render a component template.
     *
     * @param string $component Component name, for example "model-selector".
     * @param array  $args      Template arguments. `context` defaults to "theme".
     */
    public static function render(string $component, array $args = []): void
    {
        if (!self::isValidName($component)) {
            self::doingItWrong(sprintf('Invalid component name "%s".', $component));
            return;
        }

        $template = self::locate($component);
        if ($template === '') {
            self::doingItWrong(sprintf('No template found for component "%s".', $component));
            return;
        }

        if (!isset($args['context']) || !is_string($args['context']) || $args['context'] === '') {
            $args['context'] = self::CONTEXT_THEME;
        }

        /**
         * Filter the arguments a component template receives.
         *
         * @param array  $args      Template arguments.
         * @param string $component Component name.
         */
        $args = (array) apply_filters('infoplus_suite_view_args', $args, $component);

        self::enqueue($component);
        self::includeTemplate($template, $args);
    }

    /**
     * Render a component and return the markup instead of printing it.
     */
    public static function capture(string $component, array $args = []): string
    {
        ob_start();
        try {
            self::render($component, $args);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Find the template file for a component: theme override first, then plugin.
     *
     * @return string Absolute path, or '' when nothing was found.
     */
    public static function locate(string $component): string
    {
        if (!self::isValidName($component)) {
            return '';
        }

        $template = '';

        if (function_exists('locate_template')) {
            $template = (string) locate_template([self::THEME_DIR . '/' . $component . '.php'], false, false);
        }

        if ($template === '' && defined('INFOPLUS_SUITE_PATH')) {
            $fallback = INFOPLUS_SUITE_PATH . 'templates/' . $component . '.php';
            if (is_readable($fallback)) {
                $template = $fallback;
            }
        }

        /**
         * Filter the resolved template path of a component.
         *
         * @param string $template  Absolute path or ''.
         * @param string $component Component name.
         */
        return (string) apply_filters('infoplus_suite_locate_template', $template, $component);
    }

    /**
     * Declare which registered script and style handles a component needs.
     * The handles themselves are registered by the owning module.
     *
     * @param string   $component Component name.
     * @param string[] $scripts   Script handles.
     * @param string[] $styles    Style handles.
     */
    public static function registerAssets(string $component, array $scripts = [], array $styles = []): void
    {
        self::$assets[$component] = [
            'scripts' => array_values(array_unique($scripts)),
            'styles'  => array_values(array_unique($styles)),
        ];
    }

    /**
     * Enqueue the registered assets of a component. Safe to call more than once
     * and before wp_head (themes may call it early to get styles into the head).
     */
    public static function enqueue(string $component): void
    {
        if (!isset(self::$assets[$component])) {
            return;
        }

        foreach (self::$assets[$component]['scripts'] as $handle) {
            if (wp_script_is($handle, 'registered')) {
                wp_enqueue_script($handle);
            }
        }

        foreach (self::$assets[$component]['styles'] as $handle) {
            if (wp_style_is($handle, 'registered')) {
                wp_enqueue_style($handle);
            }
        }
    }

    /**
     * Include a template in an isolated scope. Only $args is meant for the template.
     */
    private static function includeTemplate(string $__template, array $args): void
    {
        (static function () use ($__template, $args): void {
            include $__template;
        })();
    }

    private static function isValidName(string $component): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]*$/', $component);
    }

    private static function doingItWrong(string $message): void
    {
        if (function_exists('_doing_it_wrong')) {
            _doing_it_wrong(__CLASS__ . '::render', esc_html($message), '0.9.113');
        }
    }
}
