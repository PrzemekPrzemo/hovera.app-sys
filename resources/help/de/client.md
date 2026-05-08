# hovera — Anleitung für das Kundenportal

> Willkommen im Kundenportal. Hier finden Sie alle Buchungen, Mehrfachkarten, Rechnungen und Informationen zu Ihren Pferden. Der Stall, dessen Sie Kunde sind, hostet das Portal unter `https://app.hovera.app/s/{stall-slug}/portal`.

---

## 1. Anmeldung (Magic Link)

Das Portal **verwendet keine Passwörter**. Sie melden sich mit einem Einmal-Link per E-Mail an:

1. Öffnen Sie die Anmeldeseite — die Adresse erhalten Sie vom Stall (z. B. `https://app.hovera.app/s/pegasus-stall/portal/login`).
2. Geben Sie die E-Mail-Adresse ein (dieselbe, die der Stall in Ihrer Kundenkarte gespeichert hat).
3. Klicken Sie auf **„Link senden"**.
4. Prüfen Sie Ihren Posteingang — Sie erhalten innerhalb weniger Sekunden eine Nachricht.
5. Klicken Sie auf den Link → Sie sind für **30 Tage** angemeldet.

> **Der Link ist einmalig und 30 Minuten gültig.** Wenn Sie ihn nicht rechtzeitig öffnen, fordern Sie einfach einen neuen an.

### Keine E-Mail erhalten — was tun?

- Prüfen Sie den Ordner **Spam / Werbung / Updates**.
- Stellen Sie sicher, dass die Adresse exakt jene ist, die der Stall hat (Tippfehler = keine Mail).
- Kontaktieren Sie den Stall — er kann den Link direkt aus dem Panel kopieren und Ihnen per SMS senden.

---

## 2. Dashboard

Nach der Anmeldung sehen Sie eine einzige Seite mit allen Bereichen. Jeder Bereich erscheint nur, wenn Sie dort Daten haben.

### 2.1 Kommende Buchungen

Liste Ihrer Buchungen ab heute, sortiert nach nächstem Termin.

Jeder Eintrag zeigt:
- **Datum und Uhrzeit** des Beginns + Lektionsdauer,
- **Status** (Angefragt / Bestätigt),
- **Trainer**, **Pferd**, **Reitplatz**,
- Aktionsschaltflächen.

#### Aktionen

- **Verschieben** — öffnet den Verschiebebildschirm (nur für Status *Bestätigt*). Wir zeigen die nächsten freien Slots beim selben Trainer; eine Auswahl = eine Anfrage an den Stall und eine Bestätigungsmail an Sie.
- **Stornieren** — öffnet ein sicheres Stornoformular (signierter Link, gültig bis zum Buchungsbeginn).

> **Wichtig:** „Verschieben" und „Stornieren" sind nur für Buchungen verfügbar, die noch nicht begonnen haben.

### 2.2 Ihre Mehrfachkarten

Wenn der Stall Mehrfachkarten verkauft (z. B. „10 Lektionen"), sehen Sie hier die aktiven. Jede Karte zeigt:

- verbleibende Einheiten (z. B. **7 / 10 verbleibend**),
- Fortschrittsbalken,
- Gültigkeitsdatum,
- Status (Aktiv / Verbraucht / Abgelaufen).

Der Bereich **„Zuletzt verwendet"** zeigt die letzten 5 Lektionen, die auf die Karte gebucht wurden.

### 2.3 Buchungsverlauf

Bereits durchgeführte oder stornierte / nicht erschienene Lektionen. Status:

- **Abgeschlossen** — Lektion fand statt,
- **Storniert** — Sie oder der Stall haben storniert,
- **Nicht erschienen** — Sie sind ohne Storno nicht erschienen.

### 2.4 Offene Rechnungen

Wenn der Stall Ihnen Rechnungen ausgestellt hat und diese unbezahlt sind, erscheinen sie hier mit:

- Belegnummer,
- Ausstellungsdatum + Fälligkeitsdatum (rot, wenn überfällig),
- offener Betrag.

Klick auf eine Zeile öffnet die öffentliche Rechnungsansicht (signierte URL — keine Anmeldung) mit einer **„Jetzt bezahlen"**-Schaltfläche, falls der Stall ein Zahlungsgateway konfiguriert hat (Przelewy24 / PayU / Stripe / Mollie).

### 2.5 Nachrichten

Die 5 neuesten Nachrichten aus pferdebezogenen Konversationen. Vollständige Liste: **„Alle →"** im Bereichstitel.

### 2.6 Ihre Pferde

Pferde, die Sie in diesem Stall besitzen. Jede Zeile zeigt:

- Name, Rasse, Alter,
- **Gesundheits-Plaketten**:
  - 🔴 **X überfällig** — X überfällige Pflegepunkte (Impfungen, Hufschmied, Zahnarzt) — **Aktion erforderlich**,
  - 🟢 **X in 30 Tagen** — X Termine im nächsten Monat,
  - ⚪ **OK** — alles aktuell.

Bei ungelesenen Nachrichten sehen Sie eine **📬 X neue Nachrichten-Plakette**.

Klick auf eine Zeile → Pferdeprofil (Abschnitt 3).

---

## 3. Pferdeprofil

Klick auf ein Pferd vom Dashboard öffnet sein vollständiges Profil. Bereiche:

### 3.1 Stammdaten

- Name, Rasse, Farbe, Geburtsdatum, Geschlecht,
- Mikrochip, Passnummer, UELN,
- aktuelle Box.

### 3.2 Pflege & Gesundheit (Timeline)

Impfungen, Hufschmiedbesuche, Zahnarzt:

- 🔴 **überfällig** — Termin überschritten, Besuch buchen,
- 🟡 **in 30 Tagen** — vorausplanen,
- 🟢 **aktuell** — nächster Termin > 30 Tage entfernt.

Jeder Eintrag zeigt Datum des letzten Besuchs + vorgeschlagenes nächstes Datum.

### 3.3 Aktivitäten

Pflegen, Bewegung, Paddockzeit — Einträge der letzten 7 Tage, vom Stall erfasst.

### 3.4 Nachrichten

Chat mit dem Stall über dieses Pferd. Sie können:

- den Nachrichtenverlauf lesen (vom Stall und von Ihnen),
- eine neue Nachricht schreiben (z. B. „Bitte vor der heutigen Lektion pflegen"),
- **bis zu 5 Dateien** anhängen (PDF/JPG/PNG, **max. 10 MB pro Datei**).

Der Stall erhält eine E-Mail-Benachrichtigung; Sie ebenfalls bei Antworten.

### 3.5 Dokumente

Pass, Vertrag, Versicherung, Zertifikate — PDF/JPG-Dateien (max. 25 MB).

Aktionen:
- **Herunterladen** beliebiger Dokumente,
- **Hochladen** neuer Dokumente (Pass, Versicherung…),
- **Löschen** der von Ihnen hochgeladenen Dokumente (Stall-Dokumente können nicht gelöscht werden).

---

## 4. Buchung verschieben

Klick auf **„Verschieben"** bei einer kommenden Buchung → öffnet einen Bildschirm mit:

- aktuellem Termin,
- Liste der **3-7 nächsten freien Slots** beim selben Trainer.

Wählen Sie den gewünschten Slot → Klick **„Anfrage senden"**:

1. Der Stall erhält eine Benachrichtigung,
2. Sie erhalten eine Bestätigungsmail,
3. Die Buchung wird verschoben (Status bleibt *Bestätigt*).

> Wenn kein Slot passt, schreiben Sie dem Stall über den **Nachrichten**-Bereich im Pferdeprofil oder per direkter Mail.

---

## 5. Buchung stornieren

Klick auf **„Stornieren"** → öffnet eine signierte URL (kryptografisch signierter Link, gültig nur bis zum Lektionsbeginn).

Das Formular zeigt:
- Buchungsdetails,
- Feld **„Stornierungsgrund"** (optional, aber für den Stall hilfreich).

Klick auf **„Stornierung bestätigen"** → Status wird *Storniert*, der Stall wird benachrichtigt.

> Stornierung weit im Voraus gibt die Einheit meist auf die Mehrfachkarte zurück. Stornierung kurz vor der Lektion kann gebührenpflichtig sein — die Bedingungen legt der Stall fest.

---

## 6. Rechnungen

Klick auf eine Rechnung im Dashboard öffnet die öffentliche Ansicht:

- vollständige Daten (Verkäufer, Käufer, Positionen, Beträge, MwSt.),
- **„PDF herunterladen"**-Schaltfläche,
- **„Jetzt bezahlen"**-Schaltfläche (falls Gateway konfiguriert).

### Online-Zahlung

Klick auf **„Jetzt bezahlen"** → Weiterleitung zum Gateway (BLIK / Karte / Überweisung). Nach der Zahlung kehren Sie ins Portal zurück — der Rechnungsstatus aktualisiert sich automatisch nach Webhook-Bestätigung.

> Bei klassischer Banküberweisung verwenden Sie Kontonummer und Verwendungszweck aus der Rechnung — der Stall markiert sie nach Buchung manuell als bezahlt.

---

## 7. Nachrichten — vollständige Liste

Der **„Alle →"**-Link öffnet einen Bildschirm mit allen Threads zu Ihren Pferden. Filter: Pferd, ungelesen.

Klick auf einen Thread führt ins Pferdeprofil, Bereich Nachrichten.

---

## 8. Portalsprache

Das Portal spricht vier Sprachen: **Polnisch / Englisch / Deutsch / Französisch**. Standardmäßig wird die Sprache des Stalls verwendet; beim Wechsel wird die Präferenz in der Sitzung gespeichert.

> Im Portal selbst gibt es keinen Sprachschalter (der ist im Mitarbeiterpanel); wenn Sie eine andere Sprache wünschen, bitten Sie den Stall, den Standard zu ändern.

---

## 9. Sicherheit & Datenschutz

- **Magic-Link-Anmeldung** = kein Passwort zu merken, kein Passwort, das geleakt werden kann.
- **30-Tage-Sitzung** — danach geben Sie Ihre E-Mail erneut ein.
- **Abmelden** — Schaltfläche oben rechts.
- **Ihre Daten** — Sie sehen nur *Ihre* Buchungen, *Ihre* Pferde, *Ihre* Rechnungen. Selbst wenn der Stall 100 Kunden hat, sieht jeder nur seine eigenen.

> Das Portal zeigt nur Daten dieses einen Stalls. Wenn Sie in mehreren Ställen reiten — jeder hat seine eigene Portal-URL.

---

## 10. Häufige Probleme

| Problem | Was tun |
|---|---|
| Keine E-Mail mit Link | Spam prüfen; dann Stall bitten, den Link manuell zu senden |
| Link abgelaufen / funktioniert nicht | E-Mail erneut eingeben — wir senden einen neuen |
| „Verschieben" zeigt keine Slots | Trainer evtl. nicht verfügbar — dem Stall schreiben |
| Plakette „X überfällig" verschwindet nicht | Der Stall muss den Hufschmied-/Impftermin als erledigt markieren |
| Rechnung nicht sichtbar | Stall kontaktieren — eventuell muss neu ausgestellt werden |
| Anhänge >10 MB lassen sich nicht hochladen | Foto komprimieren / PDF in Teile teilen |

---

## 11. Support

- **Stall** — Mail- / Telefonkontakt auf der öffentlichen Stallseite `https://app.hovera.app/s/{stall-slug}`,
- **hovera (technisch)** — `support@hovera.app`.

---

*Die Dokumentation wird mit neuen Portal-Funktionen aktualisiert. Die Systemversion ist im Footer des Stall-Adminpanels sichtbar.*
