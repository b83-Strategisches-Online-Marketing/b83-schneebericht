<?php
/**
 * Deinstallation. Daten werden nur entfernt, wenn das in den Einstellungen
 * ausdrücklich aktiviert wurde – sonst bleibt der Schneebericht erhalten.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$b83_sb_settings = get_option( 'b83_schneebericht_settings', array() );

if ( is_array( $b83_sb_settings ) && ! empty( $b83_sb_settings['delete_on_uninstall'] ) ) {
	foreach ( array(
		'b83_schneebericht_config',
		'b83_schneebericht_data',
		'b83_schneebericht_settings',
		'b83_schneebericht_db_version',
		'b83_schneebericht_migrated_from_tvb',
		'b83_schneebericht_migration_seen',
		'b83_schneebericht_pages',
	) as $b83_sb_option ) {
		delete_option( $b83_sb_option );
	}
	delete_transient( 'b83_schneebericht_pages_scan' );
	delete_transient( 'b83_schneebericht_release' );

	foreach ( array_keys( wp_roles()->roles ) as $b83_sb_role_key ) {
		$b83_sb_role = get_role( $b83_sb_role_key );
		if ( $b83_sb_role ) {
			$b83_sb_role->remove_cap( 'b83_schneebericht_edit' );
		}
	}
}
