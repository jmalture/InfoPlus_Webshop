<?php
/**
 * Settings of the inquiry module, stored as one array in the option
 * `infoplus_inquiry_settings`. Rendered as the "Anfragen" tab of the
 * IKONA IFE admin page (same look as the EditorLinkSettings tab).
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

if (!defined('ABSPATH')) exit;

final class Settings
{
    public const OPTION = 'infoplus_inquiry_settings';

    /** Upper bound for the total upload size setting, in MB. */
    public const MAX_UPLOAD_CAP_MB = 64;

    /**
     * Defaults. The recipient stays empty on purpose: empty means the site
     * admin e-mail at send time. The real address is set at rollout.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'recipient'       => '',
            'max_upload_mb'   => 20,
            'max_files'       => 10,
            'attach_limit_mb' => 10,
            'success_text'    => 'Wir prüfen Ihre Anfrage und melden uns innerhalb von 24 Stunden mit einem Angebot.',
            'success_text_contact' => 'Wir lesen Ihre Nachricht und melden uns innerhalb von 24 Stunden bei Ihnen.',
            'customer_mail'   => true,
            'privacy_page'    => 0,
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    /** @return mixed */
    public static function get(string $key)
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    /**
     * Team recipients: the setting (comma separated), else the site admin e-mail.
     *
     * @return string[]
     */
    public static function recipients(): array
    {
        $list = array_filter(array_map('trim', explode(',', (string) self::get('recipient'))), 'is_email');
        if (!$list) {
            $list = [(string) get_option('admin_email')];
        }
        /**
         * Filter the team recipients of a new inquiry.
         *
         * @param string[] $list
         */
        return (array) apply_filters('infoplus_suite_inquiry_recipients', array_values($list));
    }

    /** Total upload limit in bytes. */
    public static function maxUploadBytes(): int
    {
        $mb = (int) self::get('max_upload_mb');
        $mb = max(1, min(self::MAX_UPLOAD_CAP_MB, $mb));
        return $mb * MB_IN_BYTES;
    }

    /**
     * Largest single file the server accepts: the total limit, capped by PHP's
     * upload_max_filesize / post_max_size (wp_max_upload_size()).
     */
    public static function maxFileBytes(): int
    {
        $php = (int) wp_max_upload_size();
        $total = self::maxUploadBytes();
        return $php > 0 ? min($php, $total) : $total;
    }

    public static function maxFiles(): int
    {
        return max(1, min(20, (int) self::get('max_files')));
    }

    public static function attachLimitBytes(): int
    {
        return max(0, (int) self::get('attach_limit_mb')) * MB_IN_BYTES;
    }

    /** URL of the privacy page: the setting, else the WordPress privacy page. */
    public static function privacyUrl(): string
    {
        $id = (int) self::get('privacy_page');
        if ($id && get_post_status($id) === 'publish') {
            return (string) get_permalink($id);
        }
        $url = (string) get_privacy_policy_url();
        if ($url === '') {
            $page = get_page_by_path('datenschutz');
            if ($page && $page->post_status === 'publish') {
                $url = (string) get_permalink($page);
            }
        }
        return $url;
    }

    /**
     * "So geht es weiter" text. The contact variant (Kontakt page) has its own
     * text, because the product text promises an offer.
     */
    public static function successText(string $variant = 'product'): string
    {
        $key = $variant === 'contact' ? 'success_text_contact' : 'success_text';
        $text = trim((string) self::get($key));
        return $text !== '' ? $text : (string) self::defaults()[$key];
    }

    /**
     * Save handler for the settings tab. Called from render() on POST with a
     * valid nonce; the tab itself is only reachable with manage_options.
     *
     * @param array<string, mixed> $post Raw $_POST.
     */
    private static function save(array $post): void
    {
        $recipients = array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($post['recipient'] ?? '')))));
        $valid = array_filter($recipients, 'is_email');

        $data = [
            'recipient'       => implode(', ', array_map('sanitize_email', $valid)),
            'max_upload_mb'   => max(1, min(self::MAX_UPLOAD_CAP_MB, absint($post['max_upload_mb'] ?? 20))),
            'max_files'       => max(1, min(20, absint($post['max_files'] ?? 10))),
            'attach_limit_mb' => max(0, min(self::MAX_UPLOAD_CAP_MB, absint($post['attach_limit_mb'] ?? 10))),
            'success_text'    => sanitize_textarea_field(wp_unslash($post['success_text'] ?? '')),
            'success_text_contact' => sanitize_textarea_field(wp_unslash($post['success_text_contact'] ?? '')),
            'customer_mail'   => !empty($post['customer_mail']),
            'privacy_page'    => absint($post['privacy_page'] ?? 0),
        ];
        update_option(self::OPTION, $data, false);

        if (count($valid) !== count($recipients)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Mindestens eine Empfängeradresse war ungültig und wurde nicht gespeichert.', 'infoplus-suite') . '</p></div>';
        }
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['infoplus_inquiry_settings_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['infoplus_inquiry_settings_nonce'])), 'infoplus_inquiry_settings_save')) {
            self::save($_POST);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Einstellungen gespeichert.', 'infoplus-suite') . '</p></div>';
        }

        $s = self::all();
        $list_url = admin_url('edit.php?post_type=' . PostType::TYPE);
        $php_max = size_format((int) wp_max_upload_size());
        ?>
        <style>
            .ip-inq-settings { max-width: 900px; margin-top: 20px }
            .ip-inq-settings .infoplus-settings-section { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px 24px; margin-bottom: 20px }
            .ip-inq-settings h3 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e5e5; display: flex; align-items: center; gap: 8px }
            .ip-inq-settings h3 .dashicons { color: #2271b1 }
            .ip-inq-settings .form-table th { width: 220px }
            .ip-inq-settings textarea { width: 100%; max-width: 560px }
        </style>
        <div class="ip-inq-settings">
            <p>
                <?php esc_html_e('Das Anfrageformular „Individuelle Anfrage“ (Produktseite, Kontakt) speichert jede Anfrage unter WooCommerce → Anfragen und schickt sie per E-Mail an das Team.', 'infoplus-suite'); ?>
                <a href="<?php echo esc_url($list_url); ?>"><?php esc_html_e('Zu den Anfragen', 'infoplus-suite'); ?></a>
            </p>
            <form method="post">
                <?php wp_nonce_field('infoplus_inquiry_settings_save', 'infoplus_inquiry_settings_nonce'); ?>
                <div class="infoplus-settings-section">
                    <h3><span class="dashicons dashicons-email"></span><?php esc_html_e('E-Mail', 'infoplus-suite'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ip-inq-recipient"><?php esc_html_e('Empfänger (Team)', 'infoplus-suite'); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="ip-inq-recipient" name="recipient" value="<?php echo esc_attr((string) $s['recipient']); ?>" placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>">
                                <p class="description"><?php esc_html_e('Mehrere Adressen mit Komma trennen. Leer: die Administrator-E-Mail der Website.', 'infoplus-suite'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bestätigung an den Kunden', 'infoplus-suite'); ?></th>
                            <td><label><input type="checkbox" name="customer_mail" value="1" <?php checked(!empty($s['customer_mail'])); ?>> <?php esc_html_e('Eingangsbestätigung mit Referenznummer senden', 'infoplus-suite'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ip-inq-attach"><?php esc_html_e('Dateien anhängen bis (MB)', 'infoplus-suite'); ?></label></th>
                            <td>
                                <input type="number" min="0" max="<?php echo (int) self::MAX_UPLOAD_CAP_MB; ?>" id="ip-inq-attach" name="attach_limit_mb" value="<?php echo (int) $s['attach_limit_mb']; ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Sind die Dateien zusammen größer, enthält die Team-E-Mail Links zum Download (Anmeldung im Backend nötig) statt Anhängen. 0 = nie anhängen.', 'infoplus-suite'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="infoplus-settings-section">
                    <h3><span class="dashicons dashicons-upload"></span><?php esc_html_e('Dateien', 'infoplus-suite'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ip-inq-max"><?php esc_html_e('Upload gesamt höchstens (MB)', 'infoplus-suite'); ?></label></th>
                            <td>
                                <input type="number" min="1" max="<?php echo (int) self::MAX_UPLOAD_CAP_MB; ?>" id="ip-inq-max" name="max_upload_mb" value="<?php echo (int) $s['max_upload_mb']; ?>" class="small-text">
                                <p class="description"><?php
                                    /* translators: %s: PHP upload limit */
                                    printf(esc_html__('Eine einzelne Datei ist zusätzlich durch das PHP-Limit des Servers begrenzt (zurzeit %s).', 'infoplus-suite'), esc_html($php_max));
                                ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ip-inq-files"><?php esc_html_e('Höchstens Dateien', 'infoplus-suite'); ?></label></th>
                            <td><input type="number" min="1" max="20" id="ip-inq-files" name="max_files" value="<?php echo (int) $s['max_files']; ?>" class="small-text"></td>
                        </tr>
                    </table>
                </div>
                <div class="infoplus-settings-section">
                    <h3><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e('Texte und Datenschutz', 'infoplus-suite'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ip-inq-success"><?php esc_html_e('„So geht es weiter“', 'infoplus-suite'); ?></label></th>
                            <td>
                                <textarea id="ip-inq-success" name="success_text" rows="3"><?php echo esc_textarea((string) $s['success_text']); ?></textarea>
                                <p class="description"><?php esc_html_e('Erscheint nach dem Absenden und in der Bestätigungs-E-Mail. Leer: Standardtext.', 'infoplus-suite'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ip-inq-success-contact"><?php esc_html_e('„So geht es weiter“ (Kontaktseite)', 'infoplus-suite'); ?></label></th>
                            <td>
                                <textarea id="ip-inq-success-contact" name="success_text_contact" rows="3"><?php echo esc_textarea((string) $s['success_text_contact']); ?></textarea>
                                <p class="description"><?php esc_html_e('Für Nachrichten über das Formular der Kontaktseite. Leer: Standardtext.', 'infoplus-suite'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ip-inq-privacy"><?php esc_html_e('Datenschutzseite', 'infoplus-suite'); ?></label></th>
                            <td>
                                <?php
                                wp_dropdown_pages([
                                    'name'              => 'privacy_page',
                                    'id'                => 'ip-inq-privacy',
                                    'selected'          => (int) $s['privacy_page'],
                                    'show_option_none'  => __('Datenschutzseite von WordPress', 'infoplus-suite'),
                                    'option_none_value' => '0',
                                ]);
                                ?>
                                <p class="description"><?php esc_html_e('Ziel des Links neben der Einwilligung im Formular.', 'infoplus-suite'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(__('Einstellungen speichern', 'infoplus-suite')); ?>
            </form>
        </div>
        <?php
    }
}
