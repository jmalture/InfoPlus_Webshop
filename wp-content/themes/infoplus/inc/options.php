<?php
/**
 * Site-wide settings: contact data, the floating Kontakt panel and every
 * footer text, defined once on the options page "ip-settings".
 *
 * Why the setup looks like this:
 * - The site currently runs the FREE ACF plugin: no options pages, no
 *   repeaters, no galleries. A switch to Secure Custom Fields (free, has all
 *   three) is pending. So the field group below is registered in PHP (no
 *   JSON to sync) and simply becomes visible once SCF provides the options
 *   page registered in inc/fields.php. Nothing has to be imported.
 * - Until then every value falls back to the current text of the old
 *   Elementor site (ip_setting_defaults()), so the theme renders the same
 *   footer and panel today, with no options page at all.
 * - Opening hours are a textarea ("label | value" per line), not a repeater,
 *   so the field works on free ACF and SCF alike.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current values of the old site, used while nothing has been saved yet.
 * Sources: Elementor footer template 21, snippet 14273 (Kontakt panel),
 * kit 5 (site logo 85). The same array feeds the fields' default values, so
 * the options page opens pre-filled with exactly what the site shows.
 */
function ip_setting_defaults() {
	static $defaults = null;
	if ( null === $defaults ) {
		$logo     = (int) get_theme_mod( 'custom_logo' );
		$defaults = array(
			// Tab "Kontakt" (shared by footer, Kontakt panel and header).
			'phone'                        => '+49 40 - 696 3003-0',
			'fax'                          => '+49 40 - 696 3003-13',
			'email'                        => 'info@infoplus.de',
			'company_name'                 => "Infoplus Blindow\nNamensschilder GmbH & Co. KG",
			'company_address'              => "Schierenberg 62\n22145 Hamburg\nDeutschland",
			'kontakt_panel_show'           => 1,
			'kontakt_tab_label'            => 'Kontakt',
			'kontakt_heading'              => 'Persönlich für Sie da',
			'kontakt_intro'                => 'Stellen Sie Ihre Fragen einfach und bequem per Mail oder Fax!',
			'kontakt_hours'                => "MO–DO | 8:00–17:00\nFR | 8:00–16:00",
			'kontakt_form_label'           => 'Kontakt-Formular',
			'kontakt_form_page'            => 3040,

			// Tab "Footer".
			'footer_logo'                  => $logo ? $logo : 85,
			'footer_info_title'            => 'informationen',
			'footer_legal_title'           => 'rechtliches',
			'footer_legal_note'            => 'Unser Angebot richtet sich nur an Unternehmen, Gewerbetreibende und Freiberufler – nicht an Privatpersonen.',
			'footer_contact_title'         => 'kontakt',
			'footer_payment_title'         => 'zahlungsarten',
			'footer_payment_logos'         => array( 117, 118, 119, 120, 121, 122 ),
			'footer_shipping_title'        => 'versand',
			'footer_shipping_logos'        => array( 123 ),
			'footer_security_title'        => 'sicherheit',
			'footer_security_logos'        => array( 125, 124 ),
			'footer_security_button_label' => 'Datenschutz',
			'footer_security_button_page'  => 2642,
			'footer_copyright'             => '© {jahr} {firma}',
			'footer_credit_text'           => 'Webdesign by',
			'footer_credit_name'           => 'Alture',
			'footer_credit_url'            => 'https://alture.io/',
			'footer_credit_logo'           => 3065,
			'footer_price_note'            => '*Alle Preise inkl. MwSt., zzgl. Versandkosten',
		);
		$defaults = apply_filters( 'ip_setting_defaults', $defaults );
	}
	return $defaults;
}

/**
 * A value from the options page with the old site's value as fallback.
 *
 * Unlike ip_opt(), the default applies only while the value was never saved
 * (null). A field the client empties on purpose stays empty, so the block it
 * feeds disappears ("every block renders only when filled"). The raw value is
 * read (format_value = false): image and gallery fields then give IDs, and a
 * never-saved image is null instead of false.
 */
function ip_setting( $name ) {
	$defaults = ip_setting_defaults();
	$default  = array_key_exists( $name, $defaults ) ? $defaults[ $name ] : '';
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	ip_setting_prime();
	$value = get_field( $name, 'option', false );
	return null === $value ? $default : $value;
}

/**
 * Loads every settings value with one query on the first ip_setting() call.
 * The plugin reads each field as two options (options_{name} and the field
 * key _options_{name}). Options that were never saved are not autoloaded, so
 * without this every footer and Kontakt panel value cost two queries (70 on
 * each page view, measured 2026-10-23). wp_prime_option_caches() also marks
 * the missing ones, so WordPress does not ask again. WordPress 6.4 and later.
 */
function ip_setting_prime() {
	static $done = false;
	if ( $done || ! function_exists( 'wp_prime_option_caches' ) ) {
		return;
	}
	$done  = true;
	$names = array();
	foreach ( array_keys( ip_setting_defaults() ) as $key ) {
		$names[] = 'options_' . $key;
		$names[] = '_options_' . $key;
	}
	wp_prime_option_caches( $names );
}

/** Attachment IDs from a gallery/image setting (array, single ID or "1,2,3"). */
function ip_setting_ids( $name ) {
	$value = ip_setting( $name );
	if ( is_string( $value ) ) {
		$value = explode( ',', $value );
	}
	return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
}

/**
 * URL of a page chosen in a post_object setting, or '' when the client
 * cleared it. While the setting was never saved and the default page does
 * not exist (yet), $fallback_path keeps the old link working.
 */
function ip_setting_page_url( $name, $fallback_path = '' ) {
	$id = absint( ip_setting( $name ) );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	$defaults = ip_setting_defaults();
	if ( $id && isset( $defaults[ $name ] ) && (int) $defaults[ $name ] === $id && $fallback_path ) {
		return home_url( $fallback_path );
	}
	return '';
}

/** Escaped multi-line text: each line break becomes <br>. */
function ip_lines( $text ) {
	$lines = preg_split( '/\R/', trim( (string) $text ) );
	$lines = array_filter( array_map( 'trim', $lines ), 'strlen' );
	return implode( '<br>', array_map( 'esc_html', $lines ) );
}

/** Multi-line text on one line ("Infoplus Blindow Namensschilder GmbH & Co. KG"). */
function ip_one_line( $text ) {
	return trim( preg_replace( '/\s*\R\s*/', ' ', (string) $text ) );
}

/**
 * Opening hours textarea ("MO–DO | 8:00–17:00" per line) as rows of
 * array( 'label' => ..., 'value' => ... ). A line without "|" is a value
 * without label. Empty lines are skipped.
 */
function ip_hours_rows( $text ) {
	$rows = array();
	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts  = array_map( 'trim', explode( '|', $line, 2 ) );
		$rows[] = 2 === count( $parts )
			? array( 'label' => $parts[0], 'value' => $parts[1] )
			: array( 'label' => '', 'value' => $parts[0] );
	}
	return $rows;
}

/*
 * Pre-fill never-saved values with the defaults above, for field types whose
 * own default_value ACF/SCF ignores (image, gallery, post object). The
 * options page then opens showing what the site shows, and the first save
 * stores those values instead of blanks that would hide the logos.
 */
add_filter( 'acf/load_value', function ( $value, $post_id, $field ) {
	if ( null !== $value || ! in_array( $post_id, array( 'option', 'options' ), true ) ) {
		return $value;
	}
	if ( empty( $field['key'] ) || 0 !== strpos( $field['key'], 'field_ip_opt_' ) ) {
		return $value;
	}
	$defaults = ip_setting_defaults();
	return array_key_exists( $field['name'], $defaults ) ? $defaults[ $field['name'] ] : $value;
}, 5, 3 );

/*
 * The field group. PHP-only (no acf-json), so it versions with the theme and
 * activates by itself as soon as SCF/ACF Pro provides the options page.
 * Stable keys field_ip_opt_<name>: labels can change without losing data.
 */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$d = ip_setting_defaults();

	$f = function ( $name, $type, $label, $extra = array() ) use ( $d ) {
		$field = array(
			'key'   => 'field_ip_opt_' . $name,
			'name'  => $name,
			'label' => $label,
			'type'  => $type,
		);
		if ( in_array( $type, array( 'text', 'textarea', 'email', 'url', 'true_false' ), true ) && isset( $d[ $name ] ) ) {
			$field['default_value'] = $d[ $name ];
		}
		return array_merge( $field, $extra );
	};
	$tab = function ( $key, $label ) {
		return array( 'key' => 'field_ip_opt_tab_' . $key, 'label' => $label, 'type' => 'tab', 'placement' => 'top' );
	};
	$logos = array(
		'return_format' => 'id',
		'preview_size'  => 'thumbnail',
		'library'       => 'all',
		'mime_types'    => 'png,jpg,jpeg,webp,svg',
		'instructions'  => 'Logos in der gewünschten Reihenfolge. Bitte bei jedem Bild in der Mediathek einen Alternativtext hinterlegen (z. B. „PayPal“), sonst lesen Screenreader nichts vor. Leer lassen, um den Block auszublenden.',
	);

	acf_add_local_field_group( array(
		'key'                   => 'group_ip_settings',
		'title'                 => 'Website-Einstellungen',
		'location'              => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'ip-settings' ) ) ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'seamless',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
		'fields'                => array(
			$tab( 'kontakt', 'Kontakt' ),
			$f( 'phone', 'text', 'Telefon', array(
				'instructions' => 'So, wie die Nummer angezeigt werden soll. Wird im Footer, im Kontakt-Panel und im Header verwendet; der Anruf-Link wird automatisch daraus erzeugt.',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'fax', 'text', 'Telefax', array(
				'instructions' => 'Nur im Footer. Leer lassen, um die Zeile auszublenden.',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'email', 'email', 'E-Mail', array(
				'instructions' => 'Footer und Kontakt-Panel. Die Adresse wird gegen Spam-Robots verschleiert ausgegeben.',
			) ),
			$f( 'company_name', 'textarea', 'Firmenname', array(
				'instructions' => 'Zeilenumbrüche werden im Footer übernommen. Im Copyright-Hinweis steht der Name einzeilig (Platzhalter {firma}).',
				'rows'         => 2,
				'new_lines'    => '',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'company_address', 'textarea', 'Anschrift', array(
				'instructions' => 'Eine Zeile pro Adresszeile.',
				'rows'         => 3,
				'new_lines'    => '',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'kontakt_panel_show', 'true_false', 'Kontakt-Panel anzeigen', array(
				'instructions' => 'Der Reiter „Kontakt“ am rechten Bildschirmrand (auf dem Handy ein runder Button unten rechts), der Öffnungszeiten, Telefon und E-Mail ausklappt.',
				'ui'           => 1,
				'ui_on_text'   => 'Ja',
				'ui_off_text'  => 'Nein',
			) ),
			$f( 'kontakt_tab_label', 'text', 'Beschriftung des Reiters', array(
				'instructions' => 'Auf dem Handy wird nur das Symbol gezeigt; der Text bleibt für Screenreader erhalten.',
				'wrapper'      => array( 'width' => '33' ),
			) ),
			$f( 'kontakt_heading', 'text', 'Überschrift im Panel', array(
				'wrapper' => array( 'width' => '33' ),
			) ),
			$f( 'kontakt_intro', 'textarea', 'Einleitung im Panel', array(
				'rows'      => 2,
				'new_lines' => '',
				'wrapper'   => array( 'width' => '34' ),
			) ),
			$f( 'kontakt_hours', 'textarea', 'Öffnungszeiten', array(
				'instructions' => 'Eine Zeile pro Eintrag, Tage und Uhrzeit durch einen senkrechten Strich getrennt, z. B. „MO–DO | 8:00–17:00“. Der Teil vor dem Strich wird fett gesetzt. Leer lassen, um den Block auszublenden.',
				'rows'         => 4,
				'new_lines'    => '',
			) ),
			$f( 'kontakt_form_label', 'text', 'Button im Panel: Text', array(
				'instructions' => 'Leer lassen, um den Button auszublenden.',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'kontakt_form_page', 'post_object', 'Button im Panel: Ziel', array(
				'instructions'  => 'Die Seite mit dem Kontaktformular.',
				'post_type'     => array( 'page' ),
				'return_format' => 'id',
				'allow_null'    => 1,
				'multiple'      => 0,
				'ui'            => 1,
				'wrapper'       => array( 'width' => '50' ),
			) ),

			$tab( 'footer', 'Footer' ),
			$f( 'footer_logo', 'image', 'Logo im Footer', array(
				'instructions'  => 'Wird 170 px breit angezeigt (Handy 150 px) und verlinkt auf die Startseite.',
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'library'       => 'all',
			) ),
			$f( 'footer_info_title', 'text', 'Überschrift Spalte „Informationen“', array(
				'instructions' => 'Kleine Überschrift in Großbuchstaben. Die Links darunter pflegen Sie unter Design → Menüs (Position „Footer: Informationen“).',
				'wrapper'      => array( 'width' => '33' ),
			) ),
			$f( 'footer_legal_title', 'text', 'Überschrift Spalte „Rechtliches“', array(
				'instructions' => 'Links unter Design → Menüs (Position „Footer: Rechtliches“).',
				'wrapper'      => array( 'width' => '33' ),
			) ),
			$f( 'footer_contact_title', 'text', 'Überschrift Spalte „Kontakt“', array(
				'instructions' => 'Firmenname, Anschrift, Telefon, Fax und E-Mail kommen aus dem Reiter „Kontakt“.',
				'wrapper'      => array( 'width' => '34' ),
			) ),
			$f( 'footer_legal_note', 'textarea', 'Vertrauenszeile unter dem Logo', array(
				'instructions' => 'Ein kurzer, sachlicher Satz mit Symbol (z. B. für wen das Angebot ist). Nur echte Fakten, keine Werbeversprechen.',
				'rows'         => 2,
				'new_lines'    => '',
			) ),
			$f( 'footer_payment_title', 'text', 'Überschrift Zahlungsarten', array( 'wrapper' => array( 'width' => '33' ) ) ),
			$f( 'footer_shipping_title', 'text', 'Überschrift Versand', array( 'wrapper' => array( 'width' => '33' ) ) ),
			$f( 'footer_security_title', 'text', 'Überschrift Sicherheit', array( 'wrapper' => array( 'width' => '34' ) ) ),
			$f( 'footer_payment_logos', 'gallery', 'Logos Zahlungsarten', $logos ),
			$f( 'footer_shipping_logos', 'gallery', 'Logos Versand', $logos ),
			$f( 'footer_security_logos', 'gallery', 'Logos Sicherheit', $logos ),
			$f( 'footer_security_button_label', 'text', 'Button neben den Sicherheits-Logos: Text', array(
				'instructions' => 'Leer lassen, um den Button auszublenden.',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'footer_security_button_page', 'post_object', 'Button neben den Sicherheits-Logos: Ziel', array(
				'post_type'     => array( 'page' ),
				'return_format' => 'id',
				'allow_null'    => 1,
				'multiple'      => 0,
				'ui'            => 1,
				'wrapper'       => array( 'width' => '50' ),
			) ),
			$f( 'footer_copyright', 'text', 'Copyright-Zeile', array(
				'instructions' => 'Platzhalter: {jahr} = aktuelles Jahr, {firma} = Firmenname aus dem Reiter „Kontakt“.',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'footer_price_note', 'text', 'Preishinweis', array(
				'instructions' => 'In der Leiste ganz unten, hinter dem Copyright.',
				'wrapper'      => array( 'width' => '50' ),
			) ),
			$f( 'footer_credit_text', 'text', 'Agentur-Hinweis: Text', array( 'wrapper' => array( 'width' => '25' ) ) ),
			$f( 'footer_credit_name', 'text', 'Agentur-Hinweis: Name (verlinkt)', array( 'wrapper' => array( 'width' => '25' ) ) ),
			$f( 'footer_credit_url', 'url', 'Agentur-Hinweis: Link', array( 'wrapper' => array( 'width' => '25' ) ) ),
			$f( 'footer_credit_logo', 'image', 'Agentur-Hinweis: Logo', array(
				'instructions'  => 'Klein (20 px) hinter dem Namen.',
				'return_format' => 'id',
				'preview_size'  => 'thumbnail',
				'library'       => 'all',
				'wrapper'       => array( 'width' => '25' ),
			) ),
		),
	) );
} );
