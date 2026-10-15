# Changelog

## 3.0.0 – 2026-10-15

Neuaufbau als b83-Plugin (Nachfolger von „TVB Schneebericht“ 2.3.1).

### Neu
- Lifte und Loipen im Backend verwalten: Gruppen und Einträge anlegen, umbenennen, ausblenden, sortieren, löschen
- Neuer Shortcode `[b83_schneebericht bereich="…" eintraege="…" titel="0"]`
- Erlaubte Embed-Domains in den Einstellungen pflegbar
- Autosave erkennt Änderungen im Richtext-Editor, Warnung vor ungespeicherten Änderungen
- „Heute“-Knopf beim Datum, „Alle öffnen/schließen“ bei Liften
- Option, beim Löschen des Plugins alle Daten zu entfernen (standardmäßig aus)

### Geändert
- Daten liegen in der Datenbank statt in einer JSON-Datei im Plugin-Ordner (gingen sonst bei Updates verloren und waren öffentlich abrufbar)
- Struktur, Rollen, Import und Einstellungen nur noch für Administratoren
- Import prüft Dateigröße, Format und alle Werte
- Einbettung über eigenen Endpunkt statt admin-ajax; Frontend-CSS nur noch auf Seiten mit Schneebericht
- Code in Klassen aufgeteilt, übersetzbar, WordPress-Coding-Standards

### Behoben
- Mehrere Einbettungen auf einer Seite beeinflussten gegenseitig ihre Höhe
- Rollen mit Schneebericht-Zugriff konnten den Zugriff an beliebige Rollen weitergeben

### Kompatibilität
- Alte Shortcodes `[tvb_schnee_…]`, alter Embed-Endpunkt und alte Embed-Container funktionieren weiter
- Daten, Rollenfreigaben und Embed-Freigabe werden bei der Aktivierung automatisch übernommen
- Alte Backups lassen sich importieren
