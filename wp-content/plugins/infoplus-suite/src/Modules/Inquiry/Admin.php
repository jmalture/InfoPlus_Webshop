<?php
/**
 * wp-admin screens of the inquiries: list columns, status filter, a
 * readable detail meta box, the status box, file downloads and the "Neu"
 * count bubble in the menu.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

if (!defined('ABSPATH')) exit;

final class Admin
{
    public function register(): void
    {
        $type = PostType::TYPE;
        add_filter("manage_{$type}_posts_columns", [$this, 'columns']);
        add_action("manage_{$type}_posts_custom_column", [$this, 'column'], 10, 2);
        add_filter("manage_edit-{$type}_sortable_columns", [$this, 'sortable']);
        add_action('restrict_manage_posts', [$this, 'statusFilter']);
        add_action('pre_get_posts', [$this, 'query']);
        add_filter('post_row_actions', [$this, 'rowActions'], 10, 2);
        add_filter('bulk_actions-edit-' . $type, [$this, 'bulkActions']);
        add_action("add_meta_boxes_{$type}", [$this, 'metaBoxes']);
        add_action("save_post_{$type}", [$this, 'saveStatus'], 10, 2);
        add_action('before_delete_post', [$this, 'deleteFiles']);
        add_action('admin_post_infoplus_inquiry_file', [$this, 'downloadFile']);
        add_action('admin_menu', [$this, 'menuBubble'], 99);
        add_action('admin_head', [$this, 'styles']);
        add_filter('wp_insert_post_data', [$this, 'keepPrivate'], 10, 2);
        add_filter('display_post_states', [$this, 'postStates'], 10, 2);
    }

    public function columns(array $cols): array
    {
        return [
            'cb'             => $cols['cb'] ?? '',
            'title'          => __('Referenz', 'infoplus-suite'),
            'ip_status'      => __('Status', 'infoplus-suite'),
            'ip_customer'    => __('Firma / Name', 'infoplus-suite'),
            'ip_product'     => __('Produkt', 'infoplus-suite'),
            'ip_qty'         => __('Menge', 'infoplus-suite'),
            'ip_files'       => __('Dateien', 'infoplus-suite'),
            'ip_date'        => __('Eingang', 'infoplus-suite'),
        ];
    }

    public function sortable(array $cols): array
    {
        $cols['ip_status'] = 'ip_status';
        $cols['ip_date'] = ['date', true];
        return $cols;
    }

    public function column(string $col, int $post_id): void
    {
        $d = PostType::data($post_id);
        $c = $d['contact'] ?? [];
        switch ($col) {
            case 'ip_status':
                $s = PostType::status($post_id);
                echo '<span class="ip-inq-status ip-inq-status--' . esc_attr($s) . '">' . esc_html(PostType::statuses()[$s]) . '</span>';
                break;
            case 'ip_customer':
                if (!empty($c['company'])) {
                    echo '<strong>' . esc_html($c['company']) . '</strong><br>';
                }
                echo esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
                if (!empty($c['email'])) {
                    echo '<br><a href="' . esc_url('mailto:' . $c['email']) . '">' . esc_html($c['email']) . '</a>';
                }
                break;
            case 'ip_product':
                $p = $d['product'] ?? [];
                if (empty($p['name'])) {
                    echo '<span class="ip-inq-muted">' . esc_html__('Allgemeine Anfrage', 'infoplus-suite') . '</span>';
                    break;
                }
                echo esc_html($p['name']);
                if (!empty($p['model']['name'])) {
                    echo '<br><span class="ip-inq-muted">' . esc_html($p['model']['name']) . '</span>';
                }
                break;
            case 'ip_qty':
                echo isset($d['quantity']) && $d['quantity'] ? esc_html(number_format_i18n((int) $d['quantity'])) : '-';
                if (!empty($d['recurring'])) {
                    echo '<br><span class="ip-inq-muted">' . esc_html__('regelmäßig', 'infoplus-suite') . '</span>';
                }
                break;
            case 'ip_files':
                echo esc_html((string) count($d['files'] ?? []));
                break;
            case 'ip_date':
                echo esc_html(get_the_date('d.m.Y', $post_id) . ' ' . get_the_time('H:i', $post_id));
                break;
        }
    }

    public function statusFilter(string $post_type): void
    {
        if ($post_type !== PostType::TYPE) {
            return;
        }
        $current = isset($_GET['ip_status']) ? sanitize_key(wp_unslash($_GET['ip_status'])) : '';
        echo '<label class="screen-reader-text" for="ip-inq-status-filter">' . esc_html__('Nach Status filtern', 'infoplus-suite') . '</label>';
        echo '<select name="ip_status" id="ip-inq-status-filter"><option value="">' . esc_html__('Alle Status', 'infoplus-suite') . '</option>';
        foreach (PostType::statuses() as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($current, $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function query(\WP_Query $q): void
    {
        if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== PostType::TYPE) {
            return;
        }
        $meta = [];
        $status = isset($_GET['ip_status']) ? sanitize_key(wp_unslash($_GET['ip_status'])) : '';
        if ($status !== '' && isset(PostType::statuses()[$status])) {
            $meta[] = ['key' => PostType::META_STATUS, 'value' => $status];
        }
        // Searching an e-mail address finds the inquiry by its sender.
        $s = (string) $q->get('s');
        if ($s !== '' && is_email($s)) {
            $meta[] = ['key' => PostType::META_EMAIL, 'value' => $s];
            $q->set('s', '');
        }
        if ($meta) {
            $q->set('meta_query', $meta);
        }
        if ($q->get('orderby') === 'ip_status') {
            $q->set('meta_key', PostType::META_STATUS);
            $q->set('orderby', 'meta_value');
        }
    }

    public function rowActions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type === PostType::TYPE) {
            unset($actions['inline hide-if-no-js'], $actions['view']);
            if (isset($actions['edit'])) {
                $actions['edit'] = '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html__('Öffnen', 'infoplus-suite') . '</a>';
            }
        }
        return $actions;
    }

    /** Every inquiry is private; the "Private" label in the list says nothing. */
    public function postStates(array $states, \WP_Post $post): array
    {
        if ($post->post_type === PostType::TYPE) {
            unset($states['private']);
        }
        return $states;
    }

    public function bulkActions(array $actions): array
    {
        unset($actions['edit']);
        return $actions;
    }

    /** The record is never edited, only its status: keep it private whatever the publish box sends. */
    public function keepPrivate(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') === PostType::TYPE && !in_array($data['post_status'], ['trash', 'auto-draft'], true)) {
            $data['post_status'] = 'private';
            // Saving the status in wp-admin must not make the admin the author (author = customer account or 0).
            if (!empty($postarr['ID'])) {
                $data['post_author'] = (int) get_post_field('post_author', (int) $postarr['ID']);
            }
        }
        return $data;
    }

    public function metaBoxes(\WP_Post $post): void
    {
        remove_meta_box('submitdiv', PostType::TYPE, 'side');
        remove_meta_box('slugdiv', PostType::TYPE, 'normal');
        add_meta_box('ip-inquiry-detail', __('Anfrage', 'infoplus-suite'), [$this, 'renderDetail'], PostType::TYPE, 'normal', 'high');
        add_meta_box('ip-inquiry-status', __('Status', 'infoplus-suite'), [$this, 'renderStatus'], PostType::TYPE, 'side', 'high');
    }

    public function renderStatus(\WP_Post $post): void
    {
        $d = PostType::data($post->ID);
        $current = PostType::status($post->ID);
        $mail = get_post_meta($post->ID, PostType::META_MAIL, true);
        wp_nonce_field('ip_inquiry_status_' . $post->ID, 'ip_inquiry_status_nonce');
        ?>
        <p><label for="ip-inq-status" class="screen-reader-text"><?php esc_html_e('Status', 'infoplus-suite'); ?></label>
            <select name="ip_inquiry_status" id="ip-inq-status" style="width:100%">
                <?php foreach (PostType::statuses() as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($current, $slug); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></p>
        <p><button type="submit" class="button button-primary" name="save" value="1"><?php esc_html_e('Status speichern', 'infoplus-suite'); ?></button></p>
        <?php if (!empty($d['contact']['email'])) : ?>
            <p><a class="button" href="<?php echo esc_url('mailto:' . $d['contact']['email'] . '?subject=' . rawurlencode(sprintf(__('Ihre Anfrage %s', 'infoplus-suite'), $d['reference'] ?? ''))); ?>"><?php esc_html_e('Per E-Mail antworten', 'infoplus-suite'); ?></a></p>
        <?php endif; ?>
        <hr>
        <p class="ip-inq-muted">
            <?php
            echo esc_html__('Eingang:', 'infoplus-suite') . ' ' . esc_html(get_the_date('d.m.Y H:i', $post));
            if (is_array($mail)) {
                foreach (['team' => __('E-Mail an das Team', 'infoplus-suite'), 'customer' => __('Bestätigung an den Kunden', 'infoplus-suite')] as $k => $label) {
                    if (empty($mail[$k])) {
                        continue;
                    }
                    $ok = !empty($mail[$k]['sent']);
                    echo '<br>' . esc_html($label) . ': <strong style="color:' . ($ok ? '#15803D' : '#B91C1C') . '">' . esc_html($ok ? __('gesendet', 'infoplus-suite') : __('fehlgeschlagen', 'infoplus-suite')) . '</strong>';
                    if (!empty($mail[$k]['to'])) {
                        echo ' <span>(' . esc_html((string) $mail[$k]['to']) . ')</span>';
                    }
                }
            }
            ?>
        </p>
        <p><a class="submitdelete" style="color:#b32d2e" href="<?php echo esc_url(get_delete_post_link($post->ID)); ?>"><?php esc_html_e('In den Papierkorb', 'infoplus-suite'); ?></a></p>
        <?php
    }

    public function renderDetail(\WP_Post $post): void
    {
        $d = PostType::data($post->ID);
        if (!$d) {
            echo '<p>' . esc_html__('Keine Daten gespeichert.', 'infoplus-suite') . '</p>';
            return;
        }
        $p = $d['product'] ?? [];
        $c = $d['contact'] ?? [];
        $row = static function (string $label, string $html): void {
            if ($html === '') {
                return;
            }
            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- callers pass escaped HTML.
        };
        $yes = static function ($v): string {
            return $v ? esc_html__('Ja', 'infoplus-suite') : esc_html__('Nein', 'infoplus-suite');
        };
        ?>
        <div class="ip-inq-detail">
            <h3><?php echo esc_html($d['reference'] ?? ''); ?> <span class="ip-inq-status ip-inq-status--<?php echo esc_attr(PostType::status($post->ID)); ?>"><?php echo esc_html(PostType::statuses()[PostType::status($post->ID)]); ?></span></h3>

            <h4><?php esc_html_e('Projekt', 'infoplus-suite'); ?></h4>
            <?php if (!empty($p['name'])) : ?>
                <div class="ip-inq-product">
                    <?php if (!empty($p['image'])) : ?><img src="<?php echo esc_url($p['image']); ?>" alt="" width="72" height="72"><?php endif; ?>
                    <div>
                        <strong><?php echo !empty($p['url']) ? '<a href="' . esc_url($p['url']) . '" target="_blank" rel="noopener">' . esc_html($p['name']) . '</a>' : esc_html($p['name']); ?></strong>
                        <?php if (!empty($p['id'])) : ?> <a href="<?php echo esc_url((string) get_edit_post_link((int) $p['id'])); ?>" class="ip-inq-muted"><?php esc_html_e('(Produkt bearbeiten)', 'infoplus-suite'); ?></a><?php endif; ?>
                        <?php if (empty($p['verified'])) : ?><br><span class="ip-inq-muted"><?php esc_html_e('Produktname vom Kontaktformular, nicht geprüft.', 'infoplus-suite'); ?></span><?php endif; ?>
                        <?php if (!empty($p['sku'])) : ?><br><span class="ip-inq-muted"><?php echo esc_html__('Artikelnummer:', 'infoplus-suite') . ' ' . esc_html($p['sku']); ?></span><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <table class="widefat striped ip-inq-table"><tbody>
                <?php
                if (!empty($p['model']['name'])) {
                    $row(__('Modell', 'infoplus-suite'), esc_html($p['model']['name']) . (!empty($p['order_type']) ? ' <span class="ip-inq-muted">(' . esc_html($p['order_type']) . ')</span>' : ''));
                }
                foreach (($p['options'] ?? []) as $o) {
                    $row((string) $o['label'], esc_html((string) $o['value']));
                }
                if (!empty($p['variation_id'])) {
                    $row(__('Variante', 'infoplus-suite'), '#' . (int) $p['variation_id'] . (!empty($p['variation_sku']) ? ' · ' . esc_html($p['variation_sku']) : ''));
                }
                $row(__('Menge', 'infoplus-suite'), !empty($d['quantity']) ? esc_html(number_format_i18n((int) $d['quantity'])) : '-');
                $row(__('Regelmäßiger Bedarf', 'infoplus-suite'), $yes(!empty($d['recurring'])));
                $row(__('Wunschtermin', 'infoplus-suite'), !empty($d['wish_date']) ? esc_html(mysql2date('d.m.Y', $d['wish_date'])) : '-');
                $row(__('Kostenlose Muster', 'infoplus-suite'), $yes(!empty($d['samples'])));
                $row(__('Nachricht', 'infoplus-suite'), !empty($d['message']) ? nl2br(esc_html($d['message'])) : '-');
                ?>
            </tbody></table>

            <h4><?php esc_html_e('Dateien', 'infoplus-suite'); ?></h4>
            <?php if (!empty($d['files'])) : ?>
                <ul class="ip-inq-files">
                    <?php foreach (self::fileLinks($post->ID, $d['files']) as $link) : ?>
                        <li><span class="dashicons dashicons-media-default" aria-hidden="true"></span> <a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['name']); ?></a> <span class="ip-inq-muted"><?php echo esc_html($link['size']); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="ip-inq-muted"><?php esc_html_e('Keine Dateien.', 'infoplus-suite'); ?></p>
            <?php endif; ?>

            <h4><?php esc_html_e('Kontakt', 'infoplus-suite'); ?></h4>
            <table class="widefat striped ip-inq-table"><tbody>
                <?php
                $row(__('Firma', 'infoplus-suite'), esc_html($c['company'] ?? '') ?: '-');
                $row(__('Name', 'infoplus-suite'), esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))));
                $row(__('E-Mail', 'infoplus-suite'), !empty($c['email']) ? '<a href="' . esc_url('mailto:' . $c['email']) . '">' . esc_html($c['email']) . '</a>' : '');
                $row(__('Telefon', 'infoplus-suite'), !empty($c['phone']) ? '<a href="' . esc_url('tel:' . preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . esc_html($c['phone']) . '</a>' : '-');
                $row(__('PLZ / Land', 'infoplus-suite'), esc_html(trim(($c['postcode'] ?? '') . ' ' . ($c['country_name'] ?? ''))) ?: '-');
                if (!empty($d['user_id'])) {
                    $u = get_userdata((int) $d['user_id']);
                    $row(__('Kundenkonto', 'infoplus-suite'), $u ? '<a href="' . esc_url(get_edit_user_link($u->ID)) . '">' . esc_html($u->display_name) . '</a>' : '#' . (int) $d['user_id']);
                }
                ?>
            </tbody></table>

            <h4><?php esc_html_e('Technisch', 'infoplus-suite'); ?></h4>
            <table class="widefat striped ip-inq-table"><tbody>
                <?php
                $row(__('Einwilligung Datenschutz', 'infoplus-suite'), !empty($d['consent']['time']) ? esc_html(wp_date('d.m.Y H:i:s', strtotime($d['consent']['time']))) . (!empty($d['consent']['privacy_url']) ? ' · <a href="' . esc_url($d['consent']['privacy_url']) . '" target="_blank" rel="noopener">' . esc_html__('Datenschutzseite', 'infoplus-suite') . '</a>' : '') : '-');
                $row(__('Gesendet von', 'infoplus-suite'), !empty($d['source_url']) ? '<a href="' . esc_url($d['source_url']) . '" target="_blank" rel="noopener">' . esc_html($d['source_url']) . '</a>' : '-');
                $row(__('Formular', 'infoplus-suite'), esc_html(($d['variant'] ?? '') === 'contact' ? __('Kontakt', 'infoplus-suite') : __('Produktanfrage', 'infoplus-suite')));
                ?>
            </tbody></table>
        </div>
        <?php
    }

    public function saveStatus(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['ip_inquiry_status_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ip_inquiry_status_nonce'])), 'ip_inquiry_status_' . $post_id)
            || !current_user_can('edit_post', $post_id)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $status = sanitize_key(wp_unslash($_POST['ip_inquiry_status'] ?? ''));
        if (isset(PostType::statuses()[$status])) {
            update_post_meta($post_id, PostType::META_STATUS, $status);
        }
    }

    public function deleteFiles(int $post_id): void
    {
        if (get_post_type($post_id) === PostType::TYPE) {
            Files::deleteStored(PostType::data($post_id)['files'] ?? []);
        }
    }

    /**
     * Download links through admin-post.php. Capability checked on every
     * request; the stored path is never part of the URL, only the index.
     *
     * @return array<int, array{name:string,url:string,size:string}>
     */
    public static function fileLinks(int $post_id, array $files): array
    {
        $out = [];
        foreach (array_values($files) as $i => $f) {
            $out[] = [
                'name' => (string) $f['name'],
                'url'  => add_query_arg(['action' => 'infoplus_inquiry_file', 'post' => $post_id, 'file' => $i], admin_url('admin-post.php')),
                'size' => size_format((int) $f['size']),
            ];
        }
        return $out;
    }

    public function downloadFile(): void
    {
        $post_id = absint($_GET['post'] ?? 0);
        $index = absint($_GET['file'] ?? 0);
        if (!$post_id || get_post_type($post_id) !== PostType::TYPE || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('Sie dürfen diese Datei nicht herunterladen.', 'infoplus-suite'), '', ['response' => 403]);
        }
        $files = array_values(PostType::data($post_id)['files'] ?? []);
        $abs = isset($files[$index]) ? Files::absolute((string) $files[$index]['file']) : '';
        if ($abs === '') {
            wp_die(esc_html__('Datei nicht gefunden.', 'infoplus-suite'), '', ['response' => 404]);
        }
        $name = sanitize_file_name((string) $files[$index]['name']);
        nocache_headers();
        // Always a download, never rendered in the admin's browser (SVG can carry script).
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . filesize($abs));
        header('X-Content-Type-Options: nosniff');
        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($abs);
        exit;
    }

    public function menuBubble(): void
    {
        global $submenu;
        $parent = class_exists('WooCommerce') ? 'woocommerce' : 'infoplus-suite';
        if (empty($submenu[$parent])) {
            return;
        }
        $n = PostType::countNew();
        if (!$n) {
            return;
        }
        foreach ($submenu[$parent] as &$item) {
            if (($item[2] ?? '') === 'edit.php?post_type=' . PostType::TYPE) {
                $item[0] .= ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n($n) . '</span></span>';
                break;
            }
        }
    }

    public function styles(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== PostType::TYPE) {
            return;
        }
        ?>
        <style>
            .ip-inq-status { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px 2px 8px; border-radius: 999px; background: #EFEFF6; font-size: 12px; font-weight: 600; line-height: 1.8; white-space: nowrap; vertical-align: middle }
            .ip-inq-status::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--ip-s, #6B7075) }
            .ip-inq-status--neu { --ip-s: #E6007E; background: #FDEBF4; color: #9E0056 }
            .ip-inq-status--in-bearbeitung { --ip-s: #1D4ED8 }
            .ip-inq-status--angebot-gesendet { --ip-s: #C2410C }
            .ip-inq-status--erledigt { --ip-s: #15803D }
            .ip-inq-muted { color: #646970 }
            .column-ip_status { width: 150px } .column-ip_date { width: 130px } .column-ip_qty, .column-ip_files { width: 80px }
            .ip-inq-detail h3 { display: flex; align-items: center; gap: 12px; margin: 4px 0 12px; font-size: 18px }
            .ip-inq-detail h4 { margin: 20px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .5px; color: #50575e }
            .ip-inq-table th { width: 200px; font-weight: 600 }
            .ip-inq-product { display: flex; gap: 12px; align-items: center; margin-bottom: 10px }
            .ip-inq-product img { width: 72px; height: 72px; object-fit: contain; border: 1px solid #DEDEE9; border-radius: 8px; background: #fff }
            .ip-inq-files { margin: 0 } .ip-inq-files li { display: flex; gap: 6px; align-items: center }
            .post-type-<?php echo esc_attr(PostType::TYPE); ?> #titlediv, .post-type-<?php echo esc_attr(PostType::TYPE); ?> .page-title-action { display: none }
        </style>
        <?php
    }
}
