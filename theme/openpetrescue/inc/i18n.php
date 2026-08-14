<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SOD_LANGUAGES = [
    'de' => ['label' => 'DE', 'name' => 'Deutsch', 'locale' => 'de-AT', 'dir' => 'ltr'],
    'en' => ['label' => 'EN', 'name' => 'English', 'locale' => 'en', 'dir' => 'ltr'],
    'bs' => ['label' => 'BS', 'name' => 'Bosanski', 'locale' => 'bs-BA', 'dir' => 'ltr'],
];

function sod_current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    if (isset($_COOKIE['sod_lang'])) {
        $requested = sanitize_key((string)$_COOKIE['sod_lang']);
        $lang = array_key_exists($requested, SOD_LANGUAGES) ? $requested : 'de';
        return $lang;
    }
    $lang = sod_detect_browser_lang();
    return $lang;
}

/**
 * Erste Sprache ohne gespeicherte Wahl (kein sod_lang-Cookie): richtet sich nach der
 * Systemsprache des Besuchers ueber den Accept-Language-Header. Deutsch bleibt Deutsch,
 * Bosnisch bleibt Bosnisch, alle anderen Sprachen fallen auf Englisch zurueck. Ein
 * manueller Wechsel ueber den Sprachumschalter setzt danach das Cookie und hat immer
 * Vorrang vor dieser Erkennung.
 */
function sod_detect_browser_lang(): string
{
    $header = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if ($header === '') {
        return 'de';
    }
    $primary = strtolower(trim(explode(',', $header)[0]));
    $primary = substr($primary, 0, 2);
    if ($primary === 'de') {
        return 'de';
    }
    if ($primary === 'bs') {
        return 'bs';
    }
    return 'en';
}

function sod_handle_lang_switch(): void
{
    $requested = sanitize_key((string)($_GET['sod_lang'] ?? ''));
    if ($requested === '' || !array_key_exists($requested, SOD_LANGUAGES)) {
        return;
    }
    setcookie('sod_lang', $requested, [
        'expires' => time() + YEAR_IN_SECONDS,
        'path' => '/',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $redirect = remove_query_arg('sod_lang');
    wp_safe_redirect($redirect ?: home_url('/'));
    exit;
}
add_action('template_redirect', 'sod_handle_lang_switch', 1);

function sod_language_attributes(string $output, string $doctype): string
{
    unset($output, $doctype);
    $language = SOD_LANGUAGES[sod_current_lang()] ?? SOD_LANGUAGES['de'];

    return sprintf(
        'lang="%s" dir="%s"',
        esc_attr($language['locale']),
        esc_attr($language['dir'])
    );
}
add_filter('language_attributes', 'sod_language_attributes', 10, 2);

/**
 * Looks up a translated string. $key is "page.stringname".
 * Falls back to German, then to the raw key, so a missing
 * translation never breaks the page.
 */
function sod_t(string $key): string
{
    static $translations = null;
    if ($translations === null) {
        $translations = sod_translations();
    }
    [$page, $string] = array_pad(explode('.', $key, 2), 2, '');
    $lang = sod_current_lang();
    if (isset($translations[$page][$string][$lang]) && $translations[$page][$string][$lang] !== '') {
        return sod_apply_text_tokens($translations[$page][$string][$lang]);
    }
    if (isset($translations[$page][$string]['de'])) {
        return sod_apply_text_tokens($translations[$page][$string]['de']);
    }
    return $key;
}

function sod_te(string $key): void
{
    echo esc_html(sod_t($key));
}

function sod_th(string $key): void
{
    echo wp_kses_post(sod_t($key));
}

function sod_lang_url(string $lang): string
{
    global $wp;
    $current = home_url(add_query_arg([], $wp->request ?? ''));
    return esc_url(add_query_arg('sod_lang', $lang, $current));
}

function sod_lang_switcher_items(): string
{
    $current = sod_current_lang();
    $html = '';
    foreach (SOD_LANGUAGES as $code => $info) {
        $class = $code === $current ? 'lang-btn active' : 'lang-btn';
        $html .= sprintf(
            '<a class="%s" href="%s" lang="%s" hreflang="%s" aria-current="%s">%s</a>',
            esc_attr($class),
            sod_lang_url($code),
            esc_attr($code),
            esc_attr($code),
            $code === $current ? 'true' : 'false',
            esc_html($info['label'])
        );
    }
    return $html;
}

function sod_lang_switcher(): void
{
    echo '<div class="lang-switch">' . sod_lang_switcher_items() . '</div>';
}

/**
 * Platzhalter in Uebersetzungstexten aufloesen. So muss der Vereinsname nur in
 * den Einstellungen gepflegt werden und nicht in jeder Sprachdatei.
 */
function sod_apply_text_tokens(string $text): string
{
    if (strpos($text, '{org}') === false) {
        return $text;
    }
    return str_replace('{org}', sod_org_name(), $text);
}
