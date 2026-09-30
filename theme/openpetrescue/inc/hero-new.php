<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Neuer Hero-Bereich der Startseite (29.09.2026), vorerst nur fuer Admins sichtbar.
 * Oeffentlich schalten: Option sod_hero_new_public = '1'.
 */
function sod_hero_new_is_public(): bool
{
    return get_option('sod_hero_new_public', '0') === '1';
}

function sod_hero_new_visible(): bool
{
    return sod_hero_new_is_public() || current_user_can('manage_options');
}

/** Ein Satz ueber den Hund: erster Satz aus Schicksal-Kurzfassung, Beschreibung oder Charakter. */
function sod_hero_dog_line(WP_Post $dog): string
{
    foreach ([(string)get_post_meta($dog->ID, 'sod_story_summary', true), (string)$dog->post_content, (string)get_post_meta($dog->ID, 'sod_character', true)] as $text) {
        $text = trim((string)preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)));
        if ($text === '') {
            continue;
        }
        $sentence = preg_match('/^(.{20,}?[.!?])(\s|$)/u', $text, $m) ? $m[1] : $text;
        return mb_strlen($sentence) > 110 ? rtrim(mb_substr($sentence, 0, 107), " ,;–-") . '…' : $sentence;
    }
    return '';
}

function sod_hero_dog_image(WP_Post $dog): string
{
    $url = has_post_thumbnail($dog->ID) ? (string)get_the_post_thumbnail_url($dog->ID, 'large') : '';
    if ($url === '') {
        foreach (array_filter(array_map('intval', explode(',', (string)get_post_meta($dog->ID, 'sod_dog_image_ids', true)))) as $image_id) {
            $url = (string)(wp_get_attachment_image_url($image_id, 'large') ?: '');
            if ($url !== '') {
                break;
            }
        }
    }
    if ($url === '' && function_exists('sod_story_image_url')) {
        $url = sod_story_image_url($dog, 'large');
    }
    return $url;
}

/**
 * Hunde fuer den Hero: zuerst die, deren Patenschaft am wenigsten gedeckt ist; reicht das nicht,
 * werden andere Patenschafts-Hunde mit Foto ergaenzt.
 * @return array<int, array{name: string, image: string, line: string, url: string, secured: float, target: float, percent: int}>
 */
function sod_hero_new_dogs(int $limit = 5): array
{
    $dogs = get_posts([
        'post_type' => 'sod_dog',
        'post_status' => 'publish',
        'numberposts' => 60,
        'meta_query' => [['key' => 'sod_show_sponsorship', 'value' => '1']],
    ]);
    $needy = [];
    $others = [];
    foreach ($dogs as $dog) {
        if (get_post_meta($dog->ID, 'sod_deceased', true) === '1' || get_post_meta($dog->ID, 'sod_status', true) === 'vermittelt') {
            continue;
        }
        $image = sod_hero_dog_image($dog);
        if ($image === '') {
            continue;
        }
        $support = function_exists('sod_story_support') ? sod_story_support($dog->ID) : ['target' => 0.0, 'secured' => 0.0, 'remaining' => 0.0, 'percent' => 0];
        $item = [
            'name' => (string)get_the_title($dog),
            'image' => $image,
            'line' => sod_hero_dog_line($dog),
            'url' => (string)get_permalink($dog),
            'secured' => (float)$support['secured'],
            'target' => (float)$support['target'],
            'percent' => (int)$support['percent'],
        ];
        if ($support['target'] > 0 && $support['remaining'] >= 1) {
            $needy[] = $item;
        } else {
            $others[] = $item;
        }
    }
    usort($needy, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);
    return array_slice(array_merge($needy, $others), 0, $limit);
}

/** Schalter im Backend: Design > Hero der Startseite. */
add_action('admin_menu', static function (): void {
    add_theme_page('Hero der Startseite', 'Hero der Startseite', 'manage_options', 'sod-hero-new', 'sod_hero_new_admin_page');
});

function sod_hero_new_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['sod_hero_new_save']) && check_admin_referer('sod_hero_new_save')) {
        update_option('sod_hero_new_public', isset($_POST['sod_hero_new_public']) ? '1' : '0', false);
        echo '<div class="notice notice-success"><p>Gespeichert.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Hero der Startseite</h1>
        <p>Der neue Hero zeigt wechselnde Tiere, die gerade Paten suchen – mit Foto, einem Satz über das Tier und Fortschrittsbalken. Solange er nicht freigeschaltet ist, sehen ihn nur eingeloggte Admins.</p>
        <form method="post">
            <?php wp_nonce_field('sod_hero_new_save'); ?>
            <p><label><input type="checkbox" name="sod_hero_new_public" value="1" <?php checked(sod_hero_new_is_public()); ?>> Für alle Besucher freischalten</label></p>
            <?php submit_button('Speichern', 'primary', 'sod_hero_new_save'); ?>
        </form>
    </div>
    <?php
}
