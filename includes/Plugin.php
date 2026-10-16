<?php
/**
 * Boot: detect Divi 5, load the SVG Filter Kit (bundled or the theme's copy),
 * collect admin notices.
 *
 * Guiding rule: when something is missing, do nothing on the frontend and
 * tell administrators why. Never fatal.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Oldest kit version with the hooks this plugin needs (extra sources/defs, svgf_filters()). */
	const MIN_KIT_VERSION = '1.3.0';

	/** @var bool Divi 5 detected and the kit loaded. */
	private static $active = false;

	/** @var string Where the kit came from: '' (not loaded), 'plugin' or 'theme'. */
	private static $kit_source = '';

	/** @var string[] Plain-text admin notices, escaped on output. */
	private static $notices = array();

	public static function boot() {
		// After the theme: its copy of the kit (functions.php) and Divi 5 are known by then.
		add_action( 'after_setup_theme', array( __CLASS__, 'setup' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'print_notices' ) );
	}

	public static function setup() {
		if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
			self::$notices[] = __( 'SVG Filters for Divi 5 needs the Divi 5 theme or builder. It does nothing until Divi 5 is active.', 'divi5-svg-filters' );
			return;
		}

		if ( defined( 'SVGF_VERSION' ) ) {
			self::$kit_source = 'theme';
			self::$notices[]  = __( 'Another copy of the SVG Filter Kit is already loaded, usually from your theme. SVG Filters for Divi 5 uses that copy; remove it from the theme so the plugin\'s copy (and its updates) apply. Pages using .fx-* classes keep working either way.', 'divi5-svg-filters' );

			if ( version_compare( SVGF_VERSION, self::MIN_KIT_VERSION, '<' ) ) {
				/* translators: 1: version of the already loaded kit, 2: required version */
				self::$notices[] = sprintf( __( 'That SVG Filter Kit is version %1$s; custom uploads and tuning need %2$s or newer and stay off until then.', 'divi5-svg-filters' ), SVGF_VERSION, self::MIN_KIT_VERSION );
			}
		} else {
			require_once D5SVGF_DIR . 'kit/svg-filters.php';
			self::$kit_source = 'plugin';
		}

		self::$active = true;

		require_once D5SVGF_DIR . 'includes/Sanitizer.php';
		require_once D5SVGF_DIR . 'includes/Uploads.php';
		require_once D5SVGF_DIR . 'includes/Admin.php';
		if ( self::kit_has_hooks() ) {
			add_filter( 'svgf_extra_sources', array( Uploads::class, 'sources' ) );
		}
		Admin::register(); // also explains when uploads are off

		add_action( 'init', array( __CLASS__, 'start' ), 20 ); // Divi's classes autoload only by init
	}

	/** Start the frontend if this Divi has the pieces it relies on. */
	public static function start() {
		require_once D5SVGF_DIR . 'includes/Tweaks.php';
		require_once D5SVGF_DIR . 'includes/Registry.php';
		require_once D5SVGF_DIR . 'includes/Frontend.php';
		require_once D5SVGF_DIR . 'includes/Builder.php';

		if ( ! Frontend::supported() ) {
			self::$notices[] = __( 'SVG Filters for Divi 5 does not recognise this Divi version, so SVG filters chosen in the builder are not applied. Pages render as without the plugin; an update of the plugin should fix this.', 'divi5-svg-filters' );
			return;
		}
		( new Frontend( array( __CLASS__, 'registry' ) ) )->register();
		( new Builder( array( __CLASS__, 'registry' ) ) )->register();
	}

	/** The filters on offer: the loaded kit's, uploads under "Custom". */
	public static function registry(): Registry {
		return Registry::from_kit( self::kit_has_hooks() ? Uploads::ids() : array() );
	}

	/** Divi 5 detected and the kit loaded. */
	public static function active(): bool {
		return self::$active;
	}

	/** '' (not loaded), 'plugin' or 'theme'. */
	public static function kit_source(): string {
		return self::$kit_source;
	}

	/** Whether the loaded kit has the extension points (needed for uploads and tuned copies). */
	public static function kit_has_hooks(): bool {
		return self::$active && defined( 'SVGF_VERSION' ) && version_compare( SVGF_VERSION, self::MIN_KIT_VERSION, '>=' );
	}

	public static function print_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::$notices as $notice ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $notice ) . '</p></div>';
		}
	}
}
