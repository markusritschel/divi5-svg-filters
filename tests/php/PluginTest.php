<?php
/**
 * Boot behaviour: Divi 5 detection, kit loading, theme copies, admin notices.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

use D5SVGF\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {

	private const ROOT = __DIR__ . '/../../';

	/** Load the plugin file and fire after_setup_theme, as WordPress would after the theme. */
	private function boot( ?bool $divi5 ): void {
		$GLOBALS['divi5_enabled'] = $divi5;
		if ( null !== $divi5 ) {
			require_once __DIR__ . '/fixtures/divi5-enabled.php';
		}
		require self::ROOT . 'divi5-svg-filters.php';
		do_action( 'after_setup_theme' );
	}

	private function notices(): string {
		ob_start();
		do_action( 'admin_notices' );
		return (string) ob_get_clean();
	}

	public function test_without_divi_5_it_loads_nothing_and_says_so(): void {
		$this->boot( null );

		$this->assertFalse( defined( 'SVGF_VERSION' ), 'kit not loaded' );
		$this->assertSame( '', Plugin::kit_source() );
		$this->assertFalse( Plugin::active() );
		$this->assertStringContainsString( 'Divi 5', $this->notices() );
	}

	public function test_divi_5_disabled_by_filter_counts_as_absent(): void {
		$this->boot( false );

		$this->assertFalse( defined( 'SVGF_VERSION' ) );
		$this->assertFalse( Plugin::active() );
		$this->assertStringContainsString( 'Divi 5', $this->notices() );
	}

	public function test_with_divi_5_it_loads_the_bundled_kit(): void {
		$this->boot( true );

		$this->assertTrue( Plugin::active() );
		$this->assertSame( 'plugin', Plugin::kit_source() );
		$this->assertSame( realpath( self::ROOT . 'kit' ), realpath( SVGF_DIR ) );
		$this->assertTrue( Plugin::kit_has_hooks() );
		$this->assertSame( '', $this->notices() );
	}

	public function test_a_current_theme_copy_wins_and_is_reported(): void {
		define( 'SVGF_VERSION', '1.3.0' );
		define( 'SVGF_DIR', '/themes/child/svg-filters' );
		$this->boot( true );

		$this->assertSame( 'theme', Plugin::kit_source() );
		$this->assertSame( '/themes/child/svg-filters', SVGF_DIR, 'bundled kit not loaded' );
		$this->assertTrue( Plugin::kit_has_hooks() );
		$this->assertStringContainsString( 'theme', $this->notices() );
	}

	public function test_an_old_theme_copy_disables_the_features_that_need_the_kit_hooks(): void {
		define( 'SVGF_VERSION', '1.2.1' );
		define( 'SVGF_DIR', '/themes/child/svg-filters' );
		$this->boot( true );

		$this->assertTrue( Plugin::active() );
		$this->assertFalse( Plugin::kit_has_hooks() );
		$this->assertStringContainsString( '1.2.1', $this->notices() );
	}

	public function test_frontend_starts_on_init_when_divi_has_the_needed_classes(): void {
		require_once __DIR__ . '/fixtures/divi-fakes.php';
		$this->boot( true );

		$this->assertArrayNotHasKey( 'divi_module_wrapper_render', $GLOBALS['wp_hooks'], 'Divi classes load only by init' );
		do_action( 'init' );
		$this->assertArrayHasKey( 'divi_module_wrapper_render', $GLOBALS['wp_hooks'] );
		$this->assertSame( '', $this->notices() );
	}

	public function test_unknown_divi_internals_switch_the_frontend_off_with_a_notice(): void {
		$this->boot( true );
		do_action( 'init' );

		$this->assertArrayNotHasKey( 'divi_module_wrapper_render', $GLOBALS['wp_hooks'] );
		$this->assertStringContainsString( 'Divi version', $this->notices() );
	}

	public function test_uploads_feed_the_kit_and_appear_under_custom(): void {
		require_once __DIR__ . '/fixtures/divi-fakes.php';
		$GLOBALS['wp_options']['d5svgf_uploads'] = array( 'my-glow' => array( 'title' => 'Glow', 'markup' => '<filter id="my-glow" data-category="color"><title>Glow</title><feOffset dx="1"/></filter>' ) );
		$this->boot( true );
		do_action( 'init' );

		$this->assertArrayHasKey( 'svgf_extra_sources', $GLOBALS['wp_hooks'] );
		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['wp_hooks'] );
		$this->assertSame( array( 'my-glow' => 'Glow' ), Plugin::registry()->grouped()['Custom'] );
	}

	public function test_an_old_theme_kit_gets_no_uploads(): void {
		define( 'SVGF_VERSION', '1.2.1' );
		define( 'SVGF_DIR', '/themes/child/svg-filters' );
		$this->boot( true );

		$this->assertArrayNotHasKey( 'svgf_extra_sources', $GLOBALS['wp_hooks'] );
		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['wp_hooks'], 'the page still explains why' );
	}

	public function test_notices_only_for_administrators_and_escaped(): void {
		define( 'SVGF_VERSION', '1.2.1<b>' );
		define( 'SVGF_DIR', '/themes/child/svg-filters' );
		$this->boot( true );

		$this->assertStringContainsString( '1.2.1&lt;b&gt;', $this->notices() );

		$GLOBALS['current_user'] = array();
		$this->assertSame( '', $this->notices() );
	}
}
