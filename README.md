# Relation Select

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/relation_select/assets/screen.png)

Relation Select ist ein Auswahl-Widget für Datensätze: links die verfügbaren Einträge mit Suche, rechts die Auswahl, die sich per Drag & Drop sortieren lässt. Es läuft in Modulen, in YForm-Formularen und mit eigenem Token auch im Frontend. Gespeichert wird eine kommaseparierte Liste der gewählten Werte, in der Reihenfolge der Auswahl.

Das Widget bringt seine Symbole als Inline-SVG mit und braucht weder Bootstrap noch eine Icon-Font. Farben, Formen und Dark Mode folgen dem REDAXO-Backend (wie Linkmap und MediaPlace), im Frontend lassen sie sich über CSS-Variablen (`--rs-*`) anpassen.

## Auf einen Blick

- Split-Auswahl mit Suche, „Alle sichtbaren hinzufügen“, „Auswahl leeren“
- Sortierung per Drag & Drop, Tastaturbedienung (Enter/Leertaste, Enter im Suchfeld)
- Inline unter dem Feld oder als Overlay mit Zähler-Button („Übernehmen“/„Abbrechen“)
- Mehrfach- oder Einzelauswahl
- Zusatzanzeigen im Label: Farbpunkt, Badge, Online/Offline-Punkt, ID
- Mehrsprachige Felder (`lang:feld` für YForm `lang_text`)
- **Zwei Quellen:** Datensätze per API aus einer Tabelle, oder die Optionen eines vorhandenen `<select>` – damit lassen sich YForm-Felder vom Typ `be_manager_relation` aufwerten, ohne API und ohne Tabellenfreigabe
- Eigener YForm-Feldtyp `relation_select` mit Suche und Listendarstellung im Table Manager
- Rechte: Backend-Benutzer sehen nur Tabellen, die sie auch im Backend sehen dürfen (Details unter Sicherheit)

Verwaltung unter **AddOns → Relation Select**: Einstellungen (Tabellenfreigaben, Zeilenlimit, be_manager_relation-Aufwertung, Frontend-Token), Demo mit echten Daten der Installation, Hilfe.

## Installation

Im Installer „relation_select“ laden und installieren. REDAXO ≥ 5.17, PHP ≥ 8.2. YForm ist optional.

## Verwendung in Modulen

### Inline

```html
<input type="text" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]"
    data-relation-config='{
        "table": "rex_article",
        "valueField": "id",
        "labelField": "name"
    }'>
```

### Modal

```html
<input type="text" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]"
    data-relation-mode="modal"
    data-relation-title="Artikel wählen"
    data-relation-config='{ "table": "rex_article", "valueField": "id", "labelField": "name" }'>
```

Der Button zeigt die Anzahl der gewählten Einträge. „Übernehmen“ schreibt in das Feld, „Abbrechen“ oder Escape verwirft.

### Einzelauswahl

`data-relation-multiple="0"` – es wird genau ein Wert gespeichert, eine neue Auswahl ersetzt die alte.

### Konfiguration (`data-relation-config`)

| Schlüssel | Bedeutung | Beispiel |
|---|---|---|
| `table` | Quelltabelle | `rex_article` |
| `valueField` | gespeicherte Spalte | `id` |
| `labelField` | Anzeige, mehrere mit `\|`, `lang:` für mehrsprachige JSON-Felder | `vorname\|nachname`, `lang:title` |
| `displayFields` | Zusatzanzeige: `color:feld`, `badge:feld`, `(id)` | `color:farbe\|badge:status\|(id)` |
| `dbw` | Filter (siehe unten) | `status = 1, deleted = NULL` |
| `dbob` | Sortierung, Feld und Richtung abwechselnd | `name, ASC, prio, DESC` |
| `clang` | Sprach-ID für `lang:` und Artikeltabellen | `1` |
| `multiple` | `false` = Einzelauswahl (alternativ Attribut) | `false` |
| `token` | nur Frontend | |
| `endpoint` | nur Frontend, Pfad zur index.php | `/index.php` |

`badge:status` wird als Online/Offline-Punkt dargestellt (1 = online).

### Filter-Syntax (`dbw`)

Bedingungen kommasepariert, Werte werden als Parameter gebunden. Operatoren `=`, `!=`, `>`, `<`, `>=`, `<=` und `~` (Textsuche, `*` als Platzhalter, ohne Platzhalter „enthält“). Besondere Werte: `NULL`, `now` (CURRENT_TIMESTAMP), `today` (CURRENT_DATE). Werte mit Komma in `[[...]]`.

```
status = 1
name ~ Meier*
parent_id = NULL
valid_until > now
title = [[Kaffee, Tee und mehr]]
```

Feldnamen in Filter und Sortierung müssen Spalten der Tabelle sein, sonst antwortet die API mit einem Fehler.

## YForm

### Feldtyp `relation_select`

Im Table Manager unter Felder → Hinzufügen den Typ `relation_select` wählen: Tabelle, Wertfeld, Anzeigefeld(er), Zusatzfelder, WHERE-Filter, Sortierung, Mehrfachauswahl. Filter und Sortierung nutzen dieselbe Syntax wie `dbw`/`dbob`. Der Modal-Modus kommt über die individuellen Attribute: `{"data-relation-mode": "modal"}`.

Im Table Manager wird das Feld als Dropdown durchsuchbar, in der Liste erscheint das Label des Datensatzes bzw. bei mehreren die Anzahl.

Bestehende Textfelder mit `data-relation-config` in den Attributen können auf den Typ umgestellt werden; solange „Tabelle“ leer bleibt, wird die Konfiguration aus dem Attribut gelesen.

### `be_manager_relation` aufwerten

Für Felder vom Typ `be_manager_relation` (Select single/multiple) ersetzt Relation Select das Standard-Select durch das Widget. Die Optionen kommen aus dem Select selbst, es gibt keinen API-Aufruf, YForm speichert und validiert wie gewohnt (inklusive `relation_table`). In den individuellen Attributen des Feldes:

```json
{"data-relation-select": "inline"}
```

oder `"modal"`. Unter Einstellungen lässt sich die Aufwertung auch automatisch für alle Mehrfach- bzw. alle Select-Relationen einschalten; `{"data-relation-select": "off"}` nimmt ein Feld davon aus. Die Reihenfolge der Auswahl wird an YForm übergeben, beim erneuten Öffnen zeigt YForm sie allerdings in der Reihenfolge der Zieltabelle, weil das Select keine Reihenfolge kennt.

Dasselbe funktioniert mit jedem `<select>`: `<select multiple data-relation-select="inline">`. Optionen können Zusatzdaten für `data-relation-format` mitbringen (`<option data-status="1">`, Format `badge:status`).

## Frontend

Die API ist vom Frontend aus nur mit Token erreichbar (Einstellungen → Frontend-Token) und liest dann ausschließlich die dort freigegebenen Tabellen. Beispiel:

```html
<link rel="stylesheet" href="/assets/addons/relation_select/relation_select.css">
<script src="/assets/addons/relation_select/relation_select.js"></script>

<input type="hidden" name="tags" value=""
    data-relation-config='{ "table": "rex_tags", "valueField": "id", "labelField": "name", "token": "…", "endpoint": "/index.php" }'>
```

Der Token steht im Quelltext der Seite und ist damit für jeden Besucher lesbar. Er sollte nur Tabellen freischalten, deren Inhalt ohnehin öffentlich ist. „Token löschen“ schaltet die Frontend-API ab.

## Sicherheit

Die API liefert Rohdaten aus Tabellen, deshalb prüft sie jeden Aufruf:

- **Wer:** eingeloggter Backend-Benutzer oder gültiger Frontend-Token. Ohne beides: 401.
- **Welche Tabelle:** Admins alles; Backend-Benutzer die Inhaltstabellen des Cores (`rex_article`, `rex_article_slice`, `rex_media`, `rex_media_category`, `rex_clang`, `rex_template`, `rex_module`, `rex_metainfo_field`) und YForm-Tabellen mit Tabellenrecht (`yform_manager_table_view`/`_edit`); alle Aufrufer die unter Einstellungen freigegebenen Tabellen; der Token nur diese. Tabellen mit Zugangsdaten oder Konfiguration (`rex_user`, `rex_user_session`, `rex_user_passkey`, `rex_user_role`, `rex_config`, `rex_cronjob`, `rex_yform_history*`) sind für alle gesperrt. Sonst: 403.
- **Welche Spalten:** Tabelle und Spalten müssen existieren (sonst 400). Spalten, deren Name auf Passwörter, Tokens, Sessions oder Schlüssel hindeutet, sind gesperrt (403).
- **SQL:** Bezeichner werden geprüft und escaped, Werte gebunden. Farbwerte aus `color:` werden vor der Ausgabe auf gültige CSS-Farben geprüft, alle Texte escaped.
- Es werden keine externen Skripte geladen.

Eigene Tabellen, die Redakteure ohne Admin-Rechte im Widget brauchen, werden unter Einstellungen → Freigegebene Tabellen ausgewählt. Die Auswahl ist selbst ein Relation Select über alle Tabellen der Datenbank, gesperrte Tabellen fehlen darin.

## API-Referenz

`GET index.php?rex-api-call=relation_select`

| Parameter | Pflicht | Bedeutung |
|---|---|---|
| `table` | ja | Tabelle |
| `value_field` | ja | Wertspalte |
| `label_field` | ja | Anzeige, `\|`-getrennt, `lang:` möglich |
| `display_fields` | | Zusatzspalten (`badge:x\|color:y`) |
| `dbw`, `dbob` | | Filter, Sortierung |
| `clang` | | Sprach-ID |
| `values` | | bereits gewählte Werte (kommasepariert), werden unabhängig von Filter und Zeilenlimit mitgeliefert |
| `token` | Frontend | Frontend-Token |

Antwort: JSON-Liste `[{"value": "1", "label": "…", "status": "1"}, …]`, Fehler als `{"error": "…"}` mit passendem HTTP-Status.

JavaScript: `RelationSelect.init(container)` initialisiert neue Felder (Backend automatisch bei `rex:ready`). Das Feld löst bei Änderung `change` (und im Backend `rex:change`) aus; `feld.rsWidget` ist die Widget-Instanz. CSS-Variablen für eigene Farben: `--rs-bg`, `--rs-pane-bg`, `--rs-text`, `--rs-muted`, `--rs-border`, `--rs-active-bg`, `--rs-link`, `--rs-online`, `--rs-danger`, `--rs-btn-primary-bg`, `--rs-header-bg` u. a.

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

**Projektleitung**

- [Peter Bickel](https://github.com/polarpixel)
- [Thomas Skerbis](https://github.com/skerbis)

## Lizenz

MIT License, siehe [LICENSE](LICENSE)
