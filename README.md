# b83 Schneebericht

WordPress-Plugin für einen tagesaktuellen Schneebericht: Backend-Formular mit Autosave, frei verwaltbare Lifte und Loipen, Shortcodes, Backup und Einbettung auf anderen Websites.

Entwickelt von [b83 – Strategisches Online Marketing](https://b83.at), Wolfgang Grabs-Schrempf, office@b83.at.

- **Version:** 3.0.0
- **Voraussetzungen:** WordPress 6.2+, PHP 8.1+
- **Lizenz:** GPL-2.0-or-later

## Funktionen

- **Allgemein:** Datum, Richtext-Infos, Schneehöhen, Neuschnee, Temperatur, Lawinenwarnstufe, Link zur Wetter-App
- **Gruppen mit Einträgen**, zwei Typen:
  - *Loipen:* Ampel (rot/gelb/grün), Schwierigkeit, Stil, Notiz, Link
  - *Lifte / Anlagen:* geöffnet/geschlossen, Notiz
- **Lifte & Loipen verwalten** (Administratoren): Gruppen und Einträge anlegen, umbenennen, ausblenden, per Ziehen sortieren, löschen. IDs bleiben beim Umbenennen gleich.
- **Autosave** inklusive Richtext-Editor, Warnung bei ungespeicherten Änderungen, „Alle öffnen/schließen“
- **Berechtigungen:** Bearbeiten je Rolle freischaltbar; Struktur, Rollen, Import und Einstellungen nur für Administratoren
- **Backup:** Export/Import als JSON (auch Backups des alten „TVB Schneebericht“)
- **Einbettung** per iFrame auf freigegebenen Domains, Höhe passt sich automatisch an

## Shortcodes

```
[b83_schneebericht bereich="allgemein"]
[b83_schneebericht bereich="loipen_postalm"]
[b83_schneebericht bereich="skilifte_postalm" eintraege="welserlift,edtlift" titel="0"]
```

Die bisherigen Shortcodes `[tvb_schnee_allgemein]`, `[tvb_schnee_loipen_abtenau]`, `[tvb_schnee_loipen_postalm]`, `[tvb_schnee_skipisten_sonnleiten]`, `[tvb_schnee_skilifte_karkogel]` und `[tvb_schnee_skilifte_postalm]` funktionieren weiter.

## Einbettung auf anderen Websites

```html
<div class="b83-sb-embed" data-origin="https://www.example.at/" data-section="loipen_postalm"></div>
<script async src="https://www.example.at/wp-content/plugins/b83-schneebericht/assets/js/embed.js"></script>
```

Optional: `data-items="id1,id2"`, `data-title="0"`. Die einbettende Domain muss unter *Schneebericht → Shortcodes & Backup → Einstellungen* freigegeben sein.

## Umstieg vom alten Plugin „TVB Schneebericht“

1. b83 Schneebericht installieren und aktivieren – Daten, Rollenfreigaben und die Embed-Freigabe für `*.abtenau-info.at` werden automatisch übernommen.
2. Im Backend kurz prüfen, ob alles stimmt.
3. Altes Plugin deaktivieren und löschen.
4. Bestehende Embed-Codes: Im `<script>`-Tag den Pfad von `/plugins/tvb-schneebericht/embed/tvb-sb-embed.js` auf `/plugins/b83-schneebericht/assets/js/embed.js` ändern. Die `<div class="tvb-sb-embed" …>`-Container können bleiben.

## Erweitern

| Hook | Typ | Zweck |
|---|---|---|
| `b83_schneebericht_loaded` | Action | Plugin geladen, Instanz als Parameter |
| `b83_schneebericht_data_saved` | Action | Nach jedem Speichern des Schneeberichts |
| `b83_schneebericht_config_saved` | Action | Nach Änderung der Struktur |
| `b83_schneebericht_section_html` | Filter | HTML eines Bereichs anpassen |
| `b83_schneebericht_default_config` | Filter | Ausgangsstruktur bei Neuinstallation |

## Aufbau

```
b83-schneebericht.php      Plugin-Header, Konstanten, Autoloader
uninstall.php              Daten nur entfernen, wenn in den Einstellungen gewünscht
src/
  Plugin.php               verdrahtet alle Komponenten
  Repository.php           Datenhaltung (wp_options)
  Sanitizer.php            zentrale Validierung aller Eingaben
  GroupTypes.php           Gruppentypen und ihre Felder
  Capabilities.php         Berechtigungen
  Migration.php            Installation, Updates, Übernahme aus TVB-Plugin
  Admin/                   Backend (Status, Struktur, Werkzeuge)
  Frontend/                Renderer, Shortcodes, Embed
assets/css, assets/js      Styles und Skripte
```

Gespeichert wird in den Optionen `b83_schneebericht_config`, `b83_schneebericht_data` und `b83_schneebericht_settings`.
