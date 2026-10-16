<?php
/**
 * Upload sanitizer: only clean <filter> elements survive (allow-list rebuild).
 */

use D5SVGF\Sanitizer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/Sanitizer.php';

final class SanitizerTest extends TestCase {

	private function svg( string $inner ): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . $inner . '</svg>';
	}

	private function one( string $inner, array $taken = array() ): array {
		return Sanitizer::filters( $this->svg( $inner ), $taken );
	}

	public function test_a_clean_filter_with_metadata_is_kept(): void {
		$r = $this->one( '<filter id="my-glow" x="-10%" color-interpolation-filters="sRGB" data-category="light">'
			. '<title>My glow</title><desc>Soft glow</desc>'
			. '<feGaussianBlur stdDeviation="4" result="b" data-tweak-stdDeviation="[0, 20] Radius"/>'
			. '<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>' );

		$this->assertSame( array(), $r['skipped'] );
		$this->assertCount( 1, $r['filters'] );
		$f = $r['filters'][0];
		$this->assertSame( 'my-glow', $f['id'] );
		$this->assertSame( 'My glow', $f['title'] );
		$this->assertStringContainsString( 'data-tweak-stdDeviation="[0, 20] Radius"', $f['markup'] );
		$this->assertStringContainsString( '<feMergeNode in="SourceGraphic"/>', $f['markup'] );
		$this->assertStringStartsWith( '<filter id="my-glow"', $f['markup'] );
		$this->assertStringNotContainsString( 'xmlns', $f['markup'] );
	}

	/** @dataProvider hostile */
	public function test_hostile_content_is_removed( string $inner, string $must_not_contain ): void {
		$r = $this->one( '<filter id="evil">' . $inner . '<feGaussianBlur stdDeviation="2"/></filter>' );

		$this->assertCount( 1, $r['filters'], 'the filter itself survives' );
		$this->assertStringNotContainsStringIgnoringCase( $must_not_contain, $r['filters'][0]['markup'] );
	}

	public function hostile(): array {
		return array(
			'script'           => array( '<script>alert(1)</script>', 'script' ),
			'event handler'    => array( '<feFlood onload="alert(1)" flood-color="red"/>', 'onload' ),
			'style attribute'  => array( '<feFlood style="background:url(x)" flood-color="red"/>', 'style' ),
			'foreignObject'    => array( '<foreignObject><div>x</div></foreignObject>', 'foreignObject' ),
			'feImage href'     => array( '<feImage href="https://evil.test/x.png"/>', 'evil.test' ),
			'xlink href'       => array( '<feTile xlink:href="#a"/>', 'href' ),
			'url in value'     => array( '<feFlood flood-color="url(https://evil.test)"/>', 'evil.test' ),
			'javascript value' => array( '<feFlood result="javascript:alert(1)"/>', 'javascript' ),
			'unknown element'  => array( '<image href="x"/><a href="x"><feFlood/></a>', '<a' ),
			'animate on href'  => array( '<feTile><animate attributeName="href" values="javascript:alert(1)"/></feTile>', 'animate' ),
			'set unknown attr' => array( '<feOffset dx="1"><set attributeName="onclick" to="x"/></feOffset>', '<set' ),
			'text in primitive' => array( '<feFlood>hello<b>bold</b></feFlood>', 'hello' ),
		);
	}

	public function test_smil_on_a_parent_attribute_is_kept(): void {
		$r = $this->one( '<filter id="anim"><feTurbulence baseFrequency=".02" seed="1"><animate attributeName="seed" values="1;2;3" dur="1s" repeatCount="indefinite"/></feTurbulence></filter>' );

		$this->assertStringContainsString( '<animate attributeName="seed" values="1;2;3" dur="1s" repeatCount="indefinite"/>', $r['filters'][0]['markup'] );
	}

	public function test_multi_filter_files_import_valid_and_report_the_rest(): void {
		$r = $this->one(
			'<filter id="ok-one"><feOffset dx="2"/></filter>'
			. '<filter id="fx-duotone"><feOffset dx="2"/></filter>'
			. '<filter><feOffset dx="2"/></filter>'
			. '<filter id="bad--bg"><feOffset dx="2"/></filter>'
			. '<filter id="empty"></filter>'
			. '<filter id="ok-one"><feOffset dx="3"/></filter>'
			. '<filter id="inner-clash"><feOffset id="fx-spot-light" dx="2"/></filter>',
			array( 'fx-duotone', 'fx-spot-light' )
		);

		$this->assertSame( array( 'ok-one' ), array_column( $r['filters'], 'id' ) );
		$this->assertSame(
			array( 'fx-duotone', '', 'bad--bg', 'empty', 'ok-one', 'inner-clash' ),
			array_column( $r['skipped'], 'id' )
		);
		foreach ( $r['skipped'] as $s ) {
			$this->assertNotSame( '', $s['reason'] );
		}
	}

	/** @dataProvider rejected_files */
	public function test_unusable_files_are_rejected( string $svg ): void {
		$r = Sanitizer::filters( $svg, array() );

		$this->assertSame( array(), $r['filters'] );
		$this->assertNotSame( '', $r['error'] );
	}

	public function rejected_files(): array {
		return array(
			'no filter'    => array( '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>' ),
			'not xml'      => array( 'GIF89a……' ),
			'doctype'      => array( '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg"><filter id="a"><feOffset dx="1"/></filter></svg>' ),
			'too large'    => array( '<svg xmlns="http://www.w3.org/2000/svg"><!--' . str_repeat( 'x', 600 * 1024 ) . '--><filter id="a"><feOffset dx="1"/></filter></svg>' ),
		);
	}
}
