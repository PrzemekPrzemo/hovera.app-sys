# hovera — Anleitung für Stallmitarbeiter

> Willkommen. Diese Anleitung ist für die Rollen **Trainer / Mitarbeiter / Manager / Nur Ansicht** im Stall-Panel `/app`. Die vollständige Eigentümer-Anleitung finden Sie unter `/app/help` aus einem Eigentümer- / Admin-Konto.

---

## 1. Anmeldung

1. Öffnen Sie `https://app.hovera.app/app/login`.
2. E-Mail und Passwort — die Einladung kam vom Stall per Mail; beim ersten Klick legen Sie Ihr Passwort fest.
3. Nach der Anmeldung landen Sie auf der für Ihre Rolle passenden Startseite.

### Passwort zurücksetzen

`https://app.hovera.app/forgot-password` oder „Passwort vergessen" auf der Anmeldeseite. Link gültig **60 Minuten**.

### 2FA (optional)

Benutzermenü (oben rechts) → **„Zwei-Faktor-Authentifizierung"** → QR-Code mit TOTP-App (Google Authenticator / 1Password / Authy) scannen. Wiederherstellungscodes speichern.

---

## 2. Rollen im Stall

Je nach Rolle sehen Sie unterschiedliche Menüs und Aktionen:

| Rolle | Was Sie sehen | Was Sie tun können |
|---|---|---|
| **Manager** | Alles außer Stalleinstellungen und Mitarbeitern | Kalender, Rechnungen, Kunden, Pferde verwalten |
| **Trainer** | Kalender, eigene Buchungen, eigene Pferde, Kunden | Eigene Buchungen bearbeiten, Aktivitäten für trainierte Pferde |
| **Mitarbeiter** | Pferdeprofil (Aktivitätsprotokoll), Kalender nur lesen | Pflege, Fütterung, Paddockzeit erfassen |
| **Nur Ansicht** | Alles lesbar | Keine Änderungen |

> Wenn Sie **Hufschmied / Tierarzt** sind, gibt es eine separate Anleitung (Rolle `vet` → Spezialisten-Anleitung).

---

## 3. Tagesablauf — Trainer

### 3.1 Tageskalender

**Pfad:** `/app/calendar` (Tag / Woche, gruppiert nach Reitplatz / Trainer).

- Eintrag anklicken → Details öffnen (Teilnehmer, Pferd, Status).
- Leerer Slot → klicken, neue Buchung anlegen (falls Rolle erlaubt).

### 3.2 Buchungsliste

**Pfad:** `/app/calendar-entries` — vollständige Tabelle. Filter: Typ, Status, „nur meine", „kommende".

Status: *Angefragt* → *Bestätigt* → *Abgeschlossen* / *Storniert* / *Nicht erschienen*.

Nach der Lektion markieren Sie den Status:
- **Abgeschlossen** — er hat teilgenommen,
- **Nicht erschienen** — Kunde kam ohne Storno nicht,
- **Storniert** — Kunde hat storniert (mit Grund).

### 3.3 Ihre Pferde

**Pfad:** `/app/horses`. Standardmäßig „Meine" (die Sie trainieren).

Pferdeprofil → Tab **Aktivitäten**:
- ➕ **„Aktivität hinzufügen"** → Typ (Pflege / Füttern / Paddock / andere), Notiz.

> Die letzten 7 Tage Aktivitäten sind für den Eigentümer im Kundenportal sichtbar.

---

## 4. Tagesablauf — Mitarbeiter

Ihr Hauptbereich: **Pferdeprofil → Aktivitäten**.

Sie tragen tägliche Pflegearbeiten ein:

- **Füttern** — z. B. „6:00 – Heu + Hafer",
- **Pflege** — Dauer + Notizen,
- **Paddock** — welcher Paddock, wie viele Stunden,
- **Andere** — z. B. „Hufeisen verloren, Hufschmied anrufen".

Einträge landen sofort in der Pferdetimeline. Stall und Eigentümer sehen sie.

> Bei Auffälligem (Lahmheit, Husten) — Eintrag **und** Nachricht über das Pferdeprofil → Tab **Nachrichten**. Der Stall erhält Mail-Benachrichtigung.

---

## 5. Tagesablauf — Manager

Manager hat den breitesten Zugriff außer Systemeinstellungen:

- **Kalender + Buchungen** — vollständige Verwaltung, fremde Einträge verschieben,
- **Kunden** — anlegen, bearbeiten, Magic-Links zum Portal generieren,
- **Pferde** — anlegen, bearbeiten, Dokumente, Gesundheit,
- **Rechnungen** — ausstellen, mailen, als bezahlt markieren,
- **Mehrfachkarten** — verkaufen, stornieren, Gültigkeit ändern.

Vollständige Modulbeschreibung in der Eigentümer-Anleitung (`/app/help` aus einem Eigentümer-/Admin-Konto).

---

## 6. Nachrichten und Benachrichtigungen

### 6.1 Nachrichten im Pferdeprofil

Jedes Pferd hat einen Chat zwischen Stall und Eigentümer. Sie können:
- Verlauf lesen,
- eine Nachricht schreiben (z. B. „Heute 3h Paddock" — geht auch über Aktivitäten),
- Dateien anhängen (PDF/JPG/PNG, max. 10 MB pro Datei, bis 5 Dateien).

### 6.2 E-Mail-Benachrichtigungen

Standardmäßig erhalten Sie eine Mail bei:
- neuer Buchung bei Ihnen (Trainer),
- Kundenantwort auf Ihre Nachricht,
- Stallnachricht („Morgen 14:00 kommt der Hufschmied").

Benutzermenü → **„Benachrichtigungen"** — Sie können einzelne Kategorien deaktivieren.

---

## 7. Sprache der Oberfläche

Benutzermenü (oben rechts) → **Polski / English / Deutsch / Français**. Präferenz pro Benutzer gespeichert — auch nach erneuter Anmeldung.

> Kunden sehen das Portal in der vom Stall festgelegten Sprache (oder eigener, falls verfügbar). Ihr Wechsel beeinflusst nicht, was sie sehen.

---

## 8. Sicherheit

- **Passwort** — min. 8 Zeichen; nicht aus anderen Diensten wiederverwenden.
- **Reset** — `/forgot-password` (Mail mit Link, TTL 60 Min).
- **2FA** — dringend empfohlen für Rollen Manager / Trainer.
- **Abmeldung** — Benutzermenü → „Abmelden"; Sitzung läuft nach 8h Inaktivität automatisch ab.

> **Geben Sie Ihr Passwort niemals an Kollegen weiter.** Jeder hat ein eigenes Konto — wichtig für die Audit-Log (wer eine falsche Rechnung eingegeben hat, wer eine Aktivität erfasst).

---

## 9. Häufige Probleme

| Problem | Was tun |
|---|---|
| Passwort vergessen | `/forgot-password` → Mail mit Link |
| Reset-Mail kommt nicht | Spam prüfen; Stalleigentümer fragen |
| Buchung nicht sichtbar | Filter „nur meine" prüfen — ggf. abwählen |
| „Keine Berechtigung" beim Bearbeiten | Rolle erlaubt es nicht — Admin um Änderung bitten |
| Kunde sagt er habe keine Mail erhalten | Adresse in Kundenkarte prüfen; „erneut senden" versuchen |

---

## 9a. Für Sie relevante neue Module

- **Pferde-Fütterungsplan** — Tab „Fütterungsplan" im Pferdeprofil zeigt genau, was und wann zu füttern ist. Trainer/Manager bearbeitet; Mitarbeiter liest und führt aus.
- **Futterlager** — `/app/feed-inventory`. Tägliche Ausgabe: „+ Lagerbewegung" → „Verbrauch" → Menge → bestätigen. Bestand sinkt. Unter-Schwelle-Artikel zeigen Sidebar-Badge.
- **Pferdegewicht** — Trainer/Mitarbeiter kann monatliche Wiegung im Tab „Gewicht" eintragen (kg + optional Brustumfang).

---

## 10. Support

- **Ihr Stall** — Eigentümer / Admin (Kontakt in Stallkarte),
- **hovera (technisch)** — `support@hovera.app`.

---

*Die Dokumentation wird mit neuen Funktionen aktualisiert. Version im Panel-Footer.*

Andere Rollen: Anleitung für **Eigentümer / Admin** (`/app/help` aus Eigentümer-/Admin-Konto), Anleitung für **Spezialisten** (`/app/help` aus vet-Konto) und für **Kundenportal** (`/s/{slug}/portal/help`).
