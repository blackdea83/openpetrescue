<?php
/**
 * Schicksale (live seit 14.09.2026, von SODBETA uebernommen)
 *
 * SICHTBARKEIT: Solange unter Hunde → Schicksale der Schalter "oeffentlich anzeigen" aus ist, sieht
 * nur das Team (Redakteure/Admins, angemeldet) Geschichten-Seiten, Abschnitt auf der Hundeseite,
 * Startseiten-Teaser, Menuepunkt und den Button "Schicksal". Gaeste sehen die Seite unveraendert.
 *
 * Jeder Hund kann eine Geschichte in Kapiteln bekommen, erzaehlt vom Team vor Ort.
 * - Pflege: Kasten "Schicksal" im Hunde-Editor (Titel, 20-Sekunden-Text, Zitat, Kapitel).
 * - Videos: kommen aus der YouTube-Playlist des Hundes (Feld "YouTube-Playlist" im Plugin).
 *   Die Playlist wird beim Speichern und woechentlich abgerufen; Vorschaubilder werden auf
 *   den eigenen Server kopiert. Abgespielt wird erst nach Zustimmung (assets/js/stories.js).
 * - Kapitel-Zuordnung: Jedes Kapitel hat ein Startdatum; ein Video gehoert zum letzten
 *   Kapitel, dessen Datum vor dem Veroeffentlichungsdatum des Videos liegt.
 * - Ausgabe: /geschichten/ (Uebersicht mit Filter) und /geschichten/<hund>/ (Geschichte),
 *   Startseiten-Teaser in template-parts/story-teaser.php.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SOD_STORY_REWRITE_VERSION = '1';
const SOD_STORY_TEAM_CAP = 'edit_others_posts';

/** Oeffentlich freigeschaltet? */
function sod_story_is_public(): bool
{
    return get_option('sod_story_public') === '1';
}

/** Darf der aktuelle Besucher die Schicksale sehen? */
function sod_story_visible(): bool
{
    return sod_story_is_public() || current_user_can(SOD_STORY_TEAM_CAP);
}
const SOD_STORY_MAX_VIDEOS = 40;

/* ------------------------------------------------------------------ Routing */

add_action('init', static function (): void {
    add_rewrite_rule('^geschichten/([^/]+)/?$', 'index.php?pagename=geschichten&sod_story=$matches[1]', 'top');
    if (get_option('sod_story_rewrite_version') !== SOD_STORY_REWRITE_VERSION) {
        flush_rewrite_rules(false);
        update_option('sod_story_rewrite_version', SOD_STORY_REWRITE_VERSION, false);
    }
});

add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'sod_story';
    return $vars;
});

/** Hund der aktuell aufgerufenen Geschichte (oder null). */
function sod_story_current_dog(): ?WP_Post
{
    static $dog = false;
    if ($dog !== false) {
        return $dog;
    }
    $dog = null;
    $slug = sanitize_title((string)get_query_var('sod_story'));
    if ($slug !== '') {
        $found = get_posts([
            'post_type' => 'sod_dog',
            'post_status' => 'publish',
            'name' => $slug,
            'numberposts' => 1,
        ]);
        if ($found && sod_story_enabled($found[0]->ID)) {
            $dog = $found[0];
        }
    }
    return $dog;
}

add_action('template_redirect', static function (): void {
    if (is_page('geschichten') && !sod_story_visible()) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }
    if (get_query_var('sod_story') !== '' && get_query_var('sod_story') !== null && !sod_story_current_dog()) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
    }
});

function sod_story_url(WP_Post $dog): string
{
    return trailingslashit(sod_page_url('geschichten')) . $dog->post_name . '/';
}

/* ------------------------------------------------------------------ Daten */

function sod_story_enabled(int $dog_id): bool
{
    return get_post_meta($dog_id, 'sod_story_enabled', true) === '1' || sod_story_only($dog_id);
}

/** "Nur Schicksale" (Plugin-Checkbox): Hund erscheint nur in den Schicksalen, nicht in Vermittlung/Patenschaft. */
function sod_story_only(int $dog_id): bool
{
    return get_post_meta($dog_id, 'sod_story_only', true) === '1';
}

/** @return array<int, array{title: string, date: string, text: string, quote: string}> */
function sod_story_chapters(int $dog_id): array
{
    $raw = json_decode((string)get_post_meta($dog_id, 'sod_story_chapters', true), true);
    if (!is_array($raw)) {
        return [];
    }
    $chapters = [];
    foreach ($raw as $chapter) {
        if (!is_array($chapter)) {
            continue;
        }
        $title = trim((string)($chapter['title'] ?? ''));
        $text = trim((string)($chapter['text'] ?? ''));
        if ($title === '' && $text === '') {
            continue;
        }
        $chapters[] = [
            'title' => $title,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($chapter['date'] ?? '')) ? (string)$chapter['date'] : '',
            'text' => $text,
            'quote' => trim((string)($chapter['quote'] ?? '')),
        ];
    }
    return $chapters;
}

/** @return array<int, array{id: string, title: string, date: string, seconds: int, thumb: string}> */
function sod_story_videos(int $dog_id): array
{
    $videos = json_decode((string)get_post_meta($dog_id, 'sod_story_videos', true), true);
    return is_array($videos) ? $videos : [];
}

/** Kapitel mit zugeordneten Videos. */
function sod_story_chapters_with_videos(int $dog_id): array
{
    $chapters = sod_story_chapters($dog_id);
    $videos = sod_story_videos($dog_id);
    usort($videos, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
    if (!$chapters) {
        return $videos ? [['title' => '', 'date' => '', 'text' => '', 'quote' => '', 'videos' => $videos]] : [];
    }
    foreach ($chapters as &$chapter) {
        $chapter['videos'] = [];
    }
    unset($chapter);
    // Ohne Kapitel-Daten landen Videos im letzten (aktuellsten) Kapitel.
    $has_dates = (bool)array_filter($chapters, static fn (array $c): bool => $c['date'] !== '');
    foreach ($videos as $video) {
        $target = $has_dates ? 0 : count($chapters) - 1;
        foreach ($chapters as $index => $chapter) {
            if ($chapter['date'] !== '' && $chapter['date'] <= $video['date']) {
                $target = $index;
            }
        }
        $chapters[$target]['videos'][] = $video;
    }
    return $chapters;
}

/** Versorgung ueber das Plugin (Monatsbedarf, gesichert, offen). */
function sod_story_support(int $dog_id): array
{
    $support = class_exists('SOD_Plugin') && method_exists('SOD_Plugin', 'dog_support_summary')
        ? SOD_Plugin::dog_support_summary($dog_id)
        : ['target' => 0.0, 'secured' => 0.0, 'remaining' => 0.0];
    $target = (float)($support['target'] ?? 0);
    $secured = (float)($support['secured'] ?? 0);
    return [
        'target' => $target,
        'secured' => $secured,
        'remaining' => max(0.0, $target - $secured),
        'percent' => $target > 0 ? min(100, max(0, (int)round($secured / $target * 100))) : 0,
        'sponsorable' => get_post_meta($dog_id, 'sod_show_sponsorship', true) === '1',
        'adoptable' => get_post_meta($dog_id, 'sod_show_adoption', true) === '1',
    ];
}

function sod_story_is_happy(int $dog_id): bool
{
    return get_post_meta($dog_id, 'sod_status', true) === 'vermittelt';
}

/** @return WP_Post[] */
function sod_story_all_dogs(): array
{
    static $dogs = null;
    if ($dogs === null) {
        $dogs = get_posts([
            'post_type' => 'sod_dog',
            'post_status' => 'publish',
            'numberposts' => 50,
            // Auch vermittelte Hunde bleiben in den Schicksalen; "Nur Schicksale" zaehlt ebenfalls.
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'sod_story_enabled', 'value' => '1'],
                ['key' => 'sod_story_only', 'value' => '1'],
            ],
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
    }
    return $dogs;
}

/** Hunde mit offener Patenschaft (fuer "Diese Hunde brauchen dich noch"). */
function sod_story_dogs_needing_sponsors(int $exclude_id, int $limit = 2): array
{
    $result = [];
    $dogs = get_posts([
        'post_type' => 'sod_dog',
        'post_status' => 'publish',
        'numberposts' => 30,
        'meta_key' => 'sod_show_sponsorship',
        'meta_value' => '1',
        'exclude' => [$exclude_id],
    ]);
    foreach ($dogs as $dog) {
        $support = sod_story_support($dog->ID);
        if ($support['target'] > 0 && $support['remaining'] > 0 && !sod_story_is_happy($dog->ID)) {
            $result[] = ['dog' => $dog, 'support' => $support];
        }
    }
    usort($result, static fn (array $a, array $b): int => $a['support']['percent'] <=> $b['support']['percent']);
    return array_slice($result, 0, $limit);
}

function sod_story_title(WP_Post $dog): string
{
    $title = trim((string)get_post_meta($dog->ID, 'sod_story_title', true));
    return $title !== '' ? $title : $dog->post_title;
}

function sod_story_image_url(WP_Post $dog, string $size = 'large'): string
{
    $url = has_post_thumbnail($dog->ID) ? (string)get_the_post_thumbnail_url($dog->ID, $size) : '';
    if ($url === '') {
        $videos = sod_story_videos($dog->ID);
        $url = $videos ? (string)$videos[0]['thumb'] : '';
    }
    return $url;
}

function sod_story_money(float $value): string
{
    return abs($value - round($value)) < 0.001 ? (string)(int)round($value) : number_format($value, 2, ',', '.');
}

function sod_story_video_label(int $count): string
{
    return sprintf(sod_t($count === 1 ? 'stories.video_count_one' : 'stories.video_count'), $count);
}

function sod_story_duration(int $seconds): string
{
    return $seconds > 0 ? sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60) : '';
}

/* ------------------------------------------------------------------ YouTube-Abruf */

function sod_story_playlist_id(int $dog_id): string
{
    $value = (string)get_post_meta($dog_id, 'sod_story_playlist', true) ?: (string)get_post_meta($dog_id, 'sod_youtube_playlist', true);
    return preg_match('~[?&]list=([A-Za-z0-9_-]{10,64})~', $value, $match) ? $match[1] : '';
}

function sod_story_http(string $url): string
{
    $response = wp_remote_get($url, [
        'timeout' => 20,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
        'headers' => ['Accept-Language' => 'de-AT,de;q=0.9', 'Cookie' => 'CONSENT=YES+1; SOCS=CAI'],
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return '';
    }
    return (string)wp_remote_retrieve_body($response);
}

/**
 * Liest Videos der Playlist (ID, Titel, Datum, Laenge) und kopiert Vorschaubilder.
 * Bei Fehlern bleibt der letzte Stand erhalten. Gibt eine Statusmeldung zurueck.
 */
/**
 * Einzelne YouTube-Videos (ein Link pro Zeile) – fuer Hunde ohne eigene Playlist,
 * z. B. wenn ihr Video in einer Sammel-Playlist wie "Adoption" liegt.
 * @return string[]
 */
function sod_story_extra_video_ids(int $dog_id): array
{
    $ids = [];
    foreach (preg_split('/\R/', (string)get_post_meta($dog_id, 'sod_story_extra_videos', true)) ?: [] as $line) {
        $line = trim($line);
        if (preg_match('~(?:youtu\.be/|[?&]v=|/shorts/|/embed/)([A-Za-z0-9_-]{11})~', $line, $m) || preg_match('~^([A-Za-z0-9_-]{11})$~', $line, $m)) {
            $ids[] = $m[1];
        }
    }
    return array_values(array_unique($ids));
}

function sod_story_sync_videos(int $dog_id): string
{
    $list_id = sod_story_playlist_id($dog_id);
    $extra_ids = sod_story_extra_video_ids($dog_id);
    if ($list_id === '' && !$extra_ids) {
        delete_post_meta($dog_id, 'sod_story_videos');
        return 'Keine Playlist und keine einzelnen Videos eingetragen.';
    }
    $ids = [];
    if ($list_id !== '') {
        $html = sod_story_http('https://www.youtube.com/playlist?list=' . rawurlencode($list_id));
        if ($html === '' || !preg_match_all('/"videoId":"([A-Za-z0-9_-]{11})"/', $html, $matches)) {
            update_post_meta($dog_id, 'sod_story_sync_status', 'Playlist nicht erreichbar (' . wp_date('d.m.Y H:i') . ').');
            return 'Playlist nicht erreichbar.';
        }
        $ids = $matches[1];
    }
    $ids = array_slice(array_values(array_unique(array_merge($ids, $extra_ids))), 0, SOD_STORY_MAX_VIDEOS);

    $known = [];
    foreach (sod_story_videos($dog_id) as $video) {
        $known[$video['id']] = $video;
    }
    $uploads = wp_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . 'sod-stories';
    $base = trailingslashit($uploads['baseurl']) . 'sod-stories';
    wp_mkdir_p($dir);

    $videos = [];
    foreach ($ids as $id) {
        $video = $known[$id] ?? null;
        if (!$video || $video['date'] === '') {
            $page = sod_story_http('https://www.youtube.com/watch?v=' . $id);
            if ($page === '') {
                continue;
            }
            $date = preg_match('/"publishDate":"(\d{4}-\d{2}-\d{2})/', $page, $m) ? $m[1] : (preg_match('/"uploadDate":"(\d{4}-\d{2}-\d{2})/', $page, $m) ? $m[1] : '');
            $title = preg_match('/<meta name="title" content="([^"]*)"/', $page, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
            $seconds = preg_match('/"lengthSeconds":"(\d+)"/', $page, $m) ? (int)$m[1] : 0;
            $video = [
                'id' => $id,
                'title' => trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/#[\p{L}\p{N}_]+/u', '', $title))),
                'date' => $date,
                'seconds' => $seconds,
                'thumb' => '',
            ];
        }
        $file = $dir . '/' . $id . '.jpg';
        if (!is_file($file)) {
            $image = wp_remote_get('https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg', ['timeout' => 15]);
            $body = is_wp_error($image) ? '' : (string)wp_remote_retrieve_body($image);
            $info = $body !== '' ? @getimagesizefromstring($body) : false;
            if ($info && ($info['mime'] ?? '') === 'image/jpeg') {
                file_put_contents($file, $body);
            }
        }
        $video['thumb'] = is_file($file) ? $base . '/' . $id . '.jpg' : '';
        $videos[] = $video;
    }
    if (!$videos) {
        update_post_meta($dog_id, 'sod_story_sync_status', 'Keine Videos gelesen (' . wp_date('d.m.Y H:i') . ').');
        return 'Keine Videos gelesen.';
    }
    update_post_meta($dog_id, 'sod_story_videos', wp_slash(wp_json_encode($videos, JSON_UNESCAPED_UNICODE)));
    $status = count($videos) . ' Videos abgerufen am ' . wp_date('d.m.Y H:i') . '.';
    update_post_meta($dog_id, 'sod_story_sync_status', $status);
    return $status;
}

add_action('sod_story_videos_weekly', static function (): void {
    foreach (sod_story_all_dogs() as $dog) {
        sod_story_sync_videos($dog->ID);
    }
});
add_action('init', static function (): void {
    if (!wp_next_scheduled('sod_story_videos_weekly')) {
        wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'weekly', 'sod_story_videos_weekly');
    }
});

/* ------------------------------------------------------------------ Admin */

add_action('add_meta_boxes_sod_dog', static function (): void {
    add_meta_box('sod_story_box', 'Schicksal (Geschichte)', 'sod_story_meta_box', 'sod_dog', 'normal', 'default');
});

function sod_story_meta_box(WP_Post $post): void
{
    wp_nonce_field('sod_story_save', 'sod_story_nonce');
    $chapters = sod_story_chapters($post->ID);
    if (!$chapters) {
        $chapters = [['title' => '', 'date' => '', 'text' => '', 'quote' => '']];
    }
    $videos = sod_story_videos($post->ID);
    $status = (string)get_post_meta($post->ID, 'sod_story_sync_status', true);
    ?>
    <style>
        .sod-story-admin label{display:block;font-weight:600;margin:12px 0 4px}
        .sod-story-admin input[type=text],.sod-story-admin input[type=date],.sod-story-admin textarea{width:100%}
        .sod-story-admin textarea{min-height:70px}
        .sod-story-chapter{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px 12px;margin-top:10px}
        .sod-story-chapter-head{display:flex;justify-content:space-between;align-items:center;font-weight:600}
        .sod-story-grid{display:grid;grid-template-columns:2fr 1fr;gap:10px}
        .sod-story-videos{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
        .sod-story-videos img{width:56px;height:42px;object-fit:cover;border-radius:3px}
    </style>
    <div class="sod-story-admin">
        <p class="description"><?php echo sod_story_is_public() ? '<strong style="color:#008a20">Schicksale sind öffentlich sichtbar.</strong>' : '<strong style="color:#b32d2e">Schicksale sind derzeit nur für das Team sichtbar</strong> – freischalten unter Hunde → Schicksale.'; ?> Die Geschichte erscheint auf der Hundeseite, auf der Seite „Geschichten“ und als Teaser auf der Startseite. Wird der Hund auf „Vermittelt“ gestellt, wandert die Geschichte automatisch zu den Happy Ends.</p>
        <label><input type="checkbox" name="sod_story_enabled" value="1" <?php checked(sod_story_enabled($post->ID)); ?>> Geschichte öffentlich anzeigen</label>
        <label for="sod_story_title">Überschrift</label>
        <input type="text" id="sod_story_title" name="sod_story_title" value="<?php echo esc_attr((string)get_post_meta($post->ID, 'sod_story_title', true)); ?>" placeholder="z. B. Kira – vom „Monster“ zum Mädchen, das wieder vertraut">
        <label for="sod_story_summary">Das Wichtigste in 20 Sekunden</label>
        <textarea id="sod_story_summary" name="sod_story_summary" placeholder="2–3 Sätze: was passiert ist und was der Hund jetzt braucht."><?php echo esc_textarea((string)get_post_meta($post->ID, 'sod_story_summary', true)); ?></textarea>
        <label for="sod_story_quote">Zitat (Startseite)</label>
        <input type="text" id="sod_story_quote" name="sod_story_quote" value="<?php echo esc_attr((string)get_post_meta($post->ID, 'sod_story_quote', true)); ?>">

        <label>Kapitel</label>
        <p class="description">Reihenfolge = Reihenfolge der Geschichte. „Ab Datum“ ordnet die Playlist-Videos automatisch zu: Ein Video landet im letzten Kapitel, das vor seinem Veröffentlichungsdatum beginnt.</p>
        <div data-sod-story-chapters>
            <?php foreach ($chapters as $index => $chapter) : ?>
                <div class="sod-story-chapter" data-sod-story-chapter>
                    <div class="sod-story-chapter-head"><span>Kapitel <span data-sod-story-n><?php echo (int)$index + 1; ?></span></span><button type="button" class="button-link-delete" data-sod-story-remove>Entfernen</button></div>
                    <div class="sod-story-grid">
                        <div><label>Titel</label><input type="text" name="sod_story_chapter_title[]" value="<?php echo esc_attr($chapter['title']); ?>"></div>
                        <div><label>Ab Datum</label><input type="date" name="sod_story_chapter_date[]" value="<?php echo esc_attr($chapter['date']); ?>"></div>
                    </div>
                    <label>Text</label>
                    <textarea name="sod_story_chapter_text[]"><?php echo esc_textarea($chapter['text']); ?></textarea>
                    <label>Zitat in diesem Kapitel (optional)</label>
                    <input type="text" name="sod_story_chapter_quote[]" value="<?php echo esc_attr($chapter['quote']); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p><button type="button" class="button" data-sod-story-add>+ Kapitel hinzufügen</button></p>

        <label for="sod_story_playlist">YouTube-Playlist dieses Hundes</label>
        <input type="text" id="sod_story_playlist" name="sod_story_playlist" value="<?php echo esc_attr((string)get_post_meta($post->ID, 'sod_story_playlist', true)); ?>" placeholder="https://www.youtube.com/playlist?list=…">
        <label for="sod_story_extra_videos">Einzelne YouTube-Videos (optional)</label>
        <textarea id="sod_story_extra_videos" name="sod_story_extra_videos" placeholder="https://www.youtube.com/shorts/…&#10;ein Link pro Zeile"><?php echo esc_textarea((string)get_post_meta($post->ID, 'sod_story_extra_videos', true)); ?></textarea>
        <p class="description">Für Hunde ohne eigene Playlist, z. B. wenn ihr Video in der Sammel-Playlist „Adoption“ liegt. Wird zusammen mit der Playlist angezeigt.</p>

        <label>Videos der Geschichte</label>
        <?php if ($videos) : ?>
            <div class="sod-story-videos"><?php foreach (array_slice($videos, 0, 20) as $video) : ?><img src="<?php echo esc_url($video['thumb']); ?>" alt="" title="<?php echo esc_attr($video['date'] . ' – ' . $video['title']); ?>"><?php endforeach; ?></div>
        <?php endif; ?>
        <p class="description"><?php echo esc_html($status !== '' ? $status : 'Noch nicht abgerufen.'); ?> Neue Videos werden wöchentlich automatisch geholt.</p>
        <label style="font-weight:400"><input type="checkbox" name="sod_story_resync" value="1"> Videos beim Speichern jetzt neu abrufen</label>
    </div>
    <script>
    (function () {
        var wrap = document.querySelector('[data-sod-story-chapters]');
        if (!wrap) return;
        function renumber() {
            wrap.querySelectorAll('[data-sod-story-chapter]').forEach(function (el, i) {
                el.querySelector('[data-sod-story-n]').textContent = i + 1;
            });
        }
        document.querySelector('[data-sod-story-add]').addEventListener('click', function () {
            var first = wrap.querySelector('[data-sod-story-chapter]');
            var copy = first.cloneNode(true);
            copy.querySelectorAll('input, textarea').forEach(function (field) { field.value = ''; });
            wrap.appendChild(copy);
            renumber();
        });
        wrap.addEventListener('click', function (event) {
            if (!event.target.closest('[data-sod-story-remove]')) return;
            var items = wrap.querySelectorAll('[data-sod-story-chapter]');
            var item = event.target.closest('[data-sod-story-chapter]');
            if (items.length === 1) {
                item.querySelectorAll('input, textarea').forEach(function (field) { field.value = ''; });
            } else {
                item.remove();
            }
            renumber();
        });
    })();
    </script>
    <?php
}

add_action('save_post_sod_dog', static function (int $post_id): void {
    if (!isset($_POST['sod_story_nonce']) || !wp_verify_nonce((string)$_POST['sod_story_nonce'], 'sod_story_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    update_post_meta($post_id, 'sod_story_enabled', isset($_POST['sod_story_enabled']) ? '1' : '0');
    update_post_meta($post_id, 'sod_story_title', sanitize_text_field(wp_unslash((string)($_POST['sod_story_title'] ?? ''))));
    update_post_meta($post_id, 'sod_story_summary', sanitize_textarea_field(wp_unslash((string)($_POST['sod_story_summary'] ?? ''))));
    update_post_meta($post_id, 'sod_story_quote', sanitize_text_field(wp_unslash((string)($_POST['sod_story_quote'] ?? ''))));

    $titles = (array)($_POST['sod_story_chapter_title'] ?? []);
    $dates = (array)($_POST['sod_story_chapter_date'] ?? []);
    $texts = (array)($_POST['sod_story_chapter_text'] ?? []);
    $quotes = (array)($_POST['sod_story_chapter_quote'] ?? []);
    $chapters = [];
    foreach ($titles as $index => $title) {
        $chapter = [
            'title' => sanitize_text_field(wp_unslash((string)$title)),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($dates[$index] ?? '')) ? (string)$dates[$index] : '',
            'text' => sanitize_textarea_field(wp_unslash((string)($texts[$index] ?? ''))),
            'quote' => sanitize_text_field(wp_unslash((string)($quotes[$index] ?? ''))),
        ];
        if ($chapter['title'] !== '' || $chapter['text'] !== '') {
            $chapters[] = $chapter;
        }
    }
    update_post_meta($post_id, 'sod_story_chapters', wp_slash(wp_json_encode($chapters, JSON_UNESCAPED_UNICODE)));

    // Playlist-Feld speichert das Plugin (Prioritaet 10) - danach abrufen, wenn neu oder gewuenscht.
    $playlist_raw = trim(wp_unslash((string)($_POST['sod_story_playlist'] ?? '')));
    $playlist_id = preg_match('~[?&]list=([A-Za-z0-9_-]{10,64})~', $playlist_raw, $pm) ? $pm[1] : (preg_match('~^[A-Za-z0-9_-]{10,64}$~', $playlist_raw) ? $playlist_raw : '');
    update_post_meta($post_id, 'sod_story_playlist', $playlist_id === '' ? '' : 'https://www.youtube.com/playlist?list=' . $playlist_id);
    update_post_meta($post_id, 'sod_story_extra_videos', sanitize_textarea_field(wp_unslash((string)($_POST['sod_story_extra_videos'] ?? ''))));
    $source = sod_story_playlist_id($post_id) . '|' . implode(',', sod_story_extra_video_ids($post_id));
    $synced = (string)get_post_meta($post_id, 'sod_story_synced_list', true);
    if ($source !== '|' && (isset($_POST['sod_story_resync']) || $source !== $synced)) {
        sod_story_sync_videos($post_id);
        update_post_meta($post_id, 'sod_story_synced_list', $source);
    } elseif ($source === '|' && $synced !== '') {
        delete_post_meta($post_id, 'sod_story_videos');
        delete_post_meta($post_id, 'sod_story_synced_list');
    }
}, 20);

/* ------------------------------------------------------------------ Frontend-Helfer */

add_action('wp_enqueue_scripts', static function (): void {
    if (!sod_story_visible() || (!is_front_page() && !is_page('geschichten') && !is_singular('sod_dog'))) {
        return;
    }
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('sod-stories', get_template_directory_uri() . '/assets/css/stories.css', ['sod-legacy-pages'], $version);
    wp_enqueue_script('sod-stories', get_template_directory_uri() . '/assets/js/stories.js', [], $version, true);
}, 30);

/** Seitentitel und Vorschaubild beim Teilen einer Geschichte. */
add_filter('pre_get_document_title', static function (string $title): string {
    if (!is_page('geschichten') || !sod_story_visible()) {
        return $title;
    }
    $dog = sod_story_current_dog();
    return ($dog ? sod_story_title($dog) : sod_t('stories.eyebrow') . ' – ' . sod_t('stories.title')) . ' | ' . sod_org_name();
}, 30);

add_action('template_redirect', static function (): void {
    if (is_page('geschichten') && sod_story_visible()) {
        remove_action('wp_head', 'sod_seo_head', 5);
    }
}, 20);

add_action('wp_head', static function (): void {
    if (!is_page('geschichten') || !sod_story_visible()) {
        return;
    }
    if (!sod_story_is_public()) {
        echo "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
    }
    $dog = sod_story_current_dog();
    if ($dog) {
        $title = sod_story_title($dog);
        $summary = (string)get_post_meta($dog->ID, 'sod_story_summary', true);
        $url = sod_story_url($dog);
        $image = sod_story_image_url($dog, 'large');
    } else {
        $title = sod_t('stories.title');
        $summary = sod_t('stories.lead');
        $url = sod_page_url('geschichten');
        $image = '';
        foreach (sod_story_all_dogs() as $story_dog) {
            $image = sod_story_image_url($story_dog, 'large');
            break;
        }
        if ($image === '' && function_exists('sod_share_default_image')) {
            $image = sod_share_default_image();
        }
    }
    // Einfache Anfuehrungszeichen: in "..." wuerde PHP "%1$s" als Variable $s lesen.
    printf('<link rel="canonical" href="%3$s">' . "\n" . '<meta property="og:type" content="article">' . "\n" . '<meta property="og:site_name" content="%4$s">' . "\n" . '<meta property="og:title" content="%1$s">' . "\n" . '<meta property="og:description" content="%2$s">' . "\n" . '<meta property="og:url" content="%3$s">' . "\n",
        esc_attr($title), esc_attr(wp_trim_words($summary, 40)), esc_url($url), esc_attr(sod_org_name()));
    if ($image !== '') {
        printf("<meta property=\"og:image\" content=\"%s\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n", esc_url($image));
    }
    if ($summary !== '') {
        printf("<meta name=\"description\" content=\"%s\">\n", esc_attr(wp_trim_words($summary, 40)));
    }
}, 5);

/** Karte einer Geschichte fuer Uebersicht. */
function sod_story_render_card(WP_Post $dog): void
{
    $happy = sod_story_is_happy($dog->ID);
    $support = sod_story_support($dog->ID);
    $videos = sod_story_videos($dog->ID);
    $summary = (string)get_post_meta($dog->ID, 'sod_story_summary', true);
    $image = sod_story_image_url($dog, 'medium_large');
    if ($happy) {
        $badge = ['happy', sod_t('stories.badge_happy')];
    } elseif ($support['sponsorable'] && $support['target'] > 0 && $support['remaining'] > 0) {
        $badge = ['need', sod_t('stories.badge_sponsor')];
    } else {
        $badge = ['home', sod_t('stories.badge_home')];
    }
    ?>
    <article class="sodst-card" data-sodst-type="<?php echo $happy ? 'happy' : 'need'; ?>">
        <a class="sodst-card-media" href="<?php echo esc_url(sod_story_url($dog)); ?>">
            <?php if ($image !== '') : ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($dog->post_title); ?>" loading="lazy"><?php endif; ?>
            <span class="sodst-badge sodst-badge-<?php echo esc_attr($badge[0]); ?>"><?php echo esc_html($badge[1]); ?></span>
            <?php if ($videos) : ?><span class="sodst-count">▶ <?php echo esc_html(sod_story_video_label(count($videos))); ?></span><?php endif; ?>
        </a>
        <div class="sodst-card-body">
            <h3><?php echo esc_html($dog->post_title); ?></h3>
            <?php if ($summary !== '') : ?><p><?php echo esc_html(wp_trim_words($summary, 26)); ?></p><?php endif; ?>
            <?php if (!$happy && $support['target'] > 0) : ?>
                <?php sod_story_render_progress($support, false); ?>
            <?php endif; ?>
            <div class="sodst-actions">
                <a class="btn btn-primary btn-sm" href="<?php echo esc_url(sod_story_url($dog)); ?>"><?php sod_te('stories.read'); ?></a>
                <?php if (!$happy && $support['sponsorable'] && $support['remaining'] > 0) : ?>
                    <a class="btn btn-secondary btn-sm" href="<?php echo esc_url(get_permalink($dog) . '#pate-werden'); ?>"><?php sod_te('stories.sponsor_short'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

function sod_story_render_progress(array $support, bool $detail): void
{
    $covered = $support['remaining'] <= 0;
    ?>
    <div class="sodst-progress<?php echo $detail ? ' is-detail' : ''; ?>">
        <div class="sodst-progress-top"><span><?php sod_te('stories.monthly'); ?><?php if ($detail) : ?> · <?php echo esc_html(sod_story_money($support['target'])); ?> €<?php endif; ?></span><span><?php echo esc_html($support['percent'] . ' %' . ($covered ? ' ✓' : '')); ?></span></div>
        <div class="sodst-bar"><i style="width:<?php echo (int)$support['percent']; ?>%"></i></div>
        <small><?php echo esc_html($covered ? sod_t('stories.covered') : sprintf(sod_t('stories.remaining'), sod_story_money($support['remaining']))); ?></small>
    </div>
    <?php
}

/** Hochkant-Videokarten (Shorts) eines Kapitels. */
function sod_story_render_videos(array $videos, string $dog_name): void
{
    if (!$videos) {
        return;
    }
    echo '<div class="sodst-shorts">';
    foreach ($videos as $video) {
        $video['title'] = sod_ct((string)($video['title'] ?? ''));
        $date = $video['date'] !== '' ? wp_date('d.m.', strtotime($video['date'])) : '';
        $wide = isset($video['vertical']) && !$video['vertical'];
        printf(
            '<a class="sodst-short%7$s" href="https://www.youtube.com/watch?v=%1$s" target="_blank" rel="noopener" data-sodst-video="%1$s" data-sodst-wide="%8$s" aria-label="%2$s">%3$s<span class="sodst-short-play" aria-hidden="true">▶</span>%4$s<span class="sodst-short-t">%5$s<small>%6$s</small></span></a>',
            esc_attr($video['id']),
            esc_attr(sprintf(sod_t('stories.play_aria'), $video['title'] !== '' ? $video['title'] : $dog_name)),
            $video['thumb'] !== '' ? '<img src="' . esc_url($video['thumb']) . '" alt="" loading="lazy">' : '',
            $video['seconds'] > 0 ? '<span class="sodst-short-len">' . esc_html(sod_story_duration((int)$video['seconds'])) . '</span>' : '',
            esc_html($video['title'] !== '' ? $video['title'] : $dog_name),
            esc_html($date),
            $wide ? ' is-wide' : '',
            $wide ? '1' : '0'
        );
    }
    echo '</div>';
}

/* ------------------------------------------------------------------ Hundeseite */

/** Mit Geschichte ersetzt die Geschichte die Beschreibung (sonst stuende der Text doppelt). */
add_filter('sod_dog_detail_description', static function (string $content, int $dog_id): string {
    return sod_story_visible() && sod_story_enabled($dog_id) && sod_story_chapters($dog_id) ? '' : $content;
}, 10, 2);

add_filter('sod_dog_detail_story', static function (string $html, int $dog_id): string {
    $dog = get_post($dog_id);
    if (!$dog instanceof WP_Post || !sod_story_enabled($dog_id) || !sod_story_visible()) {
        return $html;
    }
    ob_start();
    sod_story_render_preview_badge();
    sod_story_render_dog_section($dog);
    return $html . (string)ob_get_clean();
}, 10, 2);

/** Geschichte als Abschnitt der Hundeseite: Kurzfassung, Kapitel mit Videos, Jetzt-Kapitel, Teilen. */
function sod_story_render_dog_section(WP_Post $dog): void
{
    $name = $dog->post_title;
    $chapters = sod_story_chapters_with_videos($dog->ID);
    $summary = (string)get_post_meta($dog->ID, 'sod_story_summary', true);
    $support = sod_story_support($dog->ID);
    $happy = sod_story_is_happy($dog->ID);
    $needs_sponsor = !$happy && $support['sponsorable'] && $support['target'] > 0 && $support['remaining'] > 0;
    $playlist = sod_story_playlist_id($dog->ID);
    $videos = sod_story_videos($dog->ID);
    $url = (string)get_permalink($dog);
    $share_text = rawurlencode(sod_story_title($dog) . ' ' . $url);
    ?>
    <section class="sodst-dog" id="geschichte">
        <p class="sodst-eyebrow"><?php sod_te('stories.told_by'); ?><?php if ($videos) : ?> · <?php echo esc_html(sod_story_video_label(count($videos))); ?><?php endif; ?></p>
        <h2 class="sodst-dog-title"><?php echo esc_html(sod_story_title($dog)); ?></h2>
        <?php if ($summary !== '') : ?>
            <div class="sodst-tldr"><b><?php sod_te('stories.tldr'); ?></b><p><?php echo esc_html($summary); ?></p></div>
        <?php endif; ?>

        <?php $n = 0; foreach ($chapters as $chapter) : $n++; ?>
            <div class="sodst-chapter" data-n="<?php echo (int)$n; ?>">
                <?php if ($chapter['date'] !== '') : ?>
                    <div class="sodst-chapter-date"><?php echo esc_html(sprintf(sod_t('stories.chapter_from'), $n, sod_i18n_day_month((int)strtotime($chapter['date'])))); ?></div>
                <?php elseif ($chapter['title'] !== '') : ?>
                    <div class="sodst-chapter-date"><?php echo esc_html(sprintf(sod_t('stories.chapter_n'), $n)); ?></div>
                <?php endif; ?>
                <?php if ($chapter['title'] !== '') : ?><h3><?php echo esc_html($chapter['title']); ?></h3><?php endif; ?>
                <?php if ($chapter['text'] !== '') { echo wp_kses_post(wpautop(esc_html($chapter['text']))); } ?>
                <?php if ($chapter['quote'] !== '') : ?>
                    <figure class="sodst-quote"><span class="sodst-avatar" aria-hidden="true">D</span><blockquote><p>„<?php echo esc_html($chapter['quote']); ?>“</p><figcaption><?php sod_te('stories.quote_by'); ?></figcaption></blockquote></figure>
                <?php endif; ?>
                <?php sod_story_render_videos($chapter['videos'], $name); ?>
            </div>
        <?php endforeach; ?>

        <div class="sodst-chapter is-now<?php echo $happy ? ' is-happy' : ''; ?>" data-n="<?php echo (int)$n + 1; ?>">
            <div class="sodst-chapter-date"><?php echo esc_html(sprintf(sod_t('stories.chapter_now'), $n + 1)); ?></div>
            <?php if ($happy) : ?>
                <h3><?php echo esc_html(sprintf(sod_t('stories.now_happy_title'), $name)); ?></h3>
                <p><?php echo esc_html(sprintf(sod_t('stories.now_happy_text'), $name)); ?></p>
            <?php elseif ($needs_sponsor) : ?>
                <h3><?php echo esc_html(sprintf(sod_t('stories.now_sponsor_title'), $name)); ?></h3>
                <p><?php echo esc_html(sprintf(sod_t('stories.now_sponsor_text'), $name)); ?></p>
            <?php else : ?>
                <h3><?php sod_te('stories.now_home_title'); ?></h3>
                <p><?php echo esc_html(sprintf(sod_t('stories.now_home_text'), $name)); ?></p>
            <?php endif; ?>
        </div>

        <div class="sodst-dog-foot">
            <div class="sodst-share">
                <a class="is-wa" href="https://wa.me/?text=<?php echo esc_attr($share_text); ?>" target="_blank" rel="noopener">WhatsApp</a>
                <a class="is-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr(rawurlencode($url)); ?>" target="_blank" rel="noopener">Facebook</a>
                <button type="button" data-sodst-copy="<?php echo esc_url($url); ?>" data-sodst-copied="<?php echo esc_attr(sod_t('stories.copied')); ?>"><?php sod_te('stories.copy'); ?></button>
            </div>
            <?php if ($playlist !== '') : ?>
                <a class="sodst-dog-playlist" href="<?php echo esc_url('https://www.youtube.com/playlist?list=' . $playlist); ?>" target="_blank" rel="noopener"><?php sod_te('stories.playlist_btn'); ?> &rarr;</a>
            <?php endif; ?>
        </div>
        <div class="sodst-consent-template" hidden data-sodst-consent-text="<?php echo esc_attr(sod_t('stories.consent_text')); ?>" data-sodst-consent-btn="<?php echo esc_attr(sod_t('stories.consent_btn')); ?>" data-sodst-close="<?php echo esc_attr(sod_t('stories.close')); ?>"></div>
    </section>
    <?php
}

/* ------------------------------------------------------------------ Zusatz-Bereiche: Tierheim & Verbesserungen */

/**
 * Videos, die zu keinem einzelnen Hund gehoeren (Tierheim-Bau, technische Verbesserungen),
 * erscheinen als eigene Bereiche auf der Seite "Geschichten". Pflege unter Hunde → Schicksale-Videos.
 */
function sod_story_section_defaults(): array
{
    return [
        'tierheim' => [
            'title' => 'Unser Tierheim',
            'text' => 'Hier entsteht ein sicherer Ort für die Hunde. Verfolge den Bau Schritt für Schritt.',
            'playlist' => '',
            'extra' => '',
        ],
        'verbesserungen' => [
            'title' => 'Verbesserungen vor Ort',
            'text' => 'Neue Zwinger, Wasser, Strom, Wärme – was wir mit euren Spenden technisch verbessern.',
            'playlist' => '',
            'extra' => '',
        ],
    ];
}

function sod_story_sections(): array
{
    $saved = get_option('sod_story_sections');
    $saved = is_array($saved) ? $saved : [];
    $sections = [];
    foreach (sod_story_section_defaults() as $key => $defaults) {
        $sections[$key] = array_merge($defaults, ['videos' => [], 'status' => ''], (array)($saved[$key] ?? []));
    }
    return $sections;
}

function sod_story_ids_from_text(string $text): array
{
    $ids = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $line = trim($line);
        if (preg_match('~(?:youtu\.be/|[?&]v=|/shorts/|/embed/)([A-Za-z0-9_-]{11})~', $line, $m) || preg_match('~^([A-Za-z0-9_-]{11})$~', $line, $m)) {
            $ids[] = $m[1];
        }
    }
    return array_values(array_unique($ids));
}

/** Hochkant (Short) oder Querformat? YouTube liefert /shorts/<id> nur fuer Shorts ohne Weiterleitung aus. */
function sod_story_is_vertical(string $id): bool
{
    $response = wp_remote_get('https://www.youtube.com/shorts/' . $id, [
        'timeout' => 15,
        'redirection' => 0,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
        'headers' => ['Cookie' => 'CONSENT=YES+1; SOCS=CAI'],
    ]);
    return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
}

function sod_story_sync_section(string $key): string
{
    $sections = sod_story_sections();
    if (!isset($sections[$key])) {
        return '';
    }
    $section = $sections[$key];
    $list_id = preg_match('~[?&]list=([A-Za-z0-9_-]{10,64})~', (string)$section['playlist'], $m) ? $m[1] : '';
    $ids = [];
    if ($list_id !== '') {
        $html = sod_story_http('https://www.youtube.com/playlist?list=' . rawurlencode($list_id));
        if ($html !== '' && preg_match_all('/"videoId":"([A-Za-z0-9_-]{11})"/', $html, $matches)) {
            $ids = $matches[1];
        } else {
            $section['status'] = 'Playlist nicht erreichbar (' . wp_date('d.m.Y H:i') . ').';
        }
    }
    $ids = array_slice(array_values(array_unique(array_merge($ids, sod_story_ids_from_text((string)$section['extra'])))), 0, SOD_STORY_MAX_VIDEOS);
    $known = [];
    foreach ((array)$section['videos'] as $video) {
        $known[$video['id']] = $video;
    }
    $uploads = wp_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . 'sod-stories';
    $base = trailingslashit($uploads['baseurl']) . 'sod-stories';
    wp_mkdir_p($dir);
    $videos = [];
    foreach ($ids as $id) {
        $video = $known[$id] ?? null;
        if (!$video || $video['date'] === '') {
            $page = sod_story_http('https://www.youtube.com/watch?v=' . $id);
            if ($page === '') {
                continue;
            }
            $date = preg_match('/"publishDate":"(\d{4}-\d{2}-\d{2})/', $page, $mm) ? $mm[1] : (preg_match('/"uploadDate":"(\d{4}-\d{2}-\d{2})/', $page, $mm) ? $mm[1] : '');
            $title = preg_match('/<meta name="title" content="([^"]*)"/', $page, $mm) ? html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8') : '';
            $video = [
                'id' => $id,
                'title' => trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/#[\p{L}\p{N}_]+/u', '', $title))),
                'date' => $date,
                'seconds' => preg_match('/"lengthSeconds":"(\d+)"/', $page, $mm) ? (int)$mm[1] : 0,
                'thumb' => '',
                'vertical' => sod_story_is_vertical($id),
            ];
        }
        $file = $dir . '/' . $id . '.jpg';
        if (!is_file($file)) {
            $image = wp_remote_get('https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg', ['timeout' => 15]);
            $body = is_wp_error($image) ? '' : (string)wp_remote_retrieve_body($image);
            $info = $body !== '' ? @getimagesizefromstring($body) : false;
            if ($info && ($info['mime'] ?? '') === 'image/jpeg') {
                file_put_contents($file, $body);
            }
        }
        $video['thumb'] = is_file($file) ? $base . '/' . $id . '.jpg' : '';
        $videos[] = $video;
    }
    // Neueste zuerst
    usort($videos, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));
    $section['videos'] = $videos;
    if ($videos) {
        $section['status'] = count($videos) . ' Videos abgerufen am ' . wp_date('d.m.Y H:i') . '.';
    } elseif ($ids === [] && $list_id === '') {
        $section['status'] = 'Keine Playlist und keine Videos eingetragen.';
    }
    $all = get_option('sod_story_sections');
    $all = is_array($all) ? $all : [];
    $all[$key] = $section;
    update_option('sod_story_sections', $all, false);
    return (string)$section['status'];
}

add_action('sod_story_videos_weekly', static function (): void {
    foreach (array_keys(sod_story_section_defaults()) as $key) {
        sod_story_sync_section($key);
    }
}, 20);

add_action('admin_menu', static function (): void {
    add_submenu_page('edit.php?post_type=sod_dog', 'Schicksale', 'Schicksale', 'edit_others_posts', 'sod-story-sections', 'sod_story_sections_page');
});

function sod_story_sections_page(): void
{
    if (!current_user_can('edit_others_posts')) {
        return;
    }
    if (isset($_POST['sod_story_sections_nonce']) && wp_verify_nonce((string)$_POST['sod_story_sections_nonce'], 'sod_story_sections')) {
        $all = get_option('sod_story_sections');
        $all = is_array($all) ? $all : [];
        foreach (array_keys(sod_story_section_defaults()) as $key) {
            $input = (array)($_POST['section'][$key] ?? []);
            $all[$key] = array_merge((array)($all[$key] ?? []), [
                'title' => sanitize_text_field(wp_unslash((string)($input['title'] ?? ''))),
                'text' => sanitize_textarea_field(wp_unslash((string)($input['text'] ?? ''))),
                'playlist' => esc_url_raw(trim(wp_unslash((string)($input['playlist'] ?? '')))),
                'extra' => sanitize_textarea_field(wp_unslash((string)($input['extra'] ?? ''))),
                'hidden' => isset($input['hidden']) ? '1' : '0',
            ]);
        }
        update_option('sod_story_sections', $all, false);
        if (current_user_can('manage_options')) {
            update_option('sod_story_public', isset($_POST['sod_story_public']) ? '1' : '0', false);
        }
        foreach (array_keys(sod_story_section_defaults()) as $key) {
            sod_story_sync_section($key);
        }
        echo '<div class="notice notice-success"><p>Gespeichert und Videos abgerufen.</p></div>';
    }
    $sections = sod_story_sections();
    ?>
    <div class="wrap">
        <h1>Schicksale</h1>
        <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo sod_story_is_public() ? '#00a32a' : '#dba617'; ?>;padding:14px 16px;margin:16px 0;max-width:900px">
            <h2 style="margin:0 0 6px">Sichtbarkeit</h2>
            <?php if (current_user_can('manage_options')) : ?>
                <label style="font-size:15px"><input type="checkbox" name="sod_story_public" value="1" form="sod-story-sections-form" <?php checked(sod_story_is_public()); ?>> <strong>Schicksale öffentlich anzeigen</strong></label>
            <?php endif; ?>
            <p style="margin:6px 0 0">Aus: Nur angemeldete Redakteure und Admins sehen Geschichten-Seite, Abschnitt auf den Hundeseiten, Startseiten-Teaser, Menüpunkt „Schicksale“ und den Button „Schicksal“ (mit Hinweis „Vorschau“). Besucher sehen die Website unverändert.<br>Ein: Alles ist für alle sichtbar. Häkchen setzen und unten speichern – mehr ist nicht nötig.</p>
            <p style="margin:6px 0 0"><strong>Aktuell:</strong> <?php echo sod_story_is_public() ? 'öffentlich' : 'nur für das Team sichtbar'; ?> · <a href="<?php echo esc_url(sod_page_url('geschichten')); ?>" target="_blank">Geschichten-Seite ansehen</a></p>
        </div>
        <h2>Tierheim- &amp; Verbesserungs-Videos</h2>
        <p>Diese Videos gehören zu keinem einzelnen Hund. Sie erscheinen als eigene Bereiche auf der Seite <a href="<?php echo esc_url(sod_page_url('geschichten')); ?>" target="_blank">Geschichten</a> unter den Hunde-Geschichten. Neue Videos aus der Playlist kommen wöchentlich automatisch dazu. Hochkant-Videos (Shorts) und normale Videos werden erkannt.</p>
        <form method="post" id="sod-story-sections-form">
            <?php wp_nonce_field('sod_story_sections', 'sod_story_sections_nonce'); ?>
            <?php foreach ($sections as $key => $section) : ?>
                <h2 style="margin-top:28px"><?php echo esc_html($key === 'tierheim' ? 'Bereich 1: Tierheim' : 'Bereich 2: Technische Verbesserungen'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Überschrift</th><td><input type="text" class="regular-text" name="section[<?php echo esc_attr($key); ?>][title]" value="<?php echo esc_attr((string)$section['title']); ?>"></td></tr>
                    <tr><th scope="row">Kurzer Text</th><td><textarea class="large-text" rows="2" name="section[<?php echo esc_attr($key); ?>][text]"><?php echo esc_textarea((string)$section['text']); ?></textarea></td></tr>
                    <tr><th scope="row">YouTube-Playlist</th><td><input type="url" class="large-text" name="section[<?php echo esc_attr($key); ?>][playlist]" value="<?php echo esc_attr((string)$section['playlist']); ?>" placeholder="https://www.youtube.com/playlist?list=…"></td></tr>
                    <tr><th scope="row">Einzelne Videos</th><td><textarea class="large-text code" rows="3" name="section[<?php echo esc_attr($key); ?>][extra]" placeholder="ein YouTube-Link pro Zeile"><?php echo esc_textarea((string)$section['extra']); ?></textarea></td></tr>
                    <tr><th scope="row">Ausblenden</th><td><label><input type="checkbox" name="section[<?php echo esc_attr($key); ?>][hidden]" value="1" <?php checked(($section['hidden'] ?? '0') === '1'); ?>> Bereich vorübergehend nicht anzeigen</label></td></tr>
                    <tr><th scope="row">Stand</th><td><?php echo esc_html((string)$section['status'] ?: 'Noch nicht abgerufen.'); ?>
                        <?php if ($section['videos']) : ?><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px"><?php foreach (array_slice((array)$section['videos'], 0, 12) as $video) : ?><img src="<?php echo esc_url((string)$video['thumb']); ?>" alt="" title="<?php echo esc_attr($video['date'] . ' – ' . $video['title']); ?>" style="width:72px;height:54px;object-fit:cover;border-radius:3px"><?php endforeach; ?></div><?php endif; ?>
                    </td></tr>
                </table>
            <?php endforeach; ?>
            <?php submit_button('Speichern und Videos abrufen'); ?>
        </form>
    </div>
    <?php
}

/** Ausgabe der Zusatz-Bereiche auf der Seite "Geschichten". */
function sod_story_render_sections(): void
{
    foreach (sod_story_sections() as $key => $section) {
        if (($section['hidden'] ?? '0') === '1' || empty($section['videos'])) {
            continue;
        }
        $list_id = preg_match('~[?&]list=([A-Za-z0-9_-]{10,64})~', (string)$section['playlist'], $m) ? $m[1] : '';
        ?>
        <section class="sodst-extra sodst-extra-<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>">
            <div class="sodst-extra-head">
                <div>
                    <p class="sodst-eyebrow"><?php echo esc_html($key === 'tierheim' ? sod_t('stories.extra_tierheim') : sod_t('stories.extra_improvements')); ?> · <?php echo esc_html(sod_story_video_label(count($section['videos']))); ?></p>
                    <h2><?php echo esc_html(sod_ct((string)$section['title'])); ?></h2>
                    <?php if ((string)$section['text'] !== '') : ?><p class="sodst-lead"><?php echo esc_html(sod_ct((string)$section['text'])); ?></p><?php endif; ?>
                </div>
                <?php if ($list_id !== '') : ?><a class="sodst-dog-playlist" href="<?php echo esc_url('https://www.youtube.com/playlist?list=' . $list_id); ?>" target="_blank" rel="noopener"><?php sod_te('stories.playlist_btn'); ?> &rarr;</a><?php endif; ?>
            </div>
            <?php sod_story_render_videos((array)$section['videos'], sod_ct((string)$section['title'])); ?>
        </section>
        <?php
    }
}

/* ------------------------------------------------------------------ Vorschau nur fuer das Team */

function sod_story_render_preview_badge(): void
{
    if (sod_story_is_public()) {
        return;
    }
    echo '<p class="sodst-preview-badge">' . esc_html('Vorschau – nur für das Team sichtbar. Freischalten unter Hunde → Schicksale.') . '</p>';
}

/** Menuepunkt "Schicksale" fuer Gaeste ausblenden, solange nicht oeffentlich. */
add_filter('wp_nav_menu_objects', static function (array $items): array {
    if (sod_story_visible()) {
        return $items;
    }
    $url = untrailingslashit(sod_page_url('geschichten'));
    return array_values(array_filter($items, static fn ($item): bool => untrailingslashit((string)$item->url) !== $url));
});

/** Hundekarten: Button "Profil ansehen" heisst "Schicksal" (nur wenn sichtbar). */
add_filter('sod_dog_card_detail_button', static function (array $button, int $dog_id): array {
    if (!sod_story_visible()) {
        return $button;
    }
    $button['label'] = sod_t('dogcard.schicksal');
    if (sod_story_enabled($dog_id)) {
        $button['url'] = $button['url'] . '#geschichte';
    }
    return $button;
}, 10, 2);

/**
 * Solange die Schicksale nur fuer das Team sichtbar sind, zeigt /geschichten/ Besuchern
 * "nicht gefunden". Dann gehoert die Seite auch nicht in die Sitemap (17.09.2026).
 */
add_filter('wp_sitemaps_posts_query_args', static function (array $args, string $post_type): array {
    if ($post_type !== 'page' || sod_story_is_public()) {
        return $args;
    }
    $page = get_page_by_path('geschichten');
    if ($page) {
        $args['post__not_in'] = array_merge((array)($args['post__not_in'] ?? []), [(int)$page->ID]);
    }

    return $args;
}, 10, 2);
