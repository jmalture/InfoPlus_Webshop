<?php
/**
 * Inquiry module ("Individuelle Anfrage").
 *
 * The Suite owns the server side and the default markup; a theme opens the
 * form in its own drawer and styles it:
 * - REST routes under infoplus/v1 (Controller)
 * - private post type infoplus_inquiry, "Anfragen" under WooCommerce (PostType, Admin)
 * - protected file storage (Files), e-mails (Mailer), settings tab (Settings)
 * - templates/inquiry-form.php and templates/inquiry-email.php, overridable
 *   by the theme as infoplus-suite/{component}.php (View)
 *
 * Theme API: infoplus_suite_inquiry_form(array $args) prints the form.
 * The browser side (drawer, steps, uploads, submit) is the theme's
 * assets/js/inquiry.js; it reads the configuration from the form's
 * data-ip-inquiry-config attribute (Module::clientConfig()).
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    public const CRON = 'infoplus_inquiry_cleanup';

    public function slug(): string  { return 'inquiry'; }
    public function label(): string { return __('Anfragen', 'infoplus-suite'); }

    public function register(): void
    {
        add_action('init', [PostType::class, 'register']);
        add_action('rest_api_init', [new Controller(), 'registerRoutes']);
        (new Admin())->register();

        add_action('infoplus/module/settings/' . $this->slug(), [Settings::class, 'render']);

        add_action(self::CRON, [Files::class, 'cleanup']);
        add_action('init', static function (): void {
            if (!wp_next_scheduled(self::CRON)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
            }
        });
    }

    /**
     * Arguments for templates/inquiry-form.php.
     *
     * @param array $args Overrides: variant ("product"|"contact"), product (WC_Product|int|null), id_prefix.
     * @return array<string, mixed>
     */
    public static function formArgs(array $args = []): array
    {
        $variant = ($args['variant'] ?? 'product') === 'contact' ? 'contact' : 'product';
        $product = $args['product'] ?? null;
        if (is_numeric($product) && function_exists('wc_get_product')) {
            $product = wc_get_product((int) $product);
        }
        if ($product === null && $variant === 'product' && function_exists('is_product') && is_product()) {
            $product = wc_get_product(get_queried_object_id());
        }

        $card = null;
        if ($product instanceof \WC_Product) {
            $image_id = (int) $product->get_image_id();
            $card = [
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'image' => $image_id ? (string) wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '',
                'min'   => max(1, (int) get_post_meta($product->get_id(), '_infoplus_min_quantity', true)),
            ];
        }

        $countries = [];
        $base = 'DE';
        if (function_exists('WC') && WC()->countries) {
            $countries = WC()->countries->get_allowed_countries();
            $base = (string) WC()->countries->get_base_country();
        }

        return [
            'variant'      => $variant,
            'id_prefix'    => sanitize_html_class($args['id_prefix'] ?? 'ip-inq'),
            'product'      => $card,
            'prefill'      => self::prefill(),
            'countries'    => $countries,
            'base_country' => $base,
            'privacy_url'  => Settings::privacyUrl(),
            'success_text' => Settings::successText($variant),
            'max_bytes'    => Settings::maxUploadBytes(),
            'extensions'   => Files::extensions(),
            'config'       => self::clientConfig(),
        ];
    }

    /**
     * Contact data of the logged-in WooCommerce customer. Printed only for
     * logged-in visitors, whose pages are never page-cached.
     *
     * @return array<string, string>
     */
    public static function prefill(): array
    {
        $empty = ['company' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'postcode' => '', 'country' => ''];
        if (!is_user_logged_in()) {
            return $empty;
        }
        $user = wp_get_current_user();
        $out = $empty;
        if (class_exists('WC_Customer')) {
            try {
                $c = new \WC_Customer($user->ID);
                $out = [
                    'company'    => $c->get_billing_company(),
                    'first_name' => $c->get_billing_first_name() ?: $c->get_first_name(),
                    'last_name'  => $c->get_billing_last_name() ?: $c->get_last_name(),
                    'email'      => $c->get_billing_email() ?: $c->get_email(),
                    'phone'      => $c->get_billing_phone(),
                    'postcode'   => $c->get_billing_postcode(),
                    'country'    => $c->get_billing_country(),
                ];
            } catch (\Exception $e) {
                $out = $empty;
            }
        }
        $out['first_name'] = $out['first_name'] ?: $user->first_name;
        $out['last_name'] = $out['last_name'] ?: $user->last_name;
        $out['email'] = $out['email'] ?: $user->user_email;
        return array_map('strval', $out);
    }

    /**
     * Configuration for the browser. The wp_rest nonce is included only for
     * logged-in visitors (guest pages are cached; guests use the form token).
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $config = [
            'rest'        => esc_url_raw(rest_url(Controller::NS . '/')),
            'routes'      => [
                'session' => 'inquiries/session',
                'files'   => 'inquiries/files',
                'submit'  => 'inquiries',
            ],
            'nonce'       => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'maxBytes'    => Settings::maxUploadBytes(),
            'maxFileBytes'=> Settings::maxFileBytes(),
            'maxFiles'    => Settings::maxFiles(),
            'extensions'  => Files::extensions(),
            'messages'    => [
                'required_first_name' => __('Bitte geben Sie Ihren Vornamen an.', 'infoplus-suite'),
                'required_last_name'  => __('Bitte geben Sie Ihren Nachnamen an.', 'infoplus-suite'),
                'email'               => __('Bitte geben Sie eine gültige E-Mail-Adresse an.', 'infoplus-suite'),
                'phone'               => __('Bitte geben Sie eine gültige Telefonnummer an (Ziffern, Leerzeichen, + und -).', 'infoplus-suite'),
                'postcode'            => __('Bitte prüfen Sie die Postleitzahl.', 'infoplus-suite'),
                'quantity'            => __('Bitte geben Sie eine Menge ab 1 an.', 'infoplus-suite'),
                'wish_date'           => __('Der Wunschtermin darf nicht in der Vergangenheit liegen.', 'infoplus-suite'),
                'wish_date_invalid'   => __('Bitte geben Sie ein gültiges Datum an.', 'infoplus-suite'),
                'message'             => __('Bitte schreiben Sie uns kurz, worum es geht.', 'infoplus-suite'),
                'privacy'             => __('Bitte stimmen Sie der Datenschutzerklärung zu.', 'infoplus-suite'),
                'file_type'           => __('„%s“: Dieser Dateityp wird nicht unterstützt. Erlaubt sind PDF, AI, EPS, SVG, PNG, JPG und WEBP.', 'infoplus-suite'),
                'file_size'           => __('„%1$s“ ist zu groß. Insgesamt sind höchstens %2$s möglich.', 'infoplus-suite'),
                'file_count'          => __('Bitte laden Sie höchstens %d Dateien hoch.', 'infoplus-suite'),
                'file_busy'           => __('Bitte warten Sie, bis alle Dateien hochgeladen sind.', 'infoplus-suite'),
                'file_failed'         => __('Bitte entfernen Sie Dateien, die nicht hochgeladen werden konnten.', 'infoplus-suite'),
                'file_error'          => __('Die Datei konnte nicht hochgeladen werden. Bitte versuchen Sie es erneut.', 'infoplus-suite'),
                'summary_one'         => __('Bitte prüfen Sie das markierte Feld.', 'infoplus-suite'),
                'summary_many'        => __('Bitte prüfen Sie die %d markierten Felder.', 'infoplus-suite'),
                'network'             => __('Die Anfrage konnte nicht gesendet werden. Bitte prüfen Sie Ihre Verbindung und versuchen Sie es erneut.', 'infoplus-suite'),
                'sending'             => __('Wird gesendet …', 'infoplus-suite'),
                'uploading'           => __('Wird hochgeladen …', 'infoplus-suite'),
                'uploaded'            => __('Hochgeladen', 'infoplus-suite'),
                'remove'              => __('Entfernen', 'infoplus-suite'),
                'step'                => __('Schritt %1$d von %2$d', 'infoplus-suite'),
            ],
        ];
        /**
         * Filter the browser configuration of the inquiry form.
         *
         * @param array $config
         */
        return (array) apply_filters('infoplus_suite_inquiry_client_config', $config);
    }
}
