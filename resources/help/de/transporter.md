# Willkommen bei Hovera Transport

> ⚠️ **Maschinelle Übersetzung — vor Veröffentlichung von einem
> Muttersprachler zu prüfen.** Inhaltliche Aussagen sind verbindlich,
> aber Formulierungen sind nicht final.

Schön, dass Sie an Bord sind. Dieses Dokument führt Sie durch Ihre
ersten Tage als Transporteur — von der Aktivierung des Kontos bis zum
ersten angenommenen Angebot.

## Wie es funktioniert

- **Registrierung → Dokumentenprüfung → Aktivierung → Angebote senden.**
  Die Kontoerstellung erfolgt sofort, aber das Senden von Angeboten an
  Kunden ist gesperrt, bis das Hovera-Team Ihre Dokumente verifiziert
  hat (Frachtführerhaftpflicht, Transportlizenz, Steuernummer,
  Fahrzeugzulassung). Üblicherweise 1 Werktag.
- **Hovera ist ein vermittelnder Marktplatz, KEIN Transportunternehmen.**
  Wir besitzen keine Fahrzeuge, beschäftigen keine Fahrer, übernehmen
  keine Haftung für die Transportausführung. Wir verbinden Sie mit
  Kunden und stellen Werkzeuge bereit: Verwaltungspanel, Preisrechner,
  Angebotsgenerator, Rechnungsstellung, öffentliches Profil.
- **Transportverträge sind direkt zwischen Ihnen und dem Endkunden.**
  Sie stellen die Rechnung unter Ihrer eigenen Steuernummer, Ihrer
  Nummerierung, über Ihr eigenes KSeF (oder lokales E-Invoicing) aus.
  Hovera ist keine Partei des Transportvertrags.

## Erste Schritte nach Aktivierung

1. **Fügen Sie Ihre Fahrzeuge hinzu** im Bereich `Fahrzeuge`. Für jeden
   Eintrag: Name, Kennzeichen, Kapazität (Pferde), zulässiges
   Gesamtgewicht, Fotos (3–6 empfohlen, einschließlich Innenraum),
   Ausstattung (Luftfederung, Kamera). Diese Daten erscheinen auf
   Rechnungen und in Ihrem öffentlichen Profil.
2. **Fügen Sie Fahrer hinzu** im Bereich `Fahrer`. Jeder Fahrer erhält
   Benachrichtigungen über bevorstehende Aufträge per E-Mail/Telefon
   (über einen separaten SMTP `transport@hovera.app`).
3. **Servicegebiete einrichten** in `Einstellungen → Servicegebiete`
   (Mehrfachauswahl von Woiwodschaften). Entscheidend — ohne dies
   erhalten Sie keine Broadcast-Leads.
4. **Öffentliches Profil einrichten** (`/t/{Ihr-slug}`). Es ist Ihre
   Marketing-Landingpage — von Google indexiert, in sozialen Medien
   teilbar, mit eigenem OG-Bild für Vorschauen.
5. **Routing-API anbinden** in `Einstellungen → Routing`. Solo-Plan
   enthält OpenRouteService kostenlos. Pro/Fleet erlauben eigenen
   Mapbox- oder Google-Maps-Schlüssel.
6. **Erstes Angebot erstellen.** Öffnen Sie `Rechner`, geben Sie
   Abhol- und Lieferadresse, Datum, Pferdeanzahl ein. Klicken Sie
   „Als Angebot speichern" → senden Sie das Angebot per E-Mail.
   Der Kunde nimmt über eine signierte URL an.

## Lead-Marketplace

Ein Lead = Anfrage eines Kunden. Eingang in einem von zwei Modi:

- **Broadcast-Modus.** Lead geht an alle Transporteure, deren
  Servicegebiet die Route abdeckt. Sie haben bis zu 14 Tage Zeit zu
  antworten.
- **Direct-Modus.** Der Kunde wählt Sie bewusst aus (über Stern,
  öffentliches Profil oder Partner-Link) — der Lead geht **nur an Sie**.

Lead-Eingang: `Leads` in der linken Seitenleiste → Aktion `Mit Angebot
antworten`.

## Angebote und Rechnungen

- **Angebot:** Nummerierung `OF/YYYY/MM/NNNN`. PDF automatisch
  generiert, Versand über `transport@hovera.app`. Kunde akzeptiert
  über signierte URL.
- **Transportrechnung:** ausgestellt nach Lieferung. Aktion
  `Rechnung aus Angebot ausstellen` kopiert Positionen. Ihre
  Nummerierung, Ihre Steuernummer. KSeF — bald (Phase 9 Roadmap).

## Mini-Dashboard

`Dashboard` — 4 Widgets:

- **Lead-KPIs:** wöchentliche Lead-Anzahl + 30-Tage Win-Rate.
- **Bevorstehende Transporte:** 7-Tage-Kalender.
- **Top-Rechnungen 90d:** Best-Paid-Ranking.
- **Routen-Heatmap:** häufigste Routen.

## FAQ

**Übernimmt Hovera die Haftung für Transportschäden?**
Nein. Sie sind der Frachtführer, Sie tragen die volle Haftung (CMR
+ Ihre Versicherung).

**Stellt Hovera Transportrechnungen aus?**
Nein. Sie stellen die Rechnung unter Ihrer Steuernummer aus.

**Was, wenn der Kunde nicht zahlt?**
Sie ziehen direkt ein. Hovera vermittelt keine Transportzahlungen
(vorerst — Stripe Connect ist auf der Roadmap).

**Kann ich mehr als ein Fahrzeug haben?**
Ja — Limit je nach Plan: Solo 1, Pro bis 5, Fleet unbegrenzt.

**Kann ich den Plan wechseln?**
Ja, in `Einstellungen → Abonnement → Plan ändern`.

**Was passiert, wenn ich einen Lead nicht innerhalb von 14 Tagen
annehme?** Der Lead läuft ab. Keine Strafen.

**Kann ich internationale Routen fahren (DE/CZ/SK)?**
Im MVP — nur Polen. Internationale Routen sind in der Post-MVP-Roadmap.

**Kann ich sowohl einen Stall als auch eine Transportfirma haben?**
Ja — Multi-Tenancy. Tenant-Switcher oben auf dem Bildschirm.

## Support

- **Support-E-Mail:** `support@hovera.app` (Antwortzeit: 1 Werktag
  Solo, 4h Pro, 1h Fleet).
- **Dokumentation:** `docs.hovera.app/transport`.
- **Systemstatus:** `status.hovera.app`.
- **Bug-Reporter im Panel:** unten rechts.
