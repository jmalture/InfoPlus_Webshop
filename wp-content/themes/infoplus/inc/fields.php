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
		// Plugin inactive: the default_value from the theme's acf-json files.
		$value = ip_local_field_default( $name, $post_id );
		return ( null === $value || '' === $value || array() === $value ) ? $default : $value;
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
		$value = ip_local_field_default( $name, $post_id );
		return ( null === $value || '' === $value ) ? (bool) $default : (bool) $value;
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

/**
 * default_value of a field from the theme's own acf-json files, for when the
 * field plugin is inactive (local dev, a plugin update gone wrong). Only
 * groups whose location matches the post count, with the same rule types
 * the theme's groups use: page, page_type == front_page, post_type,
 * page_template, options_page. Any other rule type never matches here.
 * Returns null when no such field exists.
 */
function ip_local_field_default( $name, $post_id = false ) {
	static $groups = null;
	if ( null === $groups ) {
		$groups = array();
		foreach ( (array) glob( IP_DIR . '/acf-json/*.json' ) as $file ) {
			$group = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local theme file.
			if ( is_array( $group ) && ! empty( $group['fields'] ) && ! empty( $group['location'] ) ) {
				$groups[] = $group;
			}
		}
	}
	$post_id = $post_id ? $post_id : get_the_ID();
	foreach ( $groups as $group ) {
		if ( ! ip_local_location_matches( $group['location'], $post_id ) ) {
			continue;
		}
		foreach ( $group['fields'] as $field ) {
			if ( isset( $field['name'] ) && $field['name'] === $name ) {
				return $field['default_value'] ?? null;
			}
		}
	}
	return null;
}

/** ACF location rules (OR of AND groups) checked without the plugin; see ip_local_field_default(). */
function ip_local_location_matches( $location, $post_id ) {
	$is_option = in_array( $post_id, array( 'option', 'options' ), true );
	$post      = $is_option ? null : get_post( (int) $post_id );
	foreach ( (array) $location as $and ) {
		$all = true;
		foreach ( (array) $and as $rule ) {
			$param = $rule['param'] ?? '';
			$value = (string) ( $rule['value'] ?? '' );
			switch ( $param ) {
				case 'page':
					$hit = $post && (string) $post->ID === $value;
					break;
				case 'page_type':
					$hit = $post && 'front_page' === $value && (int) get_option( 'page_on_front' ) === (int) $post->ID;
					break;
				case 'post_type':
					$hit = $post && $post->post_type === $value;
					break;
				case 'page_template':
					$hit = $post && ( get_page_template_slug( $post ) ? get_page_template_slug( $post ) : 'default' ) === $value;
					break;
				case 'options_page':
					$hit = $is_option;
					break;
				default:
					$hit = false;
			}
			if ( '!=' === ( $rule['operator'] ?? '==' ) ) {
				$hit = ! $hit;
			}
			if ( ! $hit ) {
				$all = false;
				break;
			}
		}
		if ( $all ) {
			return true;
		}
	}
	return false;
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
