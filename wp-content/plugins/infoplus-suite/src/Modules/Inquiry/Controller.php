<?php
/**
 * REST routes of the inquiry form, namespace infoplus/v1.
 *
 * | Method | Route                               | Purpose                                  |
 * |--------|-------------------------------------|------------------------------------------|
 * | POST   | /inquiries/session                  | Issue a form token (Token::issue())      |
 * | POST   | /inquiries/files                    | Upload one file (multipart, field "file") |
 * | DELETE | /inquiries/files/{id}               | Remove an uploaded file again            |
 * | POST   | /inquiries                          | Submit the inquiry                       |
 *
 * The session route is a POST so no page or REST cache ever stores a token.
 * Every other route requires a valid token (permission callback). Logged-in
 * visitors also send X-WP-Nonce (wp_rest); core checks it before the route
 * runs and treats the request as a guest without it.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

if (!defined('ABSPATH')) exit;

final class Controller
{
    public const NS = 'infoplus/v1';

    /** Submits per IP and window. */
    private const SUBMIT_LIMIT  = 5;
    private const UPLOAD_LIMIT  = 40;
    private const SESSION_LIMIT = 60;
    private const WINDOW        = 15 * MINUTE_IN_SECONDS;

    private const MAX_QTY     = 1000000;
    private const MAX_MESSAGE = 5000;

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/inquiries/session', [
            'methods'             => 'POST',
            'callback'            => [$this, 'session'],
            // Public on purpose: issues a signed, short-lived form token and changes nothing. Rate limited.
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/inquiries/files', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upload'],
            'permission_callback' => [$this, 'checkToken'],
        ]);

        register_rest_route(self::NS, '/inquiries/files/(?P<id>[a-f0-9]{32})', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'removeFile'],
            'permission_callback' => [$this, 'checkToken'],
        ]);

        register_rest_route(self::NS, '/inquiries', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit'],
            'permission_callback' => [$this, 'checkToken'],
        ]);
    }

    /** Permission callback: a valid, unexpired form token is required. */
    public function checkToken(\WP_REST_Request $request)
    {
        $token = $this->token($request);
        if ($token === '' || Token::verify($token) === null) {
            return new \WP_Error(
                'infoplus_inquiry_token',
                __('Das Formular ist abgelaufen. Bitte schließen Sie es und öffnen Sie es erneut.', 'infoplus-suite'),
                ['status' => 403]
            );
        }
        return true;
    }

    public function session(\WP_REST_Request $request)
    {
        if ($this->limited('session', self::SESSION_LIMIT)) {
            return $this->tooMany();
        }
        $res = new \WP_REST_Response([
            'token'      => Token::issue(),
            'minSeconds' => Token::MIN_SECONDS,
        ]);
        $res->header('Cache-Control', 'no-store, private');
        return $res;
    }

    public function upload(\WP_REST_Request $request)
    {
        if ($this->limited('upload', self::UPLOAD_LIMIT)) {
            return $this->tooMany();
        }
        $files = $request->get_file_params();
        if (empty($files['file']) || !is_array($files['file']) || is_array($files['file']['name'] ?? null)) {
            return new \WP_Error('infoplus_inquiry_file', __('Keine Datei empfangen.', 'infoplus-suite'), ['status' => 400]);
        }
        $file = $files['file'];
        $type = Files::check($file);
        if (is_wp_error($type)) {
            $type->add_data(['status' => 422]);
            return $type;
        }
        $stored = Files::storeTemp($file, $type, $this->token($request));
        if (is_wp_error($stored)) {
            $stored->add_data(['status' => 422]);
            return $stored;
        }
        return new \WP_REST_Response($stored, 201);
    }

    public function removeFile(\WP_REST_Request $request)
    {
        Files::deleteTemp($this->token($request), (string) $request['id']);
        return new \WP_REST_Response(['deleted' => true]);
    }

    public function submit(\WP_REST_Request $request)
    {
        $token = $this->token($request);
        $tkey = Token::key($token);

        // Double click or retry after a lost response: same answer, no second record.
        $done = get_transient('infoplus_inq_done_' . $tkey);
        if (is_array($done)) {
            return new \WP_REST_Response($done, 200);
        }

        // Honeypot: a field people never see. Filled means a bot.
        if (trim((string) $request->get_param('ip_extra')) !== '') {
            return new \WP_Error('infoplus_inquiry_rejected', __('Ihre Anfrage konnte nicht gesendet werden.', 'infoplus-suite'), ['status' => 400]);
        }

        // Time trap, on the server clock: the token was issued when the form opened.
        $issued = (int) Token::verify($token);
        if (time() - $issued < Token::MIN_SECONDS) {
            return new \WP_Error('infoplus_inquiry_too_fast', __('Das ging sehr schnell. Bitte prüfen Sie Ihre Angaben und senden Sie die Anfrage erneut.', 'infoplus-suite'), ['status' => 400]);
        }

        $fields = $this->validate($request);
        if (is_wp_error($fields)) {
            return $fields;
        }

        // Counted only for complete requests, so a person correcting a field is never locked out.
        if ($this->limited('submit', self::SUBMIT_LIMIT)) {
            return $this->tooMany();
        }

        $record = $fields;
        $record['reference'] = PostType::nextReference();
        $record['created']   = gmdate('c');
        $record['user_id']   = get_current_user_id();
        $record['files']     = Files::claim($token, $fields['file_ids']);
        unset($record['file_ids']);

        /**
         * Filter the inquiry record before it is stored and mailed.
         *
         * @param array            $record
         * @param \WP_REST_Request $request
         */
        $record = (array) apply_filters('infoplus_suite_inquiry_record', $record, $request);

        $name = trim($record['contact']['first_name'] . ' ' . $record['contact']['last_name']);
        $post_id = wp_insert_post([
            'post_type'   => PostType::TYPE,
            'post_status' => 'private',
            'post_title'  => $record['reference'] . ' · ' . $name . ($record['contact']['company'] !== '' ? ' (' . $record['contact']['company'] . ')' : ''),
            'post_author' => $record['user_id'],
        ], true);

        if (is_wp_error($post_id)) {
            return new \WP_Error('infoplus_inquiry_store', __('Ihre Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', 'infoplus-suite'), ['status' => 500]);
        }

        update_post_meta($post_id, PostType::META_DATA, $record);
        update_post_meta($post_id, PostType::META_REF, $record['reference']);
        update_post_meta($post_id, PostType::META_STATUS, 'neu');
        update_post_meta($post_id, PostType::META_EMAIL, $record['contact']['email']);
        update_post_meta($post_id, PostType::META_PRODUCT, (int) ($record['product']['id'] ?? 0));

        // Stored first, mailed second: a failing mail never loses the request.
        $mail = Mailer::sendAll((int) $post_id, $record);
        update_post_meta($post_id, PostType::META_MAIL, $mail);

        /**
         * Fires after an inquiry was stored and the e-mails were sent.
         *
         * @param int   $post_id
         * @param array $record
         * @param array $mail    Mail results.
         */
        do_action('infoplus_suite_inquiry_created', (int) $post_id, $record, $mail);

        $response = [
            'reference' => $record['reference'],
            'message'   => Settings::successText(),
            'email'     => $record['contact']['email'],
        ];
        set_transient('infoplus_inq_done_' . $tkey, $response, Token::MAX_AGE);

        return new \WP_REST_Response($response, 201);
    }

    /**
     * Server-side validation and sanitising of every field.
     *
     * @return array<string, mixed>|\WP_Error 422 with data.fields = [name => message].
     */
    private function validate(\WP_REST_Request $r)
    {
        $errors = [];
        $variant = $r->get_param('variant') === 'contact' ? 'contact' : 'product';

        $text = static function ($value, int $max): string {
            return mb_substr(trim(sanitize_text_field((string) (is_scalar($value) ? $value : ''))), 0, $max);
        };
        $flag = static function ($value): bool {
            return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
        };

        // Project.
        $product = $this->resolveProduct($r);
        $qty_raw = $r->get_param('quantity');
        $quantity = null;
        if ($qty_raw !== null && $qty_raw !== '') {
            if (!is_numeric($qty_raw) || (int) $qty_raw != $qty_raw || (int) $qty_raw < 1 || (int) $qty_raw > self::MAX_QTY) {
                $errors['quantity'] = __('Bitte geben Sie eine Menge ab 1 an.', 'infoplus-suite');
            } else {
                $quantity = (int) $qty_raw;
            }
        } elseif ($variant === 'product' && !empty($product['id'])) {
            $errors['quantity'] = __('Bitte geben Sie eine Menge an.', 'infoplus-suite');
        }

        $wish = $text($r->get_param('wish_date'), 10);
        if ($wish !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $wish, wp_timezone());
            $today = new \DateTimeImmutable('today', wp_timezone());
            if (!$d || $d->format('Y-m-d') !== $wish) {
                $errors['wish_date'] = __('Bitte geben Sie ein gültiges Datum an.', 'infoplus-suite');
            } elseif ($d < $today) {
                $errors['wish_date'] = __('Der Wunschtermin darf nicht in der Vergangenheit liegen.', 'infoplus-suite');
            } elseif ($d > $today->modify('+3 years')) {
                $errors['wish_date'] = __('Bitte wählen Sie einen Termin innerhalb der nächsten drei Jahre.', 'infoplus-suite');
            }
        }

        $message = mb_substr(trim(sanitize_textarea_field((string) (is_scalar($r->get_param('message')) ? $r->get_param('message') : ''))), 0, self::MAX_MESSAGE);
        if ($variant === 'contact' && $message === '') {
            $errors['message'] = __('Bitte schreiben Sie uns kurz, worum es geht.', 'infoplus-suite');
        }

        $file_ids = $r->get_param('files');
        $file_ids = is_array($file_ids) ? array_values(array_filter(array_map('strval', $file_ids), static function ($id) {
            return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
        })) : [];
        $file_ids = array_slice($file_ids, 0, Settings::maxFiles());

        // Contact.
        $contact = [
            'company'    => $text($r->get_param('company'), 120),
            'first_name' => $text($r->get_param('first_name'), 80),
            'last_name'  => $text($r->get_param('last_name'), 80),
            'email'      => sanitize_email((string) (is_scalar($r->get_param('email')) ? $r->get_param('email') : '')),
            'phone'      => $text($r->get_param('phone'), 40),
            'postcode'   => $text($r->get_param('postcode'), 12),
            'country'    => strtoupper($text($r->get_param('country'), 2)),
        ];
        if ($contact['first_name'] === '') {
            $errors['first_name'] = __('Bitte geben Sie Ihren Vornamen an.', 'infoplus-suite');
        }
        if ($contact['last_name'] === '') {
            $errors['last_name'] = __('Bitte geben Sie Ihren Nachnamen an.', 'infoplus-suite');
        }
        if ($contact['email'] === '' || !is_email($contact['email'])) {
            $errors['email'] = __('Bitte geben Sie eine gültige E-Mail-Adresse an.', 'infoplus-suite');
        }
        if ($contact['phone'] !== '' && !preg_match('/^[0-9 +()\/.\-]{4,40}$/', $contact['phone'])) {
            $errors['phone'] = __('Bitte geben Sie eine gültige Telefonnummer an (Ziffern, Leerzeichen, + und -).', 'infoplus-suite');
        }
        if ($contact['postcode'] !== '' && !preg_match('/^[A-Za-z0-9 \-]{3,12}$/', $contact['postcode'])) {
            $errors['postcode'] = __('Bitte prüfen Sie die Postleitzahl.', 'infoplus-suite');
        }
        $countries = function_exists('WC') && WC()->countries ? WC()->countries->get_countries() : [];
        if ($contact['country'] !== '' && $countries && !isset($countries[$contact['country']])) {
            $contact['country'] = '';
        }
        $contact['country_name'] = $contact['country'] !== '' && isset($countries[$contact['country']])
            ? html_entity_decode((string) $countries[$contact['country']], ENT_QUOTES, 'UTF-8')
            : $contact['country'];

        if (!$flag($r->get_param('privacy'))) {
            $errors['privacy'] = __('Bitte stimmen Sie der Datenschutzerklärung zu.', 'infoplus-suite');
        }

        if ($errors) {
            return new \WP_Error(
                'infoplus_inquiry_invalid',
                _n('Bitte prüfen Sie das markierte Feld.', 'Bitte prüfen Sie die markierten Felder.', count($errors), 'infoplus-suite'),
                ['status' => 422, 'fields' => $errors]
            );
        }

        return [
            'variant'   => $variant,
            'product'   => $product,
            'quantity'  => $quantity,
            'recurring' => $flag($r->get_param('recurring')),
            'wish_date' => $wish,
            'message'   => $message,
            'samples'   => $flag($r->get_param('samples')),
            'file_ids'  => $file_ids,
            'contact'   => $contact,
            'consent'   => [
                'time'        => gmdate('c'),
                'privacy_url' => Settings::privacyUrl(),
            ],
            'source_url' => $this->sourceUrl($r->get_param('source_url')),
        ];
    }

    /**
     * Product context: everything is looked up again on the server from IDs
     * and slugs; names and prices from the browser are never trusted. A
     * product name without ID (Kontakt page with ?produkt=) is kept as plain
     * text and marked unverified.
     *
     * @return array<string, mixed>
     */
    private function resolveProduct(\WP_REST_Request $r): array
    {
        $ctx = $r->get_param('context');
        $ctx = is_array($ctx) ? $ctx : [];
        $id = absint($ctx['product_id'] ?? 0);
        $product = $id && function_exists('wc_get_product') ? wc_get_product($id) : null;

        if (!$product || $product->get_status() !== 'publish' || $product->is_type('variation')) {
            $name = mb_substr(trim(sanitize_text_field((string) (is_scalar($ctx['product_name'] ?? null) ? $ctx['product_name'] : ''))), 0, 200);
            return $name !== '' ? ['id' => 0, 'name' => $name, 'verified' => false] : [];
        }

        $out = [
            'id'       => $product->get_id(),
            'name'     => $product->get_name(),
            'sku'      => (string) $product->get_sku(),
            'url'      => (string) $product->get_permalink(),
            'image'    => '',
            'model'    => null,
            'options'  => [],
            'variation_id'  => 0,
            'variation_sku' => '',
            'order_type'    => '',
            'verified'      => true,
        ];
        $image_id = (int) $product->get_image_id();

        $attrs = $product->get_attributes();
        $posted = is_array($ctx['attributes'] ?? null) ? $ctx['attributes'] : [];
        $model_slug = sanitize_title((string) (is_scalar($ctx['model'] ?? null) ? $ctx['model'] : ''));
        if ($model_slug !== '') {
            $posted['pa_model'] = $model_slug;
        }

        foreach ($posted as $key => $value) {
            $key = sanitize_title(preg_replace('/^attribute_/', '', (string) $key));
            if (!is_scalar($value) || $value === '' || !isset($attrs[$key])) {
                continue;
            }
            $attr = $attrs[$key];
            $value = (string) $value;
            if ($attr->is_taxonomy()) {
                $slugs = wc_get_product_terms($product->get_id(), $key, ['fields' => 'slugs']);
                $slug = sanitize_title($value);
                if (!in_array($slug, $slugs, true)) {
                    continue;
                }
                $term = get_term_by('slug', $slug, $key);
                $entry = ['key' => $key, 'label' => wc_attribute_label($key, $product), 'slug' => $slug, 'value' => $term ? html_entity_decode($term->name, ENT_QUOTES, 'UTF-8') : $slug];
            } else {
                $value = sanitize_text_field($value);
                if (!in_array($value, $attr->get_options(), true)) {
                    continue;
                }
                $entry = ['key' => $key, 'label' => wc_attribute_label($attr->get_name(), $product), 'slug' => $value, 'value' => $value];
            }
            if ($key === 'pa_model') {
                $out['model'] = ['slug' => $entry['slug'], 'name' => $entry['value']];
                $type = (string) get_post_meta($product->get_id(), '_infoplus_attr_order_type_model_' . $entry['slug'], true);
                $out['order_type'] = $type !== '' ? $type : 'editable';
                $gallery = (string) get_post_meta($product->get_id(), '_infoplus_attr_gallery_model_' . $entry['slug'], true);
                $first = (int) strtok($gallery, ',');
                if ($first) {
                    $image_id = $first;
                }
            } else {
                $out['options'][] = $entry;
            }
        }

        $vid = absint($ctx['variation_id'] ?? 0);
        if ($vid && in_array($vid, $product->get_children(), true)) {
            $variation = wc_get_product($vid);
            // Keep the variation only when it matches the chosen model and options.
            $chosen = [];
            foreach ($out['options'] as $o) {
                $chosen[$o['key']] = $o['slug'];
            }
            if ($out['model']) {
                $chosen['pa_model'] = $out['model']['slug'];
            }
            if ($variation) {
                foreach ($variation->get_attributes() as $vkey => $vval) {
                    if ($vval !== '' && isset($chosen[$vkey]) && $chosen[$vkey] !== $vval) {
                        $variation = null;
                        break;
                    }
                }
            }
            if ($variation) {
                $out['variation_id'] = $vid;
                $out['variation_sku'] = (string) $variation->get_sku();
            }
        }

        $out['image'] = $image_id ? (string) wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '';
        return $out;
    }

    /** Page the form was sent from: same host only. */
    private function sourceUrl($value): string
    {
        $url = esc_url_raw((string) (is_scalar($value) ? $value : ''));
        if ($url === '' || wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST)) {
            return '';
        }
        return mb_substr($url, 0, 500);
    }

    private function token(\WP_REST_Request $request): string
    {
        $token = $request->get_header('x-ip-inquiry-token');
        if (!$token) {
            $token = $request->get_param('token');
        }
        return is_string($token) ? trim($token) : '';
    }

    /**
     * Fixed window rate limit per IP and bucket, in a transient. Only a hash
     * of the IP is kept, and only for the window.
     */
    private function limited(string $bucket, int $limit): bool
    {
        $ip = (string) apply_filters('infoplus_suite_inquiry_client_ip', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $limit = (int) apply_filters('infoplus_suite_inquiry_rate_limit', $limit, $bucket);
        $key = 'infoplus_inq_rl_' . $bucket . '_' . substr(hash('sha256', $ip . wp_salt('nonce')), 0, 24);
        $hits = get_transient($key);
        $hits = is_array($hits) ? $hits : ['n' => 0, 't' => time()];
        if (time() - (int) $hits['t'] > self::WINDOW) {
            $hits = ['n' => 0, 't' => time()];
        }
        $hits['n']++;
        set_transient($key, $hits, self::WINDOW);
        return $hits['n'] > $limit;
    }

    private function tooMany(): \WP_Error
    {
        return new \WP_Error(
            'infoplus_inquiry_rate',
            __('Sie haben in kurzer Zeit sehr viele Anfragen gesendet. Bitte versuchen Sie es in einigen Minuten erneut oder rufen Sie uns an.', 'infoplus-suite'),
            ['status' => 429]
        );
    }
}
