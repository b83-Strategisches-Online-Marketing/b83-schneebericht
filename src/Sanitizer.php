<?php
/**
 * Zentrale Validierung aller gespeicherten Werte.
 *
 * Jede Eingabe (Formular, Autosave, Import, Datenbank) läuft durch diese
 * Klasse. Erwartet wird bereits ungeslashte Eingabe (wp_unslash).
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	public const MAX_GROUPS          = 50;
	public const MAX_ITEMS_PER_GROUP = 200;

	/** IDs, die für Bereiche/Backend-Tabs reserviert sind und nicht als Gruppen-ID vergeben werden. */
	public const RESERVED_IDS = array( 'allgemein', 'struktur', 'werkzeuge' );

	/**
	 * Bereinigt die Struktur (Gruppen + Einträge).
	 *
	 * @param array<mixed> $raw Rohdaten.
	 * @return array{groups: array<string, array{title:string,type:string,items:array<string,array{label:string,hidden:bool}>}>}
	 */
	public static function config( array $raw ): array {
		$groups_in = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? $raw['groups'] : array();
		$groups    = array();

		foreach ( $groups_in as $group_id => $group ) {
			if ( count( $groups ) >= self::MAX_GROUPS ) {
				break;
			}
			$group_id = self::id( $group_id );
			if ( '' === $group_id || in_array( $group_id, self::RESERVED_IDS, true ) || ! is_array( $group ) ) {
				continue;
			}

			$type = isset( $group['type'] ) && is_string( $group['type'] ) ? sanitize_key( $group['type'] ) : '';
			if ( ! GroupTypes::is_valid( $type ) ) {
				continue;
			}

			$title = self::text( $group['title'] ?? '', 120 );
			$items = array();

			$items_in = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
			foreach ( $items_in as $item_id => $item ) {
				if ( count( $items ) >= self::MAX_ITEMS_PER_GROUP ) {
					break;
				}
				$item_id = self::id( $item_id );
				if ( '' === $item_id || ! is_array( $item ) ) {
					continue;
				}
				$label = self::text( $item['label'] ?? '', 120 );
				if ( '' === $label ) {
					continue;
				}
				$items[ $item_id ] = array(
					'label'  => $label,
					'hidden' => ! empty( $item['hidden'] ),
				);
			}

			$groups[ $group_id ] = array(
				'title' => '' !== $title ? $title : $group_id,
				'type'  => $type,
				'items' => $items,
			);
		}

		return array( 'groups' => $groups );
	}

	/**
	 * Bereinigt die Schneebericht-Daten anhand der Struktur. Einträge, die
	 * nicht (mehr) in der Struktur stehen, werden verworfen; fehlende
	 * Einträge erhalten Standardwerte.
	 *
	 * @param array<mixed>        $raw    Rohdaten.
	 * @param array<string,mixed> $config Bereinigte Struktur.
	 * @return array{allgemein: array<string,mixed>, groups: array<string, array<string, array<string,mixed>>>, updated: int}
	 */
	public static function data( array $raw, array $config ): array {
		$a = isset( $raw['allgemein'] ) && is_array( $raw['allgemein'] ) ? $raw['allgemein'] : array();
		$k = isset( $a['kompakt'] ) && is_array( $a['kompakt'] ) ? $a['kompakt'] : array();

		$kompakt = array();
		foreach ( Repository::KOMPAKT_FIELDS as $field ) {
			$value = isset( $k[ $field ] ) && is_scalar( $k[ $field ] ) ? (string) $k[ $field ] : '';
			$kompakt[ $field ] = 'wetterapp_link' === $field
				? esc_url_raw( $value, array( 'http', 'https' ) )
				: self::text( $value, 200 );
		}

		$info = isset( $a['info_rich'] ) && is_string( $a['info_rich'] ) ? $a['info_rich'] : '';

		$clean = array(
			'allgemein' => array(
				'datum'     => self::date( $a['datum'] ?? '' ),
				'info_rich' => wp_kses_post( $info ),
				'kompakt'   => $kompakt,
			),
			'groups'    => array(),
			'updated'   => isset( $raw['updated'] ) && is_numeric( $raw['updated'] ) ? (int) $raw['updated'] : 0,
		);

		$groups_in = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? $raw['groups'] : array();
		foreach ( $config['groups'] as $group_id => $group ) {
			$rows_in = isset( $groups_in[ $group_id ] ) && is_array( $groups_in[ $group_id ] ) ? $groups_in[ $group_id ] : array();
			foreach ( array_keys( $group['items'] ) as $item_id ) {
				$clean['groups'][ $group_id ][ $item_id ] = GroupTypes::sanitize_row( $group['type'], $rows_in[ $item_id ] ?? null );
			}
		}

		return $clean;
	}

	/**
	 * @param array<mixed> $raw Rohdaten.
	 * @return array{embed_origins: string[], delete_on_uninstall: bool, purge_cache: bool}
	 */
	public static function settings( array $raw ): array {
		$origins = $raw['embed_origins'] ?? array();
		if ( is_string( $origins ) ) {
			$origins = preg_split( '/[\s,]+/', $origins ) ?: array();
		}
		$clean_origins = array();
		foreach ( (array) $origins as $origin ) {
			$origin = self::origin( $origin );
			if ( '' !== $origin ) {
				$clean_origins[] = $origin;
			}
		}

		return array(
			'embed_origins'       => array_values( array_unique( array_slice( $clean_origins, 0, 30 ) ) ),
			'delete_on_uninstall' => ! empty( $raw['delete_on_uninstall'] ),
			// Standard: an. Nur ein ausdrücklich gesetzter Wert schaltet ab.
			'purge_cache'         => ! array_key_exists( 'purge_cache', $raw ) || ! empty( $raw['purge_cache'] ),
		);
	}

	/**
	 * Erlaubt Herkünfte wie https://example.at, https://*.example.at oder
	 * http://localhost:8080 – ohne Pfad. Alles andere wird verworfen.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function origin( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( trim( $value ) );
		$value = rtrim( $value, '/' );
		if ( 1 !== preg_match( '#^https?://(\*\.)?[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$#', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Interne ID: Kleinbuchstaben, Ziffern, Unterstrich; max. 60 Zeichen.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function id( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = str_replace( '-', '_', sanitize_key( (string) $value ) );
		return substr( $value, 0, 60 );
	}

	/**
	 * Erzeugt aus einer Bezeichnung eine ID, die in $existing noch nicht vorkommt.
	 *
	 * @param string   $label    Bezeichnung.
	 * @param string[] $existing Bereits vergebene IDs.
	 */
	public static function unique_id( string $label, array $existing ): string {
		$base = self::id( str_replace( '-', '_', sanitize_title( remove_accents( $label ) ) ) );
		if ( '' === $base ) {
			$base = 'eintrag';
		}
		$base = substr( $base, 0, 55 );
		$id   = $base;
		$n    = 2;
		while ( in_array( $id, $existing, true ) || in_array( $id, self::RESERVED_IDS, true ) ) {
			$id = $base . '_' . $n;
			++$n;
		}
		return $id;
	}

	/**
	 * Einzeiliger Text mit Längenbegrenzung.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function text( $value, int $max ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return mb_substr( sanitize_text_field( (string) $value ), 0, $max );
	}

	/**
	 * Datum im Format JJJJ-MM-TT oder leer.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function date( $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}
}
