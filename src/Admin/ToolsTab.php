<?php
/**
 * Tab „Shortcodes & Backup“: Shortcode- und Einbettungs-Hilfe, Export,
 * sowie (nur Administratoren) Import, Berechtigungen und Einstellungen.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Admin;

use B83\Schneebericht\Capabilities;
use B83\Schneebericht\Frontend\Embed;
use B83\Schneebericht\Migration;
use B83\Schneebericht\Repository;

defined( 'ABSPATH' ) || exit;

final class ToolsTab {

	private const MAX_IMPORT_BYTES = MB_IN_BYTES;

	public function __construct( private Repository $repository ) {}

	public function register(): void {
		add_action( 'admin_post_b83_sb_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_b83_sb_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_b83_sb_save_roles', array( $this, 'handle_roles' ) );
		add_action( 'admin_post_b83_sb_save_settings', array( $this, 'handle_settings' ) );
	}

	/* ---------------------------------------------------------------------
	 * Ausgabe
	 * ------------------------------------------------------------------- */

	public function render(): void {
		$this->render_shortcodes();
		$this->render_embed();
		$this->render_backup();
		if ( Capabilities::can_admin() ) {
			$this->render_roles();
			$this->render_settings();
		}
	}

	private function render_shortcodes(): void {
		$config = $this->repository->get_config();

		echo '<div class="b83-sb-card"><h2>' . esc_html__( 'Shortcodes', 'b83-schneebericht' ) . '</h2>';
		echo '<table class="widefat striped b83-sb-codes"><thead><tr><th>' . esc_html__( 'Bereich', 'b83-schneebericht' ) . '</th><th>' . esc_html__( 'Shortcode', 'b83-schneebericht' ) . '</th></tr></thead><tbody>';
		echo '<tr><td>' . esc_html__( 'Allgemein', 'b83-schneebericht' ) . '</td><td><code>[b83_schneebericht bereich="allgemein"]</code></td></tr>';
		foreach ( $config['groups'] as $group_id => $group ) {
			printf(
				'<tr><td>%1$s</td><td><code>[b83_schneebericht bereich="%2$s"]</code></td></tr>',
				esc_html( $group['title'] ),
				esc_html( $group_id )
			);
		}
		echo '</tbody></table>';

		echo '<p><strong>' . esc_html__( 'Optionen:', 'b83-schneebericht' ) . '</strong> ';
		echo '<code>titel="0"</code> ' . esc_html__( 'blendet die Überschrift aus;', 'b83-schneebericht' ) . ' ';
		echo '<code>eintraege="id1,id2"</code> ' . esc_html__( 'zeigt nur bestimmte Einträge (IDs siehe „Lifte & Loipen verwalten“).', 'b83-schneebericht' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Die bisherigen Shortcodes [tvb_schnee_allgemein], [tvb_schnee_loipen_abtenau] usw. funktionieren weiterhin.', 'b83-schneebericht' ) . '</p>';
		echo '</div>';
	}

	private function render_embed(): void {
		$config   = $this->repository->get_config();
		$sections = array_merge( array( 'allgemein' ), array_keys( $config['groups'] ) );
		$first    = $sections[1] ?? 'allgemein';
		$snippet  = '<div class="b83-sb-embed" data-origin="' . home_url( '/' ) . '" data-section="' . $first . '"></div>' . "\n"
			. '<script async src="' . Embed::script_url() . '"></script>';
		$origins  = $this->repository->get_settings()['embed_origins'];

		echo '<div class="b83-sb-card"><h2>' . esc_html__( 'Einbettung auf anderen Websites', 'b83-schneebericht' ) . '</h2>';
		echo '<p>' . esc_html__( 'Auf dieser Website selbst bitte die Shortcodes verwenden. Für andere Websites oder Subdomains diesen Code einfügen:', 'b83-schneebericht' ) . '</p>';
		echo '<textarea class="large-text code b83-sb-copy" rows="3" readonly onclick="this.select()">' . esc_textarea( $snippet ) . '</textarea>';
		echo '<p><strong>data-section:</strong> <code>' . esc_html( implode( ' | ', $sections ) ) . '</code><br>';
		echo '<strong>data-items</strong> ' . esc_html__( '(optional): kommagetrennte IDs', 'b83-schneebericht' ) . '<br>';
		echo '<strong>data-title="0"</strong> ' . esc_html__( '(optional): Überschrift ausblenden', 'b83-schneebericht' ) . '</p>';

		if ( empty( $origins ) ) {
			echo '<p class="b83-sb-warning">' . esc_html__( 'Derzeit darf keine fremde Website einbetten. Erlaubte Websites unten in den Einstellungen eintragen.', 'b83-schneebericht' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Einbetten erlaubt für:', 'b83-schneebericht' ) . ' <code>' . esc_html( implode( ', ', $origins ) ) . '</code></p>';
		}
		echo '</div>';
	}

	private function render_backup(): void {
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=b83_sb_export' ), 'b83_sb_export' );

		echo '<div class="b83-sb-card"><h2>' . esc_html__( 'Backup', 'b83-schneebericht' ) . '</h2>';
		echo '<p><a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export herunterladen (JSON)', 'b83-schneebericht' ) . '</a></p>';

		if ( Capabilities::can_admin() ) {
			echo '<h3>' . esc_html__( 'Import', 'b83-schneebericht' ) . '</h3>';
			echo '<p>' . esc_html__( 'Akzeptiert Backups aus diesem Plugin und aus dem bisherigen „TVB Schneebericht“. Der aktuelle Stand wird überschrieben.', 'b83-schneebericht' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
			echo '<input type="hidden" name="action" value="b83_sb_import">';
			wp_nonce_field( 'b83_sb_import', 'b83_sb_import_nonce' );
			echo '<p><input type="file" name="b83_sb_backup" accept="application/json,.json" required></p>';
			echo '<p><label><input type="checkbox" name="b83_sb_import_structure" value="1" checked> ' . esc_html__( 'Auch die Struktur (Gruppen und Einträge) aus der Datei übernehmen, falls enthalten', 'b83-schneebericht' ) . '</label></p>';
			submit_button( __( 'Import starten', 'b83-schneebericht' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</div>';
	}

	private function render_roles(): void {
		$with_access = Capabilities::roles_with_access();

		echo '<div class="b83-sb-card"><h2>' . esc_html__( 'Berechtigungen', 'b83-schneebericht' ) . '</h2>';
		echo '<p>' . esc_html__( 'Diese Rollen dürfen den Schneebericht bearbeiten. Struktur, Import und Einstellungen bleiben Administratoren vorbehalten.', 'b83-schneebericht' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="b83_sb_save_roles">';
		wp_nonce_field( 'b83_sb_save_roles', 'b83_sb_roles_nonce' );
		echo '<fieldset class="b83-sb-roles"><legend class="screen-reader-text">' . esc_html__( 'Rollen', 'b83-schneebericht' ) . '</legend>';

		foreach ( wp_roles()->get_names() as $role_key => $role_name ) {
			$is_admin = 'administrator' === $role_key;
			printf(
				'<label><input type="checkbox" name="b83_sb_roles[]" value="%1$s"%2$s%3$s> %4$s</label>',
				esc_attr( $role_key ),
				checked( $is_admin || in_array( $role_key, $with_access, true ), true, false ),
				$is_admin ? ' disabled' : '',
				esc_html( translate_user_role( $role_name ) )
			);
		}
		echo '</fieldset>';
		submit_button( __( 'Berechtigungen speichern', 'b83-schneebericht' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	private function render_settings(): void {
		$settings = $this->repository->get_settings();

		echo '<div class="b83-sb-card"><h2>' . esc_html__( 'Einstellungen', 'b83-schneebericht' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="b83_sb_save_settings">';
		wp_nonce_field( 'b83_sb_save_settings', 'b83_sb_settings_nonce' );

		echo '<p><label for="b83-sb-origins"><strong>' . esc_html__( 'Websites, die einbetten dürfen', 'b83-schneebericht' ) . '</strong></label><br>';
		echo '<textarea id="b83-sb-origins" name="b83_sb_settings[embed_origins]" rows="4" class="large-text code" placeholder="https://www.example.at&#10;https://*.example.at">' . esc_textarea( implode( "\n", $settings['embed_origins'] ) ) . '</textarea></p>';
		echo '<p class="description">' . esc_html__( 'Eine Adresse pro Zeile, ohne Pfad. Ein * am Anfang erlaubt alle Subdomains. Diese Website selbst ist immer erlaubt.', 'b83-schneebericht' ) . '</p>';

		printf(
			'<p><label><input type="checkbox" name="b83_sb_settings[delete_on_uninstall]" value="1"%1$s> %2$s</label></p>',
			checked( $settings['delete_on_uninstall'], true, false ),
			esc_html__( 'Beim Löschen des Plugins alle Schneebericht-Daten entfernen', 'b83-schneebericht' )
		);

		submit_button( __( 'Einstellungen speichern', 'b83-schneebericht' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	/* ---------------------------------------------------------------------
	 * Aktionen
	 * ------------------------------------------------------------------- */

	public function handle_export(): void {
		if ( ! Capabilities::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-schneebericht' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'b83_sb_export' );

		$payload = array(
			'format'   => Migration::EXPORT_FORMAT,
			'version'  => B83_SB_VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url( '/' ),
			'config'   => $this->repository->get_config(),
			'data'     => $this->repository->get_data(),
		);
		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			wp_die( esc_html__( 'Export fehlgeschlagen.', 'b83-schneebericht' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="b83-schneebericht-' . wp_date( 'Y-m-d_H-i' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-Download.
		exit;
	}

	public function handle_import(): void {
		$this->authorize_admin( 'b83_sb_import', 'b83_sb_import_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Datei-Upload, wird unten geprüft.
		$file = isset( $_FILES['b83_sb_backup'] ) && is_array( $_FILES['b83_sb_backup'] ) ? $_FILES['b83_sb_backup'] : null;

		if ( ! $file || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			AdminPage::redirect( 'werkzeuge', 'import_nofile' );
		}
		if ( (int) $file['size'] > self::MAX_IMPORT_BYTES || filesize( $file['tmp_name'] ) > self::MAX_IMPORT_BYTES ) {
			AdminPage::redirect( 'werkzeuge', 'import_toolarge' );
		}

		$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- hochgeladene Datei.
		$decoded  = is_string( $contents ) ? json_decode( $contents, true, 32 ) : null;
		if ( ! is_array( $decoded ) || ! $this->looks_like_backup( $decoded ) ) {
			AdminPage::redirect( 'werkzeuge', 'import_invalid' );
		}

		$import = Migration::normalize_import( $decoded );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce oben geprüft.
		if ( null !== $import['config'] && ! empty( $_POST['b83_sb_import_structure'] ) ) {
			$this->repository->save_config( $import['config'] );
		}
		$this->repository->save_data( $import['data'] );

		AdminPage::redirect( 'werkzeuge', 'import_ok' );
	}

	/**
	 * Grobe Plausibilitätsprüfung, damit nicht versehentlich eine fremde JSON-Datei alles leert.
	 *
	 * @param array<mixed> $decoded Inhalt.
	 */
	private function looks_like_backup( array $decoded ): bool {
		if ( ( $decoded['format'] ?? '' ) === Migration::EXPORT_FORMAT ) {
			return isset( $decoded['data'] ) && is_array( $decoded['data'] );
		}
		return isset( $decoded['allgemein'] ) && is_array( $decoded['allgemein'] );
	}

	public function handle_roles(): void {
		$this->authorize_admin( 'b83_sb_save_roles', 'b83_sb_roles_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bereinigung in set_roles().
		$roles = isset( $_POST['b83_sb_roles'] ) && is_array( $_POST['b83_sb_roles'] ) ? wp_unslash( $_POST['b83_sb_roles'] ) : array();
		$roles = array_values( array_filter( $roles, 'is_string' ) );

		// Nur tatsächlich existierende Rollen.
		$roles = array_intersect( $roles, array_keys( wp_roles()->roles ) );
		Capabilities::set_roles( $roles );

		AdminPage::redirect( 'werkzeuge', 'roles_saved' );
	}

	public function handle_settings(): void {
		$this->authorize_admin( 'b83_sb_save_settings', 'b83_sb_settings_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bereinigung im Sanitizer.
		$post = isset( $_POST['b83_sb_settings'] ) && is_array( $_POST['b83_sb_settings'] ) ? wp_unslash( $_POST['b83_sb_settings'] ) : array();
		$this->repository->save_settings( $post );

		AdminPage::redirect( 'werkzeuge', 'settings_saved' );
	}

	private function authorize_admin( string $action, string $field ): void {
		if ( ! Capabilities::can_admin() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-schneebericht' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $field );
	}
}
