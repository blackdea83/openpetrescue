<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Uebersetzung selbst erstellter Inhalte (29.09.2026).
 *
 * Feste Texte laufen ueber sod_t(). Alles, was das Team selbst eingibt (Hundebeschreibung,
 * Charakter, Patenschaftstext, Rasse/Alter/Geschlecht, Schicksale mit Kapiteln und Videotiteln,
 * Schicksale-Bereiche), steht nur auf Deutsch in der Datenbank. Dafuer gibt es ein zentrales
 * Woerterbuch: deutscher Text -> englische und bosnische Fassung (Option sod_i18n_dict).
 *
 * - Auf der oeffentlichen Seite wird in EN/BS jeder bekannte deutsche Text ersetzt.
 * - Fehlt eine Uebersetzung (neuer oder geaenderter Text), bleibt der deutsche Text stehen.
 * - Pflege unter Hunde -> Übersetzungen; fehlende Texte werden dort markiert.
 */

const SOD_I18N_OPTION = 'sod_i18n_dict';

/** Hunde-Felder mit frei eingegebenem Text. */
function sod_i18n_meta_fields(): array
{
    return [
        'sod_character' => 'Charakter',
        'sod_needs' => 'Braucht / Zuhause',
        'sod_sponsorship_text' => 'Patenschaftstext',
        'sod_breed' => 'Rasse / Typ',
        'sod_age' => 'Alter',
        'sod_gender' => 'Geschlecht',
        'sod_health' => 'Gesundheit',
        'sod_location' => 'Aufenthaltsort',
        'sod_castration_status' => 'Kastration',
        'sod_quarantine_status' => 'Quarantäne',
        'sod_story_title' => 'Schicksal: Überschrift',
        'sod_story_summary' => 'Schicksal: Kurzfassung',
        'sod_story_quote' => 'Schicksal: Zitat',
    ];
}

function sod_i18n_norm(string $text): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", $text));
}

/** @return array<string, array{de: string, en?: string, bs?: string}> */
function sod_i18n_dict(bool $fresh = false): array
{
    static $dict = null;
    if ($dict === null || $fresh) {
        $saved = get_option(SOD_I18N_OPTION, []);
        $dict = is_array($saved) ? $saved : [];
    }
    return $dict;
}

/** Uebersetzung eines deutschen Textes; null, wenn keine existiert. */
function sod_i18n_lookup(string $de, string $lang): ?string
{
    $norm = sod_i18n_norm($de);
    if ($norm === '' || $lang === 'de') {
        return null;
    }
    $entry = sod_i18n_dict()[md5($norm)] ?? null;
    $text = is_array($entry) ? trim((string)($entry[$lang] ?? '')) : '';
    return $text !== '' ? $text : null;
}

/**
 * Nur bei normalen Seitenaufrufen im Frontend uebersetzen. Nie im Admin, bei Cron, AJAX,
 * REST oder Formular-Absendungen: sonst koennten uebersetzte Werte zurueck in die
 * Datenbank geschrieben werden (z. B. beim YouTube-Abgleich der Schicksale).
 */
function sod_i18n_active(): bool
{
    if (!empty($GLOBALS['sod_i18n_force'])) {
        return sod_current_lang() !== 'de';
    }
    if (is_admin() || wp_doing_cron() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
        return false;
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'GET') {
        return false;
    }
    return sod_current_lang() !== 'de';
}

/** Oeffentliche Ausgabe: Uebersetzung, falls vorhanden, sonst Original. */
function sod_ct(string $de): string
{
    if ($de === '' || !sod_i18n_active()) {
        return $de;
    }
    return sod_i18n_lookup($de, sod_current_lang()) ?? $de;
}

/** Originalwert (deutsch) eines Feldes, auch waehrend uebersetzt wird. */
function sod_i18n_raw_meta(int $post_id, string $key): string
{
    $GLOBALS['sod_i18n_bypass'] = true;
    $value = (string)get_post_meta($post_id, $key, true);
    $GLOBALS['sod_i18n_bypass'] = false;
    return $value;
}

/** JSON-Listen (Kapitel, Videos): nur die Textfelder uebersetzen. */
function sod_i18n_translate_json_list(string $json, array $fields): string
{
    $items = json_decode($json, true);
    if (!is_array($items)) {
        return $json;
    }
    $changed = false;
    foreach ($items as &$item) {
        if (!is_array($item)) {
            continue;
        }
        foreach ($fields as $field) {
            if (isset($item[$field]) && is_string($item[$field]) && $item[$field] !== '') {
                $translated = sod_ct($item[$field]);
                if ($translated !== $item[$field]) {
                    $item[$field] = $translated;
                    $changed = true;
                }
            }
        }
    }
    unset($item);
    return $changed ? (string)wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $json;
}

add_filter('get_post_metadata', static function ($value, $object_id, $meta_key, $single) {
    if ($value !== null || !empty($GLOBALS['sod_i18n_bypass']) || !is_string($meta_key) || $meta_key === '') {
        return $value;
    }
    $json_fields = ['sod_story_chapters' => ['title', 'text', 'quote'], 'sod_story_videos' => ['title']];
    if (!isset(sod_i18n_meta_fields()[$meta_key]) && !isset($json_fields[$meta_key])) {
        return $value;
    }
    if (!sod_i18n_active() || get_post_type((int)$object_id) !== 'sod_dog') {
        return $value;
    }
    $raw = sod_i18n_raw_meta((int)$object_id, $meta_key);
    if ($raw === '') {
        return $value;
    }
    $translated = isset($json_fields[$meta_key]) ? sod_i18n_translate_json_list($raw, $json_fields[$meta_key]) : sod_ct($raw);
    return $translated === $raw ? $value : [$translated];
}, 10, 4);

/** Hundename (z. B. "Namenslos Schwarz weiß"). */
add_filter('the_title', static function ($title, $post_id = 0) {
    if (!is_string($title) || $title === '' || !$post_id || get_post_type((int)$post_id) !== 'sod_dog') {
        return $title;
    }
    return sod_ct($title);
}, 5, 2);

/** Beschreibung - greift auch fuer den automatisch erzeugten Auszug auf den Karten. */
add_filter('the_content', static function ($content) {
    return is_string($content) ? sod_ct($content) : $content;
}, 1);

/** Direkt ausgelesene Hunde-Objekte (get_posts/WP_Query): Name und Beschreibung ersetzen. */
add_filter('the_posts', static function ($posts) {
    if (!is_array($posts) || !sod_i18n_active()) {
        return $posts;
    }
    foreach ($posts as $post) {
        if ($post instanceof WP_Post && $post->post_type === 'sod_dog') {
            $post->post_title = sod_ct((string)$post->post_title);
            $post->post_content = sod_ct((string)$post->post_content);
        }
    }
    return $posts;
}, 20);

/** "15. März" / "March 15" / "15. mart" (optional mit Jahr) */
function sod_i18n_day_month(int $timestamp, bool $with_year = false): string
{
    if ($with_year) {
        $year = (int)gmdate('Y', $timestamp);
        $lang = function_exists('sod_current_lang') ? sod_current_lang() : 'de';
        return sod_i18n_day_month($timestamp) . ($lang === 'en' ? ', ' : ' ') . $year . ($lang === 'bs' ? '.' : '');
    }
    $lang = function_exists('sod_current_lang') ? sod_current_lang() : 'de';
    $month = (int)gmdate('n', $timestamp);
    $day = (int)gmdate('j', $timestamp);
    if ($lang === 'en') {
        $names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return $names[$month - 1] . ' ' . $day;
    }
    if ($lang === 'bs') {
        $names = ['januar', 'februar', 'mart', 'april', 'maj', 'juni', 'juli', 'august', 'septembar', 'oktobar', 'novembar', 'decembar'];
        return $day . '. ' . $names[$month - 1];
    }
    return wp_date('j. F', $timestamp);
}

/* ------------------------------------------------------------------ Quellen (was uebersetzt werden muss) */

/**
 * Alle aktuell verwendeten deutschen Texte.
 * @return array<string, array{de: string, group: string, where: string[]}>
 */
function sod_i18n_sources(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $out = [];
    $add = static function (string $de, string $group, string $where) use (&$out): void {
        $norm = sod_i18n_norm($de);
        // Nur echte Texte: mindestens ein Buchstabe, keine reinen Links.
        if ($norm === '' || !preg_match('/\p{L}/u', $norm) || preg_match('~^https?://\S+$~', $norm)) {
            return;
        }
        $key = md5($norm);
        if (!isset($out[$key])) {
            $out[$key] = ['de' => $norm, 'group' => $group, 'where' => []];
        }
        $out[$key]['where'][] = $where;
    };

    $GLOBALS['sod_i18n_bypass'] = true;
    $dogs = get_posts([
        'post_type' => 'sod_dog',
        'post_status' => ['publish', 'private'],
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);
    foreach ($dogs as $dog) {
        $group = $dog->post_title !== '' ? $dog->post_title : ('Hund #' . $dog->ID);
        // Einfache Namen ("Kira") bleiben gleich; beschreibende ("Namenslos Schwarz weiß") werden uebersetzt.
        if (str_contains(trim((string)$dog->post_title), ' ')) {
            $add((string)$dog->post_title, $group, 'Name');
        }
        $add((string)$dog->post_content, $group, 'Beschreibung');
        foreach (sod_i18n_meta_fields() as $key => $label) {
            $add((string)get_post_meta($dog->ID, $key, true), $group, $label);
        }
        $chapters = json_decode((string)get_post_meta($dog->ID, 'sod_story_chapters', true), true);
        foreach (is_array($chapters) ? $chapters : [] as $i => $chapter) {
            foreach (['title' => 'Titel', 'text' => 'Text', 'quote' => 'Zitat'] as $field => $label) {
                $add((string)($chapter[$field] ?? ''), $group, 'Kapitel ' . ((int)$i + 1) . ' ' . $label);
            }
        }
        $videos = json_decode((string)get_post_meta($dog->ID, 'sod_story_videos', true), true);
        foreach (is_array($videos) ? $videos : [] as $video) {
            $add((string)($video['title'] ?? ''), $group, 'Videotitel');
        }
    }
    $GLOBALS['sod_i18n_bypass'] = false;

    if (function_exists('sod_story_sections')) {
        foreach (sod_story_sections() as $key => $section) {
            $label = $key === 'tierheim' ? 'Tierheim' : 'Verbesserungen';
            $add((string)($section['title'] ?? ''), 'Schicksale-Bereiche', $label . ' Überschrift');
            $add((string)($section['text'] ?? ''), 'Schicksale-Bereiche', $label . ' Text');
            foreach ((array)($section['videos'] ?? []) as $video) {
                $add((string)($video['title'] ?? ''), 'Schicksale-Bereiche', $label . ' Videotitel');
            }
        }
    }
    $cache = $out;
    return $out;
}

function sod_i18n_is_missing(string $de, array $dict): bool
{
    $entry = $dict[md5(sod_i18n_norm($de))] ?? null;
    return !is_array($entry) || trim((string)($entry['en'] ?? '')) === '' || trim((string)($entry['bs'] ?? '')) === '';
}

function sod_i18n_missing_count(?string $group = null): int
{
    $dict = sod_i18n_dict();
    $n = 0;
    foreach (sod_i18n_sources() as $source) {
        if (($group === null || $source['group'] === $group) && sod_i18n_is_missing($source['de'], $dict)) {
            $n++;
        }
    }
    return $n;
}

/** Speichert Uebersetzungen: [['de' => ..., 'en' => ..., 'bs' => ...], ...]. */
function sod_i18n_store(array $rows): int
{
    $dict = sod_i18n_dict(true);
    $count = 0;
    foreach ($rows as $row) {
        $de = sod_i18n_norm((string)($row['de'] ?? ''));
        if ($de === '') {
            continue;
        }
        $key = md5($de);
        $en = sod_i18n_norm((string)($row['en'] ?? ''));
        $bs = sod_i18n_norm((string)($row['bs'] ?? ''));
        if ($en === '' && $bs === '') {
            unset($dict[$key]);
            continue;
        }
        // Vermerk "maschinell" nur behalten, solange der Text unveraendert bleibt.
        $old = is_array($dict[$key] ?? null) ? $dict[$key] : [];
        $auto = [];
        foreach (['en' => $en, 'bs' => $bs] as $lang => $text) {
            if (!empty($old['auto'][$lang]) && $text !== '' && $text === (string)($old[$lang] ?? '')) {
                $auto[$lang] = true;
            }
        }
        $dict[$key] = ['de' => $de, 'en' => $en, 'bs' => $bs] + ($auto ? ['auto' => $auto] : []);
        $count++;
    }
    update_option(SOD_I18N_OPTION, $dict, false);
    sod_i18n_dict(true);
    return $count;
}

/* ------------------------------------------------------------------ Admin: Hunde -> Übersetzungen */

add_action('admin_menu', static function (): void {
    $missing = sod_i18n_missing_count();
    add_submenu_page(
        'edit.php?post_type=sod_dog',
        'Übersetzungen',
        'Übersetzungen' . ($missing > 0 ? ' <span class="awaiting-mod">' . $missing . '</span>' : ''),
        'edit_others_posts',
        'sod-translations',
        'sod_i18n_admin_page'
    );
}, 30);

add_action('admin_post_sod_i18n_save', static function (): void {
    if (!current_user_can('edit_others_posts')) {
        wp_die('Keine Berechtigung.');
    }
    check_admin_referer('sod_i18n_save');
    $rows = [];
    foreach ((array)($_POST['sod_i18n'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rows[] = [
            'de' => sanitize_textarea_field(wp_unslash((string)($row['de'] ?? ''))),
            'en' => sanitize_textarea_field(wp_unslash((string)($row['en'] ?? ''))),
            'bs' => sanitize_textarea_field(wp_unslash((string)($row['bs'] ?? ''))),
        ];
    }
    $saved = sod_i18n_store($rows);
    $back = wp_get_referer() ?: admin_url('edit.php?post_type=sod_dog&page=sod-translations');
    wp_safe_redirect(add_query_arg('sod_i18n_saved', $saved, remove_query_arg('sod_i18n_saved', $back)));
    exit;
});

function sod_i18n_admin_page(): void
{
    if (!current_user_can('edit_others_posts')) {
        return;
    }
    $dict = sod_i18n_dict();
    $sources = sod_i18n_sources();
    $show_all = isset($_GET['alle']);
    $only_group = isset($_GET['gruppe']) ? sanitize_text_field(wp_unslash((string)$_GET['gruppe'])) : '';
    $missing_total = 0;
    $groups = [];
    foreach ($sources as $hash => $source) {
        $missing = sod_i18n_is_missing($source['de'], $dict);
        $missing_total += $missing ? 1 : 0;
        if ((!$show_all && !$missing) || ($only_group !== '' && $source['group'] !== $only_group)) {
            continue;
        }
        $groups[$source['group']][$hash] = $source + ['missing' => $missing];
    }
    $base = admin_url('edit.php?post_type=sod_dog&page=sod-translations');
    ?>
    <div class="wrap">
        <h1>Übersetzungen (Englisch / Bosnisch)</h1>
        <p>Hier stehen alle Texte, die ihr selbst eingegeben habt: Hundenamen, Beschreibungen, Charakter, Patenschaftstexte, Angaben wie Rasse oder Gesundheit, Schicksale mit Kapiteln und Videotiteln.
        Auf der englischen und bosnischen Seite wird automatisch die Übersetzung angezeigt. <strong>Fehlt eine Übersetzung</strong> – etwa nach einem neuen Hund oder einem geänderten Text –, erscheint dort vorerst der deutsche Text.</p>
        <?php if (function_exists('sod_gt_settings_box')) { sod_gt_settings_box(); } ?>
        <?php if (isset($_GET['sod_i18n_saved'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo (int)$_GET['sod_i18n_saved']; ?> Übersetzungen gespeichert.</p></div>
        <?php endif; ?>
        <p class="subsubsub">
            <a href="<?php echo esc_url($base); ?>"<?php echo !$show_all ? ' class="current"' : ''; ?>>Fehlend <span class="count">(<?php echo (int)$missing_total; ?>)</span></a> |
            <a href="<?php echo esc_url(add_query_arg('alle', '1', $base)); ?>"<?php echo $show_all ? ' class="current"' : ''; ?>>Alle <span class="count">(<?php echo count($sources); ?>)</span></a>
        </p>
        <div style="clear:both"></div>
        <?php if (!$groups) : ?>
            <p style="font-size:15px;margin-top:20px">✓ Alles übersetzt.<?php if (!$show_all) : ?> Maschinelle Übersetzungen findest du unter „Alle“ (markiert mit „maschinell“).<?php endif; ?></p>
        <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sod_i18n_save'); ?>
            <input type="hidden" name="action" value="sod_i18n_save">
            <?php $i = 0; foreach ($groups as $group => $rows) : ?>
                <h2 style="margin-top:28px"><?php echo esc_html($group); ?></h2>
                <table class="widefat striped" style="table-layout:fixed">
                    <thead><tr><th style="width:34%">Deutsch</th><th>Englisch</th><th>Bosnisch</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $hash => $row) : $i++; $entry = $dict[$hash] ?? []; $lines = max(2, min(12, (int)ceil(mb_strlen($row['de']) / 70) + substr_count($row['de'], "\n"))); ?>
                        <tr>
                            <td>
                                <small style="color:#646970"><?php echo esc_html(implode(' · ', array_unique($row['where']))); ?></small>
                                <?php if ($row['missing']) : ?> <span style="background:#fcf0f1;color:#b32d2e;border-radius:3px;padding:0 5px;font-size:11px">fehlt</span><?php endif; ?>
                                <div style="white-space:pre-wrap;margin-top:4px"><?php echo esc_html($row['de']); ?></div>
                                <input type="hidden" name="sod_i18n[<?php echo (int)$i; ?>][de]" value="<?php echo esc_attr($row['de']); ?>">
                            </td>
                            <?php foreach (['en', 'bs'] as $sod_lang) : ?>
                                <td>
                                    <?php if (!empty($entry['auto'][$sod_lang])) : ?><span style="display:inline-block;margin-bottom:3px;background:#f0f6fc;color:#2271b1;border-radius:3px;padding:0 5px;font-size:11px">maschinell – bitte prüfen</span><?php endif; ?>
                                    <textarea name="sod_i18n[<?php echo (int)$i; ?>][<?php echo esc_attr($sod_lang); ?>]" rows="<?php echo (int)$lines; ?>" style="width:100%"><?php echo esc_textarea((string)($entry[$sod_lang] ?? '')); ?></textarea>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
            <p class="submit" style="position:sticky;bottom:0;background:#f0f0f1;padding:12px 0"><button type="submit" class="button button-primary button-large">Übersetzungen speichern</button></p>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

/** Hinweis beim Bearbeiten eines Hundes, wenn Texte noch nicht uebersetzt sind. */
add_action('admin_notices', static function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'sod_dog' || empty($_GET['post'])) {
        return;
    }
    $dog = get_post((int)$_GET['post']);
    if (!$dog instanceof WP_Post || $dog->post_title === '') {
        return;
    }
    $missing = sod_i18n_missing_count($dog->post_title);
    if ($missing < 1) {
        return;
    }
    printf(
        '<div class="notice notice-info"><p>%d Text(e) dieses Hundes sind noch nicht auf Englisch/Bosnisch übersetzt. <a href="%s">Jetzt übersetzen</a> – bis dahin erscheint auf der englischen und bosnischen Seite der deutsche Text.</p></div>',
        (int)$missing,
        esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-translations&gruppe=' . rawurlencode($dog->post_title)))
    );
});
