<?php
/**
 * Updates from GitHub releases via Plugin Update Checker (vendor/, shipped in
 * the release zip). Missing library or any error: no update checks, nothing
 * else changes.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Updater {

	const REPOSITORY = 'https://github.com/markusritschel/divi5-svg-filters/';

	/** Release asset built by .github/workflows/release.yml (contains vendor/). */
	const ASSET = '/^divi5-svg-filters-v[\d.]+\.zip$/';

	public static function register() {
		$factory = '\YahnisElsts\PluginUpdateChecker\v5\PucFactory';
		if ( ! class_exists( $factory ) ) {
			return;
		}
		try {
			$checker = $factory::buildUpdateChecker( self::REPOSITORY, D5SVGF_FILE, 'divi5-svg-filters' );
			$api     = $checker->getVcsApi();
			if ( method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets( self::ASSET );
			}
		} catch ( \Throwable $e ) {
			return; // updates are a convenience; never let them break the site
		}
	}
}
