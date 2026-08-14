<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/inc/i18n.php';
require_once get_template_directory() . '/inc/translations.php';

function sod_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height' => 160,
        'width' => 160,
        'flex-height' => true,
        'flex-width' => true,
    ]);
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary' => __('Hauptnavigation', 'shield-of-dogs'),
        'footer_org' => __('Footer Organisation', 'shield-of-dogs'),
        'footer_help' => __('Footer Mitmachen', 'shield-of-dogs'),
    ]);
}
add_action('after_setup_theme', 'sod_theme_setup');

/**
 * Der Bereich Tierheim-Bau bleibt erhalten, wird aber bis auf Weiteres nicht
 * in öffentlichen WordPress-Menüs angezeigt.
 */
function sod_hide_paused_public_menu_items(array $items): array
{
    return array_values(array_filter($items, static function ($item): bool {
        if (($item->object ?? '') === 'page' && !empty($item->object_id)) {
            return get_post_field('post_name', (int)$item->object_id) !== 'tierheim-bau';
        }

        $path = wp_parse_url((string)($item->url ?? ''), PHP_URL_PATH);
        return trim((string)$path, '/') !== 'tierheim-bau';
    }));
}
add_filter('wp_nav_menu_objects', 'sod_hide_paused_public_menu_items');

remove_filter('the_content', 'wpautop');

function sod_cleanup_wordpress_head(): void
{
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('template_redirect', 'rest_output_link_header', 11);
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('template_redirect', 'wp_shortlink_header', 11);
    remove_action('wp_footer', 'wp_print_speculation_rules');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('emoji_svg_url', '__return_false');
    add_filter('xmlrpc_enabled', '__return_false');
}
add_action('init', 'sod_cleanup_wordpress_head');

/**
 * Author-Archive fuer nicht eingeloggte Besucher deaktivieren.
 * Verhindert Benutzernamen-Enumeration ueber /author/... und ?author=N.
 */
function sod_block_author_archives(): void
{
    if (is_admin() || is_user_logged_in()) {
        return;
    }
    if (is_author() || isset($_GET['author'])) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}
add_action('template_redirect', 'sod_block_author_archives');

function sod_theme_assets(): void
{
    $theme = wp_get_theme();
    wp_enqueue_style(
        'sod-site',
        get_template_directory_uri() . '/assets/css/site.css',
        [],
        $theme->get('Version')
    );
    wp_enqueue_style(
        'sod-theme',
        get_template_directory_uri() . '/assets/css/theme.css',
        ['sod-site'],
        $theme->get('Version')
    );
    wp_enqueue_style(
        'sod-legacy-pages',
        get_template_directory_uri() . '/assets/css/legacy-pages.css',
        ['sod-theme'],
        $theme->get('Version')
    );
    wp_enqueue_script(
        'sod-theme',
        get_template_directory_uri() . '/assets/js/theme.js',
        [],
        $theme->get('Version'),
        true
    );
    wp_enqueue_script(
        'sod-legacy-pages',
        get_template_directory_uri() . '/assets/js/legacy-pages.js',
        ['sod-theme'],
        $theme->get('Version'),
        true
    );
}
add_action('wp_enqueue_scripts', 'sod_theme_assets');

function sod_asset(string $path): string
{
    $rel = ltrim($path, '/');
    // Das Theme wird ohne eigene Fotos ausgeliefert. Fehlt eine Datei, wird ein
    // neutrales Platzhalterbild geliefert, damit die Seite nicht mit kaputten
    // Bildern ausgeliefert wird, bevor eigene Medien hinterlegt sind.
    if (!file_exists(get_template_directory() . '/assets/' . $rel)) {
        $rel = 'images/placeholder.svg';
    }
    return esc_url(get_template_directory_uri() . '/assets/' . $rel);
}

/**
 * Organisationsdaten. Gepflegt werden sie im Plugin unter
 * "Einstellungen -> Organisation"; das Theme liest hier dieselben Optionen,
 * damit Name, Anschrift und Kontakt nur an einer Stelle gepflegt werden.
 */
function sod_org_name(): string
{
    $value = trim((string)get_option('sod_org_name', ''));
    return $value !== '' ? $value : 'Mein Tierschutzverein';
}

function sod_org_email(): string
{
    $value = trim((string)get_option('sod_org_email', ''));
    return $value !== '' ? $value : (string)get_option('admin_email');
}

function sod_org_address(): string
{
    $street = trim((string)get_option('sod_org_street', ''));
    $city = trim(trim((string)get_option('sod_org_zip', '')) . ' ' . trim((string)get_option('sod_org_city', '')));
    return implode(', ', array_filter([$street, $city]));
}

function sod_org_donation_url(): string
{
    $value = trim((string)get_option('sod_org_donation_url', ''));
    return $value !== '' ? $value : sod_page_url('spenden');
}

function sod_page_url(string $slug): string
{
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page) : home_url('/' . trim($slug, '/') . '/');
}

/**
 * Die Hauptnavigation kommt aus dem WordPress-Menue, ihre Beschriftungen stehen also fest
 * auf Deutsch in der Datenbank. Nur das Ersatzmenue war bisher uebersetzt. Hier werden die
 * Menuepunkte anhand der verlinkten Seite den vorhandenen Uebersetzungen zugeordnet.
 */
function sod_nav_slug_translation_keys(): array
{
    return [
        'vermittlung'  => 'common.nav_vermittlung',
        'patenschaft'  => 'common.nav_patenschaft',
        'spenden'      => 'common.nav_spenden',
        'kontakt'      => 'common.nav_kontakt',
        'tierheim-bau' => 'common.nav_tierheim_bau',
        'impressum'    => 'common.footer_impressum',
        'datenschutz'  => 'common.footer_datenschutz',
    ];
}

function sod_default_label(string $key): string
{
    $translations = sod_translations();
    [$group, $string] = array_pad(explode('.', $key, 2), 2, '');
    return (string)($translations[$group][$string]['de'] ?? '');
}

function sod_translate_nav_items(array $items): array
{
    $keys = sod_nav_slug_translation_keys();
    foreach ($items as $item) {
        if (($item->object ?? '') !== 'page' || empty($item->object_id)) {
            continue;
        }
        $key = $keys[(string)get_post_field('post_name', (int)$item->object_id)] ?? '';
        if ($key === '') {
            continue;
        }
        // Nur ersetzen, solange der Eintrag die deutsche Standardbezeichnung traegt.
        // Eigene Beschriftungen aus dem Menue-Editor bleiben damit unangetastet.
        if (mb_strtolower(trim((string)$item->title)) !== mb_strtolower(sod_default_label($key))) {
            continue;
        }
        $item->title = sod_t($key);
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'sod_translate_nav_items');

function sod_nav_fallback(): void
{
    $items = [
        ['common.nav_start', home_url('/'), ''],
        ['common.nav_vermittlung', sod_page_url('vermittlung'), ''],
        ['common.nav_patenschaft', sod_page_url('patenschaft'), ''],
        ['common.nav_ueber_uns', home_url('/#ueber-uns'), ''],
        ['common.nav_region', home_url('/#galerie'), ''],
        ['common.nav_kontakt', sod_page_url('kontakt'), ''],
        ['common.nav_spenden', sod_page_url('spenden'), ' class="nav-mobile-cta nav-mobile-donate"'],
    ];

    echo '<ul class="nav-links">';
    foreach ($items as [$label_key, $url, $class]) {
        printf('<li%s><a href="%s">%s</a></li>', $class, esc_url($url), esc_html(sod_t($label_key)));
    }
    printf(
        '<li class="nav-mobile-lang"><div class="lang-switch">%s</div></li>',
        sod_lang_switcher_items()
    );
    echo '</ul>';
}

function sod_footer_menu(string $location, array $fallback): void
{
    if (has_nav_menu($location)) {
        wp_nav_menu([
            'theme_location' => $location,
            'container' => false,
            'menu_class' => 'footer-col-links',
            'depth' => 1,
        ]);
        return;
    }

    echo '<ul class="footer-col-links">';
    foreach ($fallback as [$label, $url]) {
        printf('<li><a href="%s">%s</a></li>', esc_url($url), esc_html($label));
    }
    echo '</ul>';
}
