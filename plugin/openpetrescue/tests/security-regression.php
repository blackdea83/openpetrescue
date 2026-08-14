<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);

$GLOBALS['sod_test_meta'] = [];
$GLOBALS['sod_test_options'] = [];
$GLOBALS['sod_test_user_meta'] = [];

function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function register_activation_hook(...$args): void {}
function register_deactivation_hook(...$args): void {}
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?: ''; }
function sanitize_file_name(string $value): string { return basename(preg_replace('/[^A-Za-z0-9._\-]/', '-', $value) ?: ''); }
function absint(mixed $value): int { return abs((int)$value); }
function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function home_url(string $path = ''): string { return 'https://example.test' . ($path !== '' ? '/' . ltrim($path, '/') : ''); }
function get_page_by_path(string $slug): null { return null; }
function get_posts(array $args = []): array { return []; }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_url(string $value): string { return $value; }
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function get_theme_file_uri(string $path = ''): string { return 'https://example.test/wp-content/themes/openpetrescue/' . ltrim($path, '/'); }
function wp_create_nonce(string $action): string { return 'fresh-' . substr(hash('sha256', $action), 0, 12); }

function add_query_arg(array|string $key, mixed $value = null, ?string $url = null): string
{
    $args = is_array($key) ? $key : [$key => $value];
    $url = is_array($key) ? (string)$value : (string)$url;
    $parts = parse_url($url);
    parse_str((string)($parts['query'] ?? ''), $query);
    $query = array_merge($query, $args);
    return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'example.test')
        . ($parts['path'] ?? '') . '?' . http_build_query($query);
}

function remove_query_arg(array|string $keys, string $url): string
{
    $parts = parse_url($url);
    parse_str((string)($parts['query'] ?? ''), $query);
    foreach ((array)$keys as $key) {
        unset($query[$key]);
    }
    return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'example.test')
        . ($parts['path'] ?? '') . ($query ? '?' . http_build_query($query) : '');
}

function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['sod_test_options'][$key] ?? $default; }
function update_option(string $key, mixed $value, bool $autoload = true): void { $GLOBALS['sod_test_options'][$key] = $value; }
function get_post_type(int $post_id): string { return $GLOBALS['sod_test_post_types'][$post_id] ?? 'sod_sponsor'; }
function get_edit_post_link(int $post_id, string $context = ''): string { return 'https://example.test/wp-admin/post.php?post=' . $post_id . '&action=edit'; }
function date_i18n(string $format, ?int $timestamp = null): string { return gmdate($format, $timestamp ?? time()); }
function sanitize_email(string $value): string { return trim($value); }
function wp_clear_scheduled_hook(string $hook, array $args = []): void {}

function get_post_meta(int $post_id, string $key, bool $single = false): mixed
{
    return $GLOBALS['sod_test_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, mixed $value): void
{
    $GLOBALS['sod_test_meta'][$post_id][$key] = $value;
}

function delete_post_meta(int $post_id, string $key): void
{
    unset($GLOBALS['sod_test_meta'][$post_id][$key]);
}

function get_user_meta(int $user_id, string $key, bool $single = false): mixed
{
    return $GLOBALS['sod_test_user_meta'][$user_id][$key] ?? '';
}

function update_user_meta(int $user_id, string $key, mixed $value): void
{
    $GLOBALS['sod_test_user_meta'][$user_id][$key] = $value;
}

function delete_user_meta(int $user_id, string $key): void
{
    unset($GLOBALS['sod_test_user_meta'][$user_id][$key]);
}

function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WP_Error;
}

final class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
}

final class WP_User
{
    public int $ID;
    public array $roles;
    public string $user_email;
    public string $display_name;

    public function __construct(int $id, array $roles, string $email = '', string $display_name = '')
    {
        $this->ID = $id;
        $this->roles = $roles;
        $this->user_email = $email;
        $this->display_name = $display_name;
    }
}

final class WP_Query
{
    public array $posts = [];

    public function __construct(array $args)
    {
        foreach ($GLOBALS['sod_test_meta'] as $post_id => $meta) {
            if (($meta[$args['meta_key']] ?? null) === ($args['meta_value'] ?? null)) {
                $this->posts[] = $post_id;
                break;
            }
        }
    }

    public function have_posts(): bool
    {
        return $this->posts !== [];
    }
}

require dirname(__DIR__) . '/openpetrescue.php';

function invoke_private(string $method, mixed ...$args): mixed
{
    $reflection = new ReflectionMethod(SOD_Plugin::class, $method);
    return $reflection->invoke(null, ...$args);
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Seit Plugin 1.26.x starten Online-Patenschaften sofort vorlaeufig als 'aktiv'
// (24-Stunden-Zahlungsfrist); 'ausstehend' ist nur noch Altbestand.
$GLOBALS['sod_test_meta'][10] = [
    'sod_sponsor_status' => 'aktiv',
    'sod_sponsor_payment_method' => 'paypal',
    'sod_sponsor_interval' => 'monatlich',
    'sod_sponsor_paypal_token' => 'valid-token',
    'sod_sponsor_paypal_expected_amount' => '25.00',
    'sod_sponsor_paypal_expected_currency' => 'EUR',
    'sod_sponsor_paypal_expected_period' => '1 M',
    'sod_sponsor_notes' => '',
];

$valid_signup = [
    'custom' => 'valid-token',
    'subscr_id' => 'I-SAFE123',
    'amount3' => '25.00',
    'mc_currency' => 'EUR',
    'period3' => '1 M',
];
invoke_private('handle_paypal_subscription_ipn', 'subscr_signup', $valid_signup);
expect(($GLOBALS['sod_test_meta'][10]['sod_sponsor_payment_received'] ?? '0') !== '1', 'Abo-Anmeldung darf den Zahlungseingang noch nicht bestaetigen.');
expect($GLOBALS['sod_test_meta'][10]['sod_sponsor_paypal_subscr_id'] === 'I-SAFE123', 'Gueltiges Abo muss fuer die erste Zahlung gebunden werden.');

expect(invoke_private('paypal_subscription_signup_matches', 10, $valid_signup) === true, 'Gueltige PayPal-Konditionen muessen akzeptiert werden.');
expect(invoke_private('paypal_subscription_signup_matches', 10, array_merge($valid_signup, ['amount3' => '1.00'])) === false, 'Manipulierter Betrag muss abgelehnt werden.');
expect(invoke_private('paypal_subscription_signup_matches', 10, array_merge($valid_signup, ['mc_currency' => 'USD'])) === false, 'Falsche Waehrung muss abgelehnt werden.');
expect(invoke_private('paypal_subscription_signup_matches', 10, array_merge($valid_signup, ['period3' => '1 Y'])) === false, 'Falsches Intervall muss abgelehnt werden.');
expect(invoke_private('paypal_subscription_payment_matches', 10, '25.00', 'EUR') === true, 'Passende erste Zahlung muss akzeptiert werden.');
expect(invoke_private('paypal_subscription_payment_matches', 10, '25.001', 'EUR') === false, 'Betrag mit unzulaessiger Genauigkeit muss abgelehnt werden.');
expect(invoke_private('paypal_subscription_payment_matches', 10, '0.00', 'EUR') === false, 'Nullzahlung darf keine Patenschaft aktivieren.');

$base_url = 'https://example.test/wp-admin/admin-post.php?action=sod_contract_document&case_id=42&_wpnonce=stale';
$canonical = invoke_private('canonicalize_private_document_urls', $base_url);
expect(!str_contains($canonical, '_wpnonce='), 'Gespeicherte Dokumentlinks duerfen keine alternde Nonce enthalten.');
$fresh = invoke_private('refresh_private_document_urls', $canonical);
expect(str_contains($fresh, '_wpnonce=fresh-'), 'Angezeigte Dokumentlinks muessen eine frische Nonce erhalten.');
$external = invoke_private('refresh_private_document_urls', 'https://attacker.test/wp-admin/admin-post.php?action=sod_contract_document&case_id=42');
expect(!str_contains($external, '_wpnonce='), 'Nonces duerfen nie an fremde Hosts angehaengt werden.');

$consent = invoke_private('privacy_consent_html', 'Ich habe die Datenschutzerklärung gelesen.');
expect(str_contains($consent, '<a href="https://example.test/datenschutz/"'), 'Datenschutzerklaerung muss verlinkt sein.');
$consent_bs = invoke_private('privacy_consent_html', 'Pročitao/la sam pravila o zaštiti podataka.');
expect(str_contains($consent_bs, '>pravila o zaštiti podataka</a>'), 'Bosnischer Datenschutzhinweis muss in seiner Sprache verlinkt sein.');
expect(!str_contains($consent_bs, '>Datenschutzerklärung</a>'), 'Bosnischer Datenschutzhinweis darf keinen zusaetzlichen deutschen Link erhalten.');

$GLOBALS['sod_test_meta'][20] = [
    'sod_sponsor_public_consent' => '0',
    'sod_sponsor_photo_approved' => '1',
];
invoke_private('update_sponsor_retention', 20, 'beendet');
expect($GLOBALS['sod_test_meta'][20]['sod_sponsor_photo_approved'] === '0', 'Beendete Patenschaften duerfen nicht weiter oeffentlich angezeigt werden.');
expect(isset($GLOBALS['sod_test_meta'][20]['sod_sponsor_photo_delete_after']), 'Beendete Patenschaften muessen eine Fotoloeschfrist erhalten.');
$photo_delete_after = $GLOBALS['sod_test_meta'][20]['sod_sponsor_photo_delete_after'];
invoke_private('update_sponsor_retention', 20, 'aktiv');
expect(($GLOBALS['sod_test_meta'][20]['sod_sponsor_photo_delete_after'] ?? '') === $photo_delete_after, 'Eine aktive Patenschaft ohne Einwilligung darf die Fotoloeschfrist nicht aufheben.');
$GLOBALS['sod_test_meta'][20]['sod_sponsor_public_consent'] = '1';
invoke_private('update_sponsor_retention', 20, 'aktiv');
expect(!isset($GLOBALS['sod_test_meta'][20]['sod_sponsor_photo_delete_after']), 'Eine erneut dokumentierte Einwilligung darf eine offene Fotoloeschfrist aufheben.');

// PayPal meldet denselben Abo-Betrag je nach Meldung als amount3 oder mc_amount3 und den
// Zeitraum als "1 M" oder "1 month". Beides muss zum selben Ergebnis fuehren.
$GLOBALS['sod_test_meta'][40] = [
    'sod_sponsor_status' => 'aktiv',
    'sod_sponsor_payment_method' => 'paypal',
    'sod_sponsor_interval' => 'monatlich',
    'sod_sponsor_paypal_token' => 'tok-40',
    'sod_sponsor_paypal_expected_amount' => '5.00',
    'sod_sponsor_paypal_expected_currency' => 'EUR',
    'sod_sponsor_paypal_expected_period' => '1 M',
    'sod_sponsor_notes' => '',
];
expect(
    invoke_private('paypal_subscription_signup_matches', 40, ['mc_amount3' => '5.00', 'mc_currency' => 'EUR', 'period3' => '1 M']) === true,
    'PayPal-Meldungen mit mc_amount3 statt amount3 muessen akzeptiert werden.'
);
expect(
    invoke_private('paypal_subscription_signup_matches', 40, ['amount3' => '5.00', 'mc_currency' => 'EUR', 'period3' => '1 month']) === true,
    'Der Abrechnungszeitraum "1 month" muss wie "1 M" behandelt werden.'
);
expect(
    invoke_private('paypal_subscription_signup_matches', 40, ['amount3' => '5,00', 'mc_currency' => 'EUR', 'period3' => '1 M']) === true,
    'Ein Betrag mit Dezimalkomma muss akzeptiert werden.'
);
expect(
    invoke_private('paypal_subscription_signup_matches', 40, ['amount3' => '1.00', 'mc_currency' => 'EUR', 'period3' => '1 M']) === false,
    'Ein abweichender Betrag muss weiterhin abgelehnt werden.'
);
expect(
    invoke_private('paypal_subscription_signup_matches', 40, ['amount3' => '5.00', 'mc_currency' => 'EUR', 'period3' => '1 Y']) === false,
    'Ein abweichender Abrechnungszeitraum muss weiterhin abgelehnt werden.'
);

// Abgelehnte PayPal-Meldungen duerfen nicht mehr spurlos verschwinden.
$GLOBALS['sod_test_options']['sod_paypal_ipn_log'] = [];
invoke_private('handle_paypal_subscription_ipn', 'subscr_signup', [
    'custom' => 'tok-40',
    'subscr_id' => 'I-MISMATCH',
    'amount3' => '99.00',
    'mc_currency' => 'EUR',
    'period3' => '1 M',
]);
$ipn_log = $GLOBALS['sod_test_options']['sod_paypal_ipn_log'] ?? [];
expect($ipn_log !== [], 'Eine abgelehnte PayPal-Meldung muss protokolliert werden.');
expect(($ipn_log[0]['sponsor'] ?? 0) === 40, 'Das Protokoll muss den betroffenen Paten benennen.');
expect(str_contains((string)($GLOBALS['sod_test_meta'][40]['sod_sponsor_notes'] ?? ''), 'PayPal-Meldung nicht verarbeitet'), 'Die Ablehnung muss auch im Paten-Datensatz sichtbar sein.');
expect(($GLOBALS['sod_test_meta'][40]['sod_sponsor_paypal_subscr_id'] ?? '') === '', 'Eine abgelehnte Meldung darf das Abo nicht binden.');

// Kommt eine Abo-Zahlung, deren Anmeldung nie verarbeitet wurde, muss der Pate ueber das
// Zuordnungs-Token gefunden und die Abo-Nummer nachtraeglich verknuepft werden.
$GLOBALS['sod_test_meta'][50] = [
    'sod_sponsor_status' => 'aktiv',
    'sod_sponsor_payment_method' => 'paypal',
    'sod_sponsor_interval' => 'monatlich',
    'sod_sponsor_paypal_token' => 'tok-50',
    'sod_sponsor_paypal_expected_amount' => '20.00',
    'sod_sponsor_paypal_expected_currency' => 'EUR',
    'sod_sponsor_notes' => '',
];
// Bewusst mit abweichendem Betrag: Die Verknuepfung passiert vor der Betragspruefung,
// dadurch bleibt der Test von der Buchungs-/Mailstrecke unabhaengig.
invoke_private('handle_paypal_subscription_ipn', 'subscr_payment', [
    'payment_status' => 'Completed',
    'subscr_id' => 'I-NEU-50',
    'custom' => 'tok-50',
    'txn_id' => 'TXN-50',
    'mc_gross' => '99.00',
    'mc_currency' => 'EUR',
]);
expect(
    ($GLOBALS['sod_test_meta'][50]['sod_sponsor_paypal_subscr_id'] ?? '') === 'I-NEU-50',
    'Eine Abo-Zahlung ohne verknuepfte Abo-Nummer muss den Paten ueber das Token finden und die Nummer nachtragen.'
);
expect(
    str_contains((string)($GLOBALS['sod_test_meta'][50]['sod_sponsor_notes'] ?? ''), 'nachträglich über das Zuordnungs-Token'),
    'Die nachtraegliche Verknuepfung muss im Paten-Datensatz dokumentiert werden.'
);

// Ohne passendes Token darf nichts verknuepft werden.
$GLOBALS['sod_test_meta'][51] = [
    'sod_sponsor_status' => 'aktiv',
    'sod_sponsor_payment_method' => 'paypal',
    'sod_sponsor_interval' => 'monatlich',
    'sod_sponsor_paypal_token' => 'tok-51',
    'sod_sponsor_notes' => '',
];
invoke_private('handle_paypal_subscription_ipn', 'subscr_payment', [
    'payment_status' => 'Completed',
    'subscr_id' => 'I-FREMD',
    'custom' => 'unbekanntes-token',
    'txn_id' => 'TXN-51',
    'mc_gross' => '20.00',
    'mc_currency' => 'EUR',
]);
expect(
    ($GLOBALS['sod_test_meta'][51]['sod_sponsor_paypal_subscr_id'] ?? '') === '',
    'Ein unbekanntes Token darf keine Abo-Nummer verknuepfen.'
);

// Erstbestaetigung eines Zahlungseingangs muss Notiz, Zertifikat und Beleg ausloesen,
// eine wiederholte Bestaetigung darf nichts davon doppelt tun.
$GLOBALS['sod_test_meta'][30] = [
    'sod_sponsor_status' => 'aktiv',
    'sod_sponsor_payment_method' => 'paypal',
    'sod_sponsor_payment_received' => '0',
    'sod_sponsor_email' => '',
    'sod_sponsor_notes' => 'Online-Patenschaft vorlaeufig aktiviert.',
];
invoke_private('confirm_sponsor_payment', 30);
expect($GLOBALS['sod_test_meta'][30]['sod_sponsor_payment_received'] === '1', 'Bestaetigung muss den Zahlungseingang setzen.');
expect(str_contains((string)$GLOBALS['sod_test_meta'][30]['sod_sponsor_notes'], 'Zahlungseingang bestätigt am'), 'Die erste Zahlungsbestaetigung muss in den Notizen dokumentiert werden.');
$notes_after_first_confirmation = (string)$GLOBALS['sod_test_meta'][30]['sod_sponsor_notes'];
invoke_private('confirm_sponsor_payment', 30);
expect((string)$GLOBALS['sod_test_meta'][30]['sod_sponsor_notes'] === $notes_after_first_confirmation, 'Eine wiederholte Bestaetigung darf die Notiz nicht erneut anhaengen.');

// Smoke-Test: Ohne Datensaetze (der get_posts-Stub liefert immer []) darf die
// taegliche Zusammenfassung nicht abstuerzen und muss sinnvolle Nullwerte liefern.
$summary = invoke_private('sponsor_daily_summary_data');
expect($summary['new_total'] === 0, 'Ohne neue Paten muss new_total 0 sein.');
expect($summary['total_amount'] === 0.0, 'Ohne aktive Patenschaften muss total_amount 0 sein.');
expect($summary['pending'] === [], 'Ohne Datensaetze darf keine Warteliste entstehen.');

// Die gebrandete HTML-Mail muss aus dieser Datengrundlage ein gueltiges Dokument mit
// Logo, Kennzahlen und (falls vorhanden) der Warteliste erzeugen - keine rohen PHP-Arrays
// oder unverarbeitete Platzhalter im Ausgabetext.
$html_summary = [
    'new_total' => 2,
    'new_by_method' => [
        'paypal' => ['label' => 'PayPal-Abo', 'count' => 1, 'amount' => 10.0],
        'dauerauftrag' => ['label' => 'Dauerauftrag (Banküberweisung)', 'count' => 1, 'amount' => 20.0],
    ],
    'total_amount' => 80.0,
    'pending' => [
        ['name' => 'Test Pate', 'dog' => 'Test Hund', 'method' => 'Dauerauftrag (Banküberweisung)', 'amount' => '20', 'url' => 'https://example.test/wp-admin/post.php?post=2'],
    ],
];
$html = invoke_private('daily_reminders_email_html', [], $html_summary);
expect(str_starts_with(trim($html), '<!doctype html>'), 'Die Tagesmail muss ein vollstaendiges HTML-Dokument sein.');
expect(str_contains($html, 'logo.png'), 'Die Tagesmail muss das Vereinslogo einbinden.');
expect(str_contains($html, '#204060') && str_contains($html, '#f3c74f'), 'Die Tagesmail muss die SOD-Markenfarben verwenden.');
expect(str_contains($html, 'Test Pate') && str_contains($html, 'Test Hund'), 'Offene Patenschaften muessen namentlich in der Mail erscheinen.');
expect(str_contains($html, '80 €'), 'Der Gesamtwert muss in der Mail erscheinen.');

$plugin_source = (string)file_get_contents(dirname(__DIR__) . '/openpetrescue.php');
expect(
    str_contains($plugin_source, "if (\$value === '1' && \$previous_payment_received !== '1') {"),
    'Eine manuell gesetzte Erstbestaetigung darf nicht vorab gespeichert werden, sonst haelt confirm_sponsor_payment() sie fuer eine Wiederholung und ueberspringt Notiz, Zertifikat und Beleg.'
);
$translations_source = (string)file_get_contents(dirname(__DIR__, 3) . '/theme/openpetrescue/inc/translations.php');
expect(str_contains($plugin_source, 'sod-video-embed-template'), 'Externe oEmbed-Videos muessen bis zur Einwilligung in einem inaktiven Template bleiben.');
expect(str_contains($plugin_source, 'sod_sponsor_public_consent_at'), 'Die Einwilligung zur oeffentlichen Patenanzeige muss mit Zeitpunkt dokumentiert werden.');
expect(str_contains($plugin_source, 'sod_privacy_delete_after'), 'Personenbezogene Vorgaenge muessen ein technisches Loeschdatum erhalten.');
// Diese Fassung wird bewusst OHNE fertige Rechtstexte ausgeliefert: eine
// Datenschutzerklaerung beschreibt die Verarbeitung genau einer Organisation und
// darf nicht versehentlich von einer anderen uebernommen werden. Der Test stellt
// sicher, dass hier ein Platzhalter steht und kein uebernehmbarer Rechtstext.
expect(
    str_contains($translations_source, 'placeholder_text'),
    'Datenschutz- und Impressumstexte muessen Platzhalter bleiben, damit niemand fremde Rechtstexte veroeffentlicht.'
);
expect(
    !str_contains($translations_source, 'foundata GmbH'),
    'Es duerfen keine Dienstleister aus einer fremden Datenschutzerklaerung uebrig bleiben.'
);
// Der QR-Code wird weiterhin ueber einen externen Dienst erzeugt - wer ihn nutzt,
// muss ihn in der eigenen Datenschutzerklaerung nennen (siehe README).
expect(
    str_contains($plugin_source, 'api.qrserver.com'),
    'Der externe QR-Dienst muss im Code auffindbar bleiben, damit er dokumentiert werden kann.'
);

// Mitgliederbereich: E-Mail-Abgleich darf ohne passenden Paten kein Konto anlegen und
// keine leere/kaputte Eingabe zum Absturz bringen (get_posts-Stub liefert immer []).
expect(invoke_private('find_sponsor_by_email', '') === 0, 'Eine leere E-Mail-Adresse darf niemals einen Paten treffen.');
expect(invoke_private('find_sponsor_by_email', 'unbekannt@example.test') === 0, 'Ohne passenden Paten-Datensatz darf kein Treffer entstehen.');

// Registrierung ist bewusst NUR fuer bestehende Paten gedacht - der Quellcode muss vor
// dem Anlegen eines Kontos immer erst pruefen, ob eine passende Patenschaft existiert,
// und bei keinem Treffer klar ablehnen statt ein verwaistes Konto anzulegen.
expect(
    str_contains($plugin_source, '$sponsor_id = self::find_sponsor_by_email($email);')
    && str_contains($plugin_source, "if (\$sponsor_id <= 0) {\n            wp_safe_redirect(add_query_arg('sod_notice', 'member_no_sponsor_match', \$redirect));\n            exit;\n        }"),
    'Registrierung ohne passende Patenschaft muss klar abgelehnt werden, statt ein Konto anzulegen.'
);

// Honeypot/Timing/Consent-Schutz muss - wie beim bestehenden Paten-Formular - VOR dem
// Anlegen des Benutzerkontos greifen, damit Bots kein Konto erzeugen koennen.
$register_source_pos = strpos($plugin_source, 'public static function handle_member_register()');
$register_source_end = $register_source_pos !== false ? strpos($plugin_source, 'public static function handle_member_resend_verify()', $register_source_pos) : false;
$register_source = ($register_source_pos !== false && $register_source_end !== false)
    ? substr($plugin_source, $register_source_pos, $register_source_end - $register_source_pos)
    : '';
expect(
    str_contains($register_source, "!empty(\$_POST['website'])")
    && str_contains($register_source, "(time() - (int)(\$_POST['started_at'] ?? 0)) < 3")
    && str_contains($register_source, "empty(\$_POST['consent'])"),
    'Die Registrierung muss denselben Honeypot-/Timing-/Consent-Schutz wie das Paten-Formular verwenden.'
);
$honeypot_guard_pos = strpos($register_source, "add_query_arg('sod_notice', 'member_spam'");
expect($honeypot_guard_pos !== false && $honeypot_guard_pos < (int)strpos($register_source, 'wp_insert_user('), 'Der Bot-Schutz muss vor dem Anlegen des Benutzerkontos ausgewertet werden.');

// Konto-Loeschung darf den Paten-Datensatz (Spendenhistorie) nicht mitloeschen, sondern
// nur die Verknuepfung trennen und den Anzeige-Consent widerrufen.
expect(
    str_contains($plugin_source, "delete_post_meta(\$sponsor_id, 'sod_sponsor_user_id');")
    && str_contains($plugin_source, "update_post_meta(\$sponsor_id, 'sod_sponsor_public_consent_withdrawn_at', gmdate('c'));")
    && !str_contains($plugin_source, 'wp_delete_post($sponsor_id'),
    'Konto-Loeschung darf nur die Verknuepfung trennen, nicht den Paten-/Spenden-Datensatz loeschen.'
);

// Login-Sperre fuer unverifizierte Mitgliederkonten (Double-Opt-In) - funktional getestet
// ueber den echten Filter-Callback mit den additiven WP_User/WP_Error-Stubs oben.
$unverified = new WP_User(101, ['sod_member'], 'pate@example.test', 'Test Pate');
$result = invoke_private('block_unverified_member_login', $unverified, 'irgendeinpasswort');
expect(is_wp_error($result), 'Ein nicht verifiziertes Mitgliederkonto darf sich nicht einloggen koennen.');

update_user_meta(101, 'sod_member_verified_at', gmdate('c'));
$verified = invoke_private('block_unverified_member_login', $unverified, 'irgendeinpasswort');
expect($verified instanceof WP_User && $verified->ID === 101, 'Ein verifiziertes Mitgliederkonto muss sich normal einloggen koennen.');

$staff_user = new WP_User(102, ['sod_staff_all']);
$staff_result = invoke_private('block_unverified_member_login', $staff_user, 'irgendeinpasswort');
expect($staff_result instanceof WP_User, 'Die Verifizierungspflicht darf nur sod_member-Konten betreffen, keine Mitarbeiter-Rollen.');

fwrite(STDOUT, "OK: security regression checks passed\n");
