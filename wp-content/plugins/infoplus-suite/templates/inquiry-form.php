<?php
/**
 * Inquiry form ("Individuelle Anfrage"), default markup.
 *
 * Rendered by infoplus_suite_inquiry_form() through View::render(). A theme
 * overrides it with infoplus-suite/inquiry-form.php. The theme script
 * (assets/js/inquiry.js of the Infoplus theme) drives it through the data
 * attributes below; keep them when overriding.
 *
 * Two steps (1 Projekt, 2 Kontakt), then a success state. One markup serves
 * both contexts: data-variant="product" (product page) or "contact" (Kontakt
 * page). Elements with data-ip-inq-only="product" are hidden, and their
 * inputs disabled by the script, in the contact variant.
 *
 * Root: form.ip-inq[data-ip-inquiry], configuration as JSON in
 * data-ip-inquiry-config (Module::clientConfig()).
 * Parts: __body, __summary, __step, __step-title, __product, __chips,
 * __change, __row, __field, __label, __input, __hint, __error, __switch,
 * __drop, __files, __file, __consent, __hp, __done, __check, __ref, __foot,
 * __progress, __nav.
 *
 * $args (Module::formArgs()):
 * - variant (string) product|contact
 * - id_prefix (string)
 * - product (array|null) id, name, image, min
 * - prefill (array) company, first_name, last_name, email, phone, postcode, country
 * - countries (array) code => name; base_country (string)
 * - privacy_url (string), success_text (string), max_bytes (int), extensions (string[])
 * - config (array)
 *
 * @package Infoplus\Suite
 */

if (!defined('ABSPATH')) exit;

$p        = (string) $args['id_prefix'];
$variant  = (string) $args['variant'];
$product  = $args['product'];
$pre      = (array) $args['prefill'];
$exts     = (array) $args['extensions'];
$accept   = implode(',', array_map(static function ($e) { return '.' . $e; }, $exts));
$max_text = size_format((int) $args['max_bytes']);
$country  = $pre['country'] !== '' ? $pre['country'] : (string) $args['base_country'];

$icon = static function (string $name): string {
    $paths = [
        'upload' => '<path d="M12 16V5M7 10l5-5 5 5"/><path d="M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3"/>',
        'pen'    => '<path d="M4 20l1-5L16 4l4 4L9 19z"/><path d="M14 6l4 4"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'send'   => '<path d="M4 12l16-8-6 16-2.5-6.5z"/><path d="M11.5 13.5L20 4"/>',
    ];
    return '<svg class="ip-icon ip-inq__icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};
$opt = '<span class="ip-inq__opt">' . esc_html__('optional', 'infoplus-suite') . '</span>';
$req = '<span class="ip-inq__req" aria-hidden="true">*</span>';
?>
<form class="ip-inq" id="<?php echo esc_attr($p); ?>-form" data-ip-inquiry data-variant="<?php echo esc_attr($variant); ?>" data-ip-inquiry-config="<?php echo esc_attr(wp_json_encode($args['config'])); ?>" method="post" action="#" novalidate>
    <div class="ip-inq__body" data-ip-inq-body>
        <div class="ip-inq__summary" data-ip-inq-summary role="alert" tabindex="-1" hidden></div>

        <section class="ip-inq__step" data-ip-inq-step="1" aria-labelledby="<?php echo esc_attr($p); ?>-s1">
            <h3 class="ip-inq__step-title" id="<?php echo esc_attr($p); ?>-s1" tabindex="-1">
                <span class="ip-sr"><?php esc_html_e('Schritt 1 von 2:', 'infoplus-suite'); ?> </span>
                <span data-ip-inq-only="product"><?php esc_html_e('Ihr Projekt', 'infoplus-suite'); ?></span>
                <span data-ip-inq-only="contact"><?php esc_html_e('Ihr Anliegen', 'infoplus-suite'); ?></span>
            </h3>

            <div class="ip-inq__product" data-ip-inq-product<?php echo $product ? '' : ' hidden'; ?>>
                <div class="ip-inq__product-media">
                    <img data-ip-inq-product-image src="<?php echo esc_url($product['image'] ?? ''); ?>" alt="" width="88" height="88"<?php echo empty($product['image']) ? ' hidden' : ''; ?>>
                </div>
                <div class="ip-inq__product-body">
                    <p class="ip-inq__product-kicker"><?php esc_html_e('Ihre Auswahl', 'infoplus-suite'); ?></p>
                    <p class="ip-inq__product-name" data-ip-inq-product-name><?php echo esc_html($product['name'] ?? ''); ?></p>
                    <ul class="ip-inq__chips" data-ip-inq-chips aria-label="<?php esc_attr_e('Gewählte Optionen', 'infoplus-suite'); ?>"></ul>
                    <button type="button" class="ip-inq__change" data-ip-inq-change data-ip-inq-only="product">
                        <?php echo $icon('pen'); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
                        <?php esc_html_e('Auswahl ändern', 'infoplus-suite'); ?>
                    </button>
                </div>
            </div>

            <div class="ip-inq__row" data-ip-inq-only="product">
                <div class="ip-inq__field ip-inq__field--qty">
                    <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-quantity"><?php esc_html_e('Menge', 'infoplus-suite'); ?> <?php echo $req; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
                    <input class="ip-inq__input" type="number" inputmode="numeric" min="1" step="1" max="1000000" name="quantity" id="<?php echo esc_attr($p); ?>-quantity" value="<?php echo esc_attr((string) ($product['min'] ?? 1)); ?>" required aria-describedby="<?php echo esc_attr($p); ?>-quantity-err">
                    <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-quantity-err" data-ip-inq-error="quantity" hidden></p>
                </div>
                <div class="ip-inq__field">
                    <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-wish_date"><?php esc_html_e('Wunschtermin', 'infoplus-suite'); ?> <?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
                    <input class="ip-inq__input" type="date" name="wish_date" id="<?php echo esc_attr($p); ?>-wish_date" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" aria-describedby="<?php echo esc_attr($p); ?>-wish_date-err">
                    <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-wish_date-err" data-ip-inq-error="wish_date" hidden></p>
                </div>
            </div>

            <label class="ip-inq__switch" data-ip-inq-only="product">
                <input type="checkbox" role="switch" name="recurring" value="1">
                <span class="ip-inq__switch-ui" aria-hidden="true"></span>
                <span class="ip-inq__switch-text">
                    <strong><?php esc_html_e('Regelmäßiger Bedarf', 'infoplus-suite'); ?></strong>
                    <span><?php esc_html_e('Sie bestellen diese Menge wiederholt, zum Beispiel für neue Mitarbeitende.', 'infoplus-suite'); ?></span>
                </span>
            </label>

            <?php ob_start(); ?>
            <div class="ip-inq__field" data-ip-inq-files-field>
                <span class="ip-inq__label" id="<?php echo esc_attr($p); ?>-files-label"><?php esc_html_e('Dateien', 'infoplus-suite'); ?> <?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div class="ip-inq__drop" data-ip-inq-drop>
                    <input class="ip-inq__file-input" type="file" id="<?php echo esc_attr($p); ?>-files" multiple accept="<?php echo esc_attr($accept); ?>" data-ip-inq-file-input aria-labelledby="<?php echo esc_attr($p); ?>-files-label <?php echo esc_attr($p); ?>-files-cta" aria-describedby="<?php echo esc_attr($p); ?>-files-hint <?php echo esc_attr($p); ?>-files-err">
                    <label class="ip-inq__drop-label" for="<?php echo esc_attr($p); ?>-files">
                        <span class="ip-inq__drop-icon"><?php echo $icon('upload'); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
                        <span class="ip-inq__drop-text" id="<?php echo esc_attr($p); ?>-files-cta">
                            <strong><?php esc_html_e('Dateien hierher ziehen', 'infoplus-suite'); ?></strong>
                            <?php esc_html_e('oder', 'infoplus-suite'); ?> <span class="ip-inq__drop-link"><?php esc_html_e('auswählen', 'infoplus-suite'); ?></span>
                        </span>
                    </label>
                </div>
                <p class="ip-inq__hint" id="<?php echo esc_attr($p); ?>-files-hint">
                    <?php esc_html_e('Logo oder Vorlage, am besten als Vektordatei.', 'infoplus-suite'); ?>
                    <?php
                    /* translators: %s: size limit, e.g. 20 MB */
                    printf(esc_html__('PDF, AI, EPS, SVG, PNG, JPG oder WEBP, zusammen bis %s.', 'infoplus-suite'), esc_html($max_text));
                    ?>
                </p>
                <ul class="ip-inq__files" data-ip-inq-file-list></ul>
                <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-files-err" data-ip-inq-error="files" hidden></p>
            </div>

            <?php $files_html = (string) ob_get_clean(); ?>
            <?php ob_start(); ?>
            <div class="ip-inq__field">
                <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-message">
                    <?php esc_html_e('Ihre Nachricht', 'infoplus-suite'); ?>
                    <span data-ip-inq-only="product"><?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <span data-ip-inq-only="contact"><?php echo $req; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                </label>
                <textarea class="ip-inq__input ip-inq__textarea" name="message" id="<?php echo esc_attr($p); ?>-message" rows="4" maxlength="5000" aria-describedby="<?php echo esc_attr($p); ?>-message-err"
                    data-placeholder-product="<?php esc_attr_e('Zum Beispiel: Sonderkontur in Form unseres Logos, ca. 60 x 25 mm, mit Magnet. Text zweizeilig: Name und Abteilung.', 'infoplus-suite'); ?>"
                    data-placeholder-contact="<?php esc_attr_e('Wie können wir Ihnen helfen?', 'infoplus-suite'); ?>"
                    placeholder="<?php echo $variant === 'contact' ? esc_attr__('Wie können wir Ihnen helfen?', 'infoplus-suite') : esc_attr__('Zum Beispiel: Sonderkontur in Form unseres Logos, ca. 60 x 25 mm, mit Magnet. Text zweizeilig: Name und Abteilung.', 'infoplus-suite'); ?>"></textarea>
                <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-message-err" data-ip-inq-error="message" hidden></p>
            </div>

            <?php $message_html = (string) ob_get_clean(); ?>
            <?php
            // Kontakt page (contact variant): the message comes first, files are the extra.
            echo $variant === 'contact' ? $message_html . $files_html : $files_html . $message_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
            ?>

            <label class="ip-inq__switch" data-ip-inq-only="product">
                <input type="checkbox" role="switch" name="samples" value="1">
                <span class="ip-inq__switch-ui" aria-hidden="true"></span>
                <span class="ip-inq__switch-text">
                    <strong><?php esc_html_e('Kostenlose Muster anfordern', 'infoplus-suite'); ?></strong>
                    <span><?php esc_html_e('Zum Prüfen von Material, Farbe und Befestigung.', 'infoplus-suite'); ?></span>
                </span>
            </label>
        </section>

        <section class="ip-inq__step" data-ip-inq-step="2" aria-labelledby="<?php echo esc_attr($p); ?>-s2" hidden>
            <h3 class="ip-inq__step-title" id="<?php echo esc_attr($p); ?>-s2" tabindex="-1">
                <span class="ip-sr"><?php esc_html_e('Schritt 2 von 2:', 'infoplus-suite'); ?> </span><?php esc_html_e('Ihre Kontaktdaten', 'infoplus-suite'); ?>
            </h3>
            <p class="ip-inq__note"><span data-ip-inq-only="product"><?php esc_html_e('Mit * markierte Felder brauchen wir für das Angebot.', 'infoplus-suite'); ?></span><span data-ip-inq-only="contact"><?php esc_html_e('Mit * markierte Felder brauchen wir für die Antwort.', 'infoplus-suite'); ?></span></p>

            <div class="ip-inq__field">
                <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-company"><?php esc_html_e('Firma', 'infoplus-suite'); ?> <span class="ip-inq__opt"><?php esc_html_e('empfohlen', 'infoplus-suite'); ?></span></label>
                <input class="ip-inq__input" type="text" name="company" id="<?php echo esc_attr($p); ?>-company" autocomplete="organization" maxlength="120" value="<?php echo esc_attr($pre['company']); ?>">
            </div>
            <div class="ip-inq__row">
                <div class="ip-inq__field">
                    <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-first_name"><?php esc_html_e('Vorname', 'infoplus-suite'); ?> <?php echo $req; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
                    <input class="ip-inq__input" type="text" name="first_name" id="<?php echo esc_attr($p); ?>-first_name" autocomplete="given-name" maxlength="80" required value="<?php echo esc_attr($pre['first_name']); ?>" aria-describedby="<?php echo esc_attr($p); ?>-first_name-err">
                    <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-first_name-err" data-ip-inq-error="first_name" hidden></p>
                </div>
                <div class="ip-inq__field">
                    <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-last_name"><?php esc_html_e('Nachname', 'infoplus-suite'); ?> <?php echo $req; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
                    <input class="ip-inq__input" type="text" name="last_name" id="<?php echo esc_attr($p); ?>-last_name" autocomplete="family-name" maxlength="80" required value="<?php echo esc_attr($pre['last_name']); ?>" aria-describedby="<?php echo esc_attr($p); ?>-last_name-err">
                    <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-last_name-err" data-ip-inq-error="last_name" hidden></p>
                </div>
            </div>
            <div class="ip-inq__field">
                <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-email"><?php esc_html_e('E-Mail', 'infoplus-suite'); ?> <?php echo $req; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
                <input class="ip-inq__input" type="email" name="email" id="<?php echo esc_attr($p); ?>-email" autocomplete="email" maxlength="190" required value="<?php echo esc_attr($pre['email']); ?>" aria-describedby="<?php echo esc_attr($p); ?>-email-err">
                <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-email-err" data-ip-inq-error="email" hidden></p>
            </div>
            <div class="ip-inq__field">
                <label class="ip-inq__label" for="<?php echo esc_attr($p); ?>-phone"><?php esc_html_e('Telefon', 'infoplus-suite'); ?> <?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
                <input class="ip-inq__input" type="tel" name="phone" id="<?php echo esc_attr($p); ?>-phone" autocomplete="tel" maxlength="40" value="<?php echo esc_attr($pre['phone']); ?>" aria-describedby="<?php echo esc_attr($p); ?>-phone-hint <?php echo esc_attr($p); ?>-phone-err">
                <p class="ip-inq__hint" id="<?php echo esc_attr($p); ?>-phone-hint"><span data-ip-inq-only="product"><?php esc_html_e('Für schnelle Rückfragen zu Ihrem Projekt.', 'infoplus-suite'); ?></span><span data-ip-inq-only="contact"><?php esc_html_e('Wenn wir Sie lieber anrufen sollen.', 'infoplus-suite'); ?></span></p>
                <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-phone-err" data-ip-inq-error="phone" hidden></p>
            </div>
            <fieldset class="ip-inq__group" data-ip-inq-only="product">
                <legend class="ip-inq__label"><?php esc_html_e('Lieferort', 'infoplus-suite'); ?> <?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?></legend>
                <div class="ip-inq__row ip-inq__row--plz">
                    <div class="ip-inq__field">
                        <label class="ip-inq__label ip-inq__label--sub" for="<?php echo esc_attr($p); ?>-postcode"><?php esc_html_e('PLZ', 'infoplus-suite'); ?></label>
                        <input class="ip-inq__input" type="text" name="postcode" id="<?php echo esc_attr($p); ?>-postcode" autocomplete="postal-code" inputmode="text" maxlength="12" value="<?php echo esc_attr($pre['postcode']); ?>" aria-describedby="<?php echo esc_attr($p); ?>-place-hint <?php echo esc_attr($p); ?>-postcode-err">
                        <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-postcode-err" data-ip-inq-error="postcode" hidden></p>
                    </div>
                    <div class="ip-inq__field">
                        <label class="ip-inq__label ip-inq__label--sub" for="<?php echo esc_attr($p); ?>-country"><?php esc_html_e('Land', 'infoplus-suite'); ?></label>
                        <select class="ip-inq__input ip-inq__select" name="country" id="<?php echo esc_attr($p); ?>-country" autocomplete="country" aria-describedby="<?php echo esc_attr($p); ?>-place-hint">
                            <option value=""><?php esc_html_e('Bitte wählen', 'infoplus-suite'); ?></option>
                            <?php
                            // Most customers order from the DACH region: those three first.
                            $first = array_intersect_key((array) $args['countries'], array_flip(['DE', 'AT', 'CH']));
                            $rest = array_diff_key((array) $args['countries'], $first);
                            foreach ([$first, $rest] as $gi => $group) {
                                if ($gi === 1 && $first && $rest) {
                                    echo '<option disabled>──────────</option>';
                                }
                                foreach ($group as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '"' . selected($country, $code, false) . '>' . esc_html(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8')) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <p class="ip-inq__hint" id="<?php echo esc_attr($p); ?>-place-hint"><?php esc_html_e('Damit wir die Versandkosten gleich mit anbieten können.', 'infoplus-suite'); ?></p>
            </fieldset>

            <div class="ip-inq__field">
                <label class="ip-inq__consent">
                    <input type="checkbox" name="privacy" value="1" required aria-describedby="<?php echo esc_attr($p); ?>-privacy-err">
                    <span>
                        <span data-ip-inq-only="product"><?php esc_html_e('Ich bin einverstanden, dass meine Angaben zur Bearbeitung dieser Anfrage gespeichert und für Rückfragen genutzt werden.', 'infoplus-suite'); ?></span>
                        <span data-ip-inq-only="contact"><?php esc_html_e('Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Nachricht gespeichert und für Rückfragen genutzt werden.', 'infoplus-suite'); ?></span>
                        <?php if ($args['privacy_url'] !== '') : ?>
                            <a href="<?php echo esc_url($args['privacy_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Datenschutzerklärung', 'infoplus-suite'); ?><span class="ip-sr"> <?php esc_html_e('(öffnet in neuem Tab)', 'infoplus-suite'); ?></span></a>
                        <?php endif; ?>
                        <?php echo $req; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </span>
                </label>
                <p class="ip-inq__error" id="<?php echo esc_attr($p); ?>-privacy-err" data-ip-inq-error="privacy" hidden></p>
            </div>

            <div class="ip-inq__hp" aria-hidden="true">
                <label for="<?php echo esc_attr($p); ?>-extra"><?php esc_html_e('Dieses Feld bitte leer lassen', 'infoplus-suite'); ?></label>
                <input type="text" name="ip_extra" id="<?php echo esc_attr($p); ?>-extra" tabindex="-1" autocomplete="off" value="">
            </div>
        </section>

        <section class="ip-inq__done" data-ip-inq-done aria-labelledby="<?php echo esc_attr($p); ?>-done" hidden>
            <svg class="ip-inq__check" viewBox="0 0 64 64" width="72" height="72" aria-hidden="true" focusable="false">
                <circle class="ip-inq__check-ring" cx="32" cy="32" r="29" fill="none" stroke-width="3"/>
                <path class="ip-inq__check-mark" d="M19 33.5l9 9 17-19" fill="none" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h3 class="ip-inq__done-title" id="<?php echo esc_attr($p); ?>-done" tabindex="-1">
                <span data-ip-inq-only="product"><?php esc_html_e('Vielen Dank, Ihre Anfrage ist eingegangen', 'infoplus-suite'); ?></span>
                <span data-ip-inq-only="contact"><?php esc_html_e('Vielen Dank, Ihre Nachricht ist eingegangen', 'infoplus-suite'); ?></span>
            </h3>
            <p class="ip-inq__ref"><?php esc_html_e('Ihre Referenznummer', 'infoplus-suite'); ?> <strong data-ip-inq-ref></strong></p>
            <p class="ip-inq__done-mail" data-ip-inq-done-mail hidden><?php esc_html_e('Eine Bestätigung ist unterwegs an', 'infoplus-suite'); ?> <strong data-ip-inq-done-email></strong>.</p>
            <div class="ip-inq__next">
                <h4><?php esc_html_e('So geht es weiter', 'infoplus-suite'); ?></h4>
                <p data-ip-inq-next-text><?php echo esc_html($args['success_text']); ?></p>
            </div>
        </section>
    </div>

    <div class="ip-inq__foot" data-ip-inq-foot>
        <ol class="ip-inq__progress" aria-label="<?php esc_attr_e('Fortschritt', 'infoplus-suite'); ?>">
            <li class="ip-inq__progress-item is-current" data-ip-inq-progress="1" aria-current="step"><span class="ip-inq__progress-num">1</span>
                <span data-ip-inq-only="product"><?php esc_html_e('Projekt', 'infoplus-suite'); ?></span><span data-ip-inq-only="contact"><?php esc_html_e('Anliegen', 'infoplus-suite'); ?></span></li>
            <li class="ip-inq__progress-item" data-ip-inq-progress="2"><span class="ip-inq__progress-num">2</span> <?php esc_html_e('Kontakt', 'infoplus-suite'); ?></li>
        </ol>
        <div class="ip-inq__nav">
            <button type="button" class="ip-btn ip-btn--ghost ip-inq__back" data-ip-inq-back hidden><?php echo $icon('back'); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e('Zurück', 'infoplus-suite'); ?></button>
            <button type="button" class="ip-btn ip-inq__next-btn" data-ip-inq-next><?php esc_html_e('Weiter', 'infoplus-suite'); ?><?php echo $icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
            <button type="submit" class="ip-btn ip-inq__submit" data-ip-inq-submit hidden><span data-ip-label data-label-product="<?php esc_attr_e('Anfrage senden', 'infoplus-suite'); ?>" data-label-contact="<?php esc_attr_e('Nachricht senden', 'infoplus-suite'); ?>"><?php echo $variant === 'contact' ? esc_html__('Nachricht senden', 'infoplus-suite') : esc_html__('Anfrage senden', 'infoplus-suite'); ?></span><?php echo $icon('send'); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
            <button type="button" class="ip-btn ip-inq__close-btn" data-ip-inq-close hidden><?php esc_html_e('Schließen', 'infoplus-suite'); ?></button>
        </div>
    </div>
</form>
