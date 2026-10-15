<?php
/**
 * Gruppentypen: welche Felder ein Eintrag hat, Standardwerte und erlaubte Werte.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht;

defined( 'ABSPATH' ) || exit;

final class GroupTypes {

	/** Loipen: Ampel, Schwierigkeit, Stil, Notiz, Link. */
	public const LOIPEN = 'loipen';

	/** Anlagen (Lifte, Pisten, Rodelbahnen …): geöffnet/geschlossen + Notiz. */
	public const ANLAGEN = 'anlagen';

	/**
	 * Typ => Bezeichnung im Backend.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			self::LOIPEN  => __( 'Loipen (Ampel, Schwierigkeit, Stil, Link)', 'b83-schneebericht' ),
			self::ANLAGEN => __( 'Lifte / Anlagen (geöffnet / geschlossen)', 'b83-schneebericht' ),
		);
	}

	public static function is_valid( string $type ): bool {
		return array_key_exists( $type, self::labels() );
	}

	/** @return array<string,string> */
	public static function ampel_options(): array {
		return array(
			'rot'   => __( 'Rot (gesperrt)', 'b83-schneebericht' ),
			'gelb'  => __( 'Gelb (teilweise geöffnet)', 'b83-schneebericht' ),
			'gruen' => __( 'Grün (geöffnet)', 'b83-schneebericht' ),
		);
	}

	/** @return array<string,string> */
	public static function difficulty_options(): array {
		return array(
			'leicht'    => __( 'Leicht', 'b83-schneebericht' ),
			'mittel'    => __( 'Mittel', 'b83-schneebericht' ),
			'schwierig' => __( 'Schwierig', 'b83-schneebericht' ),
		);
	}

	/** @return array<string,string> */
	public static function style_options(): array {
		return array(
			'klassisch' => __( 'Klassisch', 'b83-schneebericht' ),
			'skating'   => __( 'Skating', 'b83-schneebericht' ),
			'beides'    => __( 'Beides', 'b83-schneebericht' ),
		);
	}

	/**
	 * Standard-Statuszeile für einen Eintrag dieses Typs.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_row( string $type ): array {
		if ( self::LOIPEN === $type ) {
			return array(
				'ampel'      => 'rot',
				'difficulty' => 'mittel',
				'style'      => 'klassisch',
				'note'       => '',
				'link'       => '',
			);
		}
		return array(
			'open' => 0,
			'note' => '',
		);
	}

	/**
	 * Bereinigt eine (bereits ungeslashte) Statuszeile.
	 *
	 * @param mixed $row Rohdaten.
	 * @return array<string,mixed>
	 */
	public static function sanitize_row( string $type, $row ): array {
		$row   = is_array( $row ) ? $row : array();
		$clean = self::default_row( $type );

		$note          = isset( $row['note'] ) && is_scalar( $row['note'] ) ? (string) $row['note'] : '';
		$clean['note'] = mb_substr( sanitize_text_field( $note ), 0, 500 );

		if ( self::LOIPEN === $type ) {
			$clean['ampel']      = self::pick( $row['ampel'] ?? null, self::ampel_options(), 'rot' );
			$clean['difficulty'] = self::pick( $row['difficulty'] ?? null, self::difficulty_options(), 'mittel' );
			$clean['style']      = self::pick( $row['style'] ?? null, self::style_options(), 'klassisch' );
			$link                = isset( $row['link'] ) && is_scalar( $row['link'] ) ? (string) $row['link'] : '';
			$clean['link']       = esc_url_raw( $link, array( 'http', 'https' ) );
			return $clean;
		}

		$open          = $row['open'] ?? 0;
		$clean['open'] = ( 1 === $open || '1' === $open || true === $open ) ? 1 : 0;
		return $clean;
	}

	/**
	 * Gibt $value zurück, wenn es ein erlaubter Schlüssel ist, sonst $fallback.
	 *
	 * @param mixed                $value   Rohwert.
	 * @param array<string,string> $allowed Erlaubte Werte.
	 */
	private static function pick( $value, array $allowed, string $fallback ): string {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		return array_key_exists( $value, $allowed ) ? $value : $fallback;
	}
}
