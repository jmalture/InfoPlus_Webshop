<?php
/**
 * Signed form token for the inquiry form.
 *
 * Why not a wp_rest nonce for guests: the product page is page-cached for
 * visitors, so a nonce printed into it goes stale and real users get
 * rejected. Instead the drawer fetches a fresh token from
 * GET /infoplus/v1/inquiries/session (no-store) when it opens. The token is
 * an HMAC over the issue time and a random value, keyed with the site salt,
 * so it cannot be forged. It also carries the time trap: the server refuses
 * a submit that comes sooner than MIN_SECONDS after the token was issued, or
 * later than MAX_AGE.
 *
 * Logged-in visitors also send the wp_rest nonce (X-WP-Nonce), which core
 * checks before the route runs; without it core treats the request as a guest.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

if (!defined('ABSPATH')) exit;

final class Token
{
    /** A person needs longer than this for two form steps. */
    public const MIN_SECONDS = 4;

    /** The drawer can stay open a long time while someone collects files. */
    public const MAX_AGE = 6 * HOUR_IN_SECONDS;

    public static function issue(): string
    {
        $payload = time() . '.' . bin2hex(random_bytes(12));
        return $payload . '.' . self::sign($payload);
    }

    /**
     * Verify signature and maximum age.
     *
     * @return int|null Issue timestamp, or null when the token is invalid or expired.
     */
    public static function verify(string $token): ?int
    {
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{24})\.([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }
        $payload = $m[1] . '.' . $m[2];
        if (!hash_equals(self::sign($payload), $m[3])) {
            return null;
        }
        $issued = (int) $m[1];
        $age = time() - $issued;
        if ($age < 0 || $age > self::MAX_AGE) {
            return null;
        }
        return $issued;
    }

    /** Short stable key for transients that belong to one token (uploads, replay guard). */
    public static function key(string $token): string
    {
        return substr(hash('sha256', $token), 0, 32);
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', 'infoplus_inquiry|' . $payload, wp_salt('nonce'));
    }
}
