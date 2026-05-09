# hovera — Handbuch für Stallbesitzer

> Willkommen bei hovera. Dieser Leitfaden führt Sie durch alle Funktionen des Stallbesitzer-Panels. Die meisten Schritte beginnen in der Hauptnavigation unter `/app`.

---

## 1. Erste Schritte

Nach der Anmeldung landen Sie auf `/app`. Die linke Navigation ist in Gruppen unterteilt:

- **Stall** — Pferde, Kunden, Boxen, Gebäude, Spezialisten, Mehrfachkarten, Pflege, Pensionspreise
- **Kalender** — Tagesplan, Buchungen, wiederkehrende Sitzungen, Trainer, Reitplätze
- **Finanzen** — Rechnungen
- **Einstellungen** — Stalleinstellungen, Rechnungsstellung, Zahlungen, KSeF, Mitarbeiter

Empfohlene Reihenfolge:

1. **Stalleinstellungen** — Firmenangaben, Branding, Öffnungszeiten
2. **Gebäude** — mindestens eines anlegen ("Hauptstall")
3. **Boxen** — jede einem Gebäude zuweisen
4. **Pensionspreise** — Leistungen definieren (Heu, Boxenreinigung, Transport)
5. **Trainer + Reitplätze** — damit der Kalender Buchungen annehmen kann

---

## 2. Stalleinstellungen

**Pfad:** `/app/tenant-settings`

Formularabschnitte:

- **Identifikation** — Name, gesetzlicher Name, Steuer-ID
- **Standort** — Land, Standardsprache, Zeitzone, Währung
- **Branding** — Primärfarbe, Logo, Hero-Bild (für die öffentliche Seite)
- **Öffentliche Seite `/s/{slug}`** — Tagline, Öffnungszeiten, Beschreibung, Kontakt, Social Media
- **Online-Buchung** — ob Kunden über die öffentliche Seite buchen können; Lektionsdauer, Arbeitszeiten, Vorlauf

---

## 3. Pferde

**Pfad:** `/app/horses`

### Pferd hinzufügen

**"Pferd erstellen"** → ausfüllen:

- **Name** (z. B. "Bukephalos")
- **Eigentümer** (Auswahl aus Kundenliste; leer = Stall ist Eigentümer)
- **Box** (Auswahl aus aktiven Boxen)
- **Mikrochip / Passnummer / UELN**
- **Geschlecht:** Stute / Wallach / Hengst / Deckhengst
- **Rasse, Farbe, Geburtsdatum**
- **Pension — abrechenbare Leistungen** (Mehrfachauswahl aus Preisliste)

### Pferdekarte (Tabs)

- **Pflege & Gesundheit** — Impfungen, Hufschmied, Zahnarzt (mit Auto-Vorschlag für nächste Fälligkeit)
- **Aktivitäten** — Füttern, Pflege, Auslauf
- **Nachrichten** — Chat mit Eigentümer (E-Mail-Versand)
- **Dokumente** — Pass, Vertrag, Versicherung (PDF/JPG, bis 25 MB)

---

## 4. Boxen und Gebäude

### Gebäude — `/app/buildings`

Ein Stall (als Ort) kann mehrere Gebäude haben: "Roter Stall", "Neuer Stall", "Paddockpavillon". Jedes ist eine Boxengruppe.

### Boxen — `/app/boxes`

Felder: Gebäude, Name/Nummer, Kurzcode, Typ (innen / Paddock / außen / Quarantäne), Größe (m²), monatliche Pensionsrate, aktiv.

Tabellenspalten:
- **Gebäude** (mit Gruppierung — "Roter Stall" einklappbar)
- **Name, Typ, m²**
- **Status** — Frei (grün) / Belegt (grau)
- **Pferdegeschlecht** (falls zugewiesen)
- **Pension (PLN/Monat)**

---

## 5. Kunden

**Pfad:** `/app/clients`

### Kunde hinzufügen

- **Typ:** Privatperson / Familie / Firma
- **Name**
- **E-Mail, Telefon**
- **Steuer-ID** (mit "Aus GUS abrufen" wenn polnische GUS-API konfiguriert ist)
- **Pferdebesitzeridentifikation (ARMiR):**
  - **EP-Nr.** — Erzeuger-ID, vergeben von ARMiR bei der Registrierung eines Pferdes in der polnischen Equiden-Zentraldatenbank
  - **PESEL** — Fallback, wenn keine EP

### Kundenkarte

Beim Bearbeiten:

- Alle Formularfelder
- **Tab "Pferde"** — Liste der Pferde des Kunden
- Aktion **"Portal-Link kopieren"** — generiert Magic Link (TTL 30 min) zum manuellen Kopieren
- Aktion **"Portal-Link per E-Mail senden"** — sendet Anmeldelink per Mail

---

## 6. Kalender

### Tagesplan — `/app/calendar`

Tagesansicht gruppiert nach Trainer/Reitplatz. Klicken Sie auf einen leeren Slot, um eine Buchung hinzuzufügen.

### Buchungen — `/app/calendar-entries`

Vollständige Buchungstabelle. Filter: Typ, Status, Pferd, Trainer, "nur kommende".

**Status:** Angefragt → Bestätigt → Abgeschlossen / Storniert / Nicht erschienen.

**Buchungstypen:** Einzelstunde, Gruppenstunde, Training, Pflege (Tierarzt/Hufschmied), Veranstaltung, Sperre.

### Wiederkehrende Sitzungen — `/app/recurring-calendar-entries`

Erstellt eine Serie (z. B. "Schule Mo 17:00, wöchentlich, bis Jahresende"). Aktion **"Vorkommen erzeugen"** entfaltet die Serie in Einzelbuchungen.

---

## 7. Spezialisten (Hufschmiede + Tierärzte)

**Pfad:** `/app/specialists`

### Hinzufügen

- **Spezialgebiet:** Tierarzt / Hufschmied
- Name, E-Mail, Telefon, Kalenderfarbe
- **Hovera-Konto (optional)** — wenn der Spezialist Mitarbeiter ist (TenantMembership), mit User verknüpfen. Der angemeldete Mitarbeiter sieht dann **"Meine Aufgaben"**.

---

## 8. Mitarbeiter

**Pfad:** `/app/team-members` (sichtbar nur für Eigentümer / Admin)

**"Mitarbeiter hinzufügen"** → E-Mail, Name, Rolle.

### Rollen

| Rolle | Beschreibung |
|---|---|
| **Eigentümer** | Voller Zugriff |
| **Admin** | Wie Eigentümer minus Stalllöschung |
| **Manager** | Verwaltung von Kalender, Rechnungen |
| **Trainer** | Kalender, Buchungen, eigene Pferde |
| **Mitarbeiter** | Aktivitätsprotokoll |
| **Tierarzt** | Mit Spezialist verknüpft + Meine Aufgaben |
| **Nur Ansicht** | Schreibgeschützt |

### Passwort-Reset

Aktion **"Passwort-Reset-Link senden"** sendet eine E-Mail mit Link zu `/app/password-reset/request`.

---

## 9. Rechnungen

### Konfiguration — `/app/invoicing-settings`

- **Nummerierung Rechnung / Pro-forma / Korrektur** — Muster mit `{seq}`, `{YYYY}`, `{MM}`, `{prefix}`
- **Reset der Nummerierung** — Jährlich / Monatlich / Nie
- **Standard-Zahlungsfrist** in Tagen
- **Verkäuferdaten**

### Ausstellen — `/app/invoices`

1. **"Erstellen"** → Kunde wählen
2. Positionen hinzufügen
3. Speichern → Status `Entwurf` → **"Ausstellen"** → Nummer und Datum
4. **"Per E-Mail senden"** → Kunde erhält Link zur öffentlichen Ansicht
5. **"An KSeF senden"** (falls konfiguriert)

---

## 10. Online-Zahlungen — `/app/payment-settings`

Standard-Gateway: Keine / Przelewy24 / PayU / Stripe / Mollie. Nach Konfiguration enthält jede ausgestellte Rechnung einen **"Jetzt zahlen"**-Button.

---

## 11. KSeF (polnisches E-Invoicing) — `/app/ksef-settings`

Erforderlich:
- Steuer-ID des Stalls
- Umgebung: Test / Demo / Produktion
- Zertifikat — PFX oder PEM

---

## 12. Öffentliche Seite und Embed-Widgets

### Öffentliche Seite — `https://app.hovera.app/s/{slug}`

Wird automatisch basierend auf den Stalleinstellungen gerendert.

### Embed-Widgets

In `/app/tenant-settings` → "Widgets" — fertige Iframes zum Einbetten in Wordpress / Squarespace:

- **Freie Boxen**
- **Online buchen**
- **Pensionspreise**
- **Trainerliste**

---

## 13. Kundenportal

Kunde meldet sich an unter `https://app.hovera.app/s/{slug}/portal/login`:
- E-Mail eingeben
- Magic Link per Mail erhalten (TTL 30 min)
- Klick → Dashboard

Im Portal sieht der Kunde:
- kommende Buchungen (mit "Verschieben" / "Stornieren")
- seine Mehrfachkarten (X / Y verbleibend)
- Buchungsverlauf
- offene Rechnungen
- seine Pferde (mit Gesundheitswarnungen)
- Nachrichten

---

## 14. „Heute"-Dashboard — `/app`

Nach der Anmeldung 4 KPI-Kacheln oben: 🗓️ Buchungen heute · 🟢 Freie Boxen · 🔴 Überfällige Behandlungen · 💸 Offene Rechnungen. Jede verlinkt zur entsprechenden Liste mit Filter. Darunter Tabelle der heutigen aktiven Buchungen.

---

## 15. Behandlungsvorlagen — `/app/treatment-templates`

Wiederverwendbare Presets für Pflegeeinträge — ein Klick füllt Typ, Beschreibung und vorgeschlagenen Folgetermin (`interval_days`).

Jeder neue Stall startet mit 6 Standards (Tetanus/Influenza, EHV, Entwurmung, Beschlag, Zahn, Vet-Check). Anpassbar / deaktivierbar / erweiterbar.

Im Formular Pflege & Gesundheit erscheint ein Select „Behandlungsvorlage".

---

## 16. Pferdegewicht — Tab im Pferdeprofil

Monatliche Wiegungen (kg + optional Brustumfang). Spalte „Änderung" vergleicht: 🟢 Zunahme, 🟡 Abnahme, ⚪ stabil (< 5 kg).

---

## 17. Fütterungsplan — Tab im Pferdeprofil

CRUD: Tageszeit (Frühstück/Mittag/Abend/Nacht), Futter, Menge, Notizen. **Eigentümer sieht den Plan im Kundenportal** unter „Fütterungsplan" (read-only).

---

## 18. Futterlager — `/app/feed-inventory`

Futterposten mit aktuellem Bestand (`SUM(delta)`) und Alarm-Schwelle. Aktion **„+ Lagerbewegung"** erfasst Eingang / Verbrauch / Korrektur / Verlust. Posten unter Schwelle zeigen Sidebar-Badge. Per-Posten Tab **Bewegungshistorie** (Audit-Log).

---

## 19. Pferde-Fotogalerie — Tab im Pferdeprofil

Upload JPG/PNG/WEBP/HEIC bis 10 MB. Eigentümer sieht im Portal ein Grid (1:1, Lightbox). Getrennt von Dokumenten.

---

## 20. Instruktor-Kalender (.ics)

In **Stall → Trainer** Reihen-Aktion **„Kalender .ics"** → Modal mit Feed-URL. In Google Calendar / Outlook / Apple einfügen über „Kalender per URL hinzufügen". Synchronisiert alle paar Stunden. Fenster: 6 Mo zurück + 12 Mo voraus.

---

## 21. Berichte — Navigationsgruppe „Berichte"

Vier monatliche Seiten:

- **Umsatz** — Netto bucketed: Pension / Lektionen / Mehrfachkarten / Andere + Top 10.
- **Forderungsalterung** — überfällige Rechnungen 0–30 / 31–60 / 61–90 / 90+ Tage mit Farbgradient.
- **Pferdeauslastung** — Lektionen pro Pferd + Stunden. >25 = Überlastungsrisiko.
- **Trainerauslastung** — Stunden + Anwesenheit % + cancelled + no-show.

---

## 22. Bulk-Rechnungen — `/app/bulk-invoicing`

Massenerstellung von Draft-Rechnungen für einen Monat (Daily × Tage, Monthly × Menge). Vorschau pro Kunde, Auswahl optional, **„Drafts erzeugen"**. Mehrfachkarten ausgeschlossen (Auto-FV bei Verkauf). Jede Draft anschließend einzeln **Ausstellen**.

---

## 23. Self-Booking im Kundenportal

Kunde klickt im Portal „+ Lektion buchen" → wählt Pferd, Trainer, Tag + Slot → senden. Im Stall-Panel erscheint die Buchung mit Status **Angefragt** + `metadata.source = client_portal`. Bestätigen wie jede andere.

---

## 24. Was jede Rolle sieht — Zugriffsmatrix

Sidebar und Seiten werden **nach Mitarbeiterrolle gefiltert**. Tierärzte sehen keine Rechnungen, Mitarbeiter keine Stalleinstellungen — schlankere Ansicht = weniger Fehler.

| Bereich | owner | admin | manager | trainer | mitarbeiter | tierarzt | viewer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Pferde · Pflege & Gesundheit · Tagesplan · Buchungen | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Kunden | ✓ | ✓ | ✓ | ✓ | — | — | ✓ |
| Boxen · Pensionspreise · Gebäude · Serienlektionen · Trainer · Reitplätze | ✓ | ✓ | ✓ | ✓ | — | — | ✓ |
| Spezialisten · Behandlungsvorlagen | ✓ | ✓ | ✓ | — | — | ✓ | ✓ |
| Futterlager | ✓ | ✓ | ✓ | — | ✓ | — | ✓ |
| Rechnungen · Mehrfachkarten · Berichte | ✓ | ✓ | ✓ | — | — | — | ✓ |
| Bulk-Rechnungen | ✓ | ✓ | ✓ | — | — | — | — |
| Einstellungen (Stall · FV · KSeF · Zahlungen) · Mitarbeiter | ✓ | ✓ | — | — | — | — | — |
| Meine Aufgaben | — | — | — | — | — | ✓ | — |
| Hilfe | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

> **Master Admin** (hovera Support) sieht alles unabhängig von der Rolle.

---

## 25. Tipps

- **Sprache** — im Benutzermenü umschalten (PL / EN / DE / FR); Präferenz pro Benutzer gespeichert
- **Support** — support@hovera.app

---

*Systemversion: siehe Panel-Fußzeile. Diese Dokumentation wird zusammen mit neuen Modulen aktualisiert.*
