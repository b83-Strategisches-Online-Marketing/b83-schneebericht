<?php
/**
 * Leert nach dem Speichern den Seiten-Cache der Seiten, auf denen der
 * Schneebericht vorkommt (WP Rocket und weitere gängige Cache-Plugins).
 *
 * Welche Seiten betroffen sind, wird auf zwei Wegen ermittelt:
 *  1. Beim Ausgeben eines Shortcodes merkt sich das Plugin die Seite.
 *  2. Zusätzlich werden Inhalte und Elementor-Daten nach Shortcodes durchsucht.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Integrations;

use B83\Schneebericht\Repository;

defined( 'ABSPATH' ) || exit;

final class CachePurge {

	public const OPTION_PAGES = 'b83_schneebericht_pages';
	private const MAX_PAGES   = 300;

	/** Suchbegriffe, an denen Seiten mit Schneebericht erkannt werden. */
	private const NEEDLES = array( '[b83_schneebericht', '[tvb_schnee_', 'b83-sb-embed', 'tvb-sb-embed' );

	public function __construct( private Repository $repository ) {}

	public function register(): void {
		add_action( 'b83_schneebericht_data_saved', array( $this, 'purge' ) );
		add_action( 'b83_schneebericht_config_saved', array( $this, 'purge' ) );
		add_action(
			'save_post',
			static function (): void {
				delete_transient( 'b83_schneebericht_pages_scan' );
			}
		);
	}

	/** Wird vom Shortcode aufgerufen: aktuelle Seite merken (nur einmal je Seite). */
	public function remember_current_page(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( $id <= 0 ) {
			return;
		}
		$pages = $this->known_pages();
		if ( in_array( $id, $pages, true ) ) {
			return;
		}
		$pages[] = $id;
		update_option( self::OPTION_PAGES, array_slice( $pages, -self::MAX_PAGES ), true );
	}

	/** @return int[] */
	public function known_pages(): array {
		$pages = get_option( self::OPTION_PAGES, array() );
		return is_array( $pages ) ? array_values( array_unique( array_map( 'intval', $pages ) ) ) : array();
	}

	/**
	 * Alle Seiten, deren Cache geleert werden soll.
	 *
	 * @return int[]
	 */
	public function affected_pages(): array {
		$ids = array_unique( array_merge( $this->known_pages(), $this->find_pages_in_content() ) );
		$ids = array_values(
			array_filter(
				$ids,
				static fn( int $id ): bool => $id > 0 && 'publish' === get_post_status( $id )
			)
		);

		/**
		 * Filtert die IDs der Seiten, deren Cache nach dem Speichern geleert wird.
		 *
		 * @param int[] $ids Beitrags-/Seiten-IDs.
		 */
		return array_map( 'intval', (array) apply_filters( 'b83_schneebericht_purge_post_ids', $ids ) );
	}

	/**
	 * Durchsucht Beitragsinhalte und Elementor-Daten nach Shortcodes/Embeds.
	 * Ergebnis wird 12 Stunden zwischengespeichert.
	 *
	 * @return int[]
	 */
	private function find_pages_in_content(): array {
		$cached = get_transient( 'b83_schneebericht_pages_scan' );
		if ( is_array( $cached ) ) {
			return array_map( 'intval', $cached );
		}

		global $wpdb;
		$ids = array();
		foreach ( self::NEEDLES as $needle ) {
			$like = '%' . $wpdb->esc_like( $needle ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ergebnis wird per Transient gecacht.
			$from_content = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT 500", $like ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$from_meta = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s LIMIT 500", $like ) );
			$ids       = array_merge( $ids, array_map( 'intval', (array) $from_content ), array_map( 'intval', (array) $from_meta ) );
		}
		$ids = array_values( array_unique( $ids ) );
		set_transient( 'b83_schneebericht_pages_scan', $ids, 12 * HOUR_IN_SECONDS );
		return $ids;
	}

	public function purge(): void {
		if ( ! $this->repository->get_settings()['purge_cache'] ) {
			return;
		}

		$ids = $this->affected_pages();
		foreach ( $ids as $id ) {
			$this->purge_post( $id );
		}

		/**
		 * Nach dem Leeren des Caches – für weitere Cache-Lösungen.
		 *
		 * @param int[] $ids Beitrags-/Seiten-IDs.
		 */
		do_action( 'b83_schneebericht_purge_cache', $ids );
	}

	private function purge_post( int $id ): void {
		clean_post_cache( $id );

		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $id ); // WP Rocket.
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $id ); // W3 Total Cache.
		}
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $id ); // WP Super Cache.
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache( (string) get_permalink( $id ) ); // SiteGround.
		}
		do_action( 'litespeed_purge_post', $id ); // LiteSpeed Cache.
	}

	/** Name des erkannten Cache-Plugins für die Anzeige im Backend ('' wenn keins). */
	public static function detected_plugin(): string {
		return match ( true ) {
			function_exists( 'rocket_clean_post' )         => 'WP Rocket',
			defined( 'LSCWP_V' )                           => 'LiteSpeed Cache',
			function_exists( 'w3tc_flush_post' )           => 'W3 Total Cache',
			function_exists( 'wp_cache_post_change' )      => 'WP Super Cache',
			function_exists( 'sg_cachepress_purge_cache' ) => 'SiteGround Speed Optimizer',
			default                                        => '',
		};
	}
}
