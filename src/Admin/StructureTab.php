<?php
/**
 * Tab „Lifte & Loipen verwalten“ (nur Administratoren):
 * Gruppen und Einträge anlegen, umbenennen, ausblenden, sortieren, löschen.
 *
 * Die IDs bleiben beim Umbenennen erhalten, damit Shortcodes und
 * Einbettungen (data-items) weiter funktionieren.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Admin;

use B83\Schneebericht\Capabilities;
use B83\Schneebericht\GroupTypes;
use B83\Schneebericht\Repository;
use B83\Schneebericht\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class StructureTab {

	private const NONCE_SAVE = 'b83_sb_save_structure';
	private const NONCE_ADD  = 'b83_sb_add_group';

	public function __construct( private Repository $repository ) {}

	public function register(): void {
		add_action( 'admin_post_b83_sb_save_structure', array( $this, 'handle_save' ) );
		add_action( 'admin_post_b83_sb_add_group', array( $this, 'handle_add_group' ) );
	}

	/* ---------------------------------------------------------------------
	 * Ausgabe
	 * ------------------------------------------------------------------- */

	public function render(): void {
		$config = $this->repository->get_config();
		$types  = GroupTypes::labels();

		echo '<p class="b83-sb-intro">' . esc_html__( 'Hier legst du fest, welche Gruppen und Einträge es gibt. Reihenfolge per Ziehen am Griff ändern. Umbenennen ist jederzeit möglich, die interne ID bleibt dabei gleich.', 'b83-schneebericht' ) . '</p>';

		echo '<form id="b83-sb-structure" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="b83_sb_save_structure">';
		wp_nonce_field( self::NONCE_SAVE, 'b83_sb_structure_nonce' );

		echo '<div class="b83-sb-groups">';
		foreach ( $config['groups'] as $group_id => $group ) {
			$this->render_group( $group_id, $group, $types[ $group['type'] ] ?? $group['type'] );
		}
		echo '</div>';

		if ( empty( $config['groups'] ) ) {
			echo '<p>' . esc_html__( 'Noch keine Gruppen vorhanden. Lege unten die erste an.', 'b83-schneebericht' ) . '</p>';
		} else {
			submit_button( __( 'Struktur speichern', 'b83-schneebericht' ) );
		}
		echo '</form>';

		// Neue Gruppe (eigenes Formular).
		echo '<div class="b83-sb-card"><h2>' . esc_html__( 'Neue Gruppe anlegen', 'b83-schneebericht' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="b83-sb-newgroup">';
		echo '<input type="hidden" name="action" value="b83_sb_add_group">';
		wp_nonce_field( self::NONCE_ADD, 'b83_sb_add_group_nonce' );
		echo '<p><label for="b83-sb-newgroup-title">' . esc_html__( 'Name', 'b83-schneebericht' ) . '</label><br>';
		echo '<input type="text" id="b83-sb-newgroup-title" name="b83_sb_new_group[title]" class="regular-text" required maxlength="120" placeholder="' . esc_attr__( 'z. B. Rodelbahnen', 'b83-schneebericht' ) . '"></p>';
		echo '<p><label for="b83-sb-newgroup-type">' . esc_html__( 'Typ', 'b83-schneebericht' ) . '</label><br><select id="b83-sb-newgroup-type" name="b83_sb_new_group[type]">';
		foreach ( $types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="description">' . esc_html__( 'Der Typ legt die Felder fest und kann später nicht mehr geändert werden.', 'b83-schneebericht' ) . '</p>';
		submit_button( __( 'Gruppe anlegen', 'b83-schneebericht' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * @param array{title:string,type:string,items:array<string,array{label:string,hidden:bool}>} $group Gruppe.
	 */
	private function render_group( string $group_id, array $group, string $type_label ): void {
		$base = 'b83_sb_structure[groups][' . $group_id . ']';

		echo '<div class="b83-sb-card b83-sb-group" data-group="' . esc_attr( $group_id ) . '">';
		echo '<div class="b83-sb-group__head">';
		echo '<span class="b83-sb-handle dashicons dashicons-move" title="' . esc_attr__( 'Ziehen zum Sortieren', 'b83-schneebericht' ) . '" aria-hidden="true"></span>';
		printf(
			'<input type="text" class="b83-sb-group__title" name="%1$s[title]" value="%2$s" required maxlength="120" aria-label="%3$s">',
			esc_attr( $base ),
			esc_attr( $group['title'] ),
			esc_attr__( 'Name der Gruppe', 'b83-schneebericht' )
		);
		echo '<span class="b83-sb-meta">' . esc_html( $type_label ) . ' · ID <code>' . esc_html( $group_id ) . '</code></span>';
		printf(
			'<label class="b83-sb-delete"><input type="checkbox" name="%1$s[delete]" value="1"> %2$s</label>',
			esc_attr( $base ),
			esc_html__( 'Gruppe löschen', 'b83-schneebericht' )
		);
		echo '</div>';

		echo '<table class="widefat b83-sb-items"><thead><tr>';
		echo '<th class="b83-sb-col-handle"><span class="screen-reader-text">' . esc_html__( 'Sortieren', 'b83-schneebericht' ) . '</span></th>';
		echo '<th>' . esc_html__( 'Bezeichnung', 'b83-schneebericht' ) . '</th>';
		echo '<th>' . esc_html__( 'ID', 'b83-schneebericht' ) . '</th>';
		echo '<th>' . esc_html__( 'Im Frontend ausblenden', 'b83-schneebericht' ) . '</th>';
		echo '<th>' . esc_html__( 'Löschen', 'b83-schneebericht' ) . '</th>';
		echo '</tr></thead><tbody class="b83-sb-items__body">';

		foreach ( $group['items'] as $item_id => $item ) {
			$this->render_item_row( $base, 'i_' . $item_id, $item_id, $item['label'], $item['hidden'] );
		}
		echo '</tbody></table>';

		// Vorlage für neue Zeilen (wird per JavaScript kopiert, Platzhalter __KEY__).
		echo '<script type="text/template" class="b83-sb-item-template">';
		ob_start();
		$this->render_item_row( $base, '__KEY__', '', '', false );
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inhalt in render_item_row() escaped.
		echo '</script>';

		echo '<p><button type="button" class="button b83-sb-add-item">' . esc_html__( '+ Eintrag hinzufügen', 'b83-schneebericht' ) . '</button></p>';
		echo '</div>';
	}

	private function render_item_row( string $base, string $key, string $item_id, string $label, bool $hidden ): void {
		$name = $base . '[items][' . $key . ']';
		echo '<tr class="b83-sb-item">';
		echo '<td class="b83-sb-col-handle"><span class="b83-sb-handle dashicons dashicons-menu" aria-hidden="true"></span></td>';
		printf(
			'<td><input type="hidden" name="%1$s[id]" value="%2$s"><input type="text" class="regular-text" name="%1$s[label]" value="%3$s" maxlength="120" aria-label="%4$s"%5$s></td>',
			esc_attr( $name ),
			esc_attr( $item_id ),
			esc_attr( $label ),
			esc_attr__( 'Bezeichnung', 'b83-schneebericht' ),
			'' === $item_id ? ' placeholder="' . esc_attr__( 'Neuer Eintrag', 'b83-schneebericht' ) . '"' : ' required'
		);
		echo '<td>' . ( '' !== $item_id ? '<code>' . esc_html( $item_id ) . '</code>' : '<em>' . esc_html__( 'wird vergeben', 'b83-schneebericht' ) . '</em>' ) . '</td>';
		printf(
			'<td><input type="checkbox" name="%1$s[hidden]" value="1"%2$s aria-label="%3$s"></td>',
			esc_attr( $name ),
			checked( $hidden, true, false ),
			esc_attr__( 'Im Frontend ausblenden', 'b83-schneebericht' )
		);
		printf(
			'<td><input type="checkbox" class="b83-sb-delete-item" name="%1$s[delete]" value="1" aria-label="%2$s"></td>',
			esc_attr( $name ),
			esc_attr__( 'Löschen', 'b83-schneebericht' )
		);
		echo '</tr>';
	}

	/* ---------------------------------------------------------------------
	 * Speichern
	 * ------------------------------------------------------------------- */

	public function handle_save(): void {
		$this->authorize( self::NONCE_SAVE, 'b83_sb_structure_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bereinigung unten und zentral im Sanitizer.
		$post   = isset( $_POST['b83_sb_structure']['groups'] ) && is_array( $_POST['b83_sb_structure']['groups'] ) ? wp_unslash( $_POST['b83_sb_structure']['groups'] ) : array();
		$config = $this->repository->get_config();
		$new    = array( 'groups' => array() );

		// Reihenfolge der Gruppen und Einträge entspricht der Reihenfolge im Formular.
		foreach ( $post as $group_id => $group_post ) {
			$group_id = Sanitizer::id( $group_id );
			if ( ! isset( $config['groups'][ $group_id ] ) || ! is_array( $group_post ) || ! empty( $group_post['delete'] ) ) {
				continue; // Nur bestehende Gruppen; neue entstehen über „Gruppe anlegen“.
			}

			$old   = $config['groups'][ $group_id ];
			$items = array();
			$rows  = isset( $group_post['items'] ) && is_array( $group_post['items'] ) ? $group_post['items'] : array();

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
					continue;
				}
				$label = Sanitizer::text( $row['label'] ?? '', 120 );
				if ( '' === $label ) {
					continue;
				}
				$item_id = Sanitizer::id( $row['id'] ?? '' );
				if ( '' === $item_id || ! isset( $old['items'][ $item_id ] ) ) {
					$item_id = Sanitizer::unique_id( $label, array_merge( array_keys( $items ), array_keys( $old['items'] ) ) );
				}
				if ( isset( $items[ $item_id ] ) ) {
					continue;
				}
				$items[ $item_id ] = array(
					'label'  => $label,
					'hidden' => ! empty( $row['hidden'] ),
				);
			}

			$new['groups'][ $group_id ] = array(
				'title' => Sanitizer::text( $group_post['title'] ?? '', 120 ) ?: $old['title'],
				'type'  => $old['type'],
				'items' => $items,
			);
		}

		// Gruppen, die im Formular fehlen (z. B. parallel angelegt), nicht verlieren.
		foreach ( $config['groups'] as $group_id => $group ) {
			if ( ! isset( $post[ $group_id ] ) ) {
				$new['groups'][ $group_id ] = $group;
			}
		}

		$this->repository->save_config( $new );
		AdminPage::redirect( 'struktur', 'structure_saved' );
	}

	public function handle_add_group(): void {
		$this->authorize( self::NONCE_ADD, 'b83_sb_add_group_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bereinigung unten.
		$post  = isset( $_POST['b83_sb_new_group'] ) && is_array( $_POST['b83_sb_new_group'] ) ? wp_unslash( $_POST['b83_sb_new_group'] ) : array();
		$title = Sanitizer::text( $post['title'] ?? '', 120 );
		$type  = isset( $post['type'] ) && is_string( $post['type'] ) ? sanitize_key( $post['type'] ) : '';

		$config = $this->repository->get_config();
		if ( '' === $title || ! GroupTypes::is_valid( $type ) || count( $config['groups'] ) >= Sanitizer::MAX_GROUPS ) {
			AdminPage::redirect( 'struktur', 'group_invalid' );
		}

		$group_id                      = Sanitizer::unique_id( $title, array_keys( $config['groups'] ) );
		$config['groups'][ $group_id ] = array(
			'title' => $title,
			'type'  => $type,
			'items' => array(),
		);

		$this->repository->save_config( $config );
		AdminPage::redirect( 'struktur', 'group_added' );
	}

	private function authorize( string $action, string $field ): void {
		if ( ! Capabilities::can_admin() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-schneebericht' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $field );
	}
}
