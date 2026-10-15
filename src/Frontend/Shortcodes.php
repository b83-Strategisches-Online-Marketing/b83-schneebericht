<?php
/**
 * Shortcodes.
 *
 * Neu:   [b83_schneebericht bereich="loipen_postalm" eintraege="auloipe_1,auloipe_2" titel="0"]
 * Alt:   [tvb_schnee_allgemein], [tvb_schnee_loipen_abtenau] … (bleiben unverändert nutzbar)
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Frontend;

use B83\Schneebericht\Integrations\CachePurge;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	/** Alte Shortcodes des TVB-Plugins → Bereich. */
	private const LEGACY = array(
		'tvb_schnee_allgemein'            => 'allgemein',
		'tvb_schnee_loipen_abtenau'       => 'loipen_abtenau',
		'tvb_schnee_loipen_postalm'       => 'loipen_postalm',
		'tvb_schnee_skipisten_sonnleiten' => 'skipisten_sonnleiten',
		'tvb_schnee_skilifte_karkogel'    => 'skilifte_karkogel',
		'tvb_schnee_skilifte_postalm'     => 'skilifte_postalm',
	);

	public function __construct( private Renderer $renderer, private ?CachePurge $cache = null ) {}

	public function register(): void {
		// Priorität 20: überschreibt die Shortcodes des alten Plugins, falls es noch aktiv ist.
		add_action( 'init', array( $this, 'add_shortcodes' ), 20 );
	}

	public function add_shortcodes(): void {
		add_shortcode( 'b83_schneebericht', array( $this, 'shortcode' ) );

		foreach ( self::LEGACY as $tag => $section ) {
			add_shortcode(
				$tag,
				function () use ( $section ): string {
					return $this->output( $section, array() );
				}
			);
		}
	}

	/**
	 * @param array<string,string>|string $atts Attribute.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'bereich'  => 'allgemein',
				'eintraege' => '',
				'titel'    => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'b83_schneebericht'
		);

		$items = null;
		if ( '' !== trim( (string) $atts['eintraege'] ) ) {
			$items = self::parse_item_list( (string) $atts['eintraege'] );
		}

		return $this->output(
			sanitize_key( (string) $atts['bereich'] ),
			array(
				'title' => ! in_array( strtolower( (string) $atts['titel'] ), array( '0', 'nein', 'false', 'no' ), true ),
				'items' => $items,
			)
		);
	}

	/**
	 * @param array{title?:bool,items?:?array} $opts Optionen.
	 */
	private function output( string $section, array $opts ): string {
		wp_enqueue_style( 'b83-schneebericht' );
		$this->cache?->remember_current_page();
		return $this->renderer->render_section( $section, $opts );
	}

	/**
	 * Kommagetrennte ID-Liste → bereinigtes Array.
	 *
	 * @return string[]
	 */
	public static function parse_item_list( string $raw ): array {
		$parts = array_map( 'trim', explode( ',', $raw ) );
		$parts = array_map( 'sanitize_key', $parts );
		return array_values( array_filter( array_unique( $parts ) ) );
	}
}
