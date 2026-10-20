<?php
/**
 * The private post type `infoplus_inquiry` ("Anfragen") that stores every
 * request before any e-mail is sent, so nothing is lost when mail fails.
 *
 * Storage per inquiry:
 * - post_title: reference and name, e.g. "ANF-2026-0042 · Max Muster"
 * - post_status: private (never public, no front-end URL)
 * - meta `_ip_inquiry`: the whole record as an array (see Controller::submit())
 * - meta `_ip_inquiry_ref`, `_ip_inquiry_status`, `_ip_inquiry_email`,
 *   `_ip_inquiry_product_id`: single values for list columns, filter and search
 * - meta `_ip_inquiry_mail`: result of both e-mails
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

if (!defined('ABSPATH')) exit;

final class PostType
{
    public const TYPE = 'infoplus_inquiry';

    public const META_DATA    = '_ip_inquiry';
    public const META_REF     = '_ip_inquiry_ref';
    public const META_STATUS  = '_ip_inquiry_status';
    public const META_EMAIL   = '_ip_inquiry_email';
    public const META_PRODUCT = '_ip_inquiry_product_id';
    public const META_MAIL    = '_ip_inquiry_mail';

    /** @return array<string, string> slug => German label */
    public static function statuses(): array
    {
        return [
            'neu'              => __('Neu', 'infoplus-suite'),
            'in-bearbeitung'   => __('In Bearbeitung', 'infoplus-suite'),
            'angebot-gesendet' => __('Angebot gesendet', 'infoplus-suite'),
            'erledigt'         => __('Erledigt', 'infoplus-suite'),
        ];
    }

    public static function register(): void
    {
        // Under WooCommerce when it is active, else under the Suite's own menu.
        $menu = class_exists('WooCommerce') ? 'woocommerce' : 'infoplus-suite';
        $cap = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';

        register_post_type(self::TYPE, [
            'labels' => [
                'name'               => __('Anfragen', 'infoplus-suite'),
                'singular_name'      => __('Anfrage', 'infoplus-suite'),
                'menu_name'          => __('Anfragen', 'infoplus-suite'),
                'all_items'          => __('Anfragen', 'infoplus-suite'),
                'edit_item'          => __('Anfrage', 'infoplus-suite'),
                'view_item'          => __('Anfrage ansehen', 'infoplus-suite'),
                'search_items'       => __('Anfragen durchsuchen', 'infoplus-suite'),
                'not_found'          => __('Keine Anfragen gefunden.', 'infoplus-suite'),
                'not_found_in_trash' => __('Keine Anfragen im Papierkorb.', 'infoplus-suite'),
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => $menu,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,
            'show_in_rest'        => false,
            'query_var'           => false,
            'rewrite'             => false,
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => false,
            'map_meta_cap'        => true,
            // Every primitive cap maps to the shop manager capability; nobody
            // creates an inquiry in wp-admin, they only come from the form.
            'capabilities'        => [
                'create_posts'           => 'do_not_allow',
                'edit_posts'             => $cap,
                'edit_others_posts'      => $cap,
                'edit_published_posts'   => $cap,
                'edit_private_posts'     => $cap,
                'publish_posts'          => $cap,
                'read_private_posts'     => $cap,
                'read'                   => $cap,
                'delete_posts'           => $cap,
                'delete_others_posts'    => $cap,
                'delete_published_posts' => $cap,
                'delete_private_posts'   => $cap,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public static function data(int $post_id): array
    {
        $data = get_post_meta($post_id, self::META_DATA, true);
        return is_array($data) ? $data : [];
    }

    public static function status(int $post_id): string
    {
        $s = (string) get_post_meta($post_id, self::META_STATUS, true);
        return isset(self::statuses()[$s]) ? $s : 'neu';
    }

    /**
     * Next reference number, e.g. ANF-2026-0042. One counter per year in the
     * option infoplus_inquiry_seq_{year}, raised with one UPDATE so two
     * requests in the same second do not get the same number.
     */
    public static function nextReference(): string
    {
        global $wpdb;
        $year = wp_date('Y');
        $key = 'infoplus_inquiry_seq_' . $year;

        if (get_option($key, null) === null) {
            add_option($key, '0', '', false);
        }
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $key));
        wp_cache_delete($key, 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key));
        if ($n < 1) {
            $n = 1;
            update_option($key, '1', false);
        }

        $ref = sprintf('ANF-%s-%04d', $year, $n);
        /**
         * Filter the reference number of a new inquiry.
         *
         * @param string $ref
         * @param int    $n   Sequence number within the year.
         */
        return (string) apply_filters('infoplus_suite_inquiry_reference', $ref, $n);
    }

    /** Number of inquiries with status "Neu" (menu bubble). */
    public static function countNew(): int
    {
        $q = new \WP_Query([
            'post_type'      => self::TYPE,
            'post_status'    => 'private',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'meta_query'     => [['key' => self::META_STATUS, 'value' => 'neu']],
        ]);
        return (int) $q->found_posts;
    }
}
