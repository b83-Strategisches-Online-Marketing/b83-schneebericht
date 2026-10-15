<?php
/**
 * HTML-Ausgabe der Bereiche. Wird von Shortcodes und Embed gemeinsam genutzt.
 *
 * Die CSS-Klassen (tvb-sb …) sind bewusst vom alten Plugin übernommen,
 * damit bestehende Anpassungen im Theme weiter greifen.
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

namespace B83\Schneebericht\Frontend;

use B83\Schneebericht\GroupTypes;
use B83\Schneebericht\Repository;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	public function __construct( private Repository $repository ) {}

	/**
	 * Gibt einen Bereich als HTML zurück ('' bei unbekanntem Bereich).
	 *
	 * @param string                                                 $section 'allgemein' oder Gruppen-ID.
	 * @param array{title?:bool,items?:string[]|null,template?:string} $opts  Optionen.
	 */
	public function render_section( string $section, array $opts = array() ): string {
		$opts = array(
			'title'    => $opts['title'] ?? true,
			'items'    => $opts['items'] ?? null,
			'template' => sanitize_key( (string) ( $opts['template'] ?? 'default' ) ),
		);

		if ( 'allgemein' === $section ) {
			$html = $this->render_allgemein( $opts );
		} else {
			$config = $this->repository->get_config();
			if ( ! isset( $config['groups'][ $section ] ) ) {
				return '';
			}
			$html = $this->render_group( $section, $config['groups'][ $section ], $opts );
		}

		/**
		 * Filtert das HTML eines Bereichs.
		 *
		 * @param string $html    HTML.
		 * @param string $section Bereich.
		 * @param array  $opts    Optionen.
		 */
		return (string) apply_filters( 'b83_schneebericht_section_html', $html, $section, $opts );
	}

	/** @param array{title:bool,items:?array,template:string} $opts Optionen. */
	private function render_allgemein( array $opts ): string {
		$a = $this->repository->get_data()['allgemein'];
		$k = $a['kompakt'];

		$rows = array(
			'schnee_tal'       => __( 'Schneehöhe Tal', 'b83-schneebericht' ),
			'schnee_berg'      => __( 'Schneehöhe Berg', 'b83-schneebericht' ),
			'neuschnee_24h'    => __( 'Neuschnee (24h)', 'b83-schneebericht' ),
			'temp_tal'         => __( 'Temperatur im Tal', 'b83-schneebericht' ),
			'lawinenwarnstufe' => __( 'Lawinenwarnstufe', 'b83-schneebericht' ),
		);

		ob_start();
		?>
		<section class="b83-sb tvb-sb tvb-sb--allgemein tvb-sb--tpl-<?php echo esc_attr( $opts['template'] ); ?>">
			<?php if ( $opts['title'] && '' !== $a['datum'] ) : ?>
				<h2 class="tvb-sb__headline"><?php echo esc_html( $this->format_date( $a['datum'] ) ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== $a['info_rich'] ) : ?>
				<div class="tvb-sb__richtext"><?php echo wp_kses_post( wpautop( $a['info_rich'] ) ); ?></div>
			<?php endif; ?>

			<div class="tvb-sb__kompakt">
				<div class="tvb-sb__kompakt-grid">
					<?php foreach ( $rows as $key => $label ) : ?>
						<?php if ( '' !== trim( (string) $k[ $key ] ) ) : ?>
							<div class="tvb-kv">
								<div class="tvb-kv__k"><?php echo esc_html( $label ); ?></div>
								<div class="tvb-kv__v"><?php echo esc_html( $k[ $key ] ); ?></div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ( '' !== $k['wetterapp_link'] ) : ?>
						<div class="tvb-kv tvb-kv--link">
							<div class="tvb-kv__k"><?php esc_html_e( 'Wetter-App', 'b83-schneebericht' ); ?></div>
							<div class="tvb-kv__v"><a href="<?php echo esc_url( $k['wetterapp_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Link öffnen', 'b83-schneebericht' ); ?></a></div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array{title:string,type:string,items:array<string,array{label:string,hidden:bool}>} $group Gruppe.
	 * @param array{title:bool,items:?array,template:string}                                     $opts  Optionen.
	 */
	private function render_group( string $group_id, array $group, array $opts ): string {
		$rows      = $this->repository->get_data()['groups'][ $group_id ] ?? array();
		$only      = is_array( $opts['items'] ) ? $opts['items'] : null;
		$css_class = 'tvb-sb--' . str_replace( '_', '-', $group_id );
		$is_loipe  = GroupTypes::LOIPEN === $group['type'];

		ob_start();
		?>
		<section class="b83-sb tvb-sb <?php echo esc_attr( $css_class ); ?> tvb-sb--tpl-<?php echo esc_attr( $opts['template'] ); ?>">
			<?php if ( $opts['title'] ) : ?>
				<h3 class="tvb-sb__title"><?php echo esc_html( $group['title'] ); ?></h3>
			<?php endif; ?>

			<div class="tvb-sb__table">
				<?php
				foreach ( $group['items'] as $item_id => $item ) :
					if ( $item['hidden'] || ( null !== $only && ! in_array( $item_id, $only, true ) ) ) {
						continue;
					}
					$r = $rows[ $item_id ] ?? GroupTypes::default_row( $group['type'] );

					if ( $is_loipe ) :
						$tooltip = $this->ampel_label( $r['ampel'] );
						?>
						<div class="tvb-sb__row tvb-sb__row--loipe">
							<div class="tvb-sb__cell tvb-sb__cell--name"><?php echo esc_html( $item['label'] ); ?></div>
							<div class="tvb-sb__cell tvb-sb__cell--ampel">
								<span class="tvb-ampel tvb-ampel--<?php echo esc_attr( $r['ampel'] ); ?>" role="img" title="<?php echo esc_attr( $tooltip ); ?>" aria-label="<?php echo esc_attr( $tooltip ); ?>"></span>
							</div>
							<div class="tvb-sb__cell tvb-sb__cell--meta">
								<span class="tvb-pill tvb-pill--diff"><?php echo esc_html( GroupTypes::difficulty_options()[ $r['difficulty'] ] ?? '' ); ?></span>
								<span class="tvb-pill tvb-pill--style"><?php echo esc_html( $this->style_label( $r['style'] ) ); ?></span>
							</div>
							<div class="tvb-sb__cell tvb-sb__cell--note"><?php echo esc_html( $r['note'] ); ?></div>
							<div class="tvb-sb__cell tvb-sb__cell--link">
								<?php if ( '' !== $r['link'] ) : ?>
									<a class="tvb-extlink" href="<?php echo esc_url( $r['link'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Name der Loipe */ __( 'Weitere Infos zu %s (externer Link)', 'b83-schneebericht' ), $item['label'] ) ); ?>">
										<svg class="tvb-extlink__icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3z"></path><path d="M5 5h7v2H7v10h10v-5h2v7H5V5z"></path></svg>
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php else : ?>
						<?php $open = ! empty( $r['open'] ); ?>
						<div class="tvb-sb__row tvb-sb__row--openclose <?php echo $open ? 'is-open' : 'is-closed'; ?>">
							<div class="tvb-sb__cell tvb-sb__cell--name"><?php echo esc_html( $item['label'] ); ?></div>
							<div class="tvb-sb__cell tvb-sb__cell--status">
								<span role="img" aria-label="<?php echo esc_attr( $open ? __( 'geöffnet', 'b83-schneebericht' ) : __( 'geschlossen', 'b83-schneebericht' ) ); ?>"><?php echo $open ? '✅' : '⛔'; ?></span>
							</div>
							<div class="tvb-sb__cell tvb-sb__cell--note"><?php echo esc_html( $r['note'] ); ?></div>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function ampel_label( string $ampel ): string {
		return match ( $ampel ) {
			'gruen' => __( 'Grün – geöffnet', 'b83-schneebericht' ),
			'gelb'  => __( 'Gelb – teilweise geöffnet', 'b83-schneebericht' ),
			default => __( 'Rot – gesperrt', 'b83-schneebericht' ),
		};
	}

	private function style_label( string $style ): string {
		return match ( $style ) {
			'skating' => __( 'Skating Loipe', 'b83-schneebericht' ),
			'beides'  => __( 'Klassische & skating Loipe', 'b83-schneebericht' ),
			default   => __( 'Klassische Loipe', 'b83-schneebericht' ),
		};
	}

	private function format_date( string $ymd ): string {
		$ts = strtotime( $ymd . ' 12:00:00' );
		return $ts ? date_i18n( 'd.m.Y', $ts ) : $ymd;
	}
}
