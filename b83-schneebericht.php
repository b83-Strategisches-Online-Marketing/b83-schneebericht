<?php
/**
 * Plugin Name:       b83 Schneebericht
 * Plugin URI:        https://b83.at
 * Description:       Schneebericht mit Backend-Formular, frei verwaltbaren Liften und Loipen, Shortcodes, Autosave, Backup und Einbettung per iFrame.
 * Version:           3.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            b83 – Wolfgang Grabs-Schrempf
 * Author URI:        https://b83.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       b83-schneebericht
 * Domain Path:       /languages
 * Update URI:        https://github.com/b83-Strategisches-Online-Marketing/b83-schneebericht
 *
 * @package B83\Schneebericht
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'B83_SB_VERSION', '3.1.0' );
define( 'B83_SB_FILE', __FILE__ );
define( 'B83_SB_DIR', plugin_dir_path( __FILE__ ) );
define( 'B83_SB_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4-Autoloader für den Namespace B83\Schneebericht → src/.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'B83\\Schneebericht\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = B83_SB_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \B83\Schneebericht\Migration::class, 'on_activation' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\B83\Schneebericht\Plugin::instance()->boot();
	}
);
