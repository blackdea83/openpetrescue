<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function sod_translations(): array
{
    static $all = null;
    if ($all !== null) {
        return $all;
    }
    $all = array_merge(
        sod_translations_common(),
        sod_translations_home(),
        sod_translations_vermittlung(),
        sod_translations_patenschaft(),
        sod_translations_kontakt(),
        sod_translations_spenden(),
        sod_translations_impressum(),
        sod_translations_datenschutz(),
        sod_translations_dogcard(),
        sod_translations_tierheim_bau(),
        sod_translations_support(),
        sod_translations_mobile(),
        sod_translations_heronew(),
        sod_translations_rescue(),
        sod_translations_stories(),
        sod_translations_share()
    );
    // Ergaenzungen in bestehende Gruppen einmischen, statt sie zu ersetzen.
    foreach (sod_translations_modules_extra() as $group => $strings) {
        $all[$group] = array_merge($all[$group] ?? [], $strings);
    }
    return $all;
}

/**
 * Texte fuer den Hilfe-Button unten rechts (FAQ zum Bezahlen einer Patenschaft
 * plus Formular fuer technische Probleme). Wird vom Plugin ueber self::t()
 * gelesen - fehlt ein Schluessel hier, zeigt die Seite den rohen Schluesselnamen.
 */
function sod_translations_support(): array
{
    return [
        'support' => [
            'button' => ['de' => 'Hilfe', 'en' => 'Help', 'bs' => 'Pomoć'],
            'title' => ['de' => 'Hilfe & Support', 'en' => 'Help & support', 'bs' => 'Pomoć i podrška'],
            'close' => ['de' => 'Schließen', 'en' => 'Close', 'bs' => 'Zatvori'],
            'faq_title' => [
                'de' => 'Patenschaft bezahlen – häufige Fragen',
                'en' => 'Paying for a sponsorship – FAQ',
                'bs' => 'Plaćanje pokroviteljstva – česta pitanja',
            ],
            'faq_ways_q' => [
                'de' => 'Wie kann ich eine Patenschaft bezahlen?',
                'en' => 'How can I pay for a sponsorship?',
                'bs' => 'Kako mogu platiti pokroviteljstvo?',
            ],
            'faq_ways_a' => [
                'de' => 'Es gibt zwei Wege: ein monatliches PayPal-Abo oder einen Dauerauftrag bei deiner eigenen Bank. Beides läuft monatlich und ist jederzeit kündbar. Du wählst den Weg direkt im Patenschafts-Formular aus.',
                'en' => 'There are two options: a monthly PayPal subscription or a standing order at your own bank. Both run monthly and can be cancelled at any time. You choose the option directly in the sponsorship form.',
                'bs' => 'Postoje dva načina: mjesečna PayPal pretplata ili trajni nalog u vašoj banci. Oba se plaćaju mjesečno i mogu se otkazati u bilo kojem trenutku. Način birate direktno u obrascu za pokroviteljstvo.',
            ],
            'faq_paypal_q' => [
                'de' => 'PayPal: Wie läuft das ab?',
                'en' => 'PayPal: how does it work?',
                'bs' => 'PayPal: kako to funkcioniše?',
            ],
            'faq_paypal_a' => [
                'de' => 'Nach dem Absenden des Formulars wirst du zu PayPal weitergeleitet und schließt dort ein monatliches Abo ab. Wichtig: Den Vorgang bei PayPal wirklich bis zum Schluss bestätigen. Brichst du bei PayPal ab, kommt keine Patenschaft zustande. Danach läuft die Zahlung automatisch – du musst nichts weiter tun.',
                'en' => 'After submitting the form you are redirected to PayPal, where you set up a monthly subscription. Important: complete the process at PayPal all the way to the end. If you cancel at PayPal, no sponsorship is created. After that the payment runs automatically – there is nothing else for you to do.',
                'bs' => 'Nakon slanja obrasca bit ćete preusmjereni na PayPal, gdje zaključujete mjesečnu pretplatu. Važno: postupak na PayPalu potvrdite do kraja. Ako odustanete na PayPalu, pokroviteljstvo neće biti uspostavljeno. Nakon toga plaćanje teče automatski – ne morate ništa dalje raditi.',
            ],
            'faq_bank_q' => [
                'de' => 'Dauerauftrag: Was muss ich tun?',
                'en' => 'Standing order: what do I need to do?',
                'bs' => 'Trajni nalog: šta trebam uraditi?',
            ],
            'faq_bank_a_tpl' => [
                'de' => 'Nach dem Formular bekommst du unsere Bankdaten und einen QR-Code angezeigt. Richte damit bei deiner Bank einen monatlichen Dauerauftrag ein (unbefristet). Klicke danach auf der Seite auf „Ich habe den Dauerauftrag eingerichtet". Empfänger: {name}, IBAN: {iban}, BIC: {bic}.',
                'en' => 'After the form you will see our bank details and a QR code. Use them to set up a monthly standing order at your bank (with no end date). Then click "I have set up the standing order" on the page. Recipient: {name}, IBAN: {iban}, BIC: {bic}.',
                'bs' => 'Nakon obrasca prikazat će vam se naši bankovni podaci i QR kod. Njima u svojoj banci postavite mjesečni trajni nalog (bez roka). Zatim na stranici kliknite „Postavio/la sam trajni nalog". Primalac: {name}, IBAN: {iban}, BIC: {bic}.',
            ],
            'faq_reference_q' => [
                'de' => 'Was muss in den Verwendungszweck?',
                'en' => 'What should the payment reference say?',
                'bs' => 'Šta treba navesti u svrhu uplate?',
            ],
            'faq_reference_a' => [
                'de' => 'Bitte immer „Patenschaft" plus den Namen des Hundes und deinen eigenen Namen angeben, zum Beispiel: „Patenschaft Luna – Maria Muster". Ohne diese Angabe können wir deine Zahlung nicht der richtigen Patenschaft zuordnen. Wenn du den QR-Code nutzt, ist der Verwendungszweck bereits richtig hinterlegt.',
                'en' => 'Please always state "Patenschaft" plus the dog\'s name and your own name, for example: "Patenschaft Luna – Maria Muster". Without this we cannot match your payment to the right sponsorship. If you use the QR code, the reference is already filled in correctly.',
                'bs' => 'Uvijek navedite „Patenschaft" te ime psa i svoje ime, na primjer: „Patenschaft Luna – Maria Muster". Bez toga vašu uplatu ne možemo povezati s odgovarajućim pokroviteljstvom. Ako koristite QR kod, svrha uplate je već ispravno unesena.',
            ],
            'faq_certificate_q' => [
                'de' => 'Wann bekomme ich mein Patenschafts-Zertifikat?',
                'en' => 'When do I get my sponsorship certificate?',
                'bs' => 'Kada dobijam svoj certifikat o pokroviteljstvu?',
            ],
            'faq_certificate_a' => [
                'de' => 'Bei PayPal automatisch, sobald die erste Zahlung bestätigt ist. Beim Dauerauftrag, sobald die erste Überweisung bei uns eingegangen ist und wir sie geprüft haben – das kann ein paar Bankarbeitstage dauern. Das Zertifikat kommt per E-Mail.',
                'en' => 'With PayPal automatically as soon as the first payment is confirmed. With a standing order once the first transfer has reached us and we have checked it – this can take a few banking days. The certificate is sent by email.',
                'bs' => 'Kod PayPala automatski, čim je prva uplata potvrđena. Kod trajnog naloga čim prva uplata stigne kod nas i provjerimo je – to može potrajati nekoliko radnih dana banke. Certifikat stiže e-poštom.',
            ],
            'faq_amount_q' => [
                'de' => 'Kann ich auch einen kleineren Betrag übernehmen?',
                'en' => 'Can I contribute a smaller amount?',
                'bs' => 'Mogu li preuzeti i manji iznos?',
            ],
            'faq_amount_a' => [
                'de' => 'Ja. Neben der vollen Patenschaft gibt es die Teilpatenschaft ab 5 € im Monat. Wenn für einen Hund nur noch ein kleinerer Restbetrag offen ist, gilt genau dieser Restbetrag. Den Betrag wählst du im Formular aus.',
                'en' => 'Yes. Besides the full sponsorship there is a partial sponsorship from €5 per month. If only a smaller remaining amount is open for a dog, exactly that remaining amount applies. You choose the amount in the form.',
                'bs' => 'Da. Osim punog pokroviteljstva postoji i djelimično pokroviteljstvo od 5 € mjesečno. Ako je za psa otvoren samo manji preostali iznos, vrijedi upravo taj iznos. Iznos birate u obrascu.',
            ],
            'faq_cancel_q' => [
                'de' => 'Wie kann ich die Patenschaft beenden?',
                'en' => 'How can I end the sponsorship?',
                'bs' => 'Kako mogu prekinuti pokroviteljstvo?',
            ],
            'faq_cancel_a' => [
                'de' => 'Bei PayPal kündigst du das Abo direkt in deinem PayPal-Konto unter „Einstellungen → Zahlungen → Automatische Zahlungen". Beim Dauerauftrag löschst du ihn bei deiner Bank. Bitte gib uns in beiden Fällen kurz Bescheid, damit wir den Hund wieder für eine neue Patenschaft freigeben können.',
                'en' => 'With PayPal you cancel the subscription directly in your PayPal account under "Settings → Payments → Automatic payments". With a standing order you delete it at your bank. In both cases please let us know briefly, so we can make the dog available for a new sponsorship again.',
                'bs' => 'Kod PayPala pretplatu otkazujete direktno u svom PayPal računu pod „Postavke → Plaćanja → Automatska plaćanja". Trajni nalog brišete u svojoj banci. U oba slučaja nas kratko obavijestite kako bismo psa ponovo oslobodili za novo pokroviteljstvo.',
            ],
            'faq_problem_q' => [
                'de' => 'Die Zahlung hat nicht funktioniert – was nun?',
                'en' => 'The payment did not work – what now?',
                'bs' => 'Plaćanje nije uspjelo – šta sada?',
            ],
            'faq_problem_a' => [
                'de' => 'Kein Problem, es geht nichts verloren. Melde dich einfach über das Formular unten oder über unser Kontaktformular. Schreib dazu, welchen Hund du unterstützen wolltest und welchen Zahlungsweg du gewählt hast – wir klären das gemeinsam.',
                'en' => 'No problem, nothing is lost. Simply get in touch via the form below or our contact form. Please tell us which dog you wanted to support and which payment method you chose – we will sort it out together.',
                'bs' => 'Nema problema, ništa nije izgubljeno. Javite nam se putem obrasca ispod ili našeg kontakt obrasca. Napišite kojeg ste psa željeli podržati i koji ste način plaćanja odabrali – riješit ćemo to zajedno.',
            ],
            'form_title' => [
                'de' => 'Technisches Problem melden',
                'en' => 'Report a technical problem',
                'bs' => 'Prijavi tehnički problem',
            ],
            'form_lead' => [
                'de' => 'Etwas funktioniert nicht wie erwartet? Beschreib kurz, was passiert ist – gerne mit Screenshot. Die Meldung geht direkt an unsere technische Betreuung.',
                'en' => 'Something not working as expected? Briefly describe what happened – a screenshot is welcome. The report goes straight to our technical support.',
                'bs' => 'Nešto ne radi kako treba? Ukratko opišite šta se dogodilo – rado i uz snimak ekrana. Prijava ide direktno našoj tehničkoj podršci.',
            ],
            'hp' => ['de' => 'Bitte freilassen', 'en' => 'Please leave empty', 'bs' => 'Molimo ostavite prazno'],
            'field_name' => ['de' => 'Name', 'en' => 'Name', 'bs' => 'Ime'],
            'field_email' => ['de' => 'E-Mail-Adresse', 'en' => 'Email address', 'bs' => 'E-mail adresa'],
            'field_message' => [
                'de' => 'Was funktioniert nicht?',
                'en' => 'What is not working?',
                'bs' => 'Šta ne funkcioniše?',
            ],
            'field_message_ph' => [
                'de' => 'Zum Beispiel: Auf welcher Seite tritt der Fehler auf, was hast du geklickt, was ist dann passiert?',
                'en' => 'For example: on which page does the error occur, what did you click, what happened then?',
                'bs' => 'Na primjer: na kojoj se stranici javlja greška, šta ste kliknuli, šta se onda dogodilo?',
            ],
            'field_files' => [
                'de' => 'Screenshots (optional, max. 3 Bilder)',
                'en' => 'Screenshots (optional, max. 3 images)',
                'bs' => 'Snimci ekrana (opcionalno, najviše 3 slike)',
            ],
            'field_files_hint' => [
                'de' => 'JPG, PNG oder WEBP, je bis 6 MB.',
                'en' => 'JPG, PNG or WEBP, up to 6 MB each.',
                'bs' => 'JPG, PNG ili WEBP, do 6 MB po slici.',
            ],
            'consent' => [
                'de' => 'Ich habe die Datenschutzerklärung gelesen und bin einverstanden, dass meine Angaben zur Bearbeitung dieser Meldung verarbeitet werden.',
                'en' => 'I have read the privacy policy and agree that my details may be processed to handle this report.',
                'bs' => 'Pročitao/la sam pravila o zaštiti podataka i saglasan/na sam da se moji podaci obrade radi rješavanja ove prijave.',
            ],
            'submit' => ['de' => 'Meldung senden', 'en' => 'Send report', 'bs' => 'Pošalji prijavu'],
            'sent_ok' => [
                'de' => 'Danke! Deine Meldung ist bei uns angekommen. Du bekommst gleich eine Bestätigung per E-Mail – wir melden uns so schnell wie möglich bei dir.',
                'en' => 'Thank you! We have received your report. You will get a confirmation by email shortly – we will get back to you as soon as possible.',
                'bs' => 'Hvala! Vaša prijava je stigla do nas. Uskoro ćete dobiti potvrdu e-poštom – javit ćemo vam se što je prije moguće.',
            ],
            'sent_error' => [
                'de' => 'Das hat leider nicht geklappt. Bitte prüfe deine E-Mail-Adresse und die Fehlerbeschreibung und versuche es noch einmal.',
                'en' => 'Unfortunately that did not work. Please check your email address and the description and try again.',
                'bs' => 'Nažalost to nije uspjelo. Provjerite svoju e-mail adresu i opis greške pa pokušajte ponovo.',
            ],
            'sent_file_error' => [
                'de' => 'Ein Bild konnte nicht verarbeitet werden. Erlaubt sind JPG, PNG oder WEBP bis 6 MB, maximal 3 Bilder.',
                'en' => 'An image could not be processed. Allowed are JPG, PNG or WEBP up to 6 MB, maximum 3 images.',
                'bs' => 'Jedna slika nije mogla biti obrađena. Dozvoljeni su JPG, PNG ili WEBP do 6 MB, najviše 3 slike.',
            ],
        ],
    ];
}

function sod_translations_common(): array
{
    return [
        'common' => [
            'nav_home' => ['de' => '{org}', 'en' => '{org}', 'bs' => '{org}'],
            'nav_start' => ['de' => 'Start', 'en' => 'Home', 'bs' => 'Početna'],
            'nav_vermittlung' => ['de' => 'Vermittlung', 'en' => 'Adoption', 'bs' => 'Udomljavanje'],
            'nav_patenschaft' => ['de' => 'Patenschaft', 'en' => 'Sponsorship', 'bs' => 'Pokroviteljstvo'],
            'nav_spenden' => ['de' => 'Spenden', 'en' => 'Donate', 'bs' => 'Doniraj'],
            'nav_kontakt' => ['de' => 'Kontakt', 'en' => 'Contact', 'bs' => 'Kontakt'],
            'nav_ueber_uns' => ['de' => 'Über uns', 'en' => 'About us', 'bs' => 'O nama'],
            'nav_region' => ['de' => 'Unsere Arbeit', 'en' => 'Our work', 'bs' => 'Naš rad'],
            'nav_tierheim_bau' => ['de' => 'Tierheim-Bau', 'en' => 'Shelter build', 'bs' => 'Izgradnja skloništa'],
            'nav_menu_label' => ['de' => 'Menü', 'en' => 'Menu', 'bs' => 'Meni'],
            'gallery_open_image' => ['de' => 'Bild groß ansehen', 'en' => 'View larger image', 'bs' => 'Prikaži veću sliku'],
            'gallery_close_image' => ['de' => 'Bildvorschau schließen', 'en' => 'Close image preview', 'bs' => 'Zatvori pregled slike'],
            'tagline' => ['de' => 'Tierschutz', 'en' => 'Animal welfare', 'bs' => 'Zaštita životinja'],
            'footer_desc' => ['de' => 'Gemeinsam helfen wir Tieren in Not.', 'en' => 'Together we help animals in need.', 'bs' => 'Zajedno pomažemo životinjama u nevolji.'],
            'footer_org_title' => ['de' => 'Organisation', 'en' => 'Organisation', 'bs' => 'Organizacija'],
            'footer_help_title' => ['de' => 'Mitmachen', 'en' => 'Get involved', 'bs' => 'Uključite se'],
            'footer_contact_title' => ['de' => 'Kontakt', 'en' => 'Contact', 'bs' => 'Kontakt'],
            'footer_impressum' => ['de' => 'Impressum', 'en' => 'Legal notice', 'bs' => 'Impresum'],
            'footer_datenschutz' => ['de' => 'Datenschutz', 'en' => 'Privacy policy', 'bs' => 'Zaštita podataka'],
            'footer_bottom_copyright' => ['de' => '{org}', 'en' => '{org}', 'bs' => '{org}'],
            'footer_bottom_tagline' => ['de' => 'Direkte Hilfe für Tiere in Not.', 'en' => 'Direct help for animals in need.', 'bs' => 'Direktna pomoć životinjama u nevolji.'],
            'btn_jetzt_spenden' => ['de' => 'Jetzt spenden', 'en' => 'Donate now', 'bs' => 'Doniraj sada'],
            'btn_anfrage_stellen' => ['de' => 'Anfrage stellen', 'en' => 'Send inquiry', 'bs' => 'Pošalji upit'],
            'btn_patenschaft_anfragen' => ['de' => 'Patenschaft anfragen', 'en' => 'Request sponsorship', 'bs' => 'Zatraži pokroviteljstvo'],
            'btn_details' => ['de' => 'Details', 'en' => 'Details', 'bs' => 'Detalji'],
        ],
    ];
}

function sod_translations_home(): array
{
    return [
        'home' => [
            'hero_eyebrow' => ['de' => '{org}', 'en' => '{org}', 'bs' => '{org}'],
            'hero_title' => ['de' => 'Hilfe für Tiere in Not.', 'en' => 'Help for animals in need.', 'bs' => 'Pomoć životinjama u nevolji.'],
            'hero_title_em' => ['de' => 'Direkt.', 'en' => 'Direct.', 'bs' => 'Direktno.'],
            'hero_title_rest' => ['de' => 'Nachvollziehbar.', 'en' => 'Transparent.', 'bs' => 'Transparentno.'],
            'hero_lead' => [
                'de' => 'Beschreibe hier in zwei bis drei Sätzen, was deine Organisation tut und wofür Unterstützung gebraucht wird.',
                'en' => 'Describe in two or three sentences what your organisation does and what support is needed for.',
                'bs' => 'Opišite u dvije ili tri rečenice šta vaša organizacija radi i za šta je potrebna podrška.',
            ],
            'hero_cta_primary' => ['de' => 'Jetzt spenden →', 'en' => 'Donate now →', 'bs' => 'Doniraj sada →'],
            'hero_cta_secondary' => ['de' => 'Vermittlung ansehen', 'en' => 'View adoptions', 'bs' => 'Pogledaj udomljavanje'],
            'hero_scroll' => ['de' => 'Scrollen', 'en' => 'Scroll', 'bs' => 'Skrolaj'],
            'circle_amount' => ['de' => '1 €', 'en' => '€1', 'bs' => '1 €'],
            'circle_sub' => ['de' => 'im Monat', 'en' => 'a month', 'bs' => 'mjesečno'],
            'circle_members_note' => ['de' => 'unterstützen uns schon', 'en' => 'already support us', 'bs' => 'već nas podržavaju'],
            'circle_ring_text' => ['de' => 'SCHON AB 1 € IM MONAT · ', 'en' => 'FROM €1 A MONTH · ', 'bs' => 'OD 1 € MJESEČNO · '],

            'video_title' => ['de' => 'Eine Geschichte, die zeigt, worum es geht.', 'en' => 'A story that shows what this is about.', 'bs' => 'Priča koja pokazuje o čemu se radi.'],
            'video_lead' => [
                'de' => 'Platz für ein kurzes Video oder Foto mit einer echten Geschichte aus eurer Arbeit. Ersetze diesen Text im Theme unter „Startseite“.',
                'en' => 'Space for a short video or photo with a real story from your work. Replace this text in the theme under “Front page”.',
                'bs' => 'Prostor za kratki video ili fotografiju s pravom pričom iz vašeg rada. Zamijenite ovaj tekst u temi pod „Naslovna“.',
            ],
            'video_cta_teaming' => ['de' => 'Mit 1 € im Monat helfen →', 'en' => 'Help with €1 a month →', 'bs' => 'Pomozi sa 1 € mjesečno →'],
            'video_cta_spenden' => ['de' => 'Jetzt spenden', 'en' => 'Donate now', 'bs' => 'Doniraj sada'],

            'impact_headline' => ['de' => 'Aus Mitgefühl wird konkrete Hilfe.', 'en' => 'Compassion becomes concrete help.', 'bs' => 'Suosjećanje postaje konkretna pomoć.'],
            'impact_sub' => [
                'de' => 'Beschreibe hier kurz, wofür Spenden konkret verwendet werden – zum Beispiel Futter, medizinische Versorgung, Transport und Vermittlung.',
                'en' => 'Briefly describe what donations are used for – for example food, medical care, transport and adoption.',
                'bs' => 'Ukratko opišite za šta se koriste donacije – na primjer hrana, medicinska njega, transport i udomljavanje.',
            ],
            'impact_cta_teaming' => ['de' => 'Für 1 € im Monat helfen →', 'en' => 'Help with €1 a month →', 'bs' => 'Pomozi sa 1 € mjesečno →'],
            'impact_cta_spenden' => ['de' => 'Mehr spenden', 'en' => 'Donate more', 'bs' => 'Doniraj više'],

            'cards_vermittlung_eyebrow' => ['de' => 'Vermittlung', 'en' => 'Adoption', 'bs' => 'Udomljavanje'],
            'cards_vermittlung_title' => ['de' => 'Ein Zuhause, wenn es passt.', 'en' => 'A home, when it fits.', 'bs' => 'Dom, kada odgovara.'],
            'cards_vermittlung_text' => ['de' => 'Nicht jedes Tier ist sofort bereit. Die, die es sind, findest du hier.', 'en' => 'Not every animal is ready right away. Those that are, you will find here.', 'bs' => 'Nije svaka životinja odmah spremna. One koje jesu, naći ćete ovdje.'],
            'cards_vermittlung_link' => ['de' => 'Vermittlung ansehen →', 'en' => 'View adoptions →', 'bs' => 'Pogledaj udomljavanje →'],
            'cards_patenschaft_eyebrow' => ['de' => 'Patenschaft', 'en' => 'Sponsorship', 'bs' => 'Pokroviteljstvo'],
            'cards_patenschaft_title' => ['de' => 'Planung hilft mehr als ein guter Moment.', 'en' => 'Planning helps more than a good moment.', 'bs' => 'Planiranje pomaže više od dobrog trenutka.'],
            'cards_patenschaft_text' => ['de' => 'Monatliche Hilfe macht Futter und sichere Plätze planbarer.', 'en' => 'Monthly support makes food and safe places more plannable.', 'bs' => 'Mjesečna pomoć čini hranu i sigurna mjesta predvidivijim.'],
            'cards_patenschaft_link' => ['de' => 'Patenschaft übernehmen →', 'en' => 'Become a sponsor →', 'bs' => 'Postani pokrovitelj →'],
            'cards_spenden_eyebrow' => ['de' => 'Spenden', 'en' => 'Donate', 'bs' => 'Doniraj'],
            'cards_spenden_title' => ['de' => 'Schon 1 € im Monat hilft.', 'en' => 'Even €1 a month helps.', 'bs' => 'Već 1 € mjesečno pomaže.'],
            'cards_spenden_text' => ['de' => 'Auch Sachspenden wie Futter, Leinen, Decken und Zubehör werden gebraucht.', 'en' => 'In-kind donations such as food, leashes, blankets and equipment are needed too.', 'bs' => 'Potrebne su i donacije u naturi poput hrane, povodaca, deka i opreme.'],
            'cards_spenden_link' => ['de' => 'Jetzt unterstützen →', 'en' => 'Support now →', 'bs' => 'Podrži sada →'],

            'quote_text' => [
                'de' => 'Hier kann ein Zitat stehen, das eure Haltung auf den Punkt bringt.',
                'en' => 'A quote that sums up your values can go here.',
                'bs' => 'Ovdje može stajati citat koji sažima vaše vrijednosti.',
            ],
            'quote_author' => ['de' => 'Name, Funktion', 'en' => 'Name, role', 'bs' => 'Ime, funkcija'],

            'story1_eyebrow' => ['de' => 'Warum wir nicht wegsehen', 'en' => 'Why we do not look away', 'bs' => 'Zašto ne skrećemo pogled'],
            'story1_title' => ['de' => 'Warum wir nicht wegsehen.', 'en' => 'Why we do not look away.', 'bs' => 'Zašto ne skrećemo pogled.'],
            'story1_p1' => ['de' => 'Erster Absatz eurer Geschichte: Wie hat alles angefangen?', 'en' => 'First paragraph of your story: how did it all start?', 'bs' => 'Prvi pasus vaše priče: kako je sve počelo?'],
            'story1_p2' => ['de' => 'Zweiter Absatz: Was macht eure Arbeit aus?', 'en' => 'Second paragraph: what defines your work?', 'bs' => 'Drugi pasus: šta određuje vaš rad?'],
            'story1_p3' => ['de' => 'Dritter Absatz: Wie geht ihr mit den Tieren um?', 'en' => 'Third paragraph: how do you treat the animals?', 'bs' => 'Treći pasus: kako postupate sa životinjama?'],
            'story1_caption' => ['de' => 'Bildunterschrift', 'en' => 'Image caption', 'bs' => 'Opis slike'],

            'story2_eyebrow' => ['de' => 'Was wir täglich leisten', 'en' => 'What we do every day', 'bs' => 'Šta radimo svaki dan'],
            'story2_title' => ['de' => 'Hilfe vor Ort braucht Verlässlichkeit.', 'en' => 'Help on the ground needs reliability.', 'bs' => 'Pomoć na terenu treba pouzdanost.'],
            'story2_p1' => ['de' => 'Beschreibe die tägliche Arbeit in wenigen Sätzen.', 'en' => 'Describe the daily work in a few sentences.', 'bs' => 'Opišite svakodnevni rad u nekoliko rečenica.'],
            'story2_p2' => ['de' => 'Erkläre, warum planbare Hilfe wichtiger ist als einmalige Aktionen.', 'en' => 'Explain why plannable help matters more than one-off actions.', 'bs' => 'Objasnite zašto je predvidiva pomoć važnija od jednokratnih akcija.'],
            'story2_p3' => ['de' => 'Ergänze, wie ihr Transparenz sicherstellt.', 'en' => 'Add how you ensure transparency.', 'bs' => 'Dodajte kako osiguravate transparentnost.'],
            'story2_caption' => ['de' => 'Bildunterschrift', 'en' => 'Image caption', 'bs' => 'Opis slike'],

            'story3_eyebrow' => ['de' => 'Wofür sich alles lohnt', 'en' => 'What makes it worthwhile', 'bs' => 'Zbog čega se isplati'],
            'story3_title' => ['de' => 'Wofür sich die Arbeit lohnt.', 'en' => 'What makes the work worthwhile.', 'bs' => 'Zbog čega se rad isplati.'],
            'story3_p1' => ['de' => 'Beschreibe, was ein gutes Ende für ein Tier bedeutet.', 'en' => 'Describe what a good outcome means for an animal.', 'bs' => 'Opišite šta dobar ishod znači za životinju.'],
            'story3_p2' => ['de' => 'Erkläre, wie Unterstützende Teil davon werden.', 'en' => 'Explain how supporters become part of it.', 'bs' => 'Objasnite kako podržavatelji postaju dio toga.'],
            'story3_caption' => ['de' => 'Bildunterschrift', 'en' => 'Image caption', 'bs' => 'Opis slike'],

            'gallery_eyebrow' => ['de' => 'Einblicke', 'en' => 'Insights', 'bs' => 'Uvidi'],
            'gallery_title' => ['de' => 'Was wir jeden Tag sehen.', 'en' => 'What we see every day.', 'bs' => 'Šta vidimo svaki dan.'],
            'gallery_lead' => ['de' => 'Diese Bilder zeigen Eindrücke aus der Arbeit vor Ort.', 'en' => 'These images show impressions from the work on the ground.', 'bs' => 'Ove slike prikazuju utiske iz rada na terenu.'],

            'social_eyebrow' => ['de' => 'Social Media', 'en' => 'Social media', 'bs' => 'Društvene mreže'],
            'social_title' => ['de' => 'Sieh dir unsere Einsätze als Video an.', 'en' => 'Watch our work on video.', 'bs' => 'Pogledajte naš rad na videu.'],
            'social_lead' => ['de' => 'Verlinke hier euren Social-Media-Kanal.', 'en' => 'Link your social media channel here.', 'bs' => 'Ovdje povežite svoj kanal na društvenim mrežama.'],
            'social_btn' => ['de' => 'Kanal öffnen →', 'en' => 'Open channel →', 'bs' => 'Otvori kanal →'],
            'social_consent_note' => [
                'de' => 'Aus Datenschutzgründen laden wir externe Inhalte nicht automatisch. Erst wenn du auf den Button klickst, wird eine Verbindung zum Anbieter hergestellt.',
                'en' => 'For privacy reasons we do not load external content automatically. Only when you click the button is a connection to the provider established.',
                'bs' => 'Iz razloga zaštite podataka ne učitavamo vanjski sadržaj automatski. Veza s pružateljem uspostavlja se tek kada kliknete na dugme.',
            ],
            'social_consent_btn' => ['de' => 'Externe Videos laden', 'en' => 'Load external videos', 'bs' => 'Učitaj vanjske videe'],

            'dogs_eyebrow' => ['de' => 'Vermittlung', 'en' => 'Adoption', 'bs' => 'Udomljavanje'],
            'dogs_title' => ['de' => 'Diese Tiere suchen ein Zuhause', 'en' => 'These animals are looking for a home', 'bs' => 'Ove životinje traže dom'],
            'dogs_lead' => ['de' => 'Manche Tiere brauchen ein Zuhause. Andere brauchen erst Versorgung und Ruhe.', 'en' => 'Some animals need a home. Others first need care and rest.', 'bs' => 'Neke životinje trebaju dom. Druge prvo trebaju njegu i mir.'],
            'dogs_link' => ['de' => 'Zur Vermittlung →', 'en' => 'To adoptions →', 'bs' => 'Na udomljavanje →'],
        ],
    ];
}

function sod_translations_vermittlung(): array
{
    return [
        'vermittlung' => [
            'eyebrow' => ['de' => 'Vermittlung', 'en' => 'Adoption', 'bs' => 'Udomljavanje'],
            'title' => ['de' => 'Vermittlung: Hunde, die ein Zuhause suchen.', 'en' => 'Adoption: Dogs looking for a home.', 'bs' => 'Udomljavanje: Psi koji traže dom.'],
            'lead' => [
                'de' => 'Hier findest du Hunde, für die wir ein passendes Zuhause suchen. Eine Anfrage ist noch keine Zusage, sondern der erste Schritt zu einem ehrlichen Kennenlernen.',
                'en' => 'Here you will find dogs for which we are looking for the right home. An inquiry is not yet a commitment, but the first step toward getting to know each other honestly.',
                'bs' => 'Ovdje ćete pronaći pse za koje tražimo odgovarajući dom. Upit još nije obećanje, već prvi korak ka iskrenom upoznavanju.',
            ],
            'steps_aria_label' => ['de' => 'Vermittlungsablauf', 'en' => 'Adoption process', 'bs' => 'Proces udomljavanja'],
            'step1_title' => ['de' => 'Anfrage stellen', 'en' => 'Send an inquiry', 'bs' => 'Pošaljite upit'],
            'step1_text' => [
                'de' => 'Du schreibst uns, welcher Hund dich interessiert und wie dein Zuhause aussieht.',
                'en' => 'You write to us about which dog interests you and what your home is like.',
                'bs' => 'Pišete nam koji pas vas zanima i kako izgleda vaš dom.',
            ],
            'step2_title' => ['de' => 'Ehrliches Gespräch', 'en' => 'Honest conversation', 'bs' => 'Iskren razgovor'],
            'step2_text' => [
                'de' => 'Wir klären offene Fragen und prüfen, ob Tier und Mensch realistisch zusammenpassen.',
                'en' => 'We clarify open questions and check whether animal and person realistically fit together.',
                'bs' => 'Razjašnjavamo otvorena pitanja i provjeravamo da li životinja i osoba zaista odgovaraju jedno drugom.',
            ],
            'step3_title' => ['de' => 'Vorbereitung', 'en' => 'Preparation', 'bs' => 'Priprema'],
            'step3_text' => [
                'de' => 'Wenn es passt, werden Schutzvertrag, Transport, Ankunft und die ersten Tage vorbereitet.',
                'en' => 'If it is a match, the adoption contract, transport, arrival and the first days are prepared.',
                'bs' => 'Ako se uklapa, priprema se ugovor o udomljavanju, transport, dolazak i prvi dani.',
            ],
            'step4_title' => ['de' => 'Ankommen', 'en' => 'Arrival', 'bs' => 'Dolazak'],
            'step4_text' => [
                'de' => 'Der Hund bekommt Zeit. Wir bleiben erreichbar, damit der Start nicht allein bewältigt werden muss.',
                'en' => 'The dog gets time. We stay reachable so the start does not have to be managed alone.',
                'bs' => 'Pas dobija vrijeme. Ostajemo dostupni kako početak ne bi morao biti savladan sam.',
            ],
        ],
    ];
}

function sod_translations_patenschaft(): array
{
    return [
        'patenschaft' => [
            'eyebrow' => ['de' => 'Patenschaft', 'en' => 'Sponsorship', 'bs' => 'Pokroviteljstvo'],
            'title' => ['de' => 'Bleib an der Seite eines Hundes.', 'en' => 'Stay by a dog\'s side.', 'bs' => 'Ostanite uz jednog psa.'],
            'lead' => [
                'de' => 'Eine Patenschaft hilft uns, nicht jedes Mal bei null anfangen zu müssen: Futter kaufen, Schutzplätze erhalten, die Einrichtung weiter aufbauen.',
                'en' => 'A sponsorship helps us avoid starting from zero every time: buying food, keeping shelter places, and continuing to build the facility.',
                'bs' => 'Pokroviteljstvo nam pomaže da ne moramo svaki put počinjati ispočetka: kupovina hrane, održavanje sigurnih mjesta, dalja izgradnja skloništa.',
            ],
            'teaming_badge' => ['de' => '1 € im Monat', 'en' => '€1 a month', 'bs' => '1 € mjesečno'],
            'teaming_title' => ['de' => 'Verlässliche Hilfe muss nicht groß anfangen.', 'en' => 'Reliable help does not have to start big.', 'bs' => 'Pouzdana pomoć ne mora početi veliko.'],
            'teaming_text' => [
                'de' => 'Über <strong>Teaming</strong> spendest du 1 Euro im Monat. Viele kleine Beiträge helfen bei Futter, Tierheim-Bau und Ausstattung.',
                'en' => 'Through <strong>Teaming</strong> you donate 1 euro a month. Many small contributions help with food, shelter construction and equipment.',
                'bs' => 'Preko <strong>Teaminga</strong> donirate 1 euro mjesečno. Mnogi mali doprinosi pomažu kod hrane, izgradnje skloništa i opreme.',
            ],
            'teaming_btn' => ['de' => 'Für 1 € im Monat helfen →', 'en' => 'Help with €1 a month →', 'bs' => 'Pomozi sa 1 € mjesečno →'],
            'dogs_eyebrow' => ['de' => 'Patenschaft', 'en' => 'Sponsorship', 'bs' => 'Pokroviteljstvo'],
            'dogs_title' => ['de' => 'Hier kannst du monatlich spenden', 'en' => 'Here you can donate monthly', 'bs' => 'Ovdje možete donirati mjesečno'],
            'dogs_subtitle' => [
                'de' => 'Bei ausgewählten Hunden kannst du direkt online eine volle monatliche Patenschaft oder eine Teilpatenschaft ab 5 € übernehmen – automatisch per PayPal oder per selbst eingerichtetem Dauerauftrag (Überweisung).',
                'en' => 'For selected dogs, you can take out a full monthly sponsorship or a partial sponsorship from €5 online — automatically via PayPal or by setting up a recurring bank transfer.',
                'bs' => 'Za odabrane pse možeš online preuzeti puno mjesečno pokroviteljstvo ili djelimično pokroviteljstvo od 5 € – automatski putem PayPal-a ili trajnim nalogom putem banke.',
            ],
            'active_title' => ['de' => 'Aktive Patenschaften', 'en' => 'Active sponsorships', 'bs' => 'Aktivna pokroviteljstva'],
            'active_subtitle' => [
                'de' => 'Diese Menschen tragen gerade eine Patenschaft — danke für euren Rückhalt!',
                'en' => 'These people currently hold a sponsorship — thank you for your support!',
                'bs' => 'Ovi ljudi trenutno nose pokroviteljstvo — hvala na podršci!',
            ],
            'pairing_sponsor' => ['de' => 'Pate/Patin', 'en' => 'Sponsor', 'bs' => 'Pokrovitelj'],
            'pairing_dog' => ['de' => 'Hund', 'en' => 'Dog', 'bs' => 'Pas'],
            'pairing_for' => ['de' => 'ist Pate für', 'en' => 'sponsors', 'bs' => 'je pokrovitelj za'],
            'why_title' => ['de' => 'Monatliche Hilfe macht Rettung planbar.', 'en' => 'Monthly support makes rescue predictable.', 'bs' => 'Mjesečna pomoć čini spašavanje planskim.'],
            'why_lead' => [
                'de' => 'Vor Ort zählt jeder Sack Futter und jeder Schritt in Richtung eigener Einrichtung. Eine Patenschaft hilft besonders, weil sie nicht nur einmal berührt, sondern dauerhaft trägt.',
                'en' => 'On the ground, every bag of food and every step toward a proper shelter counts. A sponsorship helps especially because it does not just touch once, but carries lasting support.',
                'bs' => 'Na terenu je važna svaka vreća hrane i svaki korak ka vlastitom skloništu. Pokroviteljstvo posebno pomaže jer ne dira samo jednom, već trajno pruža podršku.',
            ],
            'why1_title' => ['de' => 'Bau und Erhaltung des Tierheims', 'en' => 'Building and maintaining the shelter', 'bs' => 'Izgradnja i održavanje skloništa'],
            'why1_text' => [
                'de' => 'Regelmäßige Hilfe baut Schutzräume auf, und hilft das Tierheim dauerhaft zu erhalten.',
                'en' => 'Regular support builds shelter spaces and helps maintain the shelter for the long term.',
                'bs' => 'Redovna pomoć izgrađuje sigurna mjesta i pomaže u trajnom održavanju skloništa.',
            ],
            'why2_title' => ['de' => 'Futterbeschaffung', 'en' => 'Sourcing food', 'bs' => 'Nabavka hrane'],
            'why2_text' => [
                'de' => 'Futter muss regelmäßig gekauft und zu den Hunden gebracht werden – jeder Beitrag hilft sofort.',
                'en' => 'Food has to be bought regularly and brought to the dogs – every contribution helps immediately.',
                'bs' => 'Hrana se mora redovno kupovati i dostavljati psima – svaki doprinos odmah pomaže.',
            ],
            'why3_title' => ['de' => 'Sachspenden und Ausstattung', 'en' => 'In-kind donations and equipment', 'bs' => 'Donacije u naturi i oprema'],
            'why3_text' => [
                'de' => 'Leinen, Geschirre, Decken, Näpfe und Transportboxen werden direkt vor Ort gebraucht.',
                'en' => 'Leashes, harnesses, blankets, bowls and transport crates are needed directly on the ground.',
                'bs' => 'Povodci, ame, deke, posude i transportne kutije su potrebni direktno na terenu.',
            ],
            'why4_title' => ['de' => 'Schutz bis zur Vermittlung', 'en' => 'Shelter until adoption', 'bs' => 'Zaštita do udomljavanja'],
            'why4_text' => [
                'de' => 'Bis ein Hund ein Zuhause findet, braucht er einen sicheren Platz, Futter und Menschen, die bleiben.',
                'en' => 'Until a dog finds a home, it needs a safe place, food and people who stay.',
                'bs' => 'Dok pas ne pronađe dom, potrebno mu je sigurno mjesto, hrana i ljudi koji ostaju.',
            ],
            'widget_title' => ['de' => 'Patenschaft anfragen', 'en' => 'Request a sponsorship', 'bs' => 'Zatražite pokroviteljstvo'],
            'widget_sub' => ['de' => 'Wenn dich ein Tier berührt, schreib uns direkt.', 'en' => 'If an animal touches your heart, write to us directly.', 'bs' => 'Ako vas je neka životinja dirnula, pišite nam direktno.'],
            'widget_impact' => [
                'de' => 'Eine Patenschaft ist persönliche, monatliche Hilfe für Futter, Schutz und Versorgung. Wir sagen dir ehrlich, was gerade gebraucht wird.',
                'en' => 'A sponsorship is personal, monthly support for food, shelter and care. We will tell you honestly what is needed right now.',
                'bs' => 'Pokroviteljstvo je lična, mjesečna pomoć za hranu, sklonište i njegu. Iskreno ćemo vam reći šta je trenutno potrebno.',
            ],
            'widget_btn_kontakt' => ['de' => 'Kontakt aufnehmen', 'en' => 'Get in touch', 'bs' => 'Kontaktirajte nas'],
            'widget_btn_spenden' => ['de' => 'Zur Spendenseite', 'en' => 'Go to donation page', 'bs' => 'Idi na stranicu za donacije'],
        ],
    ];
}

function sod_translations_kontakt(): array
{
    return [
        'kontakt' => [
            'eyebrow' => ['de' => 'Direkter Kontakt', 'en' => 'Direct contact', 'bs' => 'Direktan kontakt'],
            'title' => ['de' => '{org}', 'en' => '{org}', 'bs' => '{org}'],
            'subtitle' => [
                'de' => 'Hier stehen deine offiziellen Vereinsdaten – Name, Registernummer und Sitz. Trage sie in den Plugin-Einstellungen unter „Organisation“ ein.',
                'en' => 'Your official organisation details go here – name, registration number and location. Enter them under “Organisation” in the plugin settings.',
                'bs' => 'Ovdje idu službeni podaci vaše organizacije – naziv, registarski broj i sjedište. Unesite ih u postavkama dodatka pod „Organizacija“.',
            ],
            'label_adresse' => ['de' => 'Adresse', 'en' => 'Address', 'bs' => 'Adresa'],
            'label_email' => ['de' => 'E-Mail', 'en' => 'Email', 'bs' => 'E-mail'],
            'label_telefon' => ['de' => 'Telefon', 'en' => 'Phone', 'bs' => 'Telefon'],
            'label_ansprechpartner' => ['de' => 'Ansprechpartner', 'en' => 'Contact person', 'bs' => 'Osoba za kontakt'],
            'message_eyebrow' => ['de' => 'Nachricht', 'en' => 'Message', 'bs' => 'Poruka'],
            'message_title' => ['de' => 'Wie möchtest du helfen?', 'en' => 'How would you like to help?', 'bs' => 'Kako želite pomoći?'],
            'form_sent_title' => ['de' => 'Wir haben deine Anfrage erhalten.', 'en' => 'We\'ve received your inquiry.', 'bs' => 'Primili smo vaš upit.'],
            'form_sent_text' => [
                'de' => 'Danke! Wir melden uns so bald wie möglich persönlich bei dir.',
                'en' => 'Thank you! We will get back to you personally as soon as possible.',
                'bs' => 'Hvala! Javit ćemo vam se lično što je prije moguće.',
            ],
            'form_note_title' => ['de' => 'Deine Anfrage ist unverbindlich.', 'en' => 'Your inquiry is non-binding.', 'bs' => 'Vaš upit nije obavezujući.'],
            'form_note_text' => [
                'de' => 'Wir melden uns persönlich bei dir und speichern Anfragen maximal 30 Tage.',
                'en' => 'We will get back to you personally and store inquiries for a maximum of 30 days.',
                'bs' => 'Javit ćemo vam se lično i čuvati upite najduže 30 dana.',
            ],
            'form_vorname' => ['de' => 'Vorname', 'en' => 'First name', 'bs' => 'Ime'],
            'form_nachname' => ['de' => 'Nachname', 'en' => 'Last name', 'bs' => 'Prezime'],
            'form_email' => ['de' => 'E-Mail', 'en' => 'Email', 'bs' => 'E-mail'],
            'form_telefon' => ['de' => 'Telefon', 'en' => 'Phone', 'bs' => 'Telefon'],
            'form_hund' => ['de' => 'Hund', 'en' => 'Dog', 'bs' => 'Pas'],
            'form_hund_placeholder' => ['de' => 'Name des Hundes', 'en' => 'Name of the dog', 'bs' => 'Ime psa'],
            'form_interesse' => ['de' => 'Ich interessiere mich für', 'en' => 'I am interested in', 'bs' => 'Zanima me'],
            'form_interest_vermittlung' => ['de' => 'Vermittlung / Adoption', 'en' => 'Adoption', 'bs' => 'Udomljavanje'],
            'form_interest_patenschaft' => ['de' => 'Patenschaft', 'en' => 'Sponsorship', 'bs' => 'Pokroviteljstvo'],
            'form_interest_sachspende' => ['de' => 'Sachspende', 'en' => 'In-kind donation', 'bs' => 'Donacija u naturi'],
            'form_interest_allgemein' => ['de' => 'Allgemeine Anfrage', 'en' => 'General inquiry', 'bs' => 'Opći upit'],
            'form_wohnort' => ['de' => 'Wohnort / Umgebung', 'en' => 'Location / area', 'bs' => 'Mjesto stanovanja / okolina'],
            'form_erfahrung' => ['de' => 'Erfahrung mit Hunden', 'en' => 'Experience with dogs', 'bs' => 'Iskustvo sa psima'],
            'form_nachricht' => ['de' => 'Nachricht', 'en' => 'Message', 'bs' => 'Poruka'],
            'form_consent' => [
                'de' => 'Ich habe die Datenschutzerklärung zur Kenntnis genommen.',
                'en' => 'I have read and understood the privacy policy.',
                'bs' => 'Pročitao/la sam i primio/la na znanje pravila o zaštiti podataka.',
            ],
            'form_submit' => ['de' => 'Nachricht senden', 'en' => 'Send message', 'bs' => 'Pošalji poruku'],
        ],
    ];
}

function sod_translations_spenden(): array
{
    return [
        'spenden' => [
            'eyebrow' => ['de' => 'Unterstützung', 'en' => 'Support', 'bs' => 'Podrška'],
            'title' => ['de' => 'Futter, Schutz, ein fester Ort.', 'en' => 'Food, shelter, a safe place.', 'bs' => 'Hrana, zaštita, sigurno mjesto.'],
            'lead' => [
                'de' => 'Beschreibe hier, wohin Spenden fließen und was damit möglich wird.',
                'en' => 'Describe here where donations go and what they make possible.',
                'bs' => 'Opišite ovdje kuda idu donacije i šta one omogućavaju.',
            ],
            'teaming_title' => ['de' => 'Klein für dich. Verlässlich für die Tiere.', 'en' => 'Small for you. Reliable for the animals.', 'bs' => 'Malo za vas. Pouzdano za životinje.'],
            'teaming_text' => [
                'de' => 'Erkläre hier euer Modell für kleine, regelmäßige Beiträge.',
                'en' => 'Explain your model for small, regular contributions here.',
                'bs' => 'Ovdje objasnite svoj model malih, redovnih doprinosa.',
            ],
            'teaming_btn' => ['de' => 'Für 1 € im Monat helfen →', 'en' => 'Help with €1 a month →', 'bs' => 'Pomozi sa 1 € mjesečno →'],
            'bank_title' => ['de' => 'Überweisung', 'en' => 'Bank transfer', 'bs' => 'Bankovni transfer'],
            'bank_note' => [
                'de' => 'Die Bankdaten stammen aus den Plugin-Einstellungen unter „Organisation“.',
                'en' => 'The bank details come from the plugin settings under “Organisation”.',
                'bs' => 'Bankovni podaci dolaze iz postavki dodatka pod „Organizacija“.',
            ],
            'bank_holder_label' => ['de' => 'Kontoinhaber', 'en' => 'Account holder', 'bs' => 'Vlasnik računa'],
            'bank_iban_label' => ['de' => 'IBAN', 'en' => 'IBAN', 'bs' => 'IBAN'],
            'bank_bic_label' => ['de' => 'BIC', 'en' => 'BIC', 'bs' => 'BIC'],
            'bank_reference_label' => ['de' => 'Verwendungszweck', 'en' => 'Reference', 'bs' => 'Svrha uplate'],
            'bank_reference_value' => ['de' => 'Spende', 'en' => 'Donation', 'bs' => 'Donacija'],
            'qr_title' => ['de' => 'Mit dem Handy spenden', 'en' => 'Donate with your phone', 'bs' => 'Doniraj mobitelom'],
            'qr_note' => ['de' => 'QR-Code mit der Banking-App scannen.', 'en' => 'Scan the QR code with your banking app.', 'bs' => 'Skenirajte QR kod svojom bankarskom aplikacijom.'],
            'needs_title' => ['de' => 'Sachspenden', 'en' => 'In-kind donations', 'bs' => 'Donacije u naturi'],
            'needs_note' => ['de' => 'Sachspenden können nach Absprache abgegeben werden – bitte vorher kurz Kontakt aufnehmen.', 'en' => 'In-kind donations can be dropped off by arrangement – please get in touch first.', 'bs' => 'Donacije u naturi mogu se predati po dogovoru – molimo prvo nas kontaktirajte.'],
            'receipt_note' => [
                'de' => 'Hinweis zur steuerlichen Absetzbarkeit: Bitte prüfe die Rechtslage in deinem Land und ergänze hier den passenden Text.',
                'en' => 'Note on tax deductibility: please check the legal situation in your country and add the appropriate text here.',
                'bs' => 'Napomena o poreznoj olakšici: provjerite pravnu situaciju u svojoj zemlji i ovdje dodajte odgovarajući tekst.',
            ],
        ],
    ];
}

function sod_translations_impressum(): array
{
    return [
        'impressum' => [
            'eyebrow' => ['de' => 'Rechtliches', 'en' => 'Legal', 'bs' => 'Pravno'],
            'title' => ['de' => 'Impressum', 'en' => 'Legal notice', 'bs' => 'Impresum'],
            'placeholder_title' => ['de' => 'Hier fehlen noch eure Angaben', 'en' => 'Your details are still missing here', 'bs' => 'Ovdje još nedostaju vaši podaci'],
            'placeholder_text' => [
                'de' => 'Dieses Theme wird ohne Impressum ausgeliefert. Trage hier die für deine Organisation und dein Land gesetzlich vorgeschriebenen Angaben ein – üblicherweise Name, Rechtsform, Anschrift, Vertretungsberechtigte, Registernummer, Kontaktdaten und Aufsichtsbehörde. Im Zweifel rechtlich beraten lassen.',
                'en' => 'This theme ships without a legal notice. Add the details legally required for your organisation and country here – typically name, legal form, address, authorised representatives, registration number, contact details and supervisory authority. Seek legal advice if in doubt.',
                'bs' => 'Ova tema se isporučuje bez impresuma. Ovdje unesite podatke koje zakon zahtijeva za vašu organizaciju i zemlju – obično naziv, pravni oblik, adresu, ovlaštene zastupnike, registarski broj, kontakt podatke i nadzorno tijelo. U slučaju nedoumice potražite pravni savjet.',
            ],
        ],
    ];
}

function sod_translations_dogcard(): array
{
    return [
        'dogcard' => [
            'status_verfuegbar' => ['de' => 'Verfügbar', 'en' => 'Available', 'bs' => 'Dostupan/na'],
            'status_vermittlung' => ['de' => 'In Vermittlung', 'en' => 'In adoption process', 'bs' => 'U procesu udomljavanja'],
            'status_vermittelt' => ['de' => 'Vermittelt', 'en' => 'Adopted', 'bs' => 'Udomljen/a'],
            'status_notfall' => ['de' => 'Notfall', 'en' => 'Emergency', 'bs' => 'Hitan slučaj'],
            'status_pause' => ['de' => 'Pause', 'en' => 'On hold', 'bs' => 'Pauza'],
            'thumb_video_aria_tpl' => [
                'de' => 'Weiteres Video von {name} abspielen ({n})',
                'en' => 'Play another video of {name} ({n})',
                'bs' => 'Pusti još jedan video za {name} ({n})',
            ],
            'status_ziel_erreicht' => [
                'de' => 'Danke ❤️ Ziel erreicht',
                'en' => 'Thank you ❤️ Goal reached',
                'bs' => 'Hvala ❤️ Cilj postignut',
            ],
            'meta_alter' => ['de' => 'Alter', 'en' => 'Age', 'bs' => 'Starost'],
            'meta_typ' => ['de' => 'Typ', 'en' => 'Type', 'bs' => 'Tip'],
            'meta_rasse_typ' => ['de' => 'Rasse / Typ', 'en' => 'Breed / Type', 'bs' => 'Rasa / Tip'],
            'meta_geschlecht' => ['de' => 'Geschlecht', 'en' => 'Sex', 'bs' => 'Spol'],
            'meta_gewicht' => ['de' => 'Gewicht', 'en' => 'Weight', 'bs' => 'Težina'],
            'meta_aufenthaltsort' => ['de' => 'Aufenthaltsort', 'en' => 'Location', 'bs' => 'Lokacija'],
            'section_gesundheit' => ['de' => 'Gesundheit', 'en' => 'Health', 'bs' => 'Zdravlje'],
            'section_charakter' => ['de' => 'Charakter', 'en' => 'Character', 'bs' => 'Karakter'],
            'section_braucht' => ['de' => 'Braucht', 'en' => 'Needs', 'bs' => 'Potrebe'],
            'detail_needs_title_tpl' => ['de' => 'Das braucht {name}', 'en' => 'What {name} needs', 'bs' => 'Šta {name} treba'],
            'image_placeholder' => ['de' => 'Bild folgt', 'en' => 'Photo coming soon', 'bs' => 'Slika uskoro'],
            'aria_mehr_erfahren_tpl' => ['de' => 'Mehr über {name} erfahren', 'en' => 'Learn more about {name}', 'bs' => 'Saznajte više o psu {name}'],
            'location_fallback' => ['de' => 'Aufenthaltsort auf Anfrage', 'en' => 'Location on request', 'bs' => 'Lokacija na upit'],
            'profil_ansehen' => ['de' => 'Profil ansehen', 'en' => 'View profile', 'bs' => 'Pogledaj profil'],
            'anfrage_stellen' => ['de' => 'Anfrage stellen', 'en' => 'Send inquiry', 'bs' => 'Pošalji upit'],
            'patenschaft_anfragen' => ['de' => 'Patenschaft anfragen', 'en' => 'Request sponsorship', 'bs' => 'Zatraži pokroviteljstvo'],
            'patenschaft_vergeben' => ['de' => 'Patenschaft bereits vergeben', 'en' => 'Sponsorship already taken', 'bs' => 'Pokroviteljstvo je već zauzeto'],
            'sponsorship_covered' => ['de' => 'Monatsversorgung gedeckt', 'en' => 'Monthly support covered', 'bs' => 'Mjesečna podrška je pokrivena'],
            'sponsorship_covered_note' => [
                'de' => 'Die Monatsversorgung ist derzeit vollständig durch Patenschaften gedeckt.',
                'en' => 'The monthly support is currently fully covered by sponsorships.',
                'bs' => 'Mjesečna podrška je trenutno u potpunosti pokrivena pokroviteljstvima.',
            ],
            'sponsorship_taken_note' => [
                'de' => 'Diese Patenschaft ist derzeit vergeben. Sobald sie endet, kannst du hier Pate werden.',
                'en' => 'This sponsorship is currently taken. Once it ends, you can become a sponsor here.',
                'bs' => 'Ovo pokroviteljstvo je trenutno zauzeto. Čim se završi, ovdje možeš postati pokrovitelj.',
            ],
            'pate_werden' => ['de' => 'Pate werden', 'en' => 'Become a sponsor', 'bs' => 'Postani pokrovitelj'],
            'pate_werden_amount_tpl' => ['de' => 'Pate werden – {amount} € mtl.', 'en' => 'Become a sponsor – {amount} € / mo.', 'bs' => 'Postani pokrovitelj – {amount} € mjesečno'],
            'pate_werden_partial_tpl' => ['de' => 'Teilpatenschaft – noch {amount} € offen', 'en' => 'Partial sponsorship – {amount} € still needed', 'bs' => 'Djelimično pokroviteljstvo – potrebno još {amount} €'],
            'pate_werden_partial_short' => ['de' => 'Teilpatenschaft', 'en' => 'Partial sponsorship', 'bs' => 'Djelimično pokroviteljstvo'],
            'pate_werden_full_short' => ['de' => 'Patenschaft', 'en' => 'Full sponsorship', 'bs' => 'Puno pokroviteljstvo'],
            'pate_werden_full_tpl' => ['de' => 'Patenschaft – {amount} € monatlich', 'en' => 'Full sponsorship – €{amount} per month', 'bs' => 'Puno pokroviteljstvo – {amount} € mjesečno'],
            'pate_werden_partial_from_five' => ['de' => 'Teilpatenschaft ab 5 €', 'en' => 'Partial sponsorship from €5', 'bs' => 'Djelimično pokroviteljstvo od 5 €'],
            'signup_title' => ['de' => 'Pate werden', 'en' => 'Become a sponsor', 'bs' => 'Postani pokrovitelj'],
            'signup_lead_tpl' => [
                'de' => 'Werde Pate/Patin für {name} — {amount} € monatlich, jederzeit kündbar.',
                'en' => 'Become a sponsor for {name} — {amount} € per month, cancel anytime.',
                'bs' => 'Postani pokrovitelj/ica za {name} — {amount} € mjesečno, otkaz u bilo kojem trenutku.',
            ],
            'signup_lead_partial_tpl' => [
                'de' => 'Übernimm die volle monatliche Patenschaft oder eine Teilpatenschaft ab 5 € für {name}. Jeder Beitrag hilft und ist jederzeit kündbar.',
                'en' => 'Take on the full monthly sponsorship or a partial sponsorship from €5 for {name}. Every contribution helps and can be cancelled at any time.',
                'bs' => 'Preuzmi puno mjesečno pokroviteljstvo ili djelimično pokroviteljstvo od 5 € za psa {name}. Svaki doprinos pomaže i može se otkazati u bilo kojem trenutku.',
            ],
            'signup_amount_label' => ['de' => 'Dein monatlicher Patenschaftsbetrag', 'en' => 'Your monthly sponsorship amount', 'bs' => 'Tvoj mjesečni iznos pokroviteljstva'],
            'signup_amount_hint_tpl' => [
                'de' => 'Noch {amount} € pro Monat werden benötigt. Eine Teilpatenschaft ist ab {minimum} € möglich; mit dem gesamten offenen Betrag übernimmst du die volle Patenschaft.',
                'en' => '€{amount} per month is still needed. A partial sponsorship starts at €{minimum}; choose the full remaining amount for a full sponsorship.',
                'bs' => 'Potrebno je još {amount} € mjesečno. Djelimično pokroviteljstvo počinje od {minimum} €; za puno pokroviteljstvo odaberi cijeli preostali iznos.',
            ],
            'signup_submit_partial' => ['de' => 'Weiter zur monatlichen Patenschaft', 'en' => 'Continue to monthly sponsorship', 'bs' => 'Nastavi na mjesečno pokroviteljstvo'],
            'signup_submit_tpl' => ['de' => 'Weiter – {amount} € monatlich', 'en' => 'Continue – {amount} € per month', 'bs' => 'Nastavi – {amount} € mjesečno'],
            'signup_payment_method_label' => ['de' => 'Zahlungsart', 'en' => 'Payment method', 'bs' => 'Način plaćanja'],
            'signup_payment_paypal_title' => ['de' => 'PayPal-Abo', 'en' => 'PayPal subscription', 'bs' => 'PayPal pretplata'],
            'signup_payment_paypal_text' => [
                'de' => 'Automatisch, sofort aktiv, jederzeit in PayPal kündbar.',
                'en' => 'Automatic, active immediately, cancel anytime in PayPal.',
                'bs' => 'Automatski, odmah aktivno, otkaz u bilo kojem trenutku u PayPal-u.',
            ],
            'signup_payment_bank_title' => ['de' => 'Dauerauftrag (Banküberweisung)', 'en' => 'Standing order (bank transfer)', 'bs' => 'Trajni nalog (bankovni prijenos)'],
            'signup_payment_bank_text' => [
                'de' => 'Du richtest bei deiner Bank einen monatlichen Dauerauftrag ein — kein PayPal-Konto nötig.',
                'en' => 'You set up a monthly standing order with your bank — no PayPal account needed.',
                'bs' => 'Postavljaš mjesečni trajni nalog kod svoje banke — PayPal račun nije potreban.',
            ],
            'signup_photo_legend' => ['de' => 'Als Pate auf der Hundeseite erscheinen (optional)', 'en' => 'Appear as a sponsor on the dog\'s page (optional)', 'bs' => 'Pojavi se kao pokrovitelj na stranici psa (opcionalno)'],
            'signup_photo_optin_tpl' => [
                'de' => 'Ich willige ein, mit meinem Vornamen und Foto öffentlich als Pate/Patin von {name} zu erscheinen. Die Einwilligung ist freiwillig und jederzeit widerrufbar.',
                'en' => 'I consent to appearing publicly with my first name and photo as a sponsor of {name}. Consent is voluntary and can be withdrawn at any time.',
                'bs' => 'Pristajem da se javno prikažem sa svojim imenom i fotografijom kao pokrovitelj/ica za {name}. Saglasnost je dobrovoljna i može se opozvati u bilo kojem trenutku.',
            ],
            'signup_photo_label' => ['de' => 'Dein Foto (JPG, PNG oder WEBP, max. 6 MB)', 'en' => 'Your photo (JPG, PNG or WEBP, max. 6 MB)', 'bs' => 'Tvoja fotografija (JPG, PNG ili WEBP, maks. 6 MB)'],
            'signup_photo_note' => [
                'de' => 'Dein Foto erscheint erst nach kurzer Prüfung durch unser Team. Du kannst die Anzeige jederzeit widerrufen.',
                'en' => 'Your photo only appears after a brief review by our team. You can revoke the display at any time.',
                'bs' => 'Tvoja fotografija se pojavljuje tek nakon kratke provjere našeg tima. Prikaz možeš opozvati u bilo kojem trenutku.',
            ],
            'sponsor_wall_title_tpl' => ['de' => 'Die Paten von {name}', 'en' => 'The sponsors of {name}', 'bs' => 'Pokrovitelji za {name}'],
            'sponsor_wall_lead' => [
                'de' => 'Diese Menschen unterstützen bereits regelmäßig — danke!',
                'en' => 'These people already give regular support — thank you!',
                'bs' => 'Ovi ljudi već redovno podržavaju — hvala!',
            ],
            'sponsor_wall_anon' => ['de' => 'Pate', 'en' => 'Sponsor', 'bs' => 'Pokrovitelj'],
            'signup_bank_title' => ['de' => 'Dauerauftrag einrichten', 'en' => 'Set up standing order', 'bs' => 'Postavi trajni nalog'],
            'signup_bank_heading' => ['de' => 'Danke! Fast geschafft.', 'en' => 'Thank you! Almost there.', 'bs' => 'Hvala! Skoro gotovo.'],
            'signup_bank_lead' => [
                'de' => 'Richte bei deiner Bank einmalig einen monatlichen Dauerauftrag mit folgenden Daten ein — dein Zertifikat kommt automatisch per E-Mail, sobald wir die erste Zahlung bestätigt haben.',
                'en' => 'Set up a monthly standing order with your bank using the details below — your certificate will be emailed automatically once we confirm the first payment.',
                'bs' => 'Postavi kod svoje banke jednokratno mjesečni trajni nalog sa sljedećim podacima — tvoj certifikat stiže automatski putem e-maila čim potvrdimo prvu uplatu.',
            ],
            'signup_bank_holder' => ['de' => 'Kontoinhaber', 'en' => 'Account holder', 'bs' => 'Vlasnik računa'],
            'signup_bank_amount' => ['de' => 'Monatlicher Betrag', 'en' => 'Monthly amount', 'bs' => 'Mjesečni iznos'],
            'signup_bank_reference' => ['de' => 'Verwendungszweck', 'en' => 'Payment reference', 'bs' => 'Poziv na broj / svrha'],
            'signup_bank_qr_note' => [
                'de' => 'Scanne den Code mit deiner Banking-App — falls sie „Dauerauftrag" oder „monatlich wiederholen" anbietet, wähle das dort. Sonst richte den Dauerauftrag einfach manuell mit obigen Daten ein.',
                'en' => 'Scan the code with your banking app — if it offers "standing order" or "repeat monthly", choose that. Otherwise just set up the standing order manually with the details above.',
                'bs' => 'Skeniraj kod svojom bankovnom aplikacijom — ako nudi "trajni nalog" ili "ponavljaj mjesečno", odaberi to. U suprotnom jednostavno postavi trajni nalog ručno sa gore navedenim podacima.',
            ],
            'signup_bank_confirm_button' => [
                'de' => 'Ich habe den Dauerauftrag eingerichtet',
                'en' => 'I have set up the standing order',
                'bs' => 'Postavio/la sam trajni nalog',
            ],
            'signup_bank_cancel_button' => [
                'de' => 'Doch nicht — abbrechen',
                'en' => 'Actually, cancel',
                'bs' => 'Ipak ne — otkaži',
            ],
            'signup_bank_confirm_note' => [
                'de' => 'Bitte bestätige erst, wenn du den Dauerauftrag bei deiner Bank tatsächlich eingerichtet hast. Erst danach beginnt die Frist, innerhalb der wir den Zahlungseingang prüfen.',
                'en' => 'Please confirm only once you have actually set up the standing order with your bank. Only then does the window in which we check for the incoming payment begin.',
                'bs' => 'Molimo potvrdi tek kada zaista postaviš trajni nalog kod svoje banke. Tek tada počinje rok u kojem provjeravamo uplatu.',
            ],
            'signup_bank_invalid_title' => ['de' => 'Link ungültig', 'en' => 'Link invalid', 'bs' => 'Nevažeći link'],
            'signup_bank_invalid_text' => [
                'de' => 'Dieser Link ist ungültig oder wurde bereits verwendet.',
                'en' => 'This link is invalid or has already been used.',
                'bs' => 'Ovaj link je nevažeći ili je već iskorišten.',
            ],
            'signup_bank_cancelled_title' => ['de' => 'Alles klar', 'en' => 'Got it', 'bs' => 'U redu'],
            'signup_bank_cancelled_text' => [
                'de' => 'Wir haben die Patenschaft storniert. Du kannst jederzeit einen neuen Versuch starten.',
                'en' => 'We have cancelled the sponsorship. You can start again at any time.',
                'bs' => 'Otkazali smo pokroviteljstvo. Možeš ponovo pokušati u bilo kojem trenutku.',
            ],
            'signup_bank_confirmed_title' => [
                'de' => 'Danke für deine Bestätigung',
                'en' => 'Thanks for confirming',
                'bs' => 'Hvala na potvrdi',
            ],
            'signup_bank_confirmed_text' => [
                'de' => 'Wir haben deine Angabe vermerkt. Sobald der Zahlungseingang bestätigt ist, senden wir dir automatisch dein Zertifikat per E-Mail.',
                'en' => 'We have noted your confirmation. Once the payment is confirmed, we will automatically email you your certificate.',
                'bs' => 'Zabilježili smo tvoju potvrdu. Čim se uplata potvrdi, automatski ćemo ti poslati certifikat e-mailom.',
            ],
            'signup_thanks' => [
                'de' => 'Danke! Deine Patenschaft wird aktiv, sobald PayPal die Zahlung bestätigt hat — dein Zertifikat kommt automatisch per E-Mail.',
                'en' => 'Thank you! Your sponsorship becomes active once PayPal confirms the payment — your certificate will be emailed automatically.',
                'bs' => 'Hvala! Tvoje pokroviteljstvo postaje aktivno čim PayPal potvrdi uplatu — tvoj certifikat stiže automatski putem e-maila.',
            ],
            'signup_cancelled' => [
                'de' => 'Du hast den Vorgang bei PayPal abgebrochen. Du kannst jederzeit erneut Pate werden.',
                'en' => 'You cancelled the process at PayPal. You can become a sponsor again at any time.',
                'bs' => 'Otkazao/la si postupak na PayPal-u. Možeš ponovo postati pokrovitelj/ica u bilo kojem trenutku.',
            ],
            'signup_error' => [
                'de' => 'Deine Anmeldung konnte nicht verarbeitet werden. Bitte versuche es erneut.',
                'en' => 'Your signup could not be processed. Please try again.',
                'bs' => 'Tvoja prijava nije mogla biti obrađena. Molimo pokušaj ponovo.',
            ],
            'signup_redirect_title' => ['de' => 'Weiterleitung zu PayPal ...', 'en' => 'Redirecting to PayPal ...', 'bs' => 'Preusmjeravanje na PayPal ...'],
            'signup_redirect_text' => [
                'de' => 'Du wirst zu PayPal weitergeleitet, um dein monatliches Patenschafts-Abo abzuschließen ...',
                'en' => 'You are being redirected to PayPal to complete your monthly sponsorship subscription ...',
                'bs' => 'Bit ćeš preusmjeren/a na PayPal kako bi završio/la mjesečnu pretplatu za pokroviteljstvo ...',
            ],
            'signup_redirect_btn' => ['de' => 'Weiter zu PayPal', 'en' => 'Continue to PayPal', 'bs' => 'Nastavi na PayPal'],
            'zurueck_zur_uebersicht' => ['de' => 'Zurück zur Übersicht', 'en' => 'Back to overview', 'bs' => 'Nazad na pregled'],
            'hund_in_pruefung' => ['de' => 'Hund in Prüfung', 'en' => 'Dog under review', 'bs' => 'Pas se provjerava'],
            'detail_placeholder' => ['de' => 'Hund', 'en' => 'Dog', 'bs' => 'Pas'],
            'gallery_aria_tpl' => ['de' => 'Bilder von {name}', 'en' => 'Photos of {name}', 'bs' => 'Fotografije psa {name}'],
            'thumb_aria_tpl' => ['de' => '{name} Bild {n} groß ansehen', 'en' => 'View {name} photo {n} enlarged', 'bs' => 'Pogledaj uvećanu sliku {n} psa {name}'],
            'video_fallback' => ['de' => 'Dein Browser kann dieses Video nicht abspielen.', 'en' => 'Your browser cannot play this video.', 'bs' => 'Vaš preglednik ne može reproducirati ovaj video.'],
            'video_external_consent' => [
                'de' => 'Mit deinem Klick willigst du ein, dass der externe Videoanbieter deine IP-Adresse, Browserdaten und die aufgerufene Seite erhält. Hinweise findest du in der Datenschutzerklärung.',
                'en' => 'By clicking, you consent to the external video provider receiving your IP address, browser data and the page visited. See the privacy policy for details.',
                'bs' => 'Klikom pristajete da vanjski video pružalac primi vašu IP adresu, podatke pretraživača i posjećenu stranicu. Detalji su u pravilima privatnosti.',
            ],
            'video_external_load' => ['de' => 'Zustimmen und Video laden', 'en' => 'Consent and load video', 'bs' => 'Pristajem i učitaj video'],
            'support_progress_title' => ['de' => 'Monatsversorgung', 'en' => 'Monthly support', 'bs' => 'Mjesečna podrška'],
            'support_progress_percent_tpl' => ['de' => '{percent}% gesichert', 'en' => '{percent}% covered', 'bs' => '{percent}% obezbijeđeno'],
            'support_progress_aria_tpl' => ['de' => '{percent} Prozent der Monatsversorgung gesichert', 'en' => '{percent} percent of monthly support covered', 'bs' => '{percent} posto mjesečne podrške obezbijeđeno'],
            'support_progress_text_tpl' => ['de' => '{secured} von {need} € sind gesichert. {remaining}.', 'en' => '{secured} of {need} € are covered. {remaining}.', 'bs' => '{secured} od {need} € je obezbijeđeno. {remaining}.'],
            'support_progress_note' => ['de' => 'Aktive Teilpatenschaften und bestätigte Beiträge werden automatisch berücksichtigt.', 'en' => 'Active partial sponsorships and confirmed contributions are included automatically.', 'bs' => 'Aktivna djelimična pokroviteljstva i potvrđeni doprinosi se automatski uračunavaju.'],
            'support_progress_remaining_tpl' => ['de' => 'Noch {amount} € offen', 'en' => '{amount} € still needed', 'bs' => 'Još {amount} € potrebno'],
            'support_progress_covered' => ['de' => 'Versorgung für diesen Monat gedeckt', 'en' => 'Support for this month is fully covered', 'bs' => 'Podrška za ovaj mjesec je potpuno obezbijeđena'],
            'sponsorship_title' => ['de' => 'Patenschaft', 'en' => 'Sponsorship', 'bs' => 'Pokroviteljstvo'],
            'sponsorship_default_text' => [
                'de' => 'Mit einer Patenschaft hilfst du bei Futter, medizinischer Versorgung und Unterbringung.',
                'en' => 'With a sponsorship you help with food, medical care and shelter.',
                'bs' => 'Pokroviteljstvom pomažete sa hranom, medicinskom njegom i smještajem.',
            ],
            'sponsorship_amount_tpl' => ['de' => 'Patenschaftsbetrag: {amount} € pro Monat', 'en' => 'Sponsorship amount: €{amount} per month', 'bs' => 'Iznos pokroviteljstva: {amount} € mjesečno'],
            'sponsorship_target_tpl' => ['de' => 'Monatsbedarf: {amount} €', 'en' => 'Monthly need: €{amount}', 'bs' => 'Mjesečna potreba: {amount} €'],
            'name_sponsorship_title' => ['de' => 'Namenspatenschaft', 'en' => 'Name sponsorship', 'bs' => 'Pokroviteljstvo nad imenom'],
            'name_sponsorship_card_label' => ['de' => 'Noch ohne Namen', 'en' => 'Not yet named', 'bs' => 'Još bez imena'],
            'name_sponsorship_default_text' => [
                'de' => 'Dieser Hund hat noch keinen Namen. Mit einer Namenspatenschaft schenkst du ihm seinen Namen und unterstützt gleichzeitig seine Versorgung.',
                'en' => 'This dog doesn\'t have a name yet. With a name sponsorship you give him his name and support his care at the same time.',
                'bs' => 'Ovaj pas još nema ime. Pokroviteljstvom nad imenom poklanjate mu ime i istovremeno podržavate njegovu njegu.',
            ],
            'name_sponsorship_amount_tpl' => ['de' => 'Vorschlag: {amount} €', 'en' => 'Suggested: €{amount}', 'bs' => 'Prijedlog: {amount} €'],
            'name_sponsorship_btn' => ['de' => 'Namenspatenschaft übernehmen', 'en' => 'Take the name sponsorship', 'bs' => 'Preuzmi pokroviteljstvo nad imenom'],
            'name_sponsorship_note' => [
                'de' => 'Auch per Überweisung möglich – Kontodaten findest du auf der Spenden-Seite. Bitte „Namenspatenschaft" als Verwendungszweck angeben.',
                'en' => 'Also possible by bank transfer – account details are on the donations page. Please use "name sponsorship" as the payment reference.',
                'bs' => 'Moguće je i bankovnim transferom – podaci o računu se nalaze na stranici za donacije. Molimo navedite "pokroviteljstvo nad imenom" kao svrhu uplate.',
            ],
            'name_sponsorship_reference_tpl' => ['de' => 'Namenspatenschaft #{id}', 'en' => 'Name sponsorship #{id}', 'bs' => 'Pokroviteljstvo nad imenom #{id}'],
            'name_sponsorship_paypal_title' => ['de' => 'Mit PayPal bezahlen', 'en' => 'Pay with PayPal', 'bs' => 'Plati putem PayPal-a'],
            'name_sponsorship_paypal_text' => ['de' => 'Schnell und direkt per Karte oder PayPal-Konto.', 'en' => 'Fast and direct by card or PayPal account.', 'bs' => 'Brzo i direktno karticom ili PayPal računom.'],
            'name_sponsorship_qr_title' => ['de' => 'Per Banking-App scannen', 'en' => 'Scan with your banking app', 'bs' => 'Skeniraj bankovnom aplikacijom'],
            'name_sponsorship_qr_text' => ['de' => 'Betrag und Verwendungszweck sind im QR-Code bereits ausgefüllt.', 'en' => 'Amount and reference are already filled in the QR code.', 'bs' => 'Iznos i svrha uplate su već popunjeni u QR kodu.'],
            'name_sponsorship_qr_alt' => ['de' => 'QR-Code für Namenspatenschaft', 'en' => 'QR code for name sponsorship', 'bs' => 'QR kod za pokroviteljstvo nad imenom'],
            'name_sponsorship_reference_label' => ['de' => 'Verwendungszweck', 'en' => 'Payment reference', 'bs' => 'Svrha uplate'],
            'name_sponsorship_name_label' => ['de' => 'Dein Namensvorschlag (optional)', 'en' => 'Your name suggestion (optional)', 'bs' => 'Vaš prijedlog imena (opcionalno)'],
            'name_sponsorship_name_placeholder' => ['de' => 'z. B. Luna', 'en' => 'e.g. Luna', 'bs' => 'npr. Luna'],
            'empty_adoption_title' => ['de' => 'Die Vermittlungsliste wird gerade sorgfältig gepflegt', 'en' => 'The adoption list is being carefully updated right now', 'bs' => 'Lista za udomljavanje se trenutno pažljivo ažurira'],
            'empty_adoption_text' => [
                'de' => 'Wir veröffentlichen Hunde erst, wenn Foto, Charakter und die wichtigsten Angaben zuverlässig geprüft sind. So bleibt die Vermittlung ehrlich und niemand entscheidet auf Basis unsicherer Daten.',
                'en' => 'We only publish dogs once photo, character and the key details have been reliably checked. This keeps adoption honest, so no one decides based on unreliable information.',
                'bs' => 'Pse objavljujemo tek kada su fotografija, karakter i najvažniji podaci pouzdano provjereni. Tako udomljavanje ostaje iskreno i niko ne odlučuje na osnovu nesigurnih podataka.',
            ],
            'empty_adoption_primary' => ['de' => 'Kontakt aufnehmen', 'en' => 'Get in touch', 'bs' => 'Kontaktirajte nas'],
            'empty_adoption_secondary' => ['de' => 'Patenschaft ansehen', 'en' => 'See sponsorships', 'bs' => 'Pogledaj pokroviteljstva'],
            'empty_sponsorship_title' => ['de' => 'Patenschaften werden gerade geprüft', 'en' => 'Sponsorships are currently being reviewed', 'bs' => 'Pokroviteljstva se trenutno provjeravaju'],
            'empty_sponsorship_text' => [
                'de' => 'Wir zeigen hier nur Hunde, deren Daten und Fotos zuverlässig gepflegt sind. Du kannst trotzdem helfen: mit Futter, Sachspenden oder einer allgemeinen Patenschaftsanfrage.',
                'en' => 'We only show dogs here whose data and photos are reliably maintained. You can still help: with food, in-kind donations, or a general sponsorship inquiry.',
                'bs' => 'Ovdje prikazujemo samo pse čiji su podaci i fotografije pouzdano ažurirani. Ipak možete pomoći: hranom, donacijama u naturi ili općim upitom za pokroviteljstvo.',
            ],
            'empty_sponsorship_primary' => ['de' => 'Patenschaft anfragen', 'en' => 'Request a sponsorship', 'bs' => 'Zatraži pokroviteljstvo'],
            'empty_sponsorship_secondary' => ['de' => 'Spendenmöglichkeiten ansehen', 'en' => 'See donation options', 'bs' => 'Pogledaj mogućnosti donacije'],
        ],
    ];
}

function sod_translations_datenschutz(): array
{
    return [
        'datenschutz' => [
            'eyebrow' => ['de' => 'Rechtliches', 'en' => 'Legal', 'bs' => 'Pravno'],
            'title' => ['de' => 'Datenschutzerklärung', 'en' => 'Privacy policy', 'bs' => 'Pravila o zaštiti podataka'],
            'placeholder_title' => ['de' => 'Eigene Datenschutzerklärung erforderlich', 'en' => 'Your own privacy policy is required', 'bs' => 'Potrebna su vlastita pravila o zaštiti podataka'],
            'placeholder_text' => [
                'de' => 'Eine Datenschutzerklärung beschreibt die konkrete Datenverarbeitung einer bestimmten Organisation und kann deshalb nicht von einer anderen übernommen werden. Dieses Theme wird bewusst ohne Datenschutztext ausgeliefert. Erstelle einen eigenen Text, der mindestens abdeckt: Verantwortlicher, verarbeitete Daten (Kontakt- und Patenschaftsformulare, Mitgliederkonten, Zahlungsdienstleister), Rechtsgrundlagen, Speicherdauern, Empfänger, eingesetzte Drittanbieter sowie die Betroffenenrechte.',
                'en' => 'A privacy policy describes the specific data processing of one particular organisation and therefore cannot be reused by another. This theme deliberately ships without privacy text. Write your own, covering at least: the controller, the data processed (contact and sponsorship forms, member accounts, payment providers), legal bases, retention periods, recipients, third-party services used, and data subject rights.',
                'bs' => 'Pravila o zaštiti podataka opisuju konkretnu obradu podataka određene organizacije i stoga ih druga organizacija ne može preuzeti. Ova tema se namjerno isporučuje bez tog teksta. Napišite vlastiti koji pokriva najmanje: voditelja obrade, obrađene podatke (kontakt i obrasci za pokroviteljstvo, članski računi, pružatelji plaćanja), pravne osnove, rokove čuvanja, primatelje, korištene treće strane i prava ispitanika.',
            ],
        ],
    ];
}

function sod_translations_tierheim_bau(): array
{
    return [
        'tierheim_bau' => [
            'eyebrow' => ['de' => 'Projekt', 'en' => 'Project', 'bs' => 'Projekat'],
            'title' => ['de' => 'Baufortschritt', 'en' => 'Construction progress', 'bs' => 'Napredak izgradnje'],
            'lead' => [
                'de' => 'Dokumentiere hier den Fortschritt eines Bauprojekts. Fotos pflegst du im Plugin unter „Einstellungen → Baufortschritt“.',
                'en' => 'Document the progress of a construction project here. You manage photos in the plugin under “Settings → Construction progress”.',
                'bs' => 'Ovdje dokumentirajte napredak građevinskog projekta. Fotografijama upravljate u dodatku pod „Postavke → Napredak izgradnje“.',
            ],
            'popup_title' => ['de' => 'Aktueller Baufortschritt', 'en' => 'Current construction progress', 'bs' => 'Trenutni napredak izgradnje'],
            'empty' => ['de' => 'Noch keine Fotos hinterlegt.', 'en' => 'No photos added yet.', 'bs' => 'Još nema dodanih fotografija.'],
        ],
    ];
}

/**
 * Neue Handy-Ansicht (30.09.2026): Leiste unten und Schnellstart.
 */
function sod_translations_mobile(): array
{
    return [
        'mobile' => [
            'nav_label' => ['de' => 'Hauptnavigation', 'en' => 'Main navigation', 'bs' => 'Glavna navigacija'],
            'help_label' => ['de' => 'Jetzt helfen', 'en' => 'Help now', 'bs' => 'Pomozi sada'],
            'tab_home' => ['de' => 'Start', 'en' => 'Home', 'bs' => 'Početna'],
            'tab_stories' => ['de' => 'Schicksale', 'en' => 'Stories', 'bs' => 'Sudbine'],
            'tab_adopt' => ['de' => 'Adoptieren', 'en' => 'Adopt', 'bs' => 'Udomi'],
            'tab_sponsor' => ['de' => 'Pate werden', 'en' => 'Sponsor', 'bs' => 'Pokrovitelj'],
            'tab_donate' => ['de' => 'Spenden', 'en' => 'Donate', 'bs' => 'Doniraj'],
            'sponsor' => ['de' => 'Pate werden', 'en' => 'Become a sponsor', 'bs' => 'Postani pokrovitelj'],
            'sponsor_sub' => ['de' => 'ab 5 € im Monat', 'en' => 'from €5 a month', 'bs' => 'od 5 € mjesečno'],
            'donate' => ['de' => 'Spenden', 'en' => 'Donate', 'bs' => 'Doniraj'],
            'donate_once' => ['de' => 'Einmal spenden', 'en' => 'Donate once', 'bs' => 'Doniraj jednom'],
            'donate_sub' => ['de' => 'jeder Betrag hilft', 'en' => 'every amount helps', 'bs' => 'svaki iznos pomaže'],
            'need_sponsors' => ['de' => 'Sie suchen Paten', 'en' => 'They need sponsors', 'bs' => 'Traže pokrovitelje'],
            'see_all' => ['de' => 'Alle ansehen', 'en' => 'See all', 'bs' => 'Pogledaj sve'],
            'open_amount' => ['de' => 'noch %d € offen', 'en' => '€%d still needed', 'bs' => 'još %d € nedostaje'],
            'funded' => ['de' => 'versorgt', 'en' => 'covered', 'bs' => 'zbrinut'],
        ],
    ];
}

/**
 * Hero-Bereich der Startseite mit wechselnden Tieren.
 */
function sod_translations_heronew(): array
{
    return [
        'heronew' => [
            'preview_badge' => ['de' => 'Vorschau – nur für Admins sichtbar', 'en' => 'Preview – visible to admins only', 'bs' => 'Pregled – vidljivo samo administratorima'],
            'eyebrow' => ['de' => '{org}', 'en' => '{org}', 'bs' => '{org}'],
            'line1' => ['de' => 'Gerettet.', 'en' => 'Rescued.', 'bs' => 'Spašeni.'],
            'line2' => ['de' => 'Aber noch nicht zuhause.', 'en' => 'But not home yet.', 'bs' => 'Ali još nisu kod kuće.'],
            'line3' => ['de' => 'Bleib an ihrer Seite.', 'en' => 'Stay by their side.', 'bs' => 'Ostani uz njih.'],
            'lead' => [
                'de' => 'Futter, Tierarzt, ein warmer Platz – jeden Monat aufs Neue. Mit deiner Patenschaft begleitest du einen Hund, bis er ein Zuhause findet.',
                'en' => 'Food, vet care, a warm place – every single month. With your sponsorship you stand by a dog until it finds a home.',
                'bs' => 'Hrana, veterinar, toplo mjesto – iz mjeseca u mjesec. Svojim pokroviteljstvom pratiš jednog psa dok ne pronađe dom.',
            ],
            'cta_sponsor' => ['de' => 'Pate werden ab 5 €', 'en' => 'Sponsor from €5', 'bs' => 'Postani pokrovitelj od 5 €'],
            'cta_donate' => ['de' => 'Einmalig spenden', 'en' => 'One-time donation', 'bs' => 'Jednokratna donacija'],
            'trust' => ['de' => 'Monatlich helfen · jederzeit kündbar · einem Hund verbunden', 'en' => 'Help monthly · cancel anytime · connected to one dog', 'bs' => 'Pomaži mjesečno · otkaži bilo kada · povezan s jednim psom'],
            'card_title' => ['de' => 'Das ist %s.', 'en' => 'This is %s.', 'bs' => 'Ovo je %s.'],
            'card_meta' => ['de' => '%1$s von %2$s € im Monat gesichert', 'en' => '€%1$s of €%2$s a month secured', 'bs' => '%1$s od %2$s € mjesečno osigurano'],
            'card_link' => ['de' => 'Kennenlernen', 'en' => 'Meet', 'bs' => 'Upoznaj'],
        ],
    ];
}

/**
 * Texte fuer den Hilfe-Button unten rechts (FAQ zum Bezahlen einer Patenschaft
 * plus Formular fuer technische Probleme). Wird vom Plugin ueber self::t()
 * gelesen - fehlt ein Schluessel hier, zeigt die Seite den rohen Schluesselnamen.
 */
function sod_translations_rescue(): array
{
    return [
        'rescue' => [
            'preview' => ['de' => 'Vorschau – nur Admins', 'en' => 'Preview – admins only', 'bs' => 'Pregled – samo admini'],
            'kicker' => ['de' => 'Unsere Mission – Stand heute', 'en' => 'Our mission – as of today', 'bs' => 'Naša misija – stanje danas'],
            'alltime_unit' => ['de' => 'Hunde', 'en' => 'dogs', 'bs' => 'pasa'],
            'alltime_text' => ['de' => 'haben wir schon gerettet.', 'en' => 'rescued so far.', 'bs' => 'smo već spasili.'],
            'current_tpl' => ['de' => 'Gerade beschützen wir %1$s Hunde – %2$s davon warten noch auf Paten.', 'en' => 'Right now we protect %1$s dogs – %2$s of them are still waiting for sponsors.', 'bs' => 'Trenutno štitimo %1$s pasa – %2$s od njih još čeka pokrovitelje.'],
            'current_all_tpl' => ['de' => 'Gerade beschützen wir %1$s Hunde – und alle sind versorgt. Danke!', 'en' => 'Right now we protect %1$s dogs – and all of them are cared for. Thank you!', 'bs' => 'Trenutno štitimo %1$s pasa – i svi su zbrinuti. Hvala!'],
            'paws_label' => ['de' => '%1$d Hunde versorgt, %2$d warten noch auf Paten', 'en' => '%1$d dogs cared for, %2$d still waiting for sponsors', 'bs' => '%1$d pasa zbrinuto, %2$d još čeka pokrovitelje'],
            'legend_safe' => ['de' => 'versorgt – volle Patenschaft', 'en' => 'cared for – full sponsorship', 'bs' => 'zbrinut – puno pokroviteljstvo'],
            'legend_waiting' => ['de' => 'wartet noch auf Paten', 'en' => 'still waiting for sponsors', 'bs' => 'još čeka pokrovitelje'],
            'cta_tpl' => ['de' => 'Einem der %d helfen →', 'en' => 'Help one of the %d →', 'bs' => 'Pomozi jednom od %d →'],
            'aria_tpl' => ['de' => '%1$d Hunde gerettet, %2$d werden gerade beschützt, %3$d warten noch auf Paten', 'en' => '%1$d dogs rescued, %2$d currently protected, %3$d still waiting for sponsors', 'bs' => '%1$d pasa spašeno, %2$d trenutno zaštićeno, %3$d još čeka pokrovitelje'],
            'note' => ['de' => 'Jede Pfote ist ein Hund, den wir gerade beschützen. Zählt automatisch mit.', 'en' => 'Each paw is a dog we are protecting right now. Updated automatically.', 'bs' => 'Svaka šapa je pas kojeg trenutno štitimo. Automatski se ažurira.'],
        ],
    ];
}

function sod_translations_stories(): array
{
    return [
        'stories' => [
            'eyebrow' => ['de' => 'Schicksale', 'en' => 'Their stories', 'bs' => 'Sudbine'],
            'title' => ['de' => 'Jeder Hund hat eine Geschichte.', 'en' => 'Every dog has a story.', 'bs' => 'Svaki pas ima svoju priču.'],
            'lead' => ['de' => 'Wir zeigen euch in Videos, wie wir sie gefunden haben, was sie erlebt haben – und wie es weitergeht. Manche Geschichten haben ihr Happy End schon. Andere schreibst du mit.', 'en' => 'In videos we show how we found them, what they went through – and what happens next. Some stories already have their happy ending. Others you help to write.', 'bs' => 'U videima vam pokazujemo kako smo ih pronašli, šta su proživjeli – i šta slijedi. Neke priče već imaju sretan kraj. Druge pišeš ti s nama.'],
            'tab_need' => ['de' => 'Braucht dich jetzt', 'en' => 'Needs you now', 'bs' => 'Treba te sada'],
            'tab_happy' => ['de' => 'Happy Ends', 'en' => 'Happy endings', 'bs' => 'Sretni krajevi'],
            'empty_need' => ['de' => 'Gerade sind alle Geschichten glücklich ausgegangen.', 'en' => 'Right now all stories have a happy ending.', 'bs' => 'Trenutno su sve priče sretno završile.'],
            'empty_happy' => ['de' => 'Noch kein Happy End – sobald ein Hund vermittelt ist, erscheint seine Geschichte hier.', 'en' => 'No happy ending yet – as soon as a dog is adopted, its story appears here.', 'bs' => 'Još nema sretnog kraja – čim pas bude udomljen, njegova priča će se pojaviti ovdje.'],
            'badge_sponsor' => ['de' => 'Braucht Paten', 'en' => 'Needs sponsors', 'bs' => 'Treba pokrovitelje'],
            'badge_home' => ['de' => 'Sucht ein Zuhause', 'en' => 'Looking for a home', 'bs' => 'Traži dom'],
            'badge_happy' => ['de' => 'Happy End', 'en' => 'Happy ending', 'bs' => 'Sretan kraj'],
            'video_count' => ['de' => '%d Videos', 'en' => '%d videos', 'bs' => '%d videa'],
            'extra_tierheim' => ['de' => 'Tierheim', 'en' => 'Shelter', 'bs' => 'Sklonište'],
            'extra_improvements' => ['de' => 'Technische Verbesserungen', 'en' => 'Technical improvements', 'bs' => 'Tehnička poboljšanja'],
            'more_title' => ['de' => 'Weitere Schicksale', 'en' => 'More stories', 'bs' => 'Još sudbina'],
            'schicksal_link' => ['de' => 'Schicksal ansehen', 'en' => 'Read the story', 'bs' => 'Pogledaj sudbinu'],
            'video_count_one' => ['de' => '%d Video', 'en' => '%d video', 'bs' => '%d video'],
            'chapter_count' => ['de' => '%d Kapitel', 'en' => '%d chapters', 'bs' => '%d poglavlja'],
            'read' => ['de' => 'Geschichte ansehen', 'en' => 'Read the story', 'bs' => 'Pogledaj priču'],
            'sponsor_short' => ['de' => 'Pate werden', 'en' => 'Sponsor', 'bs' => 'Postani pokrovitelj'],
            'sponsor' => ['de' => 'Pate von %s werden', 'en' => 'Sponsor %s', 'bs' => 'Postani pokrovitelj za %s'],
            'adopt' => ['de' => '%s kennenlernen', 'en' => 'Meet %s', 'bs' => 'Upoznaj: %s'],
            'monthly' => ['de' => 'Monatsversorgung', 'en' => 'Monthly care', 'bs' => 'Mjesečna njega'],
            'covered' => ['de' => 'Voll versorgt – danke an alle Paten!', 'en' => 'Fully covered – thank you to all sponsors!', 'bs' => 'Potpuno pokriveno – hvala svim pokroviteljima!'],
            'remaining' => ['de' => 'Noch %s € im Monat offen', 'en' => '€%s per month still needed', 'bs' => 'Još %s € mjesečno nedostaje'],
            'back' => ['de' => 'Alle Geschichten', 'en' => 'All stories', 'bs' => 'Sve priče'],
            'told_by' => ['de' => 'Schicksale · erzählt von {org}', 'en' => 'Their stories · told by {org}', 'bs' => 'Sudbine · priča {org}'],
            'since' => ['de' => 'seit %s', 'en' => 'since %s', 'bs' => 'od %s'],
            'tldr' => ['de' => 'Das Wichtigste in 20 Sekunden', 'en' => 'The story in 20 seconds', 'bs' => 'Najvažnije za 20 sekundi'],
            'chapter_from' => ['de' => 'Kapitel %1$d · ab %2$s', 'en' => 'Chapter %1$d · from %2$s', 'bs' => 'Poglavlje %1$d · od %2$s'],
            'chapter_n' => ['de' => 'Kapitel %d', 'en' => 'Chapter %d', 'bs' => 'Poglavlje %d'],
            'chapter_now' => ['de' => 'Kapitel %d · Jetzt', 'en' => 'Chapter %d · Now', 'bs' => 'Poglavlje %d · Sada'],
            'quote_by' => ['de' => 'Das Team von {org}', 'en' => 'The {org} team', 'bs' => 'Tim {org}'],
            'quote_by_short' => ['de' => '— {org}', 'en' => '— {org}', 'bs' => '— {org}'],
            'mid_sponsor' => ['de' => '%s braucht Menschen wie dich. Schon 5 € im Monat helfen.', 'en' => '%s needs people like you. Even €5 a month helps.', 'bs' => '%s treba ljude poput tebe. Već 5 € mjesečno pomaže.'],
            'mid_others' => ['de' => 'Diese Rettung war nur möglich, weil Menschen wie du geholfen haben. Andere Hunde warten noch auf diese Chance.', 'en' => 'This rescue was only possible because people like you helped. Other dogs are still waiting for this chance.', 'bs' => 'Ovo spašavanje bilo je moguće samo zato što su ljudi poput tebe pomogli. Drugi psi još čekaju tu priliku.'],
            'others_btn' => ['de' => 'Hunde, die noch Hilfe brauchen', 'en' => 'Dogs who still need help', 'bs' => 'Psi kojima još treba pomoć'],
            'now_sponsor_title' => ['de' => '%s wartet – auf dich', 'en' => '%s is waiting – for you', 'bs' => '%s čeka – tebe'],
            'now_sponsor_text' => ['de' => 'Dieses Kapitel ist noch nicht fertig geschrieben. Mit einer Patenschaft sicherst du Futter, Tierarzt und Unterbringung für %s – und schreibst die Geschichte mit.', 'en' => 'This chapter has not been written yet. With a sponsorship you secure food, vet care and shelter for %s – and help write the story.', 'bs' => 'Ovo poglavlje još nije napisano. Pokroviteljstvom osiguravaš hranu, veterinara i smještaj za %s – i pišeš priču sa nama.'],
            'now_home_title' => ['de' => 'Das letzte Kapitel fehlt noch: ein Zuhause', 'en' => 'The last chapter is still missing: a home', 'bs' => 'Posljednje poglavlje još nedostaje: dom'],
            'now_home_text' => ['de' => 'Die Versorgung von %s ist dank der Paten gesichert. Was jetzt noch fehlt, sind Menschen, die für immer bleiben.', 'en' => 'Thanks to sponsors, care for %s is covered. What is still missing are people who stay forever.', 'bs' => 'Zahvaljujući pokroviteljima njega za %s je osigurana. Još nedostaju ljudi koji će ostati zauvijek.'],
            'now_happy_title' => ['de' => '%s ist angekommen', 'en' => '%s has arrived home', 'bs' => '%s je stigao/la kući'],
            'now_happy_text' => ['de' => '%s hat ein Zuhause gefunden. Danke an alle, die diesen Weg möglich gemacht haben.', 'en' => '%s has found a home. Thank you to everyone who made this journey possible.', 'bs' => '%s je pronašao/la dom. Hvala svima koji su omogućili ovaj put.'],
            'side_sponsor_text' => ['de' => 'Monatlich, jederzeit kündbar. Endet automatisch, wenn der Hund ein Zuhause findet.', 'en' => 'Monthly, cancel any time. Ends automatically when the dog finds a home.', 'bs' => 'Mjesečno, otkaz u bilo kojem trenutku. Automatski prestaje kada pas nađe dom.'],
            'side_sponsor_btn' => ['de' => 'Weiter zur Patenschaft', 'en' => 'Continue to sponsorship', 'bs' => 'Dalje na pokroviteljstvo'],
            'side_covered_title' => ['de' => '%s ist versorgt 💛', 'en' => '%s is covered 💛', 'bs' => '%s je zbrinut/a 💛'],
            'side_others_title_happy' => ['de' => 'Diese Hunde brauchen dich noch', 'en' => 'These dogs still need you', 'bs' => 'Ovim psima si još potreban/na'],
            'side_others_text' => ['de' => 'Diese Hunde brauchen noch Paten:', 'en' => 'These dogs still need sponsors:', 'bs' => 'Ovim psima još trebaju pokrovitelji:'],
            'playlist_title' => ['de' => 'Videos von %s', 'en' => 'Videos of %s', 'bs' => 'Videa: %s'],
            'playlist_text' => ['de' => '%d Videos auf YouTube. Neue Videos erscheinen automatisch in der Geschichte.', 'en' => '%d videos on YouTube. New videos appear in the story automatically.', 'bs' => '%d videa na YouTubeu. Nova videa se automatski pojavljuju u priči.'],
            'playlist_btn' => ['de' => 'Auf YouTube ansehen', 'en' => 'Watch on YouTube', 'bs' => 'Gledaj na YouTubeu'],
            'share_title' => ['de' => 'Geschichte von %s teilen', 'en' => 'Share %s’s story', 'bs' => 'Podijeli priču: %s'],
            'share_text' => ['de' => 'Jede geteilte Geschichte kann einen neuen Paten oder ein Zuhause finden.', 'en' => 'Every shared story can find a new sponsor or a home.', 'bs' => 'Svaka podijeljena priča može pronaći novog pokrovitelja ili dom.'],
            'copy' => ['de' => 'Link kopieren', 'en' => 'Copy link', 'bs' => 'Kopiraj link'],
            'copied' => ['de' => 'Kopiert ✓', 'en' => 'Copied ✓', 'bs' => 'Kopirano ✓'],
            'play_aria' => ['de' => 'Video abspielen: %s', 'en' => 'Play video: %s', 'bs' => 'Pusti video: %s'],
            'consent_text' => ['de' => 'Dieses Video kommt von YouTube. Mit deinem Klick erlaubst du externe Videos auf dieser Website; YouTube erhält dabei u. a. deine IP-Adresse. Mehr in der Datenschutzerklärung.', 'en' => 'This video comes from YouTube. By clicking, you allow external videos on this website; YouTube receives your IP address, among other data. More in the privacy policy.', 'bs' => 'Ovaj video dolazi sa YouTubea. Klikom dozvoljavaš eksterne videozapise na ovoj stranici; YouTube pritom prima i tvoju IP adresu. Više u pravilima privatnosti.'],
            'consent_btn' => ['de' => 'Externe Videos erlauben', 'en' => 'Allow external videos', 'bs' => 'Dozvoli eksterne videozapise'],
            'close' => ['de' => 'Schließen', 'en' => 'Close', 'bs' => 'Zatvori'],
            'chip_now' => ['de' => 'Jetzt', 'en' => 'Now', 'bs' => 'Sada'],
            'chip_happy' => ['de' => 'Angekommen', 'en' => 'Home', 'bs' => 'Stigao/la kući'],
            'teaser_btn' => ['de' => 'Geschichte von %s ansehen', 'en' => 'Watch %s’s story', 'bs' => 'Pogledaj priču: %s'],
            'teaser_all' => ['de' => 'Alle Schicksale', 'en' => 'All stories', 'bs' => 'Sve sudbine'],
            'cta_sponsor' => ['de' => 'Pate für %s werden', 'en' => 'Sponsor %s', 'bs' => 'Postani pokrovitelj za %s'],
            'cta_donate' => ['de' => 'Für die Hunde spenden', 'en' => 'Donate for the dogs', 'bs' => 'Doniraj za pse'],
        ],
    ];
}

function sod_translations_share(): array
{
    return [
        'share' => [
            'share' => ['de' => 'Teilen', 'en' => 'Share', 'bs' => 'Podijeli'],
            'share_dog' => ['de' => '%s teilen', 'en' => 'Share %s', 'bs' => 'Podijeli: %s'],
            'site_button' => ['de' => 'Seite teilen', 'en' => 'Share page', 'bs' => 'Podijeli stranicu'],
            'close' => ['de' => 'Schließen', 'en' => 'Close', 'bs' => 'Zatvori'],
            'copy' => ['de' => 'Link kopieren', 'en' => 'Copy link', 'bs' => 'Kopiraj link'],
            'copied' => ['de' => 'Kopiert ✓', 'en' => 'Copied ✓', 'bs' => 'Kopirano ✓'],
            'preview_badge' => ['de' => 'Vorschau – nur Team', 'en' => 'Preview – team only', 'bs' => 'Pregled – samo tim'],
            'box_text' => ['de' => 'Vielleicht sucht jemand in deinem Umfeld genau so einen Hund – oder möchte Pate werden. Jedes Teilen hilft.', 'en' => 'Maybe someone you know is looking for exactly this dog – or would like to become a sponsor. Every share helps.', 'bs' => 'Možda neko iz tvoje okoline traži baš ovakvog psa – ili želi postati pokrovitelj. Svako dijeljenje pomaže.'],
            'site_title' => ['de' => '{org} – Hilfe für Tiere in Not', 'en' => '{org} – help for animals in need', 'bs' => '{org} – pomoć životinjama u nevolji'],
            'site_text' => ['de' => 'Diese Tiere haben niemanden, der sie sucht, wenn sie nicht heimkommen. {org} gibt ihnen Futter, Tierarzt und ein sicheres Zuhause – jede Hilfe zählt 🧡', 'en' => 'These animals have no one looking for them when they do not come home. {org} gives them food, vet care and a safe home – every bit of help counts 🧡', 'bs' => 'Ove životinje nemaju nikoga ko ih traži kad se ne vrate kući. {org} im daje hranu, veterinara i siguran dom – svaka pomoć je važna 🧡'],
            // Hundetexte gibt es in maennlicher (_m), weiblicher (_f) und neutraler (_n) Form.
            // Welche verwendet wird, entscheidet das Feld "Geschlecht" im Hundeprofil.
            'card_text_m' => ['de' => 'Das ist %s. Er hat gelernt, dass niemand für ihn da ist – wir möchten, dass sich das ändert 🐾', 'en' => 'This is %s. He learned that no one is there for him – we want to change that 🐾', 'bs' => 'Ovo je %s. Naučio je da niko nije tu za njega – želimo to promijeniti 🐾'],
            'card_text_f' => ['de' => 'Das ist %s. Sie hat gelernt, dass niemand für sie da ist – wir möchten, dass sich das ändert 🐾', 'en' => 'This is %s. She learned that no one is there for her – we want to change that 🐾', 'bs' => 'Ovo je %s. Naučila je da niko nije tu za nju – želimo to promijeniti 🐾'],
            'card_text_n' => ['de' => 'Das ist %s. Bisher war niemand da – wir möchten, dass sich das ändert 🐾', 'en' => 'This is %s. So far no one was there – we want to change that 🐾', 'bs' => 'Ovo je %s. Do sada nije bilo nikoga – želimo to promijeniti 🐾'],
            'dog_title_home' => ['de' => '%s sucht ein Zuhause', 'en' => '%s is looking for a home', 'bs' => '%s traži dom'],
            'dog_text_home_m' => ['de' => '%s kennt die Straße, aber noch kein Zuhause. Er wartet darauf, dass jemand bleibt 🐾 Vielleicht kennst du wen?', 'en' => '%s knows the street, but not a home yet. He is waiting for someone to stay 🐾 Maybe you know someone?', 'bs' => '%s poznaje ulicu, ali još nema dom. Čeka nekoga ko će ostati 🐾 Možda poznaješ nekoga?'],
            'dog_text_home_f' => ['de' => '%s kennt die Straße, aber noch kein Zuhause. Sie wartet darauf, dass jemand bleibt 🐾 Vielleicht kennst du wen?', 'en' => '%s knows the street, but not a home yet. She is waiting for someone to stay 🐾 Maybe you know someone?', 'bs' => '%s poznaje ulicu, ali još nema dom. Čeka nekoga ko će ostati 🐾 Možda poznaješ nekoga?'],
            'dog_text_home_n' => ['de' => '%s kennt die Straße, aber noch kein Zuhause – und wartet darauf, dass jemand bleibt 🐾 Vielleicht kennst du wen?', 'en' => '%s knows the street, but not a home yet – still waiting for someone to stay 🐾 Maybe you know someone?', 'bs' => '%s poznaje ulicu, ali još nema dom – i čeka nekoga ko će ostati 🐾 Možda poznaješ nekoga?'],
            'dog_title_sponsor' => ['de' => '%s braucht noch Paten', 'en' => '%s still needs sponsors', 'bs' => '%s još treba pokrovitelje'],
            'dog_text_sponsor_m' => ['de' => 'Für %s ist noch niemand da. 5 € im Monat sind sein Futter, sein Tierarzt und sein Platz zum Schlafen 🧡', 'en' => 'There is still no one for %s. €5 a month means his food, his vet and his place to sleep 🧡', 'bs' => 'Za psa %s još nema nikoga. 5 € mjesečno je njegova hrana, njegov veterinar i njegovo mjesto za spavanje 🧡'],
            'dog_text_sponsor_f' => ['de' => 'Für %s ist noch niemand da. 5 € im Monat sind ihr Futter, ihr Tierarzt und ihr Platz zum Schlafen 🧡', 'en' => 'There is still no one for %s. €5 a month means her food, her vet and her place to sleep 🧡', 'bs' => 'Za %s još nema nikoga. 5 € mjesečno je njena hrana, njen veterinar i njeno mjesto za spavanje 🧡'],
            'dog_text_sponsor_n' => ['de' => 'Für %s ist noch niemand da. 5 € im Monat bedeuten Futter, Tierarzt und einen Platz zum Schlafen 🧡', 'en' => 'There is still no one for %s. €5 a month means food, vet care and a place to sleep 🧡', 'bs' => 'Za %s još nema nikoga. 5 € mjesečno znači hrana, veterinar i mjesto za spavanje 🧡'],
            'dog_title_care' => ['de' => '%s wird gerade versorgt', 'en' => '%s is being cared for', 'bs' => 'Brinemo o psu %s'],
            'dog_text_care_m' => ['de' => '%s wird gerade bei uns versorgt und ist noch nicht auf Zuhause-Suche. Jede Hilfe kommt direkt bei ihm an 🧡', 'en' => '%s is being cared for by us and is not looking for a home yet. Every bit of help reaches him directly 🧡', 'bs' => 'Trenutno brinemo o psu %s i još ne traži dom. Svaka pomoć stiže direktno do njega 🧡'],
            'dog_text_care_f' => ['de' => '%s wird gerade bei uns versorgt und ist noch nicht auf Zuhause-Suche. Jede Hilfe kommt direkt bei ihr an 🧡', 'en' => '%s is being cared for by us and is not looking for a home yet. Every bit of help reaches her directly 🧡', 'bs' => 'Trenutno brinemo o %s i još ne traži dom. Svaka pomoć stiže direktno do nje 🧡'],
            'dog_text_care_n' => ['de' => '%s wird gerade bei uns versorgt und ist noch nicht auf Zuhause-Suche. Jede Hilfe kommt direkt an 🧡', 'en' => '%s is being cared for by us and is not looking for a home yet. Every bit of help arrives directly 🧡', 'bs' => 'Trenutno brinemo o %s i još ne traži dom. Svaka pomoć stiže direktno 🧡'],
            'dog_cta_care_m' => ['de' => 'Jetzt zählt vor allem, dass er wieder gesund wird 🧡', 'en' => 'What matters now is that he gets well again 🧡', 'bs' => 'Sada je najvažnije da ponovo ozdravi 🧡'],
            'dog_cta_care_f' => ['de' => 'Jetzt zählt vor allem, dass sie wieder gesund wird 🧡', 'en' => 'What matters now is that she gets well again 🧡', 'bs' => 'Sada je najvažnije da ponovo ozdravi 🧡'],
            'dog_cta_care_n' => ['de' => 'Jetzt zählt vor allem die Genesung 🧡', 'en' => 'What matters now is getting well again 🧡', 'bs' => 'Sada je najvažniji oporavak 🧡'],
            'dog_title_happy_m' => ['de' => '%s hat ein Zuhause gefunden', 'en' => '%s has found a home', 'bs' => '%s je pronašao dom'],
            'dog_title_happy_f' => ['de' => '%s hat ein Zuhause gefunden', 'en' => '%s has found a home', 'bs' => '%s je pronašla dom'],
            'dog_title_happy_n' => ['de' => '%s hat ein Zuhause gefunden', 'en' => '%s has found a home', 'bs' => '%s ima dom'],
            'dog_text_happy_m' => ['de' => '%s hat es geschafft: von der Straße in ein eigenes Zuhause 🧡 So viele andere warten noch auf diesen Moment:', 'en' => '%s made it: from the street to a home of his own 🧡 So many others are still waiting for that moment:', 'bs' => '%s je uspio: sa ulice u vlastiti dom 🧡 Mnogi drugi još čekaju taj trenutak:'],
            'dog_text_happy_f' => ['de' => '%s hat es geschafft: von der Straße in ein eigenes Zuhause 🧡 So viele andere warten noch auf diesen Moment:', 'en' => '%s made it: from the street to a home of her own 🧡 So many others are still waiting for that moment:', 'bs' => '%s je uspjela: sa ulice u vlastiti dom 🧡 Mnogi drugi još čekaju taj trenutak:'],
            'dog_text_happy_n' => ['de' => '%s hat es geschafft: von der Straße in ein eigenes Zuhause 🧡 So viele andere warten noch auf diesen Moment:', 'en' => '%s made it: from the street to a home 🧡 So many others are still waiting for that moment:', 'bs' => '%s je uspio/la: sa ulice u vlastiti dom 🧡 Mnogi drugi još čekaju taj trenutak:'],
            // Kurzer Zusatz, wenn der Text mit einem Ausschnitt aus der Geschichte beginnt.
            'dog_cta_home_m' => ['de' => 'Jetzt wartet er noch auf ein Zuhause 🐾', 'en' => 'Now he is still waiting for a home 🐾', 'bs' => 'Sada još čeka svoj dom 🐾'],
            'dog_cta_home_f' => ['de' => 'Jetzt wartet sie noch auf ein Zuhause 🐾', 'en' => 'Now she is still waiting for a home 🐾', 'bs' => 'Sada još čeka svoj dom 🐾'],
            'dog_cta_home_n' => ['de' => 'Jetzt fehlt nur noch ein Zuhause 🐾', 'en' => 'Now only a home is missing 🐾', 'bs' => 'Sada nedostaje samo dom 🐾'],
            'dog_cta_sponsor_m' => ['de' => 'Jetzt fehlt ihm noch ein Pate – schon 5 € im Monat helfen 🧡', 'en' => 'Now he still needs a sponsor – even €5 a month helps 🧡', 'bs' => 'Sada mu još treba pokrovitelj – već 5 € mjesečno pomaže 🧡'],
            'dog_cta_sponsor_f' => ['de' => 'Jetzt fehlt ihr noch ein Pate – schon 5 € im Monat helfen 🧡', 'en' => 'Now she still needs a sponsor – even €5 a month helps 🧡', 'bs' => 'Sada joj još treba pokrovitelj – već 5 € mjesečno pomaže 🧡'],
            'dog_cta_sponsor_n' => ['de' => 'Jetzt fehlt noch ein Pate – schon 5 € im Monat helfen 🧡', 'en' => 'Now a sponsor is still missing – even €5 a month helps 🧡', 'bs' => 'Sada još nedostaje pokrovitelj – već 5 € mjesečno pomaže 🧡'],
            'dog_cta_happy_m' => ['de' => 'Heute hat er ein Zuhause 🧡 So viele andere warten noch:', 'en' => 'Today he has a home 🧡 So many others are still waiting:', 'bs' => 'Danas ima svoj dom 🧡 Mnogi drugi još čekaju:'],
            'dog_cta_happy_f' => ['de' => 'Heute hat sie ein Zuhause 🧡 So viele andere warten noch:', 'en' => 'Today she has a home 🧡 So many others are still waiting:', 'bs' => 'Danas ima svoj dom 🧡 Mnogi drugi još čekaju:'],
            'dog_cta_happy_n' => ['de' => 'Heute ist das geschafft 🧡 So viele andere warten noch:', 'en' => 'Today that is done 🧡 So many others are still waiting:', 'bs' => 'Danas je to uspjelo 🧡 Mnogi drugi još čekaju:'],
            'ig_hint' => ['de' => 'Text und Link sind kopiert. Öffne Instagram und füge sie in deine Story oder Nachricht ein.', 'en' => 'Text and link copied. Open Instagram and paste them into your story or message.', 'bs' => 'Tekst i link su kopirani. Otvori Instagram i zalijepi ih u priču ili poruku.'],
            'tt_hint' => ['de' => 'Text und Link sind kopiert. Öffne TikTok und füge sie in deine Nachricht oder Beschreibung ein.', 'en' => 'Text and link copied. Open TikTok and paste them into your message or caption.', 'bs' => 'Tekst i link su kopirani. Otvori TikTok i zalijepi ih u poruku ili opis.'],
        ],
    ];
}

/**
 * Ergaenzungen fuer Hero (Spendenkreis) und Hundekarten.
 */
function sod_translations_modules_extra(): array
{
    return [
        'home' => [
            'teaming_label' => ['de' => 'Mit einem kleinen Monatsbeitrag helfen', 'en' => 'Help with a small monthly amount', 'bs' => 'Pomozite malim mjesečnim iznosom'],
            'teaming_amount' => ['de' => '1 €', 'en' => '€1', 'bs' => '1 €'],
            'teaming_sub' => ['de' => 'im Monat', 'en' => 'per month', 'bs' => 'mjesečno'],
            'teaming_members_singular' => ['de' => 'Mitglied', 'en' => 'member', 'bs' => 'član'],
            'teaming_members_plural' => ['de' => 'Mitglieder', 'en' => 'members', 'bs' => 'članova'],
            'teaming_members_note' => ['de' => 'unterstützen uns schon', 'en' => 'already support us', 'bs' => 'nas već podržava'],
            'teaming_ring' => ['de' => 'SCHON AB 1 € IM MONAT · ', 'en' => 'FROM €1 A MONTH · ', 'bs' => 'OD 1 € MJESEČNO · '],
        ],
        'dogcard' => [
            'schicksal' => ['de' => 'Schicksal', 'en' => 'Their story', 'bs' => 'Sudbina'],
        ],
    ];
}
