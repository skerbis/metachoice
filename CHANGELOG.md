# Changelog

## Version 2.0.0 (2026-09-07)

### 🔒 Sicherheit
- **API-Zugriff eingeschränkt**: Bisher konnte jeder eingeloggte Backend-Benutzer und jeder Token-Inhaber beliebige Tabellen und Spalten lesen (z. B. `rex_user` mit Passwort-Hashes, `rex_config`). Jetzt gilt: Tabellen mit Zugangsdaten/Konfiguration sind immer gesperrt; Backend-Benutzer lesen Core-Inhaltstabellen und YForm-Tabellen mit Tabellenrecht; Admins alles; freigegebene Tabellen (Einstellungen) alle Aufrufer; der Frontend-Token nur freigegebene Tabellen. Spalten mit Passwörtern/Tokens/Sessions sind gesperrt, Tabelle und Spalten müssen existieren
- **Frontend-Token nur für Admins sichtbar**: Die Token-Seite war für alle Backend-Benutzer erreichbar. Token lässt sich jetzt neu erzeugen oder löschen (CSRF-geschützt)
- **Kein externes Skript mehr**: SortableJS wurde von einem CDN ins Backend geladen. Sortierung jetzt per nativem Drag & Drop ohne Abhängigkeit
- **CSS-Injection über `color:`-Felder** ausgeschlossen (nur gültige Farbwerte werden ausgegeben); Item-Daten nicht mehr als HTML-Attribut, sondern im JS gehalten
- **YForm-Feldtyp**: WHERE-Filter wurde im Table-Manager-Suchfeld als roher SQL-String eingefügt; jetzt gleiche geprüfte Filtersyntax wie das Widget, Werte gebunden. Bezeichner werden geprüft, Zugangsdaten-Tabellen ausgeschlossen
- Zeilenlimit pro Abfrage konfigurierbar (Einstellungen)

### ✨ Neu
- **`<select>` als Quelle**: `data-relation-select="inline|modal"` wertet jedes Select auf, insbesondere YForm `be_manager_relation` (Select single/multiple) – ohne API, ohne Tabellenfreigabe; optional automatisch für alle Relationen (Einstellungen)
- AddOn-Seiten: Einstellungen, Demo mit echten Daten, Hilfe (README)
- Einzelauswahl per `data-relation-multiple="0"`; der YForm-Schalter „Mehrfachauswahl“ wirkt jetzt tatsächlich
- Parameter `values`: bereits gewählte Datensätze werden immer mitgeliefert (unabhängig von Filter/Limit), ein Request statt zwei
- Modal mit „Abbrechen“, das Änderungen verwirft, Escape, Scroll-Lock ohne Sprung, Zähler-Button
- Tastaturbedienung (Enter/Leertaste auf Einträgen, Enter im Suchfeld nimmt den ersten Treffer)
- Gestaltung an Linkmap/MediaPlace angeglichen (Palette, Badges, Dark Mode), Symbole als Inline-SVG (frontendtauglich)
- Alle Texte übersetzbar (de/en), Fallback im JS für das Frontend
- `data-relation-title` für den Modal-Titel, `endpoint` für Frontend-Aufrufe

### 🐛 Bugfixes
- Filter: `now`/`today` wurden auch innerhalb anderer Wörter ersetzt (`unknown` → `unkCURRENT_TIMESTAMP`); `[[...]]` für Werte mit Komma funktioniert jetzt wie dokumentiert
- Assets bekommen einen Cache-Buster, Browser laden nach Updates nicht mehr alte Dateien
- `test_module.php` aus dem AddOn entfernt (Demo-Seite ersetzt es)

### ⚠️ Hinweise zum Update
- Nicht-Admins, die bisher beliebige eigene Tabellen im Widget nutzen konnten, brauchen jetzt eine Freigabe unter Einstellungen → Freigegebene Tabellen (oder ein YForm-Tabellenrecht)
- Frontend-Integrationen: die freigegebenen Tabellen müssen eingetragen werden; der Token liest nichts anderes mehr

## Version 1.5.0 (2026-03-29)

### ✨ Neue Features
- **Mehrsprachige Felder (`lang:` Präfix)**: Display-Felder können mit `lang:feldname` angegeben werden, um automatisch den Wert in der aktuellen Sprache aus YForm `lang_text`-Feldern zu extrahieren
  - Unterstützt beide JSON-Formate: Array-Format `[{"clang_id":1, "value":"..."}]` (YForm) und Object-Format `{"de":"..."}` (Legacy)
  - Extraktion erfolgt per `JSON_TABLE` (Array) bzw. `JSON_UNQUOTE/JSON_EXTRACT` (Object) direkt in SQL
  - Fallback auf erstes verfügbares Array-Element wenn die aktuelle Sprache nicht gefunden wird

### 🐛 Bugfixes
- **`json_table` für YForm `lang_text` Array-Format**: Der bisherige `JSON_UNQUOTE/JSON_EXTRACT`-Ansatz mit `$.de` hat immer den rohen JSON-String zurückgegeben. Korrigiert auf `JSON_TABLE`-Subquery mit Fallback
- **Namespace-Fehler `rex_clang`**: Fehlendes `use`-Statement in der API ergänzt
- **Format-Erkennung**: `lang:` Präfix erkennt jetzt zuverlässig beide JSON-Formate und wählt automatisch die passende Extraktionsstrategie

## Version 1.4.2 (2026-03-27)

### 🐛 Bugfixes
- **Modal-Ansicht Scroll-Sprung**: Wenn sich das Backend-Modal öffnet, sprang die Ansicht des Formulars im Hintergrund ganz nach oben (durch global gesetztes `overflow: hidden` auf den Body). Dieses Verhalten wurde deaktiviert.
- **`data-relation-mode` in Template berücksichtigt**: Das hartcodierte `inline`-Verhalten im YForm Bootstrap-Template (value.relation_select.tpl.php) wurde korrigiert, sodass im Tabllenmanager angegebene individuelle YForm-Attribute (wie `{"data-relation-mode":"modal"}`) ab jetzt korrekt angewendet werden.

## Version 1.4.1 (2026-02-25)

### 🐛 Bugfixes
- **`displayFields` mit Präfix-Syntax** (`badge:feldname`, `color:feldname`): API hat den Präfix direkt als SQL-Spaltennamen verwendet → 404-Fehler. Fix: Präfix wird vor dem `escapeIdentifier`-Aufruf entfernt (`badge:status` → `status`)
- **Badges/Farben im YForm-Feldtyp nicht sichtbar**: Das YForm-Template hat `displayFields` gesetzt, aber `displayFormat` gefehlt → `formatLabel()` im JS hat nichts gerendert. Fix: Template setzt jetzt beide Keys identisch
- **JS-Lookup `i['badge:status']` statt `i['status']`**: Bei der Suche nach vorhandenen Display-Items wurde der volle Wert inkl. Präfix als Feldname genutzt. Fix: Präfix wird jetzt auch im JS vor dem Lookup entfernt

### ✨ Neue Features
- **Legacy-Kompatibilität für konvertierte Felder**: Der neue YForm-Feldtyp liest bei leerem Feld `Tabelle` automatisch eine vorhandene `data-relation-config` aus dem Feld *Individuelle Attribute* (wie beim bisherigen Textfeld). Alle Parameternamen werden gemappt (`valueField`→`value_field`, `dbw`→`filter`, `dbob`→`order_by` usw.)
- **`attributes`-Feld wie in `text.php`**: Der YForm-Feldtyp zeigt jetzt das Standard-YForm-Attribut-Feld mit erweiterter Notice, die auf den Fallback-Mechanismus und die Backup-Empfehlung vor dem Feldtypwechsel hinweist

## Version 1.4.0 (2026-02-24)

### ✨ Neue Features
- **YForm Value-Typ `relation_select`**: Das Widget steht jetzt nativ als eigener YForm-Feldtyp zur Verfügung – kein Umweg über `text` mit individuellen Attributen mehr nötig
  - Felder: Tabelle, Wertfeld, Anzeigefeld(er), Zusatzfelder (Display), WHERE-Filter, Sortierung, Mehrfachauswahl
- **YForm Tabellenmanager-Suche**: Suchfeld für `relation_select`-Felder ist jetzt ein Dropdown mit allen verfügbaren Einträgen der Quelltabelle
- **Listendarstellung**: Bei einem ausgewählten Datensatz wird dessen Label angezeigt, bei mehreren Datensätzen die Anzahl als Badge (z. B. `5 Datensätze`)

### 🔧 Technisch
- Neue Datei `lib/yform/value/rex_yform_value_relation_select.php` mit `getSearchField()`, `getSearchFilter()`, `getListValue()`
- Neues YForm-Bootstrap-Template `ytemplates/bootstrap/value.relation_select.tpl.php`
- `boot.php` registriert automatisch den Template-Pfad wenn yform verfügbar ist
- `package.yml` deklariert yform >= 4.0 als optionale Abhängigkeit

## Version 1.3.3 (2026-02-06)

### ✨ Neue Features
- **"Alle hinzufügen" Button**: Neben der Suche - fügt alle sichtbaren/gefilterten Items auf einmal hinzu
- **"Liste leeren" Button**: Papierkorb-Icon bei ausgewählten Einträgen - entfernt alle Auswahlen
- **Status-Kreis-Indikator**: Status-Feld zeigt jetzt visuelle Kreise statt Text
  - ● Gefüllter Kreis = Online (status=1)
  - ○ Leerer Kreis = Offline (status=0)
  - Tooltip zeigt "Online"/"Offline" beim Hover

### 🎨 UX-Verbesserungen
- Bulk-Aktionen: Schnelles Hinzufügen/Entfernen mehrerer Items
- Visueller Status-Indikator ist intuitiver als "0" oder "1"
- Symmetrisches Design mit Aktions-Buttons links und rechts

### 🔧 Technisch
- Badge-Logik erweitert: Spezialbehandlung für `status` Feld
- Andere badge-Felder zeigen weiterhin Text-Badges

## Version 1.3.2 (2026-02-06)

### 🐛 Bugfixes
- **Multi-Language Support**: Farben und Badges werden jetzt korrekt beim Laden wiederhergestellt
  - API filtert jetzt nach `clang_id` für mehrsprachige Tabellen (rex_article)
  - Separater API-Call für ausgewählte Items mit vollständigen `displayFields`
  - Duplikat-Handling: Bei mehreren Sprachversionen wird die mit gefüllten Feldern bevorzugt
- **Selected Items**: Ausgewählte Einträge zeigen jetzt color-preview und badges beim Seitenladen
- **Language Filter**: Automatische Erkennung der aktuellen Backend-Sprache (`rex.clang_id`)

### 🔧 Technisch
- `clang` Parameter in API hinzugefügt (Standard: 1)
- JavaScript erkennt `rex.clang_id` automatisch oder nutzt Config-Wert
- Promise.all() für parallele API-Calls (available + selected items)
- Fallback-Logik bei mehreren Items mit gleicher ID

## Version 1.3.0 (2026-02-06)

### ✨ Neue Features
- **Erweiterte Label-Formatierung**: Visuelle Anreicherung der Einträge
  - **Color-Preview**: `displayFormat: "color:feldname"` zeigt Farbquadrat an
  - **Status-Badge**: `displayFormat: "badge:feldname"` zeigt Status als Badge
  - **ID-Display**: `displayFormat: "(id)"` zeigt ID in Klammern
  - **Kombinationen**: Mehrere Formate kombinierbar (z.B. `"color:color|(id)|badge:status"`)
  - Keine Bootstrap-Abhängigkeit - Pure CSS-Komponenten
  - Dark-Theme-Unterstützung für alle neuen Komponenten
- **API-Erweiterung**: `displayFields` Parameter für zusätzliche Datenfelder
- **Item-Daten**: Alle Felder werden als `data-item` Attribut gespeichert für flexible Verwendung

### 🐛 Bugfixes
- **PHP Syntax Error**: api.php Zeile 93 korrigiert - ORDER BY und SELECT Statements waren durcheinander geraten
- **Type Safety**: `formatLabel()` konvertiert jetzt alle Werte zu Strings vor `.trim()` Aufruf
- **Empty Value Handling**: Leere oder null Werte in displayFields werden korrekt behandelt

### 🎭 UX-Verbesserungen
- Farbvorschau macht Kategorien/Tags sofort erkennbar
- Status-Badges zeigen Zustand auf einen Blick
- ID-Anzeige hilft bei der Identifikation
- Visuelle Hierarchie durch gestaffelte Komponenten

### 📘 Dokumentation
- README erweitert mit 6 realistischen Praxisbeispielen:
  1. Artikel mit Farbkategorien (REDAXO Modul mit Eingabe + Ausgabe)
  2. Produkt-Tags mit Farben (YForm-Tabelle)
  3. Event-Auswahl mit Datum-Filter
  4. Mitarbeiter-Verwaltung mit Status-Badges
  5. Kategorien mit Hierarchie und Farbe
  6. News-System mit Prioritäten
- Kombinationsbeispiele dokumentiert
- YForm-Integration mit doppelt-escaptem JSON erklärt

### 🎨 CSS
- `.relation-color-preview` - 16x16px Farbquadrat mit Border
- `.relation-badge` - Info-Badge im REDAXO-Stil
- `.relation-id` - Monospace ID-Display
- `.relation-label-text` - Haupt-Label-Text
- Alle Komponenten mit Dark-Theme-Varianten

## Version 1.3.1 (2026-02-06)

### 🐛 Bugfixes
- **Badge Initial-State**: Badge zeigt jetzt korrekt die `has-items` Klasse (blau) beim Laden, wenn bereits Werte ausgewählt sind
- **Speicher-Logik**: Funktionierende `updateValue()` Methode von Version 1.1.2 wiederhergestellt - Daten werden jetzt wieder korrekt gespeichert
- **README korrigiert**: Alle Modul-Beispiele verwenden jetzt `REX_INPUT_VALUE[1]` statt `my_field` für korrekte REDAXO-Modul-Integration

### ✨ Features
- **SVG-Icons**: Alle FontAwesome-Icons durch eigene inline-SVG ersetzt (Material Design Style)
  - Plus-Icon für Hinzufügen
  - Minus-Icon für Entfernen
  - Drag-Handle (6 Punkte)
  - Listen-Icon für Modal-Button
  - Close-Icon für Modal
  - `fill: currentColor` für automatische Theme-Anpassung
  - Keine externe Abhängigkeit mehr
- **Click-on-Row**: Gesamte Zeile ist jetzt klickbar (nicht nur der Button)
  - Verbesserte UX
  - Hover-Effekt zeigt Klickbarkeit
  - Button hat `stopPropagation()` für saubere Event-Behandlung
- **XSS-Schutz**: Alle Values und Labels werden durch `$('<div>').text().html()` escaped

### 📚 Dokumentation
- README-Beispiele korrigiert mit `REX_INPUT_VALUE[x]` und `REX_VALUE[x]`
- Testmodul hinzugefügt (`test_module.php`) für Debug-Zwecke

### 🔧 Technisch
- Basis: Version 1.1.2 (funktionierende Speicher-Logik)
- Modal-System aufgesetzt auf bewährte Grundlage
- `input.value = ...` statt `$(input).val()` für Kompatibilität

## Version 1.2.0 (2026-02-05)

### ✨ Neue Features
- **Modal-Modus**: Eigenes Modal-System für platzsparende Layouts
  - `data-relation-mode="modal"` aktiviert Modal-Darstellung
  - **Badge mit Anzahl** ausgewählter Einträge (live-Update)
  - Badge ist grau bei 0, blau bei Auswahl
  - Input-Feld wird ausgeblendet (nur Button sichtbar)
  - Frontend-kompatibel (keine Bootstrap-Abhängigkeit)
  - ESC-Taste zum Schließen
  - Body-Scroll-Lock während Modal aktiv
  - Smooth Animations mit Scale-Effekt
- **Inline-Modus**: Standard-Darstellung (wie bisher)
- **Dual-Mode-Support**: Frei wählbar per Attribut

### 🔒 Security
- **XSS-Schutz**: Labels und Values werden jetzt korrekt escaped in JavaScript
- **Type Safety**: Strikte Typ-Prüfungen für alle API-Parameter implementiert
- **SQL-Injection**: Verbesserte Parameter-Validierung und Prepared Statements
- **Input-Validierung**: Strikte Validierung aller GET-Parameter

### ✅ Code Quality
- **Rexstan**: Alle 12 Rexstan-Fehler behoben ✅
  - Type-Hints für alle Methoden hinzugefügt
  - `empty()` durch strikte Vergleiche ersetzt (`count($array) > 0`)
  - Boolean-Operationen mit expliziten Checks korrigiert
  - Strikte `in_array()` Vergleiche mit `true` Parameter
  - Unreachable Code entfernt
- **REDAXO Standards**: 
  - `rex_response::sendJson()` statt manueller Header
  - `rex_response::cleanOutputBuffers()` am Anfang der API-Methode
  - Korrekte Return-Types für rex_api_function
- **PSR-12**: Code-Style vollständig angepasst
  - Yoda-Notation für Vergleiche (`'' === $value`)
  - Konsistente String-Quotes
  - Optimierte Imports mit `use function`
  - Trailing Commas in Arrays

### 🎨 UI/UX & Theme Support
- **Dark Theme Support**:
  - Vollständige Unterstützung für `rex-theme-dark` ✨
  - Auto-Modus via `prefers-color-scheme: dark` ✨
  - REDAXO-konforme Farbvariablen aus `_variables-dark.scss`
  - CSS Custom Properties mit Fallbacks
- **CSS-Verbesserungen**:
  - Hover-Effekte für bessere Interaktivität
  - Verbesserte Farbgebung und Kontraste
  - Handle-Cursor für Drag & Drop
  - Transition-Animationen
- **Accessibility**:
  - ARIA-Labels für alle interaktiven Elemente
  - Focus-States mit Outline für Tastatur-Navigation
  - Semantische Button-Labels
- **Responsive Design**:
  - Mobile-optimiertes Layout mit flexbox
  - Breakpoints für Tablets und Smartphones

### 🚀 Performance
- **Document Fragments**: DOM-Elemente werden gebündelt eingefügt (Single Reflow)
- **Debounced Search**: Suchfunktion mit 200ms Verzögerung reduziert API-Calls
- **Cache Control**: Korrekte HTTP-Header für frische Daten
- **Optimized Queries**: Effiziente SQL-Queries mit DISTINCT und Indexierung

### 📦 Meta
- **package.yml**: Version auf 1.2.0 erhöht
- **PHP-Requirement**: PHP >= 8.2 (für `never` Return-Type)
- **Author**: Korrigiert zu "Friends of REDAXO"
- **Repository**: GitHub-Link zu FriendsOfREDAXO aktualisiert
- **CHANGELOG.md**: Vollständige Dokumentation aller Änderungen

### 📚 Dokumentation
- **README.md**: Erweitert mit Security-Hinweisen
- **Performance-Tipps**: Dokumentiert im README
- **Accessibility-Features**: Aufgelistet und erklärt
- **Theme-Support**: Dark/Light/Auto Modi dokumentiert

## Version 1.1.2
- Initiale Version
