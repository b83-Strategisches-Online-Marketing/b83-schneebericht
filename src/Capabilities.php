<?php
/**
 * Berechtigungen.
 *
 *  - Schneebericht bearbeiten und exportieren: eigene Capability, je Rolle freischaltbar.
 *  - Struktur, Rollen, Import, Einstellungen: nur Administratoren (manage_options).
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const EDIT = 'b83_schneebericht_edit';

	/** Capability des alten TVB-Plugins (nur für die Migration). */
	public const LEGACY = 'tvb_schneebericht_manage';

	public static function can_edit(): bool {
		return current_user_can( self::EDIT ) || current_user_can( 'manage_options' );
	}

	public static function can_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function ensure_admin_role(): void {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::EDIT ) ) {
			$role->add_cap( self::EDIT );
		}
	}

	/**
	 * Rollen-Schlüssel, die derzeit bearbeiten dürfen.
	 *
	 * @return string[]
	 */
	public static function roles_with_access(): array {
		$keys = array();
		foreach ( array_keys( wp_roles()->roles ) as $role_key ) {
			$role = get_role( $role_key );
			if ( $role && $role->has_cap( self::EDIT ) ) {
				$keys[] = $role_key;
			}
		}
		return $keys;
	}

	/**
	 * Setzt die Capability exakt für die angegebenen Rollen. Administrator bleibt immer berechtigt.
	 *
	 * @param string[] $role_keys Rollen-Schlüssel.
	 */
	public static function set_roles( array $role_keys ): void {
		$role_keys   = array_map( 'sanitize_key', $role_keys );
		$role_keys[] = 'administrator';

		foreach ( array_keys( wp_roles()->roles ) as $role_key ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}
			if ( in_array( $role_key, $role_keys, true ) ) {
				$role->add_cap( self::EDIT );
			} else {
				$role->remove_cap( self::EDIT );
			}
		}
	}

	/** Übernimmt die Freigaben des alten TVB-Plugins. */
	public static function migrate_legacy(): void {
		foreach ( array_keys( wp_roles()->roles ) as $role_key ) {
			$role = get_role( $role_key );
			if ( $role && $role->has_cap( self::LEGACY ) ) {
				$role->add_cap( self::EDIT );
			}
		}
	}

	/** Entfernt die Capability aus allen Rollen (Deinstallation). */
	public static function remove_all(): void {
		foreach ( array_keys( wp_roles()->roles ) as $role_key ) {
			$role = get_role( $role_key );
			if ( $role ) {
				$role->remove_cap( self::EDIT );
			}
		}
	}
}
