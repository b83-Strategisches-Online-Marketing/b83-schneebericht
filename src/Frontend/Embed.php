<?php
/**
 * Einbettung per iFrame auf anderen (Sub-)Domains.
 *
 * Neuer Endpunkt:  https://example.at/?b83_schneebericht_embed=loipen_postalm&items=a,b&title=0
 * Alter Endpunkt:  /wp-admin/admin-ajax.php?action=tvb_sb_embed&section=… (bleibt für bestehende Einbindungen erhalten)
 *
 * Welche Websites einbetten dürfen, wird in den Einstellungen gepflegt
 * (Content-Security-Policy frame-ancestors).
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Frontend;

use B83\Schneebericht\Repository;

defined( 'ABSPATH' ) || exit;

final class Embed {

	public const QUERY_VAR     = 'b83_schneebericht_embed';
	private const LEGACY_ACTION = 'tvb_sb_embed';

	public function __construct( private Repository $repository, private Renderer $renderer ) {}

	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_handle_request' ), 0 );

		add_action( 'admin_init', array( $this, 'allow_legacy_framing' ), 0 );
		add_action( 'wp_ajax_nopriv_' . self::LEGACY_ACTION, array( $this, 'handle_legacy' ) );
		add_action( 'wp_ajax_' . self::LEGACY_ACTION, array( $this, 'handle_legacy' ) );
	}

	/** URL des Einbettungs-Skripts für Fremdseiten. */
	public static function script_url(): string {
		return add_query_arg( 'ver', B83_SB_VERSION, B83_SB_URL . 'assets/js/embed.js' );
	}

	public function maybe_handle_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- öffentlicher, rein lesender Endpunkt.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) );
		$this->output( $section );
	}

	/** Alter admin-ajax-Endpunkt: WordPress würde dort sonst X-Frame-Options: SAMEORIGIN senden. */
	public function allow_legacy_framing(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && self::LEGACY_ACTION === $_REQUEST['action'] ) {
			remove_action( 'admin_init', 'send_frame_options_header' );
		}
	}

	public function handle_legacy(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : '';
		$this->output( $section );
	}

	private function output( string $section ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- öffentlicher, rein lesender Endpunkt.
		$items = null;
		if ( isset( $_GET['items'] ) && '' !== $_GET['items'] ) {
			$items = Shortcodes::parse_item_list( sanitize_text_field( wp_unslash( (string) $_GET['items'] ) ) );
		}
		$show_title = ! ( isset( $_GET['title'] ) && '0' === $_GET['title'] );
		$template   = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( (string) $_GET['template'] ) ) : 'default';
		// phpcs:enable

		$html = $this->renderer->render_section(
			$section,
			array(
				'title'    => $show_title,
				'items'    => $items,
				'template' => $template,
			)
		);

		$status = 200;
		if ( '' === $html ) {
			$status = 404;
			$html   = '<div class="tvb-sb-embed-error">' . esc_html__( 'Unbekannter Bereich.', 'b83-schneebericht' ) . '</div>';
		}

		$this->send_headers( $status );

		$css = array(
			add_query_arg( 'ver', B83_SB_VERSION, B83_SB_URL . 'assets/css/frontend.css' ),
			add_query_arg( 'ver', B83_SB_VERSION, B83_SB_URL . 'assets/css/embed.css' ),
		);

		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">';
		echo '<title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>';
		foreach ( $css as $href ) {
			echo '<link rel="stylesheet" href="' . esc_url( $href ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- eigenständiges Dokument ohne Theme.
		}
		echo '</head><body><div id="tvb-sb-embed-root">';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
		echo '</div>';
		echo '<script>' . $this->height_script() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statischer Code.
		echo '</body></html>';
		exit;
	}

	private function send_headers( int $status ): void {
		if ( headers_sent() ) {
			return;
		}
		status_header( $status );
		header_remove( 'X-Frame-Options' );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Cache-Control: public, max-age=60' );

		$ancestors = array_merge( array( "'self'" ), $this->repository->get_settings()['embed_origins'] );
		header( 'Content-Security-Policy: frame-ancestors ' . implode( ' ', $ancestors ) );
	}

	/** Meldet die Inhaltshöhe an die einbettende Seite (neues und altes Nachrichtenformat). */
	private function height_script(): string {
		return '(function(){var last=0;function post(){var h=Math.ceil(document.documentElement.scrollHeight||document.body.scrollHeight);'
			. 'if(h===last)return;last=h;try{parent.postMessage({type:"b83-sb-embed-height",height:h},"*");'
			. 'parent.postMessage({type:"tvb-sb-embed-height",height:h},"*");}catch(e){}}'
			. 'window.addEventListener("load",post);setTimeout(post,50);setTimeout(post,300);setTimeout(post,1000);'
			. 'if("ResizeObserver" in window){new ResizeObserver(post).observe(document.body);}})();';
	}
}
