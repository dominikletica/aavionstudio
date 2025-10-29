# aavion Studio – Conzept-Outline (Draft 0.1.0-dev in German)

> Projekt: dominikletica/aavionstudio  
> Status: Entwurf – Stand: 29.10.2025  
> Zielgruppe: Dev/Tech-Lead  
> Fokus: Shared‑Hosting‑freundlich, minimaler Setup-Aufwand, LLM‑optimierte Exporte  
> Autor: Dominik Letica (+ GPT-5-Pro)  
> Projekt-Host: aavion.media  
> Mail-Kontakt: dominik@aavion.media

---

## 1. Kurzbeschreibung

Leichtgewichtiges Full‑Stack CMS mit:
- frei definierbaren Fieldsets/Schema‑validierten JSON‑Payloads pro Entity,
- Draft‑Space + Commit/Versionierung (Git‑ähnliches Arbeiten),
- Resolver für `[ref]`/`[query]` Shortcodes (Querverweise, Abfragen),
- deterministischem „Published Snapshot“ je Projekt als SoT für Frontend, API, Export,
- optionalen LLM‑Exporten (JSON/JSONL, optional TOON als Extra-Flavor).

💬 Kommentar: Der „Published Snapshot“ ist die eine, klare Wahrheit. Draft/Versionierung leben in SQLite, aber ausgeliefert werden nur veröffentlichte, bereits aufgelöste Daten.

---

## 2. Nicht‑funktionale Ziele

- **Hosting:** Shared‑Hosting‑kompatibel, PHP 8.1+ (bis 8.4), Apache/htaccess. Kein CLI‑Zwang für Endnutzer (Developer-CLI optional).
- **Portabilität:** Single‑Folder‑Drop‑in ins Webroot möglich; Installer/Setup‑Wizard im Browser.
- **Performance/Robustheit:** Atomische Writes beim Publizieren/Export; defensives Caching.
- **Sicherheit/DSGVO:** API‑Keys, Rollen, Rate Limiting, Logs mit IP‑Anonymisierung (14 Tage), MaxMind optional.
- **Übersetzbarkeit:** Alle UI‑Strings i18n; API‑Responses standardkonform englisch.

---

## 3. Tech‑Stack & Alternativen

**Präferenz:** Symfony (Framework, Security, Validator, Serializer, Translation, Monolog).  
**Frontend:** Twig + Tailwind (prebuilt), optional Stimulus/Alpine für UI‑Interaktionen.  
**DB:** SQLite (system.brain, user.brain via ATTACH), JSON‑Payloads als TEXT.  
**API:** Read‑Only auf Published Snapshot; Write‑API (optional) mit „Instant‑Commit“.

💬 Kommentar: Falls Shared‑Hosting die `public/`‑Spezifika nicht zulässt, liefern wir eine „Flat Webroot Build“-Variante (siehe Distribution). Alternativ-Framework (nur falls nötig): SlimPHP (+Twig) als „Plan B“ – leichtgewichtiger, aber mehr Handarbeit bei Security/Tools. Empfehlung: bei Symfony bleiben.

---

## 4. Distribution & Setup

**Modus A (Standard‑Symfony):**
- Struktur mit `public/` als Webroot; Releases enthalten vorkompilierte Assets; `.htaccess` regelt Rewrites.
- Für klassische vHosts/subdomains ideal.

**Modus B (Shared‑Hosting Drop‑in „Flat Webroot Build“):**
- Inhalte aus `public/` werden für Release nach Webroot gemappt (Front‑Controller/Assets liegen direkt dort).
- App‑Code liegt in geschütztem Unterordner (z. B. `app/`); `.htaccess` blockt direkten Zugriff darauf.
- Browser‑Installer „/setup“: Umgebung prüfen, DB anlegen, Admin erstellen, `.env.local.php` schreiben.

💬 Kommentar: Endnutzer braucht weder Composer noch Node. Dev‑Builds lokal, Releases als ZIP mit `vendor/` + prebuilt CSS.  
❤️ Bevorzugter Modus: Modus B (Flat Webroot Build).  

---

## 5. Domänenmodell (vereinfachte Entitäten)

- **Project**: slug, title, settings (Theme, Gate‑Config, Error‑Page‑Zuordnungen)
- **Entity**: project_id, slug, type, subtype, parent_id, flags (visible, menu, exportable, locked), meta
- **EntityVersion**: entity_id, version_id, payload_json (TEXT), author_id, committed_at, message, active_flag
- **Draft**: entity_id, payload_json, updated_by, updated_at, autosave
- **Schema**: name, scope (global/projekt), json_schema, template_ref, config
- **Template**: name, twig, meta
- **ApiKey**: user_id, key_hash, label, scopes, created_at, last_used_at
- **User**: profile, roles, locale
- **Log**: type (ERROR/WARNING/DEBUG/AUTH/ACCESS), ctx_json, created_at

IDs als ULID/UUID. Soft‑Delete über Flags/Versionen, Hard‑Delete nur via ACL‑geschütztem Modul.
Relationen werden über Entities vom Typ "relation" gelöst.

---

## 6. Hierarchie & URLs

- **Modell:** Materialized Path (Pfadspalte + Depth), da effiziente Reads und einfache Moves/Batch‑Moves.
- **URLs:** `/<project>/<hierarchie-aus-slugs>`; Standardprojekt „default“ liegt im Root ohne Präfix.
- **Regeln:** Parent mit aktiven Kindern kann nicht gelöscht/deaktiviert werden (DB‑Constraint + Domain‑Check).

💬 Kommentar: Materialized Path ist für dein „leicht + robust“‑Ziel ein guter Sweet‑Spot; Adjacency bleibt als Metapher in der UI sichtbar, technisch pflegen wir Pfade.

---

## 7. Datenhaltung & JSON

- **SQLite** mit zwei Dateien: `system.brain` (System/Configs/Logs), `user.brain` (Inhalte) über ATTACH.
- **JSON‑Payloads** als TEXT; (De)Serialisierung in PHP. JSON1‑Funktionen optional (kein Hard‑Dependency).
- **FK‑Sicherheit:** Foreign Keys aktiv; RESTRICT/SET NULL passend konfigurieren.

💬 Kommentar: Wir vermeiden DB‑seitige JSON‑Query‑Magie, um Portabilität & Einfachheit zu maximieren.

---

## 8. Draft → Commit → Published Snapshot

- **Editing:** Aktive Version → Draft kopieren; Bearbeitung im Studio; Autosave vorhanden.
- **Commit:** Transaktion; neue Version aktivieren, Vorgänger inaktiv; optional Commit‑Message/Diff.
- **Resolver‑Pipeline:** Beim Commit werden `[ref]`/`[query]` gegen den aktuellen Datenstand rekursiv aufgelöst (mit Zyklusschutz).
- **Snapshot:** Pro Projekt eine JSON‑Datei (oder logisch segmentiert), atomisch geschrieben (Temp → Rename).
- **Konsistenz:** Frontend/API/Exporter lesen ausschließlich den Snapshot.

💬 Kommentar: Start ohne Queue – Commit synchron. Später kann eine Queue (Messenger) für Lastspitzen nachrüsten.

---

## 9. Shortcodes & Resolver

**Syntax (vereinfacht):**
- `[ref @entity.field {link}]…[/ref]`
- `{link}` wird vom Resolver ignoriert, Frontend-Renderer generiert Hyperlink.
- `[query {@entity?} select field[,field…] where field <op> value|@entity.field {and/or …} {sort} {order} {mode} {template …}]…[/query]`
  - `<op>` kann folgendes sein: ==, !=, <, >, <=, >=, ~ (contains), in (array, commalist)
  - `where` `<field>` kann mehrere Felder abgleichen (`<field1>|<field2>`).
  - `value` kann `@entity.field` vergleichen.

**Verhalten:**
- Shortcodes bleiben als Marker im gespeicherten Content (nicht „eingebacken“), "mode array" überschreibt dieses Verhalten für Auswertungen.
- Beim Publish werden Ergebnisse inline ergänzt (für deterministische Nachvollziehbarkeit) und im Snapshot persistiert.
- Fehler als Codes (übersetzbar): `ERR_REF_ENTITY_NOT_FOUND`, `ERR_QUERY_UNRESOLVABLE`, `ERR_QUERY_NO_RESULTS`.

💬 Kommentar: Parser als Twig‑Tag/TokenParser (stabiler als reine Regex); Stripping der aufgelösten Inhalte bei erneutem Speichern, damit Inhalte dynamisch bleiben.  
Fieldsets (Json-Schema) kann Aggregat-Felder beinhalten (hidden, read-only, enthält einen Query und liefert ein Array zurück für weitere Abfragen).  
@(self).field referenziert ein Feld der aktuellen Entity.

---

## 10. API‑Design

- **Read‑Only API:** Gibt den Published Snapshot aus (identische Pfade wie Frontend, Prefix `/api/v1/`).
- **Write‑API (Option):** „Instant‑Commit“: POST/PUT erzeugen sofort eine neue aktive Version (oder Draft+Commit in einem Schritt).
- **Auth:** API‑Keys (Bearer), pro Nutzer mehrere Keys; Scopes möglich.
- **Rate‑Limiting:** Konfigurierbare Limits pro Route/Key/IP.

💬 Kommentar: Read‑Only auf Snapshot minimiert Laufzeitkomplexität und hält Frontend/API deterministisch konsistent.

---

## 11. Exporte (LLM‑optimiert)

- **Primärformate:** JSON / JSONL (kanonisch, parserfreundlich), YAML optional.
- **TOON (optional):** Export‑Flavor, on‑the‑fly aus JSON konvertiert; SoT bleibt JSON.
- **Presets:** Konfigurierbare Export‑Profile (Metadaten, Usage‑Hints, Policies, Selektion von Entities/Feldern).

💬 Kommentar: TOON ist jung; wir bieten es **nur** als optionalen Export an, um Tooling‑Risiken zu minimieren. Parserseitig bleibt alles auf JSON stabil. Der Converter hängt hinter einem Feature‑Flag.

---

## 12. Dateien & Auslieferung

- **Ablage:** `data/uploads/<hash>/file.ext`, Metadaten in DB (Checksumme, MIME, Owner, ACL).
- **Auslieferung:**
  - Public‑Inhalte: direkt statisch (CDN‑freundlich).
  - Geschützte Inhalte: über Controller mit Signaturen/ACL (z. B. zeitlich begrenzte Links).
- **Snapshots:** Entweder public (schnell) oder per Controller (Kontrolle/Headers). Projekt‑Setting pro Snapshot‑Gruppe.

💬 Kommentar: Hybride Strategie – du kannst später problemlos „Login‑Bereiche“ abtrennen, ohne die öffentliche Auslieferung zu bremsen.

---

## 13. UI/UX & Theming

- **UI‑Grundlage:** Tailwind (vorkompiliert), modernes aber ruhiges Design, schnelle Interaktionen, einheitliche Optik.
- **Theming:** CSS‑Variablen global und optional pro Projekt (kein Runtime‑Tailwind‑Rebuild nötig).
- **Editor:** Markdown mit CodeMirror (TWIG/JSON‑Highlighting), Autocomplete für `@entity`‑Referenzen.
- **Templatepacks:** Import/Export von Schemas/Frontends; Rückkehr zum Pack‑Original jederzeit möglich.
- **Benutzerunterstützung:** OnSite-Dokumentation (z.B. Tooltips oder Overlay) für Benutzerfreundlichkeit.

💬 Kommentar: Keine „On‑Request“-Kompilierung von Tailwind; stattdessen Variablen + Utility‑Klassen.

---

## 14. Fehlerseiten & Übersetzungen

- **Error‑Pages:** Über Entity‑Zuweisung im `default`‑Projekt konfigurierbar (403/404/5xx). Fallbacks vorhanden.
- **i18n:** Alle sichtbaren Texte translatierbar (Translation‑Domains); API‑Responses per Default englisch.
- **Mitgelieferte Sprachen zu Beginn:** Deutsch + Englisch, Dokumentation für manuelle Übersetzung in weitere Sprachen (z.B. durch Mitwirkende) bereitstellen.

---

## 15. Sicherheit, Logs & DSGVO

- **Security:** Rollen (z. B. User, Admin, Super‑Admin), ACLs auf heikle Aktionen (Hard‑Delete, System‑Settings).
- **Rate Limiter:** Login, API, Formularwege.
- **Logging:** ERROR/WARNING/DEBUG/AUTH/ACCESS mit Retention; Access‑IP nach 14 Tagen anonymisieren.
- **GeoIP (optional):** MaxMind nur bei gültigem Key; automatischer DB‑Fetch/Update; sonst „- -“ in den Logs.

---

## 16. Admin‑Funktionen ohne CLI

- **Setup‑Wizard:** Health‑Check, Schreibrechte, DB‑Init, Admin‑Account, Basiskonfiguration.
- **Migration/Update:** „Safe mode“: Web‑UI kann DB‑Migrations/Schema‑Anpassungen durchführen (nur Admin).
- **Wartung:** Cache leeren, Snapshot neu bauen, Export anstoßen – alles per Web‑UI.

💬 Kommentar: Devs nutzen CLI lokal; Endnutzer brauchen es nicht.

---

## 17. Lösch‑Policy

- **Standard:** Soft‑Delete via Inaktiv-Flag/Versionierung.
- **Hard‑Delete:** Nur manuell in Versionsverwaltung; ACL‑geschützt (per Default nur Super‑Admin).
- **History‑Pflege:** Optionales Purge alter Versionen (Projekt‑Setting).

---

## 18. Offene Punkte (bewusste Entscheidungen demnächst)

1) Snapshot‑Ablage: public vs. Controller – Default: public für Geschwindigkeit, pro Projekt umstellbar.  
2) Write‑API: „Instant‑Commit“ Standard oder Draft‑erstellt‑dann‑Commit? (aktueller Vorschlag: Instant‑Commit)  
3) Materialized‑Path‑Details: Pfadformat (z. B. `/parent/child`), Reindex‑Strategie bei Massen‑Moves.  
4) Export‑Presets: Default‑Packs definieren (Blog/Docs/Storywriting).  
5) Queue: Ab welcher Größe/häufigkeit Messenger/Worker einschalten?

---

## 19. Roadmap (MVP → v1)

- **MVP**
  - Domain‑Entities & Repos
  - Editor‑Flow (Draft → Commit)
  - Resolver v1 ([ref], [query] mit `select/where`)
  - Publish‑Pipeline & Snapshot
  - Frontend Catch‑All + Error‑Pages
  - Read‑Only API (Snapshot)
  - Exporter (JSON/JSONL), Preset‑Support minimal
  - Installer & Basis‑Security/Rate‑Limits
- **v1**
  - Templatepacks, Relation‑UI, Diffs
  - Write‑API (Instant‑Commit)
  - Geschützte Datei‑Auslieferung (Signaturen)
  - MaxMind‑Integration (optional)
  - TOON‑Export (optional, Feature‑Flag)
  - Admin‑Tools (Cache, Rebuild, Migrations via UI)

---

## 20. Hinweise & Kleinkram

- **Testing/Debugging:** Feature‑Flags (TOON, MaxMind, Queue), klare Logs, deterministische Fehlermeldungen (Schema‑Validator nennt Feld & Constraint).
- **Dokumentation (Codex):** `docs/dev/*`, `docs/user/*`, Worklog & Roadmap (`.codex/WORKLOG.md`), Environment/Toolbox gepflegt halten.

---

## 21. Entscheidungen

- Framework bleibt **Symfony**, mit Shared‑Hosting‑tauglichem Release‑Prozess (Flat Webroot Build).
- JSON ist **SoT**; TOON nur **optional** als Export‑Flavor.
- Hierarchie als **Materialized Path**.
- Dateien: **hybride Auslieferung** (public + signierte Controller‑Links).
- Write‑API: **Instant‑Commit** als einfacher Standard (später konfigurierbar).
