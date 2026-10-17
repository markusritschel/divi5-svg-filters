<?php
/**
 * Plugin Name:       SVG Filters for Divi 5
 * Plugin URI:        https://github.com/markusritschel/divi5-svg-filters
 * Description:       Brings the SVG Filter Kit into Divi 5's Filters options: pick an SVG filter per element, apply it to the element or its background, tune it, and upload your own filters.
 * Version:           0.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Markus Ritschel
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       divi5-svg-filters
 * Update URI:        https://github.com/markusritschel/divi5-svg-filters
 */

defined( 'ABSPATH' ) || exit;

define( 'D5SVGF_VERSION', '0.6.0' );
define( 'D5SVGF_FILE', __FILE__ );
define( 'D5SVGF_DIR', plugin_dir_path( __FILE__ ) );
define( 'D5SVGF_URL', plugin_dir_url( __FILE__ ) );

require_once D5SVGF_DIR . 'includes/Plugin.php';
require_once D5SVGF_DIR . 'includes/Updater.php';

// Composer dependencies (Plugin Update Checker); absent in a plain git checkout.
if ( is_readable( D5SVGF_DIR . 'vendor/autoload.php' ) ) {
	require_once D5SVGF_DIR . 'vendor/autoload.php';
}

D5SVGF\Plugin::boot();
D5SVGF\Updater::register(); // independent of Divi: updates keep working when Divi is off
