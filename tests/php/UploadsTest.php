<?php
/**
 * Upload storage: import through the sanitizer, delete, feed into the kit.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

use D5SVGF\Uploads;
use PHPUnit\Framework\TestCase;

final class UploadsTest extends TestCase {

	private const GLOW = '<svg xmlns="http://www.w3.org/2000/svg"><filter id="my-glow"><title>Glow</title><feGaussianBlur id="my-glow-blur" stdDeviation="3"/></filter></svg>';

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
		require_once __DIR__ . '/../../includes/Sanitizer.php';
		require_once __DIR__ . '/../../includes/Uploads.php';
	}

	private function with_kit(): void {
		add_filter( 'svgf_extra_sources', array( Uploads::class, 'sources' ) );
		require_once __DIR__ . '/../../kit/svg-filters.php';
	}

	public function test_import_stores_clean_filters_and_reports_skipped_ones(): void {
		$this->with_kit();
		$r = Uploads::import( self::GLOW, 'glow.svg' );

		$this->assertSame( array( 'my-glow' ), array_column( $r['filters'], 'id' ) );
		$stored = Uploads::all()['my-glow'];
		$this->assertSame( 'Glow', $stored['title'] );
		$this->assertSame( 'glow.svg', $stored['file'] );
		$this->assertStringStartsWith( '<filter id="my-glow">', $stored['markup'] );
		$this->assertSame( array( 'my-glow' ), Uploads::ids() );
	}

	public function test_kit_ids_and_inner_ids_are_taken(): void {
		$this->with_kit();
		$taken = Uploads::taken_ids();

		$this->assertContains( 'fx-duotone', $taken );
		$this->assertContains( 'fx-liquid', $taken, 'glass too' );
		$this->assertContains( 'fx-spot-light', $taken, 'inner primitive IDs' );

		$r = Uploads::import( '<svg xmlns="http://www.w3.org/2000/svg"><filter id="fx-duotone"><feOffset dx="1"/></filter></svg>', 'x.svg' );
		$this->assertSame( array(), $r['filters'] );
		$this->assertSame( 'fx-duotone', $r['skipped'][0]['id'] );
	}

	public function test_a_second_import_of_the_same_id_is_rejected_not_replaced(): void {
		$this->with_kit();
		Uploads::import( self::GLOW, 'glow.svg' );
		$r = Uploads::import( str_replace( 'stdDeviation="3"', 'stdDeviation="9"', self::GLOW ), 'glow2.svg' );

		$this->assertSame( 'my-glow', $r['skipped'][0]['id'] );
		$this->assertStringContainsString( 'stdDeviation="3"', Uploads::all()['my-glow']['markup'] );
	}

	public function test_uploads_feed_the_kit_and_get_classes(): void {
		Uploads::import( self::GLOW, 'glow.svg' );
		$this->with_kit();

		$this->assertContains( 'my-glow', svgf_collect()['ids'] );
		$this->assertSame( 'Glow', svgf_filters()['my-glow']['title'] );
	}

	public function test_delete(): void {
		Uploads::import( self::GLOW, 'glow.svg' );

		$this->assertTrue( Uploads::delete( 'my-glow' ) );
		$this->assertSame( array(), Uploads::all() );
		$this->assertFalse( Uploads::delete( 'my-glow' ) );
	}

	public function test_broken_option_data_is_ignored(): void {
		$GLOBALS['wp_options']['d5svgf_uploads'] = array( 'ok' => array( 'markup' => '<filter id="ok"/>' ), 'bad' => 'x', 3 => array() );

		$this->assertSame( array( 'ok' ), Uploads::ids() );
		$this->assertSame( array( '<filter id="ok"/>' ), Uploads::sources( array() ) );
	}
}
