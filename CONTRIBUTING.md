# Mitwirken

Danke für dein Interesse an OpenPetRescue.

## Bevor du einen Pull Request öffnest

1. **Keine personenbezogenen oder organisationsspezifischen Daten einchecken.**
   Kein echter Name, keine Adresse, keine IBAN, keine Telefonnummer, keine
   Fotos von Tieren oder Personen, keine Zugangsdaten. Alles Organisations-
   spezifische gehört in die WordPress-Einstellungen, nicht in den Code.

2. **Prüf-Suite läuft durch:**
   ```bash
   cd plugin/openpetrescue && php tests/security-regression.php
   ```

3. **Syntax ist sauber:**
   ```bash
   php -l plugin/openpetrescue/openpetrescue.php
   ```

## Lizenz deiner Beiträge

Mit einem Pull Request stimmst du zu, dass dein Beitrag unter der
[GPL-3.0-or-later](LICENSE) mit den Zusatzbedingungen aus [NOTICE](NOTICE)
veröffentlicht wird – der Lizenz dieses Projekts.

## Grundsätze im Code

- Personenbezogene Daten sparsam verarbeiten und mit Löschfristen versehen.
- Öffentliche Formulare immer mit Nonce, Honeypot, Zeitsperre und Rate-Limit.
- Dateien mit sensiblem Inhalt gehören in den geschützten Ordner
  (`private_upload_dir()`), nicht in die Mediathek.
- Neue Texte über das Übersetzungssystem (`sod_t()`), nicht fest verdrahtet.
