<?php
/**
 * Admin page: upload handling, list with preview, missing-filter warnings.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

use D5SVGF\Admin;
use D5SVGF\Uploads;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
		require_once __DIR__ . '/../../includes/Sanitizer.php';
		require_once __DIR__ . '/../../includes/Uploads.php';
		require_once __DIR__ . '/../../includes/Plugin.php';
		require_once __DIR__ . '/../../includes/Admin.php';
	}

	private function tmp( string $content ): string {
		$path = tempnam( sys_get_temp_dir(), 'd5svgf' );
		file_put_contents( $path, $content );
		return $path;
	}

	public function test_upload_messages(): void {
		$file = $this->tmp( '<svg xmlns="http://www.w3.org/2000/svg"><filter id="glow"><feOffset dx="1"/></filter><filter id="bad--id"><feOffset dx="1"/></filter></svg>' );
		$msgs = Admin::handle_upload( $file, 'glow.svg', UPLOAD_ERR_OK, filesize( $file ) );

		$this->assertSame( 'success', $msgs[0]['type'] );
		$this->assertStringContainsString( 'glow', $msgs[0]['text'] );
		$this->assertSame( 'warning', $msgs[1]['type'] );
		$this->assertStringContainsString( 'bad--id', $msgs[1]['text'] );
		$this->assertSame( array( 'glow' ), Uploads::ids() );
	}

	public function test_upload_errors(): void {
		$this->assertSame( 'error', Admin::handle_upload( '', '', UPLOAD_ERR_NO_FILE, 0 )[0]['type'] );
		$this->assertSame( 'error', Admin::handle_upload( $this->tmp( 'x' ), 'big.svg', UPLOAD_ERR_OK, 600 * 1024 )[0]['type'] );

		$msgs = Admin::handle_upload( $this->tmp( '<svg xmlns="http://www.w3.org/2000/svg"/>' ), 'empty.svg', UPLOAD_ERR_OK, 50 );
		$this->assertSame( 'error', $msgs[0]['type'] );
		$this->assertSame( array(), Uploads::ids() );
	}

	public function test_page_lists_uploads_with_preview_and_escaped_titles(): void {
		Uploads::import( '<svg xmlns="http://www.w3.org/2000/svg"><filter id="glow"><title>Glow &lt;b&gt;</title><feOffset dx="1"/></filter></svg>', 'g.svg' );
		ob_start();
		Admin::render( true );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<filter id="glow">', $html, 'defs for the preview' );
		$this->assertStringContainsString( 'filter:url(#glow)', $html );
		$this->assertStringContainsString( 'Glow &lt;b&gt;', $html );
		$this->assertStringNotContainsString( 'Glow <b>', $html );
		$this->assertStringContainsString( 'name="action" value="d5svgf_delete"', $html );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $html );
	}

	public function test_missing_filters_are_listed_until_available_again(): void {
		$GLOBALS['wp_options']['d5svgf_missing'] = array( 'fx-gone' => array( 7 => 1 ), 'glow' => array( 8 => 1 ) );
		Uploads::import( '<svg xmlns="http://www.w3.org/2000/svg"><filter id="glow"><feOffset dx="1"/></filter></svg>', 'g.svg' );
		ob_start();
		Admin::render( true );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'fx-gone', $html );
		$this->assertStringContainsString( 'post.php?post=7', $html );
		$this->assertStringNotContainsString( 'post.php?post=8', $html, 'glow exists again' );
	}

	public function test_without_kit_hooks_uploading_is_off(): void {
		ob_start();
		Admin::render( false );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'multipart/form-data', $html );
		$this->assertStringContainsString( '1.3.0', $html );
	}
}
