<?php
/**
 * Tools → SVG Filters (administrators): upload SVG filter files, list them
 * with a live preview, delete them, and see filters that pages reference but
 * that no longer exist.
 *
 * Files are read from the upload request directly and never stored as files
 * or in the Media Library; WordPress's block on SVG uploads stays untouched.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Admin {

	const SLUG = 'd5svgf';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_d5svgf_upload', array( __CLASS__, 'post_upload' ) );
		add_action( 'admin_post_d5svgf_delete', array( __CLASS__, 'post_delete' ) );
		add_action( 'admin_post_d5svgf_clear_missing', array( __CLASS__, 'post_clear_missing' ) );
	}

	public static function menu() {
		add_management_page(
			__( 'SVG Filters', 'divi5-svg-filters' ),
			__( 'SVG Filters', 'divi5-svg-filters' ),
			'manage_options',
			self::SLUG,
			static function () {
				Admin::render( Plugin::kit_has_hooks() );
			}
		);
	}

	/* ---------- admin-post handlers: capability, nonce, then redirect back ---------- */

	public static function post_upload() {
		self::guard( 'd5svgf_upload' );
		$file = $_FILES['d5svgf_file'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked in handle_upload()
		$tmp  = is_string( $file['tmp_name'] ?? null ) && is_uploaded_file( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		self::back( self::handle_upload( $tmp, sanitize_file_name( (string) ( $file['name'] ?? '' ) ), (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ), (int) ( $file['size'] ?? 0 ) ) );
	}

	public static function post_delete() {
		$id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- guard() below
		self::guard( 'd5svgf_delete_' . $id );
		self::back( array(
			Uploads::delete( $id )
				/* translators: %s: filter ID */
				? array( 'type' => 'success', 'text' => sprintf( __( 'Deleted %s. Elements using it now show no SVG filter.', 'divi5-svg-filters' ), $id ) )
				: array( 'type' => 'error', 'text' => __( 'That filter does not exist (any more).', 'divi5-svg-filters' ) ),
		) );
	}

	public static function post_clear_missing() {
		self::guard( 'd5svgf_clear_missing' );
		delete_option( 'd5svgf_missing' );
		self::back( array() );
	}

	/**
	 * Import an uploaded file.
	 *
	 * @param string $tmp   Path of the uploaded file ('' if not a valid upload).
	 * @param string $name  Original file name (sanitized).
	 * @param int    $error PHP upload error code.
	 * @param int    $size  File size in bytes.
	 * @return array<int, array{type:string, text:string}> Messages.
	 */
	public static function handle_upload( string $tmp, string $name, int $error, int $size ): array {
		if ( UPLOAD_ERR_OK !== $error || '' === $tmp ) {
			return array( array( 'type' => 'error', 'text' => __( 'No file was uploaded.', 'divi5-svg-filters' ) ) );
		}
		if ( $size > Sanitizer::MAX_BYTES ) {
			return array( array( 'type' => 'error', 'text' => __( 'The file is larger than 512 KB.', 'divi5-svg-filters' ) ) );
		}

		$result = Uploads::import( (string) file_get_contents( $tmp ), $name );
		$msgs   = array();
		if ( '' !== $result['error'] ) {
			$msgs[] = array( 'type' => 'error', 'text' => $result['error'] );
		}
		if ( $result['filters'] ) {
			$msgs[] = array(
				'type' => 'success',
				/* translators: %s: comma-separated filter IDs */
				'text' => sprintf( __( 'Imported: %s', 'divi5-svg-filters' ), implode( ', ', array_column( $result['filters'], 'id' ) ) ),
			);
		}
		foreach ( $result['skipped'] as $skip ) {
			$msgs[] = array(
				'type' => 'warning',
				/* translators: 1: filter ID, 2: reason */
				'text' => sprintf( __( 'Skipped %1$s: %2$s', 'divi5-svg-filters' ), '' !== $skip['id'] ? $skip['id'] : __( '(no id)', 'divi5-svg-filters' ), $skip['reason'] ),
			);
		}
		if ( ! $msgs ) {
			$msgs[] = array( 'type' => 'error', 'text' => __( 'Nothing was imported.', 'divi5-svg-filters' ) );
		}
		return $msgs;
	}

	/** The page. */
	public static function render( bool $enabled ) {
		$uploads = Uploads::all();
		echo '<div class="wrap"><h1>' . esc_html__( 'SVG Filters', 'divi5-svg-filters' ) . '</h1>';

		foreach ( self::take_messages() as $msg ) {
			echo '<div class="notice notice-' . esc_attr( $msg['type'] ) . ' is-dismissible"><p>' . esc_html( $msg['text'] ) . '</p></div>';
		}
		self::render_missing( array_flip( Uploads::taken_ids() ) );

		if ( ! $enabled ) {
			/* translators: %s: required kit version */
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'Uploads need SVG Filter Kit %s or newer. A theme copy of an older kit is loaded; remove it from the theme to use uploads.', 'divi5-svg-filters' ), Plugin::MIN_KIT_VERSION ) ) . '</p></div>';
		} else {
			echo '<h2>' . esc_html__( 'Upload', 'divi5-svg-filters' ) . '</h2>';
			echo '<p>' . esc_html__( 'SVG files with one or more <filter> elements. Only the filters are kept; scripts, images, external references and anything else are removed. IDs must be new.', 'divi5-svg-filters' ) . '</p>';
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'd5svgf_upload' );
			echo '<input type="hidden" name="action" value="d5svgf_upload"><input type="file" name="d5svgf_file" accept=".svg,image/svg+xml" required> ';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Upload', 'divi5-svg-filters' ) . '</button></form>';
		}

		echo '<h2>' . esc_html__( 'Your filters', 'divi5-svg-filters' ) . '</h2>';
		if ( ! $uploads ) {
			echo '<p>' . esc_html__( 'No uploaded filters yet. They appear under "Custom" in the SVG Filter dropdown and as .<id> / .<id>--bg classes.', 'divi5-svg-filters' ) . '</p></div>';
			return;
		}

		// Defs for the previews: sanitized markup only (see Sanitizer).
		echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
		foreach ( $uploads as $item ) {
			echo $item['markup']; // phpcs:ignore WordPress.Security.EscapeOutput -- allow-list sanitized on import
		}
		echo '</defs></svg>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Preview', 'divi5-svg-filters' ) . '</th><th>' . esc_html__( 'Filter', 'divi5-svg-filters' ) . '</th><th>' . esc_html__( 'File', 'divi5-svg-filters' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $uploads as $id => $item ) {
			echo '<tr><td><div style="width:160px;height:72px;border-radius:4px;display:grid;place-items:center;font:600 18px/1 sans-serif;color:#fff;'
				. 'background:linear-gradient(135deg,#3a6ea5,#e0a030 60%,#c03a5a);filter:url(#' . esc_attr( $id ) . ')">Aa</div></td>';
			echo '<td><strong>' . esc_html( '' !== $item['title'] ? $item['title'] : $id ) . '</strong><br><code>' . esc_html( $id ) . '</code></td>';
			echo '<td>' . esc_html( $item['file'] ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'd5svgf_delete_' . $id );
			echo '<input type="hidden" name="action" value="d5svgf_delete"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
			/* translators: %s: filter ID */
			$confirm = sprintf( __( 'Delete %s? Elements using it will show no SVG filter.', 'divi5-svg-filters' ), $id );
			echo '<button type="submit" class="button button-link-delete" onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')">' . esc_html__( 'Delete', 'divi5-svg-filters' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** Warning list: filters referenced on pages that are not available (deleted upload, removed theme file). */
	private static function render_missing( array $available ) {
		$missing = get_option( 'd5svgf_missing', array() );
		$rows    = '';
		foreach ( is_array( $missing ) ? $missing : array() as $id => $posts ) {
			if ( isset( $available[ $id ] ) || ! is_array( $posts ) ) {
				continue;
			}
			$links = array();
			foreach ( array_keys( $posts ) as $post_id ) {
				$link    = $post_id ? get_edit_post_link( $post_id ) : '';
				$links[] = $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a>' : esc_html__( '(archive or other page)', 'divi5-svg-filters' );
			}
			$rows .= '<li><code>' . esc_html( (string) $id ) . '</code>: ' . implode( ', ', $links ) . '</li>';
		}
		if ( '' === $rows ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Missing SVG filters', 'divi5-svg-filters' ) . '</strong> — '
			. esc_html__( 'these pages use a filter that no longer exists; the elements show no SVG filter. Re-upload it or pick another filter.', 'divi5-svg-filters' )
			. '</p><ul>' . $rows . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'd5svgf_clear_missing' );
		echo '<input type="hidden" name="action" value="d5svgf_clear_missing"><p><button type="submit" class="button">' . esc_html__( 'Clear list', 'divi5-svg-filters' ) . '</button></p></form></div>';
	}

	private static function guard( string $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'divi5-svg-filters' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	/** Store messages for the current user and return to the page. */
	private static function back( array $messages ) {
		set_transient( 'd5svgf_msgs_' . get_current_user_id(), $messages, 60 );
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::SLUG ) );
		exit;
	}

	private static function take_messages(): array {
		$key  = 'd5svgf_msgs_' . get_current_user_id();
		$msgs = get_transient( $key );
		if ( $msgs ) {
			delete_transient( $key );
		}
		return is_array( $msgs ) ? $msgs : array();
	}
}
