# hovera — Anleitung für Spezialisten (Hufschmied / Tierarzt)

> Willkommen. Diese Anleitung ist für die Rolle **Spezialist** — also Hufschmied oder Tierarzt mit hovera-Konto (TenantMembership mit Rolle `vet`). Panelzugang: `https://app.hovera.app/app`.

---

## 1. Anmeldung

1. Öffnen Sie `https://app.hovera.app/app/login`.
2. E-Mail und Passwort eingeben (Passwort bei der ersten Anmeldung setzen — die Einladung kommt vom Stall per Mail).
3. Nach der Anmeldung landen Sie auf der Startseite des Panels — standardmäßig **Meine Aufgaben**.

> **Passwort-Reset:** Schaltfläche „Passwort vergessen" oder `https://app.hovera.app/forgot-password`. Wir senden einen Link an Ihre E-Mail (TTL 60 Min).

---

## 2. Meine Aufgaben

**Pfad:** `/app` (Startseite, wenn Ihre Rolle `vet` ist).

Ihre Hauptansicht. Die Tabelle zeigt:

- **Heute** — heute geplante Besuche,
- **Diese Woche** — nächste 7 Tage,
- **Überfällig** — vergangene Termine, die nicht als erledigt markiert wurden.

Jede Zeile: Datum, Uhrzeit, Pferd, Stall (falls Sie für mehrere arbeiten), Besuchstyp (Beschlag / Impfung / Zahnkontrolle), Status.

### Aktionen

- **Öffnen** — Besuchsdetails, Pferdedaten, Historie.
- **Als erledigt markieren** — nach dem Besuch klicken und Notiz hinzufügen. Das macht automatisch:
  - Besuch wechselt auf *Abgeschlossen*,
  - Gesundheits-Plakette des Pferdes wird aktualisiert (🔴 *X überfällig* verschwindet),
  - schlägt nächsten Termin vor (z. B. Beschlag alle 6 Wochen → Vorschlag im Kalender).
- **Verschieben** — bei Geräteausfall, Krankheit etc. neuen Termin wählen → Stall wird benachrichtigt.

---

## 3. Kalender

**Pfad:** `/app/calendar`

Zeigt alle Besuche (Ihre und andere) im Stall — Tag / Woche. Ihre Einträge sind in Ihrer Farbe (vom Stall in Ihrem Spezialisten-Datensatz festgelegt).

Filter:
- **Nur meine** — zeigt nur Ihnen zugewiesene Einträge,
- **Typ** — Hufschmied / Tierarzt / andere,
- **Status** — angefragt / bestätigt / abgeschlossen.

### Eintrag hinzufügen

Sie können selbst einen Besuch erfassen (z. B. „Ich war heute zusätzlich da") — Klick auf leeren Slot → Formular:
- Pferd (aus der Stall-Liste),
- Typ (Impfung / Beschlag / Zahnarzt / andere),
- Dauer (Standard 30 Min),
- Notiz.

> Der Stall sieht Ihren Eintrag sofort — nach Bestätigung wird er auf der Rechnung positioniert (falls Sie über hovera abrechnen).

---

## 4. Pferdeprofil

Klick auf ein Pferd in einem Besuch → öffnet das Profil. Für Sie wichtige Bereiche:

### 4.1 Pflege & Gesundheit (Timeline)

Vollständige Historie:
- Impfungen (Tetanus, Influenza, EHV),
- Beschlag (Datum, Hufschmied, Beschreibung),
- Zahnkontrollen,
- weitere veterinärmedizinische Behandlungen.

Filter: Eintragstyp, Datumsbereich, Autor (Sie / anderer Spezialist / Stall).

### 4.2 Aktivitäten

Pflege, Bewegung, Paddockzeit — Einträge der letzten 7 Tage. Hilfreich, weil Sie sehen, was das Pferd vor Ihrem Besuch gemacht hat (z. B. ob es am Vortag stark gearbeitet wurde).

### 4.3 Dokumente

Pass, Versicherung, Bluttests — können Sie herunterladen und einsehen.

### 4.4 Nachrichten

Chat mit Pferdeeigentümer + Stall. Sie können:
- frühere Vereinbarungen lesen,
- eine Nachricht schreiben (z. B. „Nach gestrigem Beschlag 24h ohne Arbeit"),
- Fotos anhängen (PDF/JPG/PNG, max. 10 MB pro Datei).

---

## 5. Besuch als erledigt markieren

Ihr häufigster Workflow.

1. Öffnen Sie den Besuch (aus **Meine Aufgaben** oder Kalender).
2. Klick **„Als erledigt markieren"**.
3. Ausfüllen:
   - **Tatsächliches Datum** (Standard heute),
   - **Notiz** (z. B. „Vorne links problemlos, vorne rechts beobachten"),
   - **Nächster Besuch** — vorgeschlagenes Datum (Beschlag 6 Wochen, Impfung 12 Monate). Editierbar.
   - **Kosten** (optional) — bei Abrechnung über hovera.
4. Bestätigen.

Automatische Folgen:
- Besuch geht auf *Abgeschlossen*,
- Pferdtimeline erhält Eintrag,
- Gesundheits-Plakette wird aktualisiert,
- bei gesetztem Folgetermin → neuer Kalendereintrag (Status *Angefragt*),
- Eigentümer erhält Mail-Benachrichtigung.

---

## 6. Zwei Situationen: Stallmitarbeiter vs. extern

| Aspekt | Mitarbeiter (TenantMembership) | Extern |
|---|---|---|
| Login | Ja, Konto mit Rolle `vet` | Nein — Stall trägt Besuche für Sie ein |
| „Meine Aufgaben"-Ansicht | Ja | — |
| E-Mail-Benachrichtigungen | Ja | Ja (falls Stall Ihre E-Mail hat) |
| Kalendereinträge selbst hinzufügen | Ja | Nein |
| Pferdehistorie sichtbar | Ja | Nein (außer Stall teilt Dokumente) |

Als **externer** Spezialist (Freelancer) gilt diese Anleitung größtenteils nicht. Der Stall kontaktiert Sie per Mail / Telefon und trägt nach dem Besuch alles selbst ein.

---

## 7. Mehrere Ställe in einem Konto

Wenn Sie in **mehreren Ställen** in hovera arbeiten, lädt jeder Sie separat ein (separate TenantMembership). Oben links im Panel sehen Sie einen Stall-Umschalter (oder `/tenant/select`).

Nach dem Wechsel sehen Sie nur Daten des gewählten Stalls — Pferde, Kalender, Aufgaben.

---

## 8. Sprache der Oberfläche

Benutzermenü (oben rechts) → **Polski / English / Deutsch / Français**. Präferenz pro Benutzer gespeichert — bleibt auch nach Abmeldung.

---

## 9. Sicherheit

- **Passwort** — mindestens 8 Zeichen; Reset über `/forgot-password`.
- **2FA** — optional, im Benutzermenü → „Zwei-Faktor-Authentifizierung" (TOTP, z. B. Google Authenticator / 1Password).
- **Sitzung** — läuft nach 8 Stunden Inaktivität ab.

---

## 9a. Neuerungen

- **Behandlungsvorlagen** — beim Anlegen eines Pflegeeintrags erscheint ein Select „Behandlungsvorlage". Auswahl (z. B. „Beschlag") füllt Typ, Beschreibung und Folgetermin (z. B. heute + 42 Tage) automatisch.
- **Auto-Folgetermin** — beim Markieren als „erledigt" einen Folgetermin setzen → System erstellt automatisch eine *Angefragt*-Buchung im Kalender.

---

## 10. Support

- **Stall** — Mail- / Telefonkontakt auf der Stallseite,
- **hovera (technisch)** — `support@hovera.app`.

---

*Die Dokumentation wird mit neuen Funktionen aktualisiert. Die Version ist im Footer des Panels sichtbar.*

Andere Rollen: Anleitung für **Eigentümer / Admin** (`/app/help` aus einem Eigentümer-/Admin-Konto) und für **Kundenportal** (`/s/{slug}/portal/help`).
