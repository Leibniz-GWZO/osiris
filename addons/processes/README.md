# Add-on „Vorgänge“ (processes)

Lokales Add-on des GWZO für Verwaltungsvorgänge (z. B. Personalanforderung,
Beschaffung) und die Publikationsplanung. Es läuft neben dem OSIRIS-Kern und
verändert dessen Daten nicht.

## Abgrenzung zum Kern

| | Add-on | OSIRIS-Kern |
|---|---|---|
| Daten | `proc_cases`, `proc_counters`, GridFS-Bucket `proc_files` | wird nicht beschrieben |
| Formulare | JSON-Definitionen in `definitions/` | `adminFields`, `adminProjects` bleiben unberührt |
| Routen | `/processes/...` | – |
| Gelesen aus dem Kern | Personen, Einheiten (inkl. Leitung), Rollen, Mail-Einstellungen | |
| Geschrieben in den Kern | nur Nachrichten in den Posteingang (`notifications`, Typ `process`) | |

Anhänge liegen bewusst nicht unter `/uploads`. Die Kern-Route liefert dort
alle Dateien, die nicht dem Muster `<id>.<ext>` folgen, an alle angemeldeten
Nutzenden aus. GridFS liegt in MongoDB und ist damit im nächtlichen
`mongodump` enthalten.

## Einstiegsstellen im Kern

Alle Stellen sind generisch (`$GLOBALS['OSIRIS_ADDON_HOOKS']`) und tun nichts,
wenn das Add-on fehlt.

| Datei | Änderung |
|---|---|
| `index.php` | lädt `addons/processes/index.php` für angemeldete Nutzende |
| `php/SidebarNav.php` | fügt Abschnitte aus dem Hook `sidebar` nach „Inhalte“ ein |
| `components/sidebar.php` | zeigt Einträge aus dem Hook `tasks` unter „Meine Aufgaben“ |
| `pages/admin/features.php` | Schalter für das Feature `processes` |

Ist das Feature `processes` aus, antworten alle Routen mit 404 und das Menü
bleibt leer.

## Bereiche und Vorgangsarten

- `definitions/areas.json`: Bereiche, also Abschnitte im Seitenmenü
  (`verwaltung`, `publikation`), mit Gruppen für den Formularkatalog. Ein
  Bereich ohne Vorgangsarten ist ausgeblendet.
- `definitions/types/*.json`: eine Datei pro Vorgangsart.

`/processes/check` zeigt allen mit `admin.see` die geladenen Definitionen, die
Fehler darin und Rollen, die es nicht gibt.

### Vorgangsart

Kommentare nur zur Erklärung, JSON erlaubt keine Kommentare.

```jsonc
{
  "id": "personalanforderung",          // a-z, 0-9, -
  "area": "verwaltung",
  "group": "personalangelegenheiten",    // Gruppe im Katalog
  "prefix": "PA",                        // Vorgangsnummer PA-2026-0001
  "name": "Personalanforderung", "name_en": "Staff request",
  "description": "...",
  "title": "{pa_vorname} {pa_nachname}", // Titel aus Feldwerten
  "unit_field": "pa_abteilung",          // Einheit des Vorgangs, sonst erste Einheit der antragstellenden Person
  "email": true,                         // zusätzlich E-Mails
  "create":   { "unit_heads": true, "roles": ["personalverwaltung"] },  // wer anlegen darf
  "view_all": { "roles": ["personalverwaltung"] },                      // wer alle Vorgänge sieht
  "steps": [ ... ],
  "hints": [ ... ],
  "fields": [ ... ]
}
```

**Zielgruppen** (`create`, `view_all`): `all`, `roles`, `units` (inkl.
Untereinheiten), `persons` (Benutzernamen), `unit_heads`.

**Schritte** laufen nacheinander:

```json
{ "id": "abteilungsleitung", "label": "Abteilungsleitung",
  "approvers": { "unit_head": true, "self": "skip" },
  "condition": { "field": "betrag", "op": "gt", "value": 1000 },
  "editable": ["pa_kostenstellen"] }
```

- `approvers`: `roles`, `persons`, `unit_head`. Bei `unit_head` entscheidet
  die Leitung der Einheit des Vorgangs. Hat die Einheit keine Leitung, geht es
  zur nächsthöheren. Ist die antragstellende Person selbst Leitung, entfällt
  der Schritt (`self: skip`) oder geht eine Ebene höher (`self: next`).
- Niemand entscheidet über den eigenen Vorgang.
- `condition`: Der Schritt gilt nur, wenn die Bedingung erfüllt ist.
- `editable`: Felder, die die Zuständigen in diesem Schritt ausfüllen dürfen
  (Felder mit `only_steps`).

**Ablauf:** Entwurf (nur für die anlegende Person sichtbar) → eingereicht →
je Schritt freigeben, zurückgeben (mit Begründung) oder ablehnen (mit
Begründung). Nach einer Rückgabe geht der Vorgang beim erneuten Einreichen ab
dem Schritt weiter, der zurückgegeben hat. Zurückziehen ist möglich, solange
der Vorgang offen ist.

**Wer einen Vorgang sieht:** die anlegende Person, Personen aus Feldern mit
`participant: true`, alle, die schon gehandelt haben, die Zuständigen des
aktuellen Schritts und `view_all`.

### Phasen-Modus (`"flow": "phases"`)

Für lange laufende Projekte ohne Freigabe, z. B. Bücher einer Reihe
(`definitions/types/gwzo-reihe-ceu.json`). Statt `steps` gibt es `phases`:

```jsonc
"manage": { "roles": ["reihenkoordination"] },   // verschiebt Phasen, setzt Status, bearbeitet alles
"view_all": { "roles": ["leitungskreis"], "persons": ["anja.fritzsche"] },
"authors_field": "pp_autorinnen", "funding_field": "pp_finanzierung",  // Spalten der Übersicht
"phases": [
  { "id": "peer_review", "label": "Peer Review", "duration_weeks": [13, 17],
    "responsible": "GF", "help": "...",
    "fields": [ ... ],                               // Felder dieser Phase
    "tasks": [ { "id": "honorarvertrag", "label": "Honorarvertrag erstellen",
                 "assignees": { "persons": ["anja.fritzsche"] },
                 "condition": { ... } } ] }
]
```

- Jede Phase bekommt automatisch ein Notizfeld `note_<phase>`. Es ist der
  Zelleninhalt der Übersicht.
- Status: läuft, ruht, erschienen, abgelehnt, zurückgezogen. Phasen lassen
  sich vor, zurück und übersprungen setzen. `phase_log` hält Beginn und Ende
  je Phase fest. Überschreitet die aktuelle Phase `duration_weeks[1]`,
  erscheint ein Hinweis.
- Aufgaben werden fällig, sobald ihre Phase erreicht und die Bedingung erfüllt
  ist. Die Zuständigen sehen sie unter „Meine Aufgaben“, werden benachrichtigt
  und können den Vorgang sehen. Erledigte Aufgaben melden sich bei `manage`.
- Übersicht: `/processes/<bereich>?view=overview`, eine Matrix aus Projekten
  und Phasen.
- Bearbeitet wird je Abschnitt (Stammdaten oder eine Phase), die übrigen
  Felder bleiben unverändert.

### Felder

Typen: `heading`, `string`, `text`, `int`, `float`, `money`, `date`, `bool`
(Ja/Nein), `check`, `select`, `multiselect`, `unit`, `person`, `contacts`
(externe Personen: Name, E-Mail, Funktion), `activity` (Verknüpfung zu einer
OSIRIS-Aktivität per Link oder ID).

Optionen: `label`/`label_en`, `help`/`help_en`, `required`, `width` (1–12),
`default`, `options` (`[{value, label, label_en}]`), `show_if` (Bedingung),
`only_steps`, `participant` (bei `person`), `rows` (bei `text`).

Ausgeblendete Felder werden beim Speichern geleert. Pflichtfelder gelten nur,
wenn sie sichtbar sind.

**Bedingungen:** `{"field": "x", "op": "eq", "value": "y"}`, kombinierbar mit
`{"all": [...]}` und `{"any": [...]}`. Operatoren: `eq`, `ne`, `in`,
`not_in`, `gt`, `gte`, `lt`, `lte`, `filled`, `empty`.

**Hinweise** (`hints`) erscheinen als Warnung, blockieren aber nicht, z. B.
Fristen: `{"date_field": "pa_vertragsbeginn", "min_days_ahead": 61, "message": "..."}`.

## Deployment

1. Code deployen (Container-Rebuild).
2. Rollen, die in den Definitionen vorkommen, in OSIRIS anlegen und Personen
   zuweisen. Für die Personalanforderung sind das `personalverwaltung` (gibt es),
   `verwaltungsleitung` und `geschaeftsfuehrung`.
3. Admin → Funktionen → „Vorgänge (lokales Add-on)“ aktivieren.
4. `/processes/check` prüfen.
