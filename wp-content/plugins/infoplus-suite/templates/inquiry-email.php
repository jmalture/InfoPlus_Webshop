<?php
/**
 * Body of the inquiry e-mails (team and customer). Mailer::send() wraps it in
 * the WooCommerce e-mail header and footer (or a small branded wrapper
 * without WooCommerce). Override with infoplus-suite/inquiry-email.php.
 *
 * Inline styles only: e-mail clients ignore style sheets. Tables for layout.
 *
 * $args:
 * - audience (string) team|customer
 * - record (array) the stored inquiry (Controller::submit())
 * - next_text (string) "So geht es weiter" text from the settings
 * - team only: post_id (int), admin_url (string), file_links (array name,url,size), attached (bool)
 *
 * @package Infoplus\Suite
 */

if (!defined('ABSPATH')) exit;

$r     = (array) $args['record'];
$team  = $args['audience'] === 'team';
$c     = (array) ($r['contact'] ?? []);
$p     = (array) ($r['product'] ?? []);
$name  = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
$th    = 'style="padding:8px 12px 8px 0;text-align:left;vertical-align:top;font-weight:600;color:#4A4E53;width:38%;border-bottom:1px solid #DEDEE9"';
$td    = 'style="padding:8px 0;text-align:left;vertical-align:top;color:#191B1C;border-bottom:1px solid #DEDEE9"';
$h     = 'style="margin:28px 0 8px;font-size:16px;line-height:1.3;color:#9E0056;text-transform:uppercase;letter-spacing:1px"';
$rows  = static function (array $pairs) use ($th, $td): void {
    echo '<table cellspacing="0" cellpadding="0" border="0" width="100%" style="width:100%;border-collapse:collapse;border:0">';
    foreach ($pairs as $pair) {
        if ($pair[1] === '' || $pair[1] === null) {
            continue;
        }
        echo '<tr><th ' . $th . '>' . esc_html($pair[0]) . '</th><td ' . $td . '>' . $pair[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values are escaped below.
    }
    echo '</table>';
};
$yes = static function ($v): string {
    return $v ? esc_html__('Ja', 'infoplus-suite') : esc_html__('Nein', 'infoplus-suite');
};

$project = [];
if (!empty($p['name'])) {
    $project[] = [__('Produkt', 'infoplus-suite'), (!empty($p['url']) ? '<a href="' . esc_url($p['url']) . '" style="color:#9E0056">' . esc_html($p['name']) . '</a>' : esc_html($p['name'])) . (!empty($p['sku']) && $team ? ' <span style="color:#4A4E53">(' . esc_html($p['sku']) . ')</span>' : '')];
}
if (!empty($p['model']['name'])) {
    $project[] = [__('Modell', 'infoplus-suite'), esc_html($p['model']['name'])];
}
foreach ((array) ($p['options'] ?? []) as $o) {
    $project[] = [(string) $o['label'], esc_html((string) $o['value'])];
}
if ($team && !empty($p['variation_sku'])) {
    $project[] = [__('Variante', 'infoplus-suite'), esc_html($p['variation_sku'])];
}
if (!empty($r['quantity'])) {
    $project[] = [__('Menge', 'infoplus-suite'), esc_html(number_format_i18n((int) $r['quantity'])) . (!empty($r['recurring']) ? ' · ' . esc_html__('regelmäßiger Bedarf', 'infoplus-suite') : '')];
}
if (!empty($r['wish_date'])) {
    $project[] = [__('Wunschtermin', 'infoplus-suite'), esc_html(mysql2date('d.m.Y', $r['wish_date']))];
}
if (($r['variant'] ?? '') !== 'contact') {
    $project[] = [__('Kostenlose Muster', 'infoplus-suite'), $yes(!empty($r['samples']))];
}
if (!empty($r['message'])) {
    $project[] = [__('Nachricht', 'infoplus-suite'), nl2br(esc_html($r['message']))];
}
if (!empty($r['files'])) {
    $list = [];
    if ($team && empty($args['attached'])) {
        foreach ((array) $args['file_links'] as $f) {
            $list[] = '<a href="' . esc_url($f['url']) . '" style="color:#9E0056">' . esc_html($f['name']) . '</a> (' . esc_html($f['size']) . ')';
        }
    } else {
        foreach ((array) $r['files'] as $f) {
            $list[] = esc_html($f['name']) . ' (' . esc_html(size_format((int) $f['size'])) . ')';
        }
    }
    $project[] = [__('Dateien', 'infoplus-suite'), implode('<br>', $list)];
}

$contact = [
    [__('Firma', 'infoplus-suite'), esc_html($c['company'] ?? '')],
    [__('Name', 'infoplus-suite'), esc_html($name)],
    [__('E-Mail', 'infoplus-suite'), '<a href="' . esc_url('mailto:' . ($c['email'] ?? '')) . '" style="color:#9E0056">' . esc_html($c['email'] ?? '') . '</a>'],
    [__('Telefon', 'infoplus-suite'), esc_html($c['phone'] ?? '')],
    [__('PLZ / Land', 'infoplus-suite'), esc_html(trim(($c['postcode'] ?? '') . ' ' . ($c['country_name'] ?? '')))],
];
?>
<div style="font-family:'Archivo Narrow','Arial Narrow',Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#191B1C">
<?php if ($team) : ?>
    <p style="margin:0 0 16px">
        <?php
        printf(
            /* translators: 1: name, 2: company */
            esc_html__('Neue Anfrage von %1$s%2$s.', 'infoplus-suite'),
            '<strong>' . esc_html($name) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput
            !empty($c['company']) ? ' (' . esc_html($c['company']) . ')' : '' // phpcs:ignore WordPress.Security.EscapeOutput
        );
        ?>
        <?php esc_html_e('Mit „Antworten“ schreiben Sie direkt an den Kunden.', 'infoplus-suite'); ?>
    </p>
    <?php if (!empty($r['files']) && empty($args['attached'])) : ?>
        <p style="margin:0 0 8px;padding:10px 12px;background:#FDEBF4;border-radius:8px"><?php esc_html_e('Die Dateien sind zusammen zu groß für den Anhang. Sie liegen in der Anfrage im Backend (Links unten, Anmeldung nötig).', 'infoplus-suite'); ?></p>
    <?php endif; ?>
<?php else : ?>
    <p style="margin:0 0 16px">
        <?php
        /* translators: %s: name */
        printf(esc_html__('Guten Tag %s,', 'infoplus-suite'), esc_html($name));
        ?>
    </p>
    <p style="margin:0 0 16px"><?php esc_html_e('vielen Dank für Ihre Anfrage. Sie ist bei uns eingegangen und hat die folgende Referenznummer:', 'infoplus-suite'); ?></p>
    <p style="margin:0 0 16px;font-size:22px;font-weight:700;letter-spacing:.5px;color:#191B1C"><?php echo esc_html($r['reference']); ?></p>
    <p style="margin:0 0 8px"><strong><?php esc_html_e('So geht es weiter:', 'infoplus-suite'); ?></strong> <?php echo esc_html((string) $args['next_text']); ?></p>
    <p style="margin:0 0 8px"><?php esc_html_e('Wenn Sie etwas ergänzen möchten, antworten Sie einfach auf diese E-Mail und nennen Sie die Referenznummer.', 'infoplus-suite'); ?></p>
<?php endif; ?>

<?php if ($project) : ?>
    <h2 <?php echo $h; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo $team ? esc_html__('Projekt', 'infoplus-suite') : esc_html__('Ihre Angaben', 'infoplus-suite'); ?></h2>
    <?php $rows($project); ?>
<?php endif; ?>

<h2 <?php echo $h; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e('Kontakt', 'infoplus-suite'); ?></h2>
<?php $rows($contact); ?>

<?php if ($team) : ?>
    <p style="margin:24px 0 0">
        <a href="<?php echo esc_url((string) $args['admin_url']); ?>" style="display:inline-block;padding:10px 18px;border-radius:10px;background:#E6007E;color:#ffffff;text-decoration:none;font-weight:600"><?php esc_html_e('Anfrage im Backend öffnen', 'infoplus-suite'); ?></a>
    </p>
<?php endif; ?>
</div>
