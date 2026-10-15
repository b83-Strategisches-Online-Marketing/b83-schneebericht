<?php
/**
 * Installation, Datenbank-Versionen und Übernahme aus dem alten TVB-Plugin.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht;

defined( 'ABSPATH' ) || exit;

final class Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'b83_schneebericht_db_version';
	public const OPTION_MIGRATED   = 'b83_schneebericht_migrated_from_tvb';

	/** Dateiname, unter dem das alte Plugin seine Daten gespeichert hat. */
	private const LEGACY_FILE = 'tvb_schneebericht_data.json';

	/** Export-Kennung des neuen Formats. */
	public const EXPORT_FORMAT = 'b83-schneebericht';

	public static function on_activation(): void {
		self::run();
	}

	/** Läuft bei jedem Seitenaufruf im Backend, tut aber nur bei Versionswechsel etwas. */
	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION ) {
			self::run();
		}
	}

	private static function run(): void {
		Capabilities::ensure_admin_role();

		if ( false === get_option( Repository::OPTION_CONFIG, false ) ) {
			add_option( Repository::OPTION_CONFIG, Sanitizer::config( Repository::default_config() ), '', true );
		}

		if ( false === get_option( Repository::OPTION_DATA, false ) ) {
			$legacy = self::read_legacy_file();
			if ( null !== $legacy ) {
				$config = Sanitizer::config( (array) get_option( Repository::OPTION_CONFIG, array() ) );
				add_option( Repository::OPTION_DATA, Sanitizer::data( self::convert_legacy( $legacy ), $config ), '', true );
				update_option( self::OPTION_MIGRATED, time(), false );
				Capabilities::migrate_legacy();

				if ( false === get_option( Repository::OPTION_SETTINGS, false ) ) {
					// Das alte Plugin erlaubte die Einbettung fest auf diesen Subdomains.
					add_option(
						Repository::OPTION_SETTINGS,
						Sanitizer::settings( array( 'embed_origins' => array( 'https://*.abtenau-info.at' ) ) ),
						'',
						false
					);
				}
			}
		}

		if ( false === get_option( Repository::OPTION_SETTINGS, false ) ) {
			add_option( Repository::OPTION_SETTINGS, Sanitizer::settings( array() ), '', false );
		}

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}

	/**
	 * Sucht die Datendatei des alten Plugins (auch in umbenannten Plugin-Ordnern).
	 *
	 * @return array<mixed>|null
	 */
	private static function read_legacy_file(): ?array {
		$candidates = glob( trailingslashit( WP_PLUGIN_DIR ) . '*/' . self::LEGACY_FILE ) ?: array();

		foreach ( $candidates as $file ) {
			if ( ! is_file( $file ) || ! is_readable( $file ) || filesize( $file ) > 2 * MB_IN_BYTES ) {
				continue;
			}
			$json    = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokale Datei.
			$decoded = is_string( $json ) ? json_decode( $json, true ) : null;
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return null;
	}

	/**
	 * Wandelt das alte Datenformat (Gruppen auf oberster Ebene) ins neue um.
	 *
	 * @param array<mixed> $legacy Altdaten.
	 * @return array<string,mixed>
	 */
	public static function convert_legacy( array $legacy ): array {
		$data = array(
			'allgemein' => isset( $legacy['allgemein'] ) && is_array( $legacy['allgemein'] ) ? $legacy['allgemein'] : array(),
			'groups'    => array(),
		);
		foreach ( $legacy as $key => $value ) {
			if ( 'allgemein' !== $key && is_array( $value ) ) {
				$data['groups'][ (string) $key ] = $value;
			}
		}
		return $data;
	}

	/**
	 * Erkennt das Format einer importierten Datei.
	 *
	 * @param array<mixed> $decoded JSON-Inhalt.
	 * @return array{config: array<mixed>|null, data: array<mixed>}
	 */
	public static function normalize_import( array $decoded ): array {
		if ( ( $decoded['format'] ?? '' ) === self::EXPORT_FORMAT ) {
			return array(
				'config' => isset( $decoded['config'] ) && is_array( $decoded['config'] ) ? $decoded['config'] : null,
				'data'   => isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array(),
			);
		}
		// Backup aus dem alten TVB-Plugin.
		return array(
			'config' => null,
			'data'   => self::convert_legacy( $decoded ),
		);
	}

	/** Ist das alte Plugin noch aktiv? */
	public static function legacy_plugin_active(): bool {
		return class_exists( 'TVB_Schneebericht', false );
	}
}
