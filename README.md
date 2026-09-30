

# OpenPetRescue

Eine offene WordPress-Lösung für Tierschutzvereine und engagierte Einzelpersonen. OpenPetRescue hilft dabei, Tiere zu verwalten, Vermittlungen zu organisieren, Patenschaften zu begleiten und die eigene Arbeit nachvollziehbar zu machen.

OpenPetRescue ist aus der praktischen Arbeit von **Shield of Dogs** entstanden. Das Projekt wird von **Peter Lehner** als Hobbyprojekt entwickelt – mit Unterstützung von KI. Es ist kein kommerzielles Produkt und lebt von Menschen, die Tierschutz und offene Software sinnvoll verbinden möchten.

> Jede Hilfe ist willkommen – ob Feedback, Ideen, Tests, Übersetzungen, Dokumentation oder Code.

## Was OpenPetRescue kann

- Tiere mit Steckbriefen, Fotos und Gesundheitsdaten verwalten
- Vermittlungsanfragen und Interessenten organisieren, inklusive Vorkontrolle und Schutzvertrag als PDF
- Patenschaften und Zahlungsbestätigungen begleiten (PayPal-Abo oder Dauerauftrag mit QR-Code)
- Einen Mitgliederbereich für Paten anbieten – mit Login, Patenschaftsstatus und Neuigkeiten zum eigenen Patentier
- Futter, Zubehör und Inventar dokumentieren
- Spenden, Sachspenden und Ausgaben erfassen, inklusive Finanzbericht und Spendenbestätigung
- Antworten auf Anfragen automatisch per IMAP dem richtigen Vorgang zuordnen
- Öffentliche Seiten für Vermittlung, Patenschaften und Spenden bereitstellen – dreisprachig (Deutsch, Englisch, Bosnisch)
- Am Handy als installierbare Mitarbeiter-App (PWA) arbeiten
- Daten aus bestehenden JSON-Dateien importieren
- **Schicksale:** Geschichten der Tiere in Kapiteln, mit den passenden YouTube-Videos zu jedem Kapitel (wöchentlich automatisch aktualisiert)
- **Handy-Ansicht:** Leiste unten (Start · Schicksale · Adoptieren · Pate werden · Spenden), Schnellstart, wischbare Tierkarten
- **Hero der Startseite** mit wechselnden Tieren, die gerade Paten suchen
- **Teilen-Funktion** für Tiere und die ganze Seite (WhatsApp, Facebook, Instagram, TikTok)
- **Rettungs-Zähler** auf der Startseite
- **Übersetzung eigener Inhalte** (Hundetexte, Geschichten) ins Englische und Bosnische – per Wörterbuch oder automatisch mit Google Cloud Translation
- **Finanzbericht** aufgeschlüsselt nach Einmalspenden und Patenschaften sowie nach Zahlungsweg (PayPal, Bank, Sonstige)
- **Dankes-Mail** nach jeder Spende mit Hinweis auf Tiere, die Paten suchen
- **Dashboard** mit Paten, Finanzen, neuen Patenschaften, Anfragen und Support-Meldungen
- Checkbox **„Verstorben“** beendet alle Patenschaften eines Tieres und schickt dem Team eine Liste
- Video-Upload nur in Formaten, die überall abspielbar sind (MP4/WebM, kein HEVC)

## Was bewusst *nicht* enthalten ist

Dieses Repository enthält **keine** Vereinsdaten, keine personenbezogenen Daten und keine Fotos der ursprünglichen Organisation.

- Alle organisationsspezifischen Angaben – Name, Anschrift, Registernummer, Bankverbindung, Kontaktdaten – werden im WordPress-Backend eingetragen, nicht im Code.
- **Impressum und Datenschutzerklärung sind leere Platzhalter.** Eine Datenschutzerklärung beschreibt die Datenverarbeitung genau einer Organisation und kann nicht von einer anderen übernommen werden. Diese Texte musst du selbst erstellen – im Zweifel rechtlich beraten lassen.
- Das Theme wird ohne eigene Bilder ausgeliefert. Fehlt eine Bilddatei, zeigt es automatisch ein neutrales Platzhalterbild.

## Projektstruktur

| Ordner | Inhalt |
| --- | --- |
| `theme/openpetrescue/` | WordPress-Theme für die öffentliche Website |
| `plugin/openpetrescue/` | Verwaltung für Tiere, Vermittlungen, Patenschaften, Inventar und weitere Abläufe |
| `mu-plugins/` | Optionaler Schnellzugriff im Dashboard (Fallback, falls das Hauptplugin deaktiviert ist) |
| `assets/` | Projektgrafiken, einschließlich des OpenPetRescue-Logos |

## Voraussetzungen

- WordPress 6.4 oder neuer
- PHP 8.0 oder neuer
- Optional die PHP-Erweiterung `imap` für die automatische Zuordnung eingehender Antworten

## Installation

1. `theme/openpetrescue/` nach `wp-content/themes/` kopieren.
2. `plugin/openpetrescue/` nach `wp-content/plugins/` kopieren.
3. Optional `mu-plugins/openpetrescue-dashboard-shortcuts.php` nach `wp-content/mu-plugins/` kopieren.
4. Im WordPress-Admin das Theme und das Plugin aktivieren.
5. **Organisation einrichten:** Nach der Aktivierung erscheint oben im Backend ein Hinweis. Unter **Hunde → Einstellungen → Organisation** trägst du Name, Anschrift, Registernummer, Kontaktdaten und Bankverbindung ein. Diese Angaben erscheinen anschließend automatisch auf der Website, in E-Mails, auf Spendenbestätigungen und Patenschafts-Zertifikaten.
6. Seiten für Vermittlung, Patenschaft, Kontakt, Spenden, Impressum und Datenschutz anlegen.
7. Die passenden Seitentemplates auswählen und die Hauptnavigation unter **Design → Menüs** einrichten.
8. Eigene Texte in `theme/openpetrescue/inc/translations.php` einsetzen, eigene Bilder nach `theme/openpetrescue/assets/images/` legen.

### Zahlungen einrichten (optional)

- **PayPal:** Unter *Einstellungen → Online-Spenden* die PayPal-Adresse eintragen. Die dort angezeigte IPN-Adresse wird automatisch mitgeschickt; im PayPal-Konto ist keine zusätzliche Einstellung nötig.
- **Überweisung / Dauerauftrag:** IBAN und BIC unter *Organisation* eintragen. Der QR-Code auf der Spendenseite wird daraus erzeugt.

> Für die QR-Code-Erzeugung wird ein externer Dienst aufgerufen. Wer ihn nutzt, muss ihn in der eigenen Datenschutzerklärung nennen – oder stattdessen ein eigenes QR-Bild hinterlegen.

## Neue Funktionen ein- und ausschalten

Größere Neuerungen sind anfangs nur für eingeloggte Admins sichtbar. So kannst du sie auf der echten Seite ansehen, bevor Besucher sie sehen.

| Funktion | Wo einschalten | Option |
| --- | --- | --- |
| Handy-Ansicht | *Design → Mobile Ansicht* | `sod_mobile_v2` = `on` / `admin` / `off` |
| Schicksale | *Hunde → Schicksale* | `sod_story_public` = `1` |
| Neuer Hero | *Design → Hero der Startseite* | `sod_hero_new_public` = `1` |
| Rettungs-Zähler | *Hunde → Rettungs-Zähler* | `sod_rescue_counter_public` = `1` |
| Teilen-Funktion | *Hunde → Teilen-Funktion* | `sod_share_public` = `1` |
| Automatische Übersetzung | *Hunde → Übersetzungen* | eigener Google-Cloud-API-Schlüssel |

Für die Schicksale die Seite `geschichten` anlegen. Videos kommen aus einer YouTube-Playlist pro Tier (Feld im Bereich „Schicksal“).

## Tests

Im Plugin liegt eine Prüf-Suite, die zentrale Sicherheits- und Datenschutzannahmen absichert (Rollenrechte, CSV-Export-Härtung, Löschfristen, Formular-Spamschutz). Sie läuft ohne WordPress-Installation:

```bash
cd plugin/openpetrescue && php tests/security-regression.php
```

## Mitmachen

Du möchtest helfen? Das freut mich sehr.

- Fehler oder Verbesserungsideen als GitHub-Issue melden
- Pull Requests für konkrete Verbesserungen einreichen
- Die Installation in einer Testumgebung ausprobieren
- Texte, Übersetzungen oder die Dokumentation verbessern
- Anforderungen aus der Praxis eines Tierheims oder Tierschutzvereins teilen

Bitte vorher [CONTRIBUTING.md](CONTRIBUTING.md) lesen – vor allem den Punkt, dass keine personenbezogenen Daten ins Repository gehören.

## Hinweis zum Projektstatus

OpenPetRescue ist ein fortlaufendes Hobbyprojekt. Bitte vor einem produktiven Einsatz alle Abläufe, Datenschutzanforderungen, E-Mail-Versand und Zahlungsprozesse sorgfältig in einer Testumgebung prüfen.

## Lizenz

**Kostenlos für jede NGO, jeden Tierschutzverein und alle Ehrenamtlichen.**

OpenPetRescue steht unter der [GNU General Public License v3.0](LICENSE) (GPL-3.0-or-later) – derselben Lizenzfamilie wie WordPress selbst – mit einer Zusatzbedingung nach § 7 (b) GPL, siehe [NOTICE](NOTICE):

- ✅ **Kostenlos** verwenden, anpassen und weitergeben.
- 📝 **Namensnennung ist Pflicht:** **Peter Lehner** und **Shield of Dogs** müssen sichtbar genannt bleiben – im Footer der Website (z. B. „Basiert auf OpenPetRescue von Peter Lehner und Shield of Dogs“) sowie in den Lizenz- und Kopfhinweisen des Codes.
- 🔓 Wer eine veränderte Fassung **weitergibt**, muss sie ebenfalls unter der GPL mit dieser Nennung veröffentlichen.

Die mitgelieferte Schriftart *Inter* steht unter der SIL Open Font License 1.1 und ist von der GPL nicht erfasst – siehe <https://github.com/rsms/inter>.

## Danksagung

Entwickelt von **Peter Lehner** für und mit der Erfahrung von **Shield of Dogs** – mit Hilfe von KI und der Open-Source-Community.
