<?php
/**
 * Finder ("Namensschild-Berater"): data for the theme's guided badge picker
 * and the "Drei Wege" section of the front page.
 *
 * The module only provides data. The theme renders the questions, the live
 * preview and the results (page-berater.php, assets/js/finder.js) and scores
 * the candidates in the browser, so the live counter needs no requests.
 *
 * - infoplus_suite_finder_data(): candidates, options, meta (Catalog::get)
 * - GET /infoplus/v1/finder: the same, public, cached (for other front ends)
 * - the cache is cleared when a product is saved
 *
 * @package Infoplus\Suite\Modules\Finder
 */

namespace Infoplus\Suite\Modules\Finder;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    public function slug(): string  { return 'finder'; }
    public function label(): string { return __('Namensschild-Berater', 'infoplus-suite'); }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('save_post_product', [Catalog::class, 'flush']);
        add_action('woocommerce_update_product', [Catalog::class, 'flush']);
        add_action('woocommerce_save_product_variation', [Catalog::class, 'flush']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('infoplus/v1', '/finder', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => function () {
                return new \WP_REST_Response(Catalog::get(), 200);
            },
        ]);
    }
}
