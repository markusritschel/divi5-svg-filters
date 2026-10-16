<?php
/**
 * Unit test bootstrap: minimal WordPress stubs, no WordPress install.
 *
 * Hooks are recorded in $GLOBALS['wp_hooks'] and run in registration order
 * (priorities are ignored). Tests that load the plugin or the kit run in a
 * separate process, because both define constants.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WEEK_IN_SECONDS', 604800 );

$GLOBALS['wp_hooks']     = array();
$GLOBALS['current_user'] = array( 'manage_options' => true );

function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['wp_hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { return add_action( $hook, $cb, $prio, $args ); }
function do_action( $hook, ...$args ) { foreach ( $GLOBALS['wp_hooks'][ $hook ] ?? array() as $cb ) { $cb( ...$args ); } }
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['wp_hooks'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$args );
	}
	return $value;
}
function __( $text, $domain = 'default' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['current_user'][ $cap ] ); }
function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/' ) . '/'; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $c ); }
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $ttl = 0 ) { return true; }
function delete_transient( $key ) { return true; }
function get_stylesheet_directory() { return '/nonexistent/child'; }
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/themes/child'; }
function get_template_directory() { return '/nonexistent/parent'; }
function get_template_directory_uri() { return 'https://example.test/wp-content/themes/parent'; }
function plugins_url( $rel, $file ) { return 'https://example.test/wp-content/plugins/divi5-svg-filters/' . $rel; }
function wp_enqueue_style( ...$a ) {}
function wp_register_style( ...$a ) {}
function wp_enqueue_script( ...$a ) {}
function wp_add_inline_style( $handle, $css ) {}
function get_option( $name, $default = false ) { return $GLOBALS['wp_options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['wp_options'][ $name ] = $value; return true; }
function wp_json_encode( $data ) { return json_encode( $data ); }
function get_queried_object_id() { return 42; }
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/divi5-svg-filters/'; }
function wp_register_script( $handle, ...$a ) { $GLOBALS['wp_scripts'][ $handle ] = $a; return true; }
function wp_add_inline_script( $handle, $js, $position = 'after' ) { $GLOBALS['wp_inline_scripts'][ $handle ][] = array( $position, $js ); return true; }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $url ) { return esc_html( $url ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function get_edit_post_link( $id ) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit'; }
function get_the_title( $id ) { return 'Page ' . (int) $id; }
function delete_option( $name ) { unset( $GLOBALS['wp_options'][ $name ] ); return true; }
function esc_html__( $text, $domain = "default" ) { return esc_html( $text ); }
function get_current_user_id() { return 1; }

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
