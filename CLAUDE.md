# b83 Plugin-Standard

Dieses Repository gehört zu den WordPress-Plugins von b83 – Strategisches Online Marketing (https://b83.at, Wolfgang Grabs-Schrempf, office@b83.at). Jede Änderung folgt diesem Standard.

## Kennzeichnung
- Slug, Ordner, Hauptdatei, Text Domain und Repository: `b83-<name>` (Kleinbuchstaben)
- Header: `Author: b83 – Wolfgang Grabs-Schrempf`, `Author URI: https://b83.at`, `Plugin URI: https://b83.at`, `License: GPL-2.0-or-later`, `Requires PHP: 8.1`, `Requires at least: 6.2`, `Update URI:` GitHub-URL des Repositorys
- Namespace `B83\<Name>`, Präfix für Optionen, Hooks, Capabilities, Nonces: `b83_<name>_` bzw. kurz `b83_<kürzel>_`

## Struktur
- Hauptdatei nur Header, Konstanten, PSR-4-Autoloader (`src/`), Bootstrap
- Logik in Klassen unter `src/`, Backend in `src/Admin/`, Ausgabe in `src/Frontend/`
- CSS/JS als eigene Dateien unter `assets/`, kein Inline-Code außer kleiner Konfiguration (wp_localize_script)
- `uninstall.php` vorhanden; Daten nur löschen, wenn der Nutzer das aktiviert hat
- `README.md` und `CHANGELOG.md` pflegen, Version in Header, Konstante und Changelog gleich halten

## Sicherheit (Pflicht)
- `defined( 'ABSPATH' ) || exit;` in jeder PHP-Datei
- Jede schreibende Aktion: Capability prüfen **und** Nonce prüfen (`check_admin_referer` / `check_ajax_referer`)
- Eingaben: `wp_unslash`, dann zentral validieren (Whitelist statt Blacklist), Längen begrenzen
- Ausgaben: immer spät escapen (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- SQL nur über `$wpdb->prepare()`; REST-Routen immer mit `permission_callback`
- Uploads: `is_uploaded_file`, Größen- und Formatprüfung
- Keine Daten im Plugin-Ordner speichern (wird bei Updates ersetzt), keine Secrets im Code
- Hinweise per festem Schlüssel in der URL, nie Freitext aus der URL ausgeben

## Erweiterbarkeit
- Eigene Actions/Filter mit Präfix an sinnvollen Stellen, im README dokumentieren
- Datenbank-Version als Option, Migrationen in `Migration`
- Bestehende Shortcodes, CSS-Klassen und Endpunkte bei Umbauten kompatibel halten

## Sprache
- Benutzeroberfläche Deutsch, alle Texte über `__()` / `esc_html__()` mit Text Domain übersetzbar
- Kommentare auf Deutsch

## Commits
- Aussagekräftige Commit-Nachrichten auf Deutsch, Version bei Release taggen (`v3.0.0`)
