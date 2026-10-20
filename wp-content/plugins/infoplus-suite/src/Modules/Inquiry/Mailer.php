<?php
/**
 * E-mails of a new inquiry: one to the team (Reply-To the customer, files
 * attached or linked) and an optional confirmation to the customer.
 *
 * The body comes from the template `inquiry-email` (View, theme override
 * `infoplus-suite/inquiry-email.php`). With WooCommerce active it is wrapped
 * in WooCommerce's e-mail header and footer and sent through WC_Email, so it
 * gets the same styles and sender as the shop e-mails. Without WooCommerce a
 * small branded wrapper is used.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

use Infoplus\Suite\View;

if (!defined('ABSPATH')) exit;

final class Mailer
{
    /**
     * @return array{team:array{sent:bool,time:string,to:string,attached:bool},customer:array{sent:bool,time:string,to:string}|null}
     */
    public static function sendAll(int $post_id, array $record): array
    {
        $result = ['team' => null, 'customer' => null];
        try {
            $result['team'] = self::sendTeam($post_id, $record);
        } catch (\Throwable $e) {
            $result['team'] = ['sent' => false, 'time' => gmdate('c'), 'to' => '', 'attached' => false, 'error' => $e->getMessage()];
        }
        if (Settings::get('customer_mail')) {
            try {
                $result['customer'] = self::sendCustomer($record);
            } catch (\Throwable $e) {
                $result['customer'] = ['sent' => false, 'time' => gmdate('c'), 'to' => (string) $record['contact']['email'], 'error' => $e->getMessage()];
            }
        }
        return $result;
    }

    /** @return array<string, mixed> */
    public static function sendTeam(int $post_id, array $record): array
    {
        $to = Settings::recipients();
        $c = $record['contact'];
        $name = trim($c['first_name'] . ' ' . $c['last_name']);
        $product = $record['product']['name'] ?? '';

        $subject = sprintf(
            /* translators: 1: reference, 2: product or "Kontakt", 3: company or name */
            __('Neue Anfrage %1$s: %2$s (%3$s)', 'infoplus-suite'),
            $record['reference'],
            $product !== '' ? $product : __('Allgemeine Anfrage', 'infoplus-suite'),
            $c['company'] !== '' ? $c['company'] : $name
        );

        // Attach when all files together stay under the limit, else link them.
        $total = 0;
        foreach ($record['files'] as $f) {
            $total += (int) $f['size'];
        }
        $attach = $record['files'] && Settings::attachLimitBytes() > 0 && $total <= Settings::attachLimitBytes();
        $attachments = [];
        if ($attach) {
            foreach ($record['files'] as $f) {
                $abs = Files::absolute((string) $f['file']);
                if ($abs !== '') {
                    // A copy with the original name, so the attachment is not called a3f9….pdf.
                    $copy = self::namedCopy($abs, (string) $f['name']);
                    $attachments[] = $copy !== '' ? $copy : $abs;
                }
            }
        }

        $body = View::capture('inquiry-email', [
            'audience'   => 'team',
            'record'     => $record,
            'post_id'    => $post_id,
            'admin_url'  => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'file_links' => $attach ? [] : Admin::fileLinks($post_id, $record['files']),
            'attached'   => $attach,
            'next_text'  => Settings::successText(),
        ]);

        $headers = ['Reply-To: ' . self::address($name, (string) $c['email'])];
        $sent = self::send($to, $subject, __('Neue Anfrage', 'infoplus-suite') . ' ' . $record['reference'], $body, $headers, $attachments);

        foreach ($attachments as $a) {
            if (strpos($a, get_temp_dir()) === 0) {
                @unlink($a);
                @rmdir(dirname($a));
            }
        }

        return ['sent' => $sent, 'time' => gmdate('c'), 'to' => implode(', ', $to), 'attached' => $attach];
    }

    /** @return array<string, mixed> */
    public static function sendCustomer(array $record): array
    {
        $c = $record['contact'];
        $site = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $subject = sprintf(
            /* translators: 1: reference, 2: site name */
            __('Ihre Anfrage %1$s bei %2$s', 'infoplus-suite'),
            $record['reference'],
            $site
        );
        $body = View::capture('inquiry-email', [
            'audience'  => 'customer',
            'record'    => $record,
            'next_text' => Settings::successText(),
        ]);
        $team = Settings::recipients();
        $headers = $team ? ['Reply-To: ' . $team[0]] : [];
        $sent = self::send([(string) $c['email']], $subject, __('Vielen Dank für Ihre Anfrage', 'infoplus-suite'), $body, $headers, []);
        return ['sent' => $sent, 'time' => gmdate('c'), 'to' => (string) $c['email']];
    }

    /**
     * @param string[] $to
     * @param string[] $headers
     * @param string[] $attachments
     */
    private static function send(array $to, string $subject, string $heading, string $body, array $headers, array $attachments): bool
    {
        if (class_exists('WC_Email') && function_exists('WC')) {
            WC()->mailer(); // Loads the e-mail classes and registers the header/footer template actions.
            ob_start();
            do_action('woocommerce_email_header', $heading, null);
            echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- the template escapes.
            do_action('woocommerce_email_footer', null);
            $html = (string) ob_get_clean();

            $email = new \WC_Email();
            $email->email_type = 'html';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            return (bool) $email->send(implode(', ', $to), $subject, $html, $headers, $attachments);
        }

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        return (bool) wp_mail($to, $subject, self::plainWrap($heading, $body), $headers, $attachments);
    }

    private static function plainWrap(string $heading, string $body): string
    {
        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:24px;background:#EFEFF6;font-family:Arial,Helvetica,sans-serif;color:#191B1C">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #DEDEE9">'
            . '<div style="background:#E6007E;color:#fff;padding:20px 24px;font-size:22px;font-weight:bold">' . esc_html($heading) . '</div>'
            . '<div style="padding:24px">' . $body . '</div></div></body></html>';
    }

    private static function address(string $name, string $email): string
    {
        $name = trim(str_replace(['"', "\r", "\n", '<', '>'], '', $name));
        $email = str_replace(["\r", "\n"], '', $email);
        return $name !== '' ? '"' . $name . '" <' . $email . '>' : $email;
    }

    /** Copy a stored file to a temp folder under its display name, for the attachment name. */
    private static function namedCopy(string $abs, string $name): string
    {
        $dir = trailingslashit(get_temp_dir()) . 'ip-inq-' . bin2hex(random_bytes(6));
        if (!wp_mkdir_p($dir)) {
            return '';
        }
        $target = $dir . '/' . sanitize_file_name($name);
        return @copy($abs, $target) ? $target : '';
    }
}
