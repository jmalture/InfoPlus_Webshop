<?php
/**
 * Field helpers (ACF / Secure Custom Fields). Templates read fields only
 * through these, never with raw get_field(), so one place handles:
 * - the field plugin being inactive (templates still render with defaults);
 * - values never saved (get_field() returns null and does NOT apply the
 *   field's own default then);
 * - yes/no switches, where false is a real value and not "empty".
 */

defined( 'ABSPATH' ) || exit;

/**
 * A field value, or $default when it is empty. A value never saved gets the
 * field's own default_value first. Do not use for true_false fields: use
 * ip_flag(), otherwise a switch defaulting to "on" can never be turned off.
 */
function ip_field( $name, $post_id = false, $default = '' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$value = get_field( $name, $post_id );
	if ( null === $value ) {
		$field = ip_field_object( $name, $post_id );
		if ( $field && isset( $field['default_value'] ) && '' !== $field['default_value'] && array() !== $field['default_value'] ) {
			return $field['default_value'];
		}
	}
	return ( null === $value || '' === $value || false === $value || array() === $value ) ? $default : $value;
}

/**
 * A yes/no switch. The default applies only when the switch was never saved.
 */
function ip_flag( $name, $post_id = false, $default = false ) {
	if ( ! function_exists( 'get_field' ) ) {
		return (bool) $default;
	}
	$value = get_field( $name, $post_id );
	if ( null === $value || '' === $value ) {
		$field = ip_field_object( $name, $post_id );
		return ( $field && isset( $field['default_value'] ) && '' !== $field['default_value'] ) ? (bool) $field['default_value'] : (bool) $default;
	}
	return (bool) $value;
}

/** A value from the options page ("Site settings"). */
function ip_opt( $name, $default = '' ) {
	return ip_field( $name, 'option', $default );
}

/** Repeater rows as a clean array (the plugin returns '' or false when empty). */
function ip_rows( $value ) {
	return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
}

/**
 * Field definition by name, searched only in the groups that apply to this
 * post (or to the options page), because generic names such as "heading"
 * repeat across groups.
 */
function ip_field_object( $name, $post_id = false ) {
	static $cache = array();
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		return null;
	}
	$post_id = $post_id ? $post_id : get_the_ID();
	$key     = $post_id . '|' . $name;
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}
	$filter = in_array( $post_id, array( 'option', 'options' ), true )
		? array( 'options_page' => 'ip-settings' ) // The options page menu_slug.
		: array( 'post_id' => (int) $post_id );
	$found = null;
	foreach ( acf_get_field_groups( $filter ) as $group ) {
		foreach ( acf_get_fields( $group ) as $f ) {
			if ( $f['name'] === $name ) {
				$found = $f;
				break 2;
			}
		}
	}
	return $cache[ $key ] = $found;
}

/** Options page for site-wide values (contact, socials, footer, default texts). */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	acf_add_options_page( array(
		'page_title' => __( 'Website-Einstellungen', 'infoplus' ),
		'menu_title' => __( 'Website-Einstellungen', 'infoplus' ),
		'menu_slug'  => 'ip-settings',
		'capability' => 'edit_pages', // Low enough for the client role.
		'position'   => 58,
		'icon_url'   => 'dashicons-admin-generic',
		'redirect'   => false,
		'autoload'   => true,
	) );
} );
