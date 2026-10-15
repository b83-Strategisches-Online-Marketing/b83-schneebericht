<?php
/**
 * Datenhaltung in der WordPress-Datenbank (wp_options).
 *
 * Drei getrennte Optionen:
 *  - config:   Struktur (Gruppen und ihre Einträge)
 *  - data:     aktueller Schneebericht (Allgemein + Status je Eintrag)
 *  - settings: technische Einstellungen (Einbettung, Deinstallation)
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht;

defined( 'ABSPATH' ) || exit;

final class Repository {

	public const OPTION_CONFIG   = 'b83_schneebericht_config';
	public const OPTION_DATA     = 'b83_schneebericht_data';
	public const OPTION_SETTINGS = 'b83_schneebericht_settings';

	/** Felder im Block „Kompakt“ des Bereichs Allgemein. */
	public const KOMPAKT_FIELDS = array( 'schnee_tal', 'schnee_berg', 'neuschnee_24h', 'temp_tal', 'lawinenwarnstufe', 'wetterapp_link' );

	/** @var array<string,mixed>|null */
	private ?array $config_cache = null;

	/** @var array<string,mixed>|null */
	private ?array $data_cache = null;

	/* ---------------------------------------------------------------------
	 * Struktur
	 * ------------------------------------------------------------------- */

	/**
	 * @return array{groups: array<string, array{title:string,type:string,items:array<string,array{label:string,hidden:bool}>}>}
	 */
	public function get_config(): array {
		if ( null === $this->config_cache ) {
			$stored             = get_option( self::OPTION_CONFIG, null );
			$this->config_cache = is_array( $stored ) ? Sanitizer::config( $stored ) : self::default_config();
		}
		return $this->config_cache;
	}

	/** @param array<string,mixed> $config Bereits bereinigte Struktur. */
	public function save_config( array $config ): bool {
		$config             = Sanitizer::config( $config );
		$this->config_cache = $config;
		$this->data_cache   = null;
		update_option( self::OPTION_CONFIG, $config, true );
		do_action( 'b83_schneebericht_config_saved', $config );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Schneebericht-Daten
	 * ------------------------------------------------------------------- */

	/**
	 * Liefert die Daten, vollständig ergänzt um Standardwerte für alle
	 * Einträge der aktuellen Struktur.
	 *
	 * @return array{allgemein: array<string,mixed>, groups: array<string, array<string, array<string,mixed>>>}
	 */
	public function get_data(): array {
		if ( null === $this->data_cache ) {
			$stored           = get_option( self::OPTION_DATA, array() );
			$this->data_cache = Sanitizer::data( is_array( $stored ) ? $stored : array(), $this->get_config() );
		}
		return $this->data_cache;
	}

	/** @param array<string,mixed> $data Daten im Format von get_data(). */
	public function save_data( array $data ): bool {
		$data             = Sanitizer::data( $data, $this->get_config() );
		$data['updated']  = time();
		$this->data_cache = $data;
		update_option( self::OPTION_DATA, $data, true );
		do_action( 'b83_schneebericht_data_saved', $data );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Einstellungen
	 * ------------------------------------------------------------------- */

	/** @return array{embed_origins: string[], delete_on_uninstall: bool, purge_cache: bool} */
	public function get_settings(): array {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		return Sanitizer::settings( is_array( $stored ) ? $stored : array() );
	}

	/** @param array<string,mixed> $settings Einstellungen. */
	public function save_settings( array $settings ): bool {
		update_option( self::OPTION_SETTINGS, Sanitizer::settings( $settings ), false );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Standardwerte
	 * ------------------------------------------------------------------- */

	/**
	 * Ausgangsstruktur (entspricht dem bisherigen TVB-Plugin, IDs unverändert,
	 * damit Shortcodes, Embeds und Backups weiter funktionieren).
	 *
	 * @return array<string,mixed>
	 */
	public static function default_config(): array {
		$items = static function ( array $labels, array $hidden = array() ): array {
			$out = array();
			foreach ( $labels as $id => $label ) {
				$out[ $id ] = array(
					'label'  => $label,
					'hidden' => in_array( $id, $hidden, true ),
				);
			}
			return $out;
		};

		$config = array(
			'groups' => array(
				'loipen_abtenau'       => array(
					'title' => 'Langlauf-Loipen Abtenau',
					'type'  => GroupTypes::LOIPEN,
					'items' => $items(
						array(
							'auloipe_1'                 => 'Auloipe 1',
							'auloipe_2'                 => 'Auloipe 2',
							'zusatzloipe_au_1'          => 'Zusatzloipe Au 1',
							'radochsberg_panoramaloipe' => 'Radochsberg Panoramaloipe',
							'egelseeloipe'              => 'Egelseeloipe',
							'rossbuehelloipe'           => 'Rossbühelloipe',
						),
						array( 'egelseeloipe', 'rossbuehelloipe' )
					),
				),
				'loipen_postalm'       => array(
					'title' => 'Langlauf-Loipen Postalm',
					'type'  => GroupTypes::LOIPEN,
					'items' => $items(
						array(
							'anfaengerrunde'   => 'Anfängerrunde',
							'lienbachloipe'    => 'Lienbachloipe',
							'verbindungsloipe' => 'Verbindungsloipe',
							'panoramaloipe'    => 'Panoramaloipe',
							'raumsauloipe'     => 'Raumsauloipe',
						)
					),
				),
				'skipisten_sonnleiten' => array(
					'title' => 'Skilifte Sonnleiten',
					'type'  => GroupTypes::ANLAGEN,
					'items' => $items(
						array(
							'unterer_sonnleitenlift' => 'Unterer Sonnleitenlift',
							'oberer_sonnleitenlift'  => 'Oberer Sonnleitenlift',
						)
					),
				),
				'skilifte_karkogel'    => array(
					'title' => 'Skilifte Karkogel',
					'type'  => GroupTypes::ANLAGEN,
					'items' => $items(
						array(
							'karkogel_kombibahn' => 'Karkogel-Kombibahn',
							'karalm_lift'        => 'Karalm-Lift',
							'karkogel_rodelbahn' => 'Karkogel Winter-Rodelbahn',
							'pistentour'         => 'Pistentour',
						)
					),
				),
				'skilifte_postalm'     => array(
					'title' => 'Skilifte Postalm',
					'type'  => GroupTypes::ANLAGEN,
					'items' => $items(
						array(
							'schischullift'    => 'Schischullift',
							'welserlift'       => 'Welserlift',
							'stroblerlift'     => 'Stroblerlift',
							'geschloessl_lift' => 'Gschlössl-Lift',
							'edtlift'          => 'Edtlift',
							'zauberteppich'    => 'Zauberteppich',
						)
					),
				),
			),
		);

		/**
		 * Filtert die Ausgangsstruktur, die bei einer Neuinstallation angelegt wird.
		 *
		 * @param array $config Struktur.
		 */
		return (array) apply_filters( 'b83_schneebericht_default_config', $config );
	}
}
