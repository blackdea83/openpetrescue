<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Neue Handy-Ansicht (30.09.2026): Leiste unten, Schnellstart auf der Startseite,
 * wischbare Paten-Hunde, groessere Spenden-Kacheln, Schicksale als Liste.
 * Wirkt nur unter 768 px Breite, Desktop bleibt unveraendert.
 *
 * Ein-/Ausschalten: Verwaltung > Design > Mobile Ansicht, oder Option sod_mobile_v2
 * ('on' = alle, 'admin' = nur Admins, 'off' = aus). Ausgeschaltet ist alles wie vorher.
 */
function sod_m2_mode(): string
{
    $mode = (string)get_option('sod_mobile_v2', 'off');
    return in_array($mode, ['on', 'admin', 'off'], true) ? $mode : 'off';
}

function sod_m2_active(): bool
{
    if (is_admin()) {
        return false;
    }
    $mode = sod_m2_mode();
    return $mode === 'on' || ($mode === 'admin' && current_user_can('manage_options'));
}

function sod_m2_is_story_detail(): bool
{
    return is_page('geschichten') && (string)get_query_var('sod_story') !== '';
}

add_filter('body_class', static function (array $classes): array {
    if (sod_m2_active()) {
        $classes[] = 'sod-m2';
        if (sod_m2_is_story_detail()) {
            $classes[] = 'sod-m2-story';
        }
    }
    return $classes;
});

add_action('wp_enqueue_scripts', static function (): void {
    if (!sod_m2_active()) {
        return;
    }
    // Dateidatum als Version, damit Aenderungen sofort ankommen.
    $version = (string)(filemtime(get_template_directory() . '/assets/css/mobile-v2.css') ?: wp_get_theme()->get('Version'));
    wp_enqueue_style('sod-mobile-v2', get_template_directory_uri() . '/assets/css/mobile-v2.css', ['sod-legacy-pages'], $version);
}, 30);

/** Leiste unten (bzw. auf einer Schicksal-Seite: Pate werden / Spenden). */
add_action('wp_footer', static function (): void {
    if (!sod_m2_active()) {
        return;
    }
    if (sod_m2_is_story_detail()) {
        $dog = function_exists('sod_story_current_dog') ? sod_story_current_dog() : null;
        $sponsor_url = sod_page_url('patenschaft');
        if ($dog) {
            $support = function_exists('sod_story_support') ? sod_story_support($dog->ID) : ['target' => 0.0, 'remaining' => 0.0];
            // Hund braucht noch Paten: direkt zu seiner Patenschaft, sonst zu den Hunden, die noch Hilfe brauchen.
            $sponsor_url = ($support['target'] > 0 && $support['remaining'] >= 1) ? get_permalink($dog) . '#pate-werden' : '#sodst-help';
        }
        ?>
        <div class="sod-m2-storybar" role="region" aria-label="<?php echo esc_attr(sod_t('mobile.help_label')); ?>">
            <a class="sod-m2-storybar-main" href="<?php echo esc_url($sponsor_url); ?>"><?php sod_te('mobile.sponsor'); ?></a>
            <a class="sod-m2-storybar-alt" href="<?php echo esc_url(sod_page_url('spenden')); ?>"><?php sod_te('mobile.donate'); ?></a>
        </div>
        <?php
        return;
    }
    $items = [
        ['home', home_url('/'), 'mobile.tab_home', is_front_page(), '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>'],
        ['stories', sod_page_url('geschichten'), 'mobile.tab_stories', is_page('geschichten'), '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M9 8h6M9 12h5"/>'],
        ['adopt', sod_page_url('vermittlung'), 'mobile.tab_adopt', is_page('vermittlung'), '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M12 17s-3-1.8-3-4a1.6 1.6 0 0 1 3-.9 1.6 1.6 0 0 1 3 .9c0 2.2-3 4-3 4z"/>'],
        ['sponsor', sod_page_url('patenschaft'), 'mobile.tab_sponsor', is_page('patenschaft'), '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>'],
        ['donate', sod_page_url('spenden'), 'mobile.tab_donate', is_page('spenden'), '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8v12M3 12h18M12 8c-2-4-6-3-5 0M12 8c2-4 6-3 5 0"/>'],
    ];
    echo '<nav class="sod-m2-tabs" aria-label="' . esc_attr(sod_t('mobile.nav_label')) . '">';
    foreach ($items as [$key, $url, $label, $on, $icon]) {
        printf(
            '<a class="sod-m2-tab%1$s" href="%2$s" data-sod-m2-tab="%3$s"%4$s><svg viewBox="0 0 24 24" aria-hidden="true">%5$s</svg><span>%6$s</span></a>',
            $on ? ' is-on' : '',
            esc_url($url),
            esc_attr($key),
            $on ? ' aria-current="page"' : '',
            $icon, // statisches SVG aus diesem File
            esc_html(sod_t($label))
        );
    }
    echo '</nav>';
    ?>
    <script>
    (function () {
        // Auf der gemeinsamen Seite Vermittlung/Patenschaft den passenden Reiter markieren.
        var tabs = document.querySelector('.sod-m2-tabs');
        if (!tabs || !document.querySelector('.sod-combined')) return;
        function mark() {
            var adopt = location.hash === '#vermittlung' || !!document.querySelector('#vermittlung:not([hidden])');
            tabs.querySelectorAll('[data-sod-m2-tab]').forEach(function (a) {
                var k = a.getAttribute('data-sod-m2-tab');
                var on = adopt ? k === 'adopt' : k === 'sponsor';
                a.classList.toggle('is-on', on);
                if (on) { a.setAttribute('aria-current', 'page'); } else { a.removeAttribute('aria-current'); }
            });
        }
        mark();
        window.addEventListener('hashchange', mark);
        document.addEventListener('click', function () { setTimeout(mark, 60); });
    })();
    </script>
    <?php
}, 5);

/** Schnellstart auf der Startseite (nur am Handy sichtbar). */
function sod_m2_home_block(): void
{
    if (!sod_m2_active()) {
        return;
    }
    $dogs = function_exists('sod_hero_new_dogs') ? sod_hero_new_dogs(8) : [];
    ?>
    <section class="sod-m2-home" aria-label="<?php echo esc_attr(sod_t('mobile.help_label')); ?>">
        <div class="sod-m2-quick">
            <a class="sod-m2-quick-main" href="<?php echo esc_url(sod_page_url('patenschaft')); ?>">
                <strong><?php sod_te('mobile.sponsor'); ?></strong><span><?php sod_te('mobile.sponsor_sub'); ?></span>
            </a>
            <a class="sod-m2-quick-alt" href="<?php echo esc_url(sod_page_url('spenden')); ?>">
                <strong><?php sod_te('mobile.donate_once'); ?></strong><span><?php sod_te('mobile.donate_sub'); ?></span>
            </a>
        </div>
        <?php if ($dogs) : ?>
            <div class="sod-m2-row-head">
                <h2><?php sod_te('mobile.need_sponsors'); ?></h2>
                <a href="<?php echo esc_url(sod_page_url('patenschaft')); ?>"><?php sod_te('mobile.see_all'); ?></a>
            </div>
            <div class="sod-m2-swipe">
                <?php foreach ($dogs as $dog) :
                    $remaining = max(0, (int)round($dog['target'] - $dog['secured']));
                    ?>
                    <a class="sod-m2-dog" href="<?php echo esc_url($dog['url']); ?>">
                        <img src="<?php echo esc_url($dog['image']); ?>" alt="<?php echo esc_attr($dog['name']); ?>" loading="lazy" decoding="async">
                        <span class="sod-m2-dog-body">
                            <strong><?php echo esc_html($dog['name']); ?></strong>
                            <?php if ($dog['target'] > 0) : ?>
                                <span class="sod-m2-bar"><i style="width:<?php echo (int)$dog['percent']; ?>%"></i></span>
                                <small><?php echo esc_html($remaining > 0 ? sprintf(sod_t('mobile.open_amount'), $remaining) : sod_t('mobile.funded')); ?></small>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

/** Schalter im Backend: Design > Mobile Ansicht. */
add_action('admin_menu', static function (): void {
    add_theme_page('Mobile Ansicht', 'Mobile Ansicht', 'manage_options', 'sod-mobile-v2', 'sod_m2_admin_page');
});

function sod_m2_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['sod_m2_mode']) && check_admin_referer('sod_m2_save')) {
        $mode = sanitize_key((string)wp_unslash($_POST['sod_m2_mode']));
        if (in_array($mode, ['on', 'admin', 'off'], true)) {
            update_option('sod_mobile_v2', $mode, true);
            echo '<div class="notice notice-success"><p>Gespeichert.</p></div>';
        }
    }
    $mode = sod_m2_mode();
    $labels = [
        'on' => 'Eingeschaltet – alle Besucher sehen die neue Handy-Ansicht',
        'admin' => 'Nur für Admins – zum Testen',
        'off' => 'Ausgeschaltet – Handy-Ansicht wie vorher',
    ];
    ?>
    <div class="wrap">
        <h1>Mobile Ansicht</h1>
        <p>Die neue Handy-Ansicht (Leiste unten, Schnellstart, wischbare Hundekarten, größere Spenden-Kacheln) wirkt nur auf Bildschirmen unter 768 px. Am Computer ändert sich nichts.</p>
        <form method="post">
            <?php wp_nonce_field('sod_m2_save'); ?>
            <?php foreach ($labels as $value => $label) : ?>
                <p><label><input type="radio" name="sod_m2_mode" value="<?php echo esc_attr($value); ?>" <?php checked($mode, $value); ?>> <?php echo esc_html($label); ?></label></p>
            <?php endforeach; ?>
            <?php submit_button('Speichern'); ?>
        </form>
    </div>
    <?php
}
