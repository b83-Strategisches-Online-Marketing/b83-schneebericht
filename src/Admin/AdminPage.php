<?php
/**
 * Backend-Seite „Schneebericht“: Menü, Assets, Tabs, Hinweise und das
 * Speichern des Schneeberichts (Formular + Autosave).
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Admin;

use B83\Schneebericht\Capabilities;
use B83\Schneebericht\GroupTypes;
use B83\Schneebericht\Migration;
use B83\Schneebericht\Repository;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const SLUG = 'b83-schneebericht';

	private const NONCE_SAVE     = 'b83_sb_save_status';
	private const NONCE_AUTOSAVE = 'b83_sb_autosave';

	private StructureTab $structure;
	private ToolsTab $tools;
	private string $hook = '';

	public function __construct( private Repository $repository ) {
		$this->structure = new StructureTab( $repository );
		$this->tools     = new ToolsTab( $repository );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'global_notices' ) );
		add_action( 'admin_post_b83_sb_save_status', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_b83_sb_autosave', array( $this, 'handle_autosave' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( B83_SB_FILE ), array( $this, 'action_links' ) );
		add_filter( 'b83_admin_plugin_links', array( $this, 'overview_link' ) );

		( new B83Menu( B83_SB_URL, B83_SB_DIR ) )->register();

		$this->structure->register();
		$this->tools->register();
	}

	/** Unterpunkt im gemeinsamen b83-Menü. */
	public function add_menu(): void {
		$this->hook = (string) add_submenu_page(
			B83Menu::SLUG,
			__( 'Schneebericht', 'b83-schneebericht' ),
			__( 'Schneebericht', 'b83-schneebericht' ),
			Capabilities::EDIT,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Link für die Übersichtsseite des b83-Menüs.
	 *
	 * @param array<string,string> $links Links.
	 * @return array<string,string>
	 */
	public function overview_link( array $links ): array {
		$links[ plugin_basename( B83_SB_FILE ) ] = self::url();
		return $links;
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Öffnen', 'b83-schneebericht' ) . '</a>' );
		return $links;
	}

	/** URL der Backend-Seite. */
	public static function url( string $tab = '', array $args = array() ): string {
		$args = array_merge( array( 'page' => self::SLUG ), '' !== $tab ? array( 'tab' => $tab ) : array(), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** Leitet mit einem Hinweis-Schlüssel zurück auf die Seite und beendet. */
	public static function redirect( string $tab, string $notice ): void {
		wp_safe_redirect( self::url( $tab, array( 'b83_sb_notice' => $notice ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------- */

	public function enqueue( string $hook ): void {
		if ( '' === $this->hook || $this->hook !== $hook ) {
			return;
		}
		$tab = $this->current_tab();

		wp_enqueue_style( 'b83-schneebericht-admin', B83_SB_URL . 'assets/css/admin.css', array(), B83_SB_VERSION );

		$deps = array( 'jquery' );
		if ( 'struktur' === $tab ) {
			$deps[] = 'jquery-ui-sortable';
		}
		wp_enqueue_script( 'b83-schneebericht-admin', B83_SB_URL . 'assets/js/admin.js', $deps, B83_SB_VERSION, true );
		wp_localize_script(
			'b83-schneebericht-admin',
			'b83Schneebericht',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'autosaveNonce' => wp_create_nonce( self::NONCE_AUTOSAVE ),
				'i18n'          => array(
					'ready'         => __( 'Bereit', 'b83-schneebericht' ),
					'dirty'         => __( 'Änderungen erkannt – wird gleich gespeichert …', 'b83-schneebericht' ),
					'saving'        => __( 'Speichert …', 'b83-schneebericht' ),
					'saved'         => __( 'Automatisch gespeichert um %s', 'b83-schneebericht' ),
					'error'         => __( 'Automatisches Speichern fehlgeschlagen – bitte manuell speichern.', 'b83-schneebericht' ),
					'leave'         => __( 'Es gibt ungespeicherte Änderungen.', 'b83-schneebericht' ),
					'newItem'       => __( 'Neuer Eintrag', 'b83-schneebericht' ),
					'confirmDelete' => __( 'Markierte Gruppen bzw. Einträge werden samt ihrem Status gelöscht. Fortfahren?', 'b83-schneebericht' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Hinweise
	 * ------------------------------------------------------------------- */

	public function global_notices(): void {
		if ( ! Capabilities::can_admin() ) {
			return;
		}
		if ( Migration::legacy_plugin_active() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'b83 Schneebericht:', 'b83-schneebericht' ),
				esc_html__( 'Das alte Plugin „TVB Schneebericht“ ist noch aktiv. Die Daten wurden übernommen – bitte das alte Plugin jetzt deaktivieren, damit nur noch an einer Stelle gepflegt wird.', 'b83-schneebericht' )
			);
		}
	}

	private function page_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige eines festen Hinweistextes.
		$key = isset( $_GET['b83_sb_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['b83_sb_notice'] ) ) : '';

		$notices = array(
			'saved'            => array( 'success', __( 'Gespeichert.', 'b83-schneebericht' ) ),
			'structure_saved'  => array( 'success', __( 'Struktur gespeichert.', 'b83-schneebericht' ) ),
			'group_added'      => array( 'success', __( 'Gruppe angelegt. Einträge kannst du jetzt hinzufügen.', 'b83-schneebericht' ) ),
			'group_invalid'    => array( 'error', __( 'Gruppe konnte nicht angelegt werden: Bitte Namen und Typ angeben.', 'b83-schneebericht' ) ),
			'settings_saved'   => array( 'success', __( 'Einstellungen gespeichert.', 'b83-schneebericht' ) ),
			'roles_saved'      => array( 'success', __( 'Berechtigungen gespeichert.', 'b83-schneebericht' ) ),
			'import_ok'        => array( 'success', __( 'Import erfolgreich.', 'b83-schneebericht' ) ),
			'import_nofile'    => array( 'error', __( 'Keine Datei hochgeladen.', 'b83-schneebericht' ) ),
			'import_toolarge'  => array( 'error', __( 'Die Datei ist zu groß (maximal 1 MB).', 'b83-schneebericht' ) ),
			'import_invalid'   => array( 'error', __( 'Die Datei ist kein gültiges Schneebericht-Backup.', 'b83-schneebericht' ) ),
		);

		if ( isset( $notices[ $key ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notices[ $key ][0] ),
				esc_html( $notices[ $key ][1] )
			);
		}

		if ( get_option( Migration::OPTION_MIGRATED ) && Capabilities::can_admin() && ! get_option( 'b83_schneebericht_migration_seen' ) ) {
			update_option( 'b83_schneebericht_migration_seen', 1, false );
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'Die Daten aus dem bisherigen Plugin „TVB Schneebericht“ wurden übernommen. Bitte kurz prüfen.', 'b83-schneebericht' )
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Tabs
	 * ------------------------------------------------------------------- */

	/**
	 * Tab-Schlüssel => Bezeichnung (nur die, die der Benutzer sehen darf).
	 *
	 * @return array<string,string>
	 */
	private function tabs(): array {
		$tabs = array( 'allgemein' => __( 'Allgemein', 'b83-schneebericht' ) );
		foreach ( $this->repository->get_config()['groups'] as $group_id => $group ) {
			$tabs[ $group_id ] = $group['title'];
		}
		if ( Capabilities::can_admin() ) {
			$tabs['struktur'] = __( 'Lifte & Loipen verwalten', 'b83-schneebericht' );
		}
		$tabs['werkzeuge'] = __( 'Shortcodes & Backup', 'b83-schneebericht' );
		return $tabs;
	}

	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'allgemein';
		$tabs = $this->tabs();
		return isset( $tabs[ $tab ] ) ? $tab : 'allgemein';
	}

	public function render(): void {
		if ( ! Capabilities::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-schneebericht' ), '', array( 'response' => 403 ) );
		}

		$tabs   = $this->tabs();
		$active = $this->current_tab();

		echo '<div class="wrap b83-sb-admin">';
		echo '<h1 class="b83-sb-admin__title">' . esc_html__( 'Schneebericht', 'b83-schneebericht' );
		if ( $this->is_status_tab( $active ) ) {
			echo ' <span id="b83-sb-autosave-status" class="b83-sb-status is-idle" aria-live="polite"></span>';
		}
		echo '</h1>';

		$this->page_notice();

		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Bereiche', 'b83-schneebericht' ) . '">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a class="nav-tab%1$s" href="%2$s"%3$s>%4$s</a>',
				$active === $key ? ' nav-tab-active' : '',
				esc_url( self::url( $key ) ),
				$active === $key ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( 'struktur' === $active ) {
			$this->structure->render();
		} elseif ( 'werkzeuge' === $active ) {
			$this->tools->render();
		} else {
			$this->render_status_form( $active );
		}

		echo '<p class="b83-sb-admin__footer">b83 Schneebericht ' . esc_html( B83_SB_VERSION ) . ' · <a href="https://b83.at" target="_blank" rel="noopener">b83.at</a></p>';
		echo '</div>';
	}

	private function is_status_tab( string $tab ): bool {
		return ! in_array( $tab, array( 'struktur', 'werkzeuge' ), true );
	}

	/* ---------------------------------------------------------------------
	 * Statusformular
	 * ------------------------------------------------------------------- */

	private function render_status_form( string $tab ): void {
		$data = $this->repository->get_data();

		echo '<form id="b83-sb-form" class="b83-sb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="b83_sb_save_status">';
		echo '<input type="hidden" name="b83_sb[tab]" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( self::NONCE_SAVE, 'b83_sb_nonce' );

		if ( 'allgemein' === $tab ) {
			$this->render_allgemein( $data['allgemein'] );
		} else {
			$group = $this->repository->get_config()['groups'][ $tab ];
			$rows  = $data['groups'][ $tab ] ?? array();
			if ( GroupTypes::LOIPEN === $group['type'] ) {
				$this->render_loipen( $tab, $group, $rows );
			} else {
				$this->render_anlagen( $tab, $group, $rows );
			}
		}

		submit_button( __( 'Speichern', 'b83-schneebericht' ) );
		echo '</form>';
	}

	/** @param array<string,mixed> $a Bereich Allgemein. */
	private function render_allgemein( array $a ): void {
		$k = $a['kompakt'];

		echo '<h2>' . esc_html__( 'Allgemein', 'b83-schneebericht' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="b83-sb-datum">' . esc_html__( 'Datum', 'b83-schneebericht' ) . '</label></th><td>';
		echo '<input type="date" id="b83-sb-datum" name="b83_sb[allgemein][datum]" value="' . esc_attr( $a['datum'] ) . '">';
		echo ' <button type="button" class="button b83-sb-today" data-target="b83-sb-datum">' . esc_html__( 'Heute', 'b83-schneebericht' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Wird im Frontend als Überschrift ausgegeben.', 'b83-schneebericht' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Allgemeine Infos', 'b83-schneebericht' ) . '</th><td>';
		wp_editor(
			$a['info_rich'],
			'b83_sb_info_rich',
			array(
				'textarea_name' => 'b83_sb[allgemein][info_rich]',
				'media_buttons' => false,
				'teeny'         => true,
				'textarea_rows' => 6,
				'tinymce'       => array(
					'toolbar1' => 'bold,italic,link,unlink,bullist,numlist,undo,redo',
					'toolbar2' => '',
				),
			)
		);
		echo '</td></tr></tbody></table>';

		echo '<h3>' . esc_html__( 'Schneebericht kompakt', 'b83-schneebericht' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$fields = array(
			'schnee_tal'       => __( 'Schneehöhe Tal', 'b83-schneebericht' ),
			'schnee_berg'      => __( 'Schneehöhe Berg', 'b83-schneebericht' ),
			'neuschnee_24h'    => __( 'Neuschnee in cm (letzte 24 h)', 'b83-schneebericht' ),
			'temp_tal'         => __( 'Temperatur im Tal', 'b83-schneebericht' ),
			'lawinenwarnstufe' => __( 'Aktuelle Lawinenwarnstufe', 'b83-schneebericht' ),
			'wetterapp_link'   => __( 'Link zur Wetter-App', 'b83-schneebericht' ),
		);
		foreach ( $fields as $key => $label ) {
			$is_url = 'wetterapp_link' === $key;
			printf(
				'<tr><th scope="row"><label for="b83-sb-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="b83-sb-%1$s" name="b83_sb[allgemein][kompakt][%1$s]" value="%4$s"%5$s></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				$is_url ? 'url' : 'text',
				esc_attr( (string) $k[ $key ] ),
				$is_url ? ' placeholder="https://"' : ''
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * @param array<string,mixed>                  $group Gruppe.
	 * @param array<string, array<string,mixed>>   $rows  Status.
	 */
	private function render_loipen( string $group_id, array $group, array $rows ): void {
		echo '<h2>' . esc_html( $group['title'] ) . '</h2>';
		if ( empty( $group['items'] ) ) {
			$this->render_empty_group();
			return;
		}

		echo '<table class="widefat striped b83-sb-table"><thead><tr>';
		foreach ( array( __( 'Name', 'b83-schneebericht' ), __( 'Ampel', 'b83-schneebericht' ), __( 'Schwierigkeit', 'b83-schneebericht' ), __( 'Loipe', 'b83-schneebericht' ), __( 'Notiz', 'b83-schneebericht' ), __( 'Link', 'b83-schneebericht' ) ) as $th ) {
			echo '<th scope="col">' . esc_html( $th ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $group['items'] as $item_id => $item ) {
			$r    = $rows[ $item_id ] ?? GroupTypes::default_row( GroupTypes::LOIPEN );
			$base = 'b83_sb[groups][' . $group_id . '][' . $item_id . ']';

			echo '<tr><th scope="row"><strong>' . esc_html( $item['label'] ) . '</strong>';
			if ( $item['hidden'] ) {
				echo '<span class="b83-sb-badge">' . esc_html__( 'im Frontend ausgeblendet', 'b83-schneebericht' ) . '</span>';
			}
			echo '</th>';

			echo '<td>' . $this->select( $base . '[ampel]', GroupTypes::ampel_options(), $r['ampel'], $item['label'] . ' – ' . __( 'Ampel', 'b83-schneebericht' ), 'b83-sb-ampel b83-sb-ampel--' . $r['ampel'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in select() escaped.
			echo '<td>' . $this->select( $base . '[difficulty]', GroupTypes::difficulty_options(), $r['difficulty'], $item['label'] . ' – ' . __( 'Schwierigkeit', 'b83-schneebericht' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . $this->select( $base . '[style]', GroupTypes::style_options(), $r['style'], $item['label'] . ' – ' . __( 'Stil', 'b83-schneebericht' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			printf(
				'<td><textarea rows="2" class="b83-sb-note" name="%1$s[note]" aria-label="%2$s">%3$s</textarea></td>',
				esc_attr( $base ),
				esc_attr( $item['label'] . ' – ' . __( 'Notiz', 'b83-schneebericht' ) ),
				esc_textarea( $r['note'] )
			);
			printf(
				'<td><input type="url" class="b83-sb-link" name="%1$s[link]" value="%2$s" placeholder="https://" aria-label="%3$s"></td>',
				esc_attr( $base ),
				esc_attr( $r['link'] ),
				esc_attr( $item['label'] . ' – ' . __( 'Link', 'b83-schneebericht' ) )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param array<string,mixed>                $group Gruppe.
	 * @param array<string, array<string,mixed>> $rows  Status.
	 */
	private function render_anlagen( string $group_id, array $group, array $rows ): void {
		echo '<h2>' . esc_html( $group['title'] ) . '</h2>';
		if ( empty( $group['items'] ) ) {
			$this->render_empty_group();
			return;
		}

		echo '<p><button type="button" class="button b83-sb-all" data-value="1">' . esc_html__( 'Alle öffnen', 'b83-schneebericht' ) . '</button> ';
		echo '<button type="button" class="button b83-sb-all" data-value="0">' . esc_html__( 'Alle schließen', 'b83-schneebericht' ) . '</button></p>';

		echo '<table class="widefat striped b83-sb-table b83-sb-table--anlagen"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Name', 'b83-schneebericht' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'b83-schneebericht' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Notiz', 'b83-schneebericht' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $group['items'] as $item_id => $item ) {
			$r    = $rows[ $item_id ] ?? GroupTypes::default_row( GroupTypes::ANLAGEN );
			$base = 'b83_sb[groups][' . $group_id . '][' . $item_id . ']';

			echo '<tr><th scope="row"><strong>' . esc_html( $item['label'] ) . '</strong>';
			if ( $item['hidden'] ) {
				echo '<span class="b83-sb-badge">' . esc_html__( 'im Frontend ausgeblendet', 'b83-schneebericht' ) . '</span>';
			}
			echo '</th>';
			printf(
				'<td><label class="b83-sb-toggle"><input type="checkbox" class="b83-sb-open" name="%1$s[open]" value="1"%2$s> %3$s</label></td>',
				esc_attr( $base ),
				checked( ! empty( $r['open'] ), true, false ),
				esc_html__( 'geöffnet', 'b83-schneebericht' )
			);
			printf(
				'<td><input type="text" class="b83-sb-note" name="%1$s[note]" value="%2$s" aria-label="%3$s"></td>',
				esc_attr( $base ),
				esc_attr( $r['note'] ),
				esc_attr( $item['label'] . ' – ' . __( 'Notiz', 'b83-schneebericht' ) )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_empty_group(): void {
		echo '<p>' . esc_html__( 'Diese Gruppe hat noch keine Einträge.', 'b83-schneebericht' );
		if ( Capabilities::can_admin() ) {
			echo ' <a href="' . esc_url( self::url( 'struktur' ) ) . '">' . esc_html__( 'Einträge anlegen', 'b83-schneebericht' ) . '</a>';
		}
		echo '</p>';
	}

	/**
	 * @param array<string,string> $options Optionen.
	 */
	private function select( string $name, array $options, string $current, string $label, string $class = '' ): string {
		$html = '<select name="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $label ) . '"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		return $html . '</select>';
	}

	/* ---------------------------------------------------------------------
	 * Speichern
	 * ------------------------------------------------------------------- */

	public function handle_save(): void {
		if ( ! Capabilities::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-schneebericht' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_SAVE, 'b83_sb_nonce' );

		$tab = $this->apply_post();
		self::redirect( $tab, 'saved' );
	}

	public function handle_autosave(): void {
		if ( ! Capabilities::can_edit() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		if ( ! check_ajax_referer( self::NONCE_AUTOSAVE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
		}

		$tab = $this->apply_post();
		wp_send_json_success(
			array(
				'time' => wp_date( 'H:i:s' ),
				'tab'  => $tab,
			)
		);
	}

	/**
	 * Übernimmt den Inhalt des gerade bearbeiteten Tabs aus $_POST und speichert.
	 * Die Prüfung von Nonce und Berechtigung erfolgt vorher durch den Aufrufer.
	 *
	 * @return string Tab, der gespeichert wurde.
	 */
	private function apply_post(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce im Aufrufer geprüft; Bereinigung zentral im Sanitizer.
		$post = isset( $_POST['b83_sb'] ) && is_array( $_POST['b83_sb'] ) ? wp_unslash( $_POST['b83_sb'] ) : array();
		$tab  = isset( $post['tab'] ) && is_string( $post['tab'] ) ? sanitize_key( $post['tab'] ) : '';

		$data   = $this->repository->get_data();
		$config = $this->repository->get_config();

		if ( 'allgemein' === $tab ) {
			$data['allgemein'] = isset( $post['allgemein'] ) && is_array( $post['allgemein'] ) ? $post['allgemein'] : array();
		} elseif ( isset( $config['groups'][ $tab ] ) ) {
			$data['groups'][ $tab ] = isset( $post['groups'][ $tab ] ) && is_array( $post['groups'][ $tab ] ) ? $post['groups'][ $tab ] : array();
		} else {
			wp_die( esc_html__( 'Unbekannter Bereich.', 'b83-schneebericht' ), '', array( 'response' => 400 ) );
		}

		$this->repository->save_data( $data );
		return $tab;
	}
}
