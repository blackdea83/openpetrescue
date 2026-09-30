<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automatische Uebersetzung neuer Inhalte mit Google Cloud Translation (Basic, v2) - 29.09.2026.
 *
 * - Uebersetzt nur FEHLENDE Texte (neuer Hund, geaenderter Text, neue Videotitel).
 *   Vorhandene oder vom Team korrigierte Uebersetzungen werden nie ueberschrieben.
 * - Laeuft kurz nach dem Speichern eines Hundes und einmal taeglich (neue YouTube-Titel).
 * - Maschinelle Uebersetzungen sind in Hunde -> Übersetzungen als "maschinell" markiert.
 * - Kostenbremse: hoechstens SOD_GT_MONTHLY_LIMIT Zeichen pro Monat (Gratiskontingent 500.000).
 * - Der API-Schluessel wird nur vom Team eingetragen und nie vollstaendig angezeigt.
 */

const SOD_GT_KEY_OPTION = 'sod_i18n_google_key';
const SOD_GT_AUTO_OPTION = 'sod_i18n_google_auto';
const SOD_GT_STATUS_OPTION = 'sod_i18n_google_status';
const SOD_GT_USAGE_OPTION = 'sod_i18n_google_usage';
const SOD_GT_MONTHLY_LIMIT = 450000;
const SOD_GT_CRON = 'sod_i18n_google_translate';

function sod_gt_key(): string
{
    return trim((string)get_option(SOD_GT_KEY_OPTION, ''));
}

function sod_gt_enabled(): bool
{
    return sod_gt_key() !== '' && get_option(SOD_GT_AUTO_OPTION, '1') === '1';
}

function sod_gt_usage_this_month(): int
{
    $usage = get_option(SOD_GT_USAGE_OPTION, []);
    return is_array($usage) ? (int)($usage[gmdate('Y-m')] ?? 0) : 0;
}

function sod_gt_add_usage(int $chars): void
{
    $usage = get_option(SOD_GT_USAGE_OPTION, []);
    $usage = is_array($usage) ? $usage : [];
    $month = gmdate('Y-m');
    $usage[$month] = (int)($usage[$month] ?? 0) + $chars;
    // Nur die letzten 12 Monate behalten.
    krsort($usage);
    update_option(SOD_GT_USAGE_OPTION, array_slice($usage, 0, 12, true), false);
}

function sod_gt_set_status(string $message, bool $ok): void
{
    update_option(SOD_GT_STATUS_OPTION, ['at' => time(), 'ok' => $ok, 'message' => $message], false);
}

/**
 * Uebersetzt eine Liste deutscher Texte in eine Zielsprache.
 * @param string[] $texts
 * @return string[]|WP_Error gleiche Reihenfolge wie $texts
 */
function sod_gt_request(array $texts, string $target)
{
    $key = sod_gt_key();
    if ($key === '') {
        return new WP_Error('sod_gt_no_key', 'Kein API-Schlüssel eingetragen.');
    }
    $body = 'source=de&target=' . rawurlencode($target) . '&format=text';
    foreach ($texts as $text) {
        $body .= '&q=' . rawurlencode($text);
    }
    $response = wp_remote_post('https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($key), [
        'timeout' => 25,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'],
        'body' => $body,
    ]);
    if (is_wp_error($response)) {
        return new WP_Error('sod_gt_http', 'Google nicht erreichbar: ' . $response->get_error_message());
    }
    $code = (int)wp_remote_retrieve_response_code($response);
    $data = json_decode((string)wp_remote_retrieve_body($response), true);
    if ($code !== 200) {
        $msg = is_array($data) ? (string)($data['error']['message'] ?? '') : '';
        return new WP_Error('sod_gt_api', 'Google meldet Fehler ' . $code . ($msg !== '' ? ': ' . $msg : '.'));
    }
    $items = is_array($data) ? ($data['data']['translations'] ?? null) : null;
    if (!is_array($items) || count($items) !== count($texts)) {
        return new WP_Error('sod_gt_format', 'Unerwartete Antwort von Google.');
    }
    $out = [];
    foreach ($items as $item) {
        $out[] = html_entity_decode((string)($item['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $out;
}

/** Hausbegriffe: auf der Seite heisst Patenschaft auf Bosnisch "pokroviteljstvo". */
function sod_gt_postprocess(string $text, string $lang): string
{
    if ($lang === 'bs') {
        $text = str_replace(['Sponzorstv', 'sponzorstv', 'Sponzor', 'sponzor'], ['Pokroviteljstv', 'pokroviteljstv', 'Pokrovitelj', 'pokrovitelj'], $text);
    }
    return trim($text);
}

/**
 * Uebersetzt alle fehlenden Texte. Gibt [uebersetzt, Fehlermeldung|null] zurueck.
 * @return array{0: int, 1: ?string}
 */
function sod_gt_translate_missing(): array
{
    if (sod_gt_key() === '') {
        return [0, 'Kein API-Schlüssel eingetragen.'];
    }
    $dict = sod_i18n_dict(true);
    $todo = ['en' => [], 'bs' => []];
    foreach (sod_i18n_sources() as $hash => $source) {
        foreach (['en', 'bs'] as $lang) {
            if (trim((string)($dict[$hash][$lang] ?? '')) === '') {
                $todo[$lang][$hash] = $source['de'];
            }
        }
    }
    $done = 0;
    foreach ($todo as $lang => $texts) {
        // Pakete: hoechstens 50 Texte bzw. ca. 20.000 Zeichen pro Anfrage.
        $batch = [];
        $batch_len = 0;
        $flush = static function () use (&$batch, &$batch_len, $lang, &$done): ?string {
            if (!$batch) {
                return null;
            }
            if (sod_gt_usage_this_month() + $batch_len > SOD_GT_MONTHLY_LIMIT) {
                return 'Monatsgrenze von ' . number_format(SOD_GT_MONTHLY_LIMIT, 0, ',', '.') . ' Zeichen erreicht – automatische Übersetzung pausiert bis nächsten Monat.';
            }
            $result = sod_gt_request(array_values($batch), $lang);
            if (is_wp_error($result)) {
                return $result->get_error_message();
            }
            sod_gt_add_usage($batch_len);
            $dict = sod_i18n_dict(true);
            $i = 0;
            foreach ($batch as $hash => $de) {
                $translation = sod_gt_postprocess((string)$result[$i++], $lang);
                if ($translation === '' || trim((string)($dict[$hash][$lang] ?? '')) !== '') {
                    continue; // nie vorhandene Uebersetzungen ueberschreiben
                }
                $entry = is_array($dict[$hash] ?? null) ? $dict[$hash] : ['de' => $de, 'en' => '', 'bs' => ''];
                $entry['de'] = $de;
                $entry[$lang] = $translation;
                $entry['auto'] = array_merge((array)($entry['auto'] ?? []), [$lang => true]);
                $dict[$hash] = $entry;
                $done++;
            }
            update_option(SOD_I18N_OPTION, $dict, false);
            sod_i18n_dict(true);
            $batch = [];
            $batch_len = 0;
            return null;
        };
        foreach ($texts as $hash => $de) {
            $len = mb_strlen($de);
            if ($batch && (count($batch) >= 50 || $batch_len + $len > 20000)) {
                $error = $flush();
                if ($error !== null) {
                    return [$done, $error];
                }
            }
            $batch[$hash] = $de;
            $batch_len += $len;
        }
        $error = $flush();
        if ($error !== null) {
            return [$done, $error];
        }
    }
    return [$done, null];
}

function sod_gt_run(): void
{
    if (!sod_gt_enabled()) {
        return;
    }
    [$done, $error] = sod_gt_translate_missing();
    if ($error !== null) {
        sod_gt_set_status($error . ($done ? ' (' . $done . ' Texte vorher übersetzt)' : ''), false);
    } elseif ($done > 0) {
        sod_gt_set_status($done . ' Texte automatisch übersetzt.', true);
    }
}
add_action(SOD_GT_CRON, 'sod_gt_run');

/** Kurz nach dem Speichern eines Hundes (im Hintergrund) und taeglich. */
add_action('save_post_sod_dog', static function (int $post_id): void {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !sod_gt_enabled()) {
        return;
    }
    if (!wp_next_scheduled(SOD_GT_CRON, ['single'])) {
        wp_schedule_single_event(time() + 15, SOD_GT_CRON, ['single']);
    }
}, 99);
add_action('update_option_sod_story_sections', static function (): void {
    if (sod_gt_enabled() && !wp_next_scheduled(SOD_GT_CRON, ['single'])) {
        wp_schedule_single_event(time() + 15, SOD_GT_CRON, ['single']);
    }
});
add_action('init', static function (): void {
    if (!wp_next_scheduled(SOD_GT_CRON)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', SOD_GT_CRON);
    }
});

/* ------------------------------------------------------------------ Einstellungen (Hunde -> Übersetzungen) */

add_action('admin_post_sod_gt_settings', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.');
    }
    check_admin_referer('sod_gt_settings');
    $do = sanitize_key((string)($_POST['sod_gt_do'] ?? 'save'));
    $notice = '';
    if ($do === 'save') {
        $new_key = trim(sanitize_text_field(wp_unslash((string)($_POST['sod_gt_key'] ?? ''))));
        if (!empty($_POST['sod_gt_remove'])) {
            delete_option(SOD_GT_KEY_OPTION);
            $notice = 'Schlüssel entfernt.';
        } elseif ($new_key !== '') {
            update_option(SOD_GT_KEY_OPTION, $new_key, false);
            $notice = 'Schlüssel gespeichert.';
        }
        update_option(SOD_GT_AUTO_OPTION, !empty($_POST['sod_gt_auto']) ? '1' : '0', false);
        $notice = $notice !== '' ? $notice : 'Einstellungen gespeichert.';
    } elseif ($do === 'test') {
        $result = sod_gt_request(['Der Hund sucht ein Zuhause.'], 'bs');
        if (is_wp_error($result)) {
            $notice = 'Test fehlgeschlagen: ' . $result->get_error_message();
            sod_gt_set_status($notice, false);
        } else {
            sod_gt_add_usage(27);
            $notice = 'Verbindung klappt. Test: „Der Hund sucht ein Zuhause.“ → „' . $result[0] . '“';
            sod_gt_set_status('Verbindung getestet.', true);
        }
    } elseif ($do === 'run') {
        [$done, $error] = sod_gt_translate_missing();
        $notice = $error !== null ? $done . ' Texte übersetzt, dann abgebrochen: ' . $error : $done . ' fehlende Texte übersetzt.';
        sod_gt_set_status($notice, $error === null);
    }
    $back = admin_url('edit.php?post_type=sod_dog&page=sod-translations');
    wp_safe_redirect(add_query_arg('sod_gt_notice', rawurlencode($notice), $back));
    exit;
});

function sod_gt_settings_box(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $key = sod_gt_key();
    $status = get_option(SOD_GT_STATUS_OPTION, []);
    $usage = sod_gt_usage_this_month();
    ?>
    <div class="card" style="max-width:none;margin:16px 0 8px;padding:14px 18px">
        <h2 style="margin-top:0">Automatische Übersetzung (Google Cloud Translation)</h2>
        <?php if (isset($_GET['sod_gt_notice']) && $_GET['sod_gt_notice'] !== '') : ?>
            <div class="notice notice-info inline"><p><?php echo esc_html(wp_unslash((string)$_GET['sod_gt_notice'])); ?></p></div>
        <?php endif; ?>
        <p>Neue oder geänderte Texte werden kurz nach dem Speichern eines Hundes und einmal täglich automatisch übersetzt. Es werden nur <strong>fehlende</strong> Übersetzungen ergänzt – eure eigenen Korrekturen bleiben immer erhalten. Maschinelle Übersetzungen sind unten mit „maschinell“ markiert.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sod_gt_settings'); ?>
            <input type="hidden" name="action" value="sod_gt_settings">
            <table class="form-table" role="presentation" style="margin-top:0">
                <tr>
                    <th scope="row"><label for="sod_gt_key">API-Schlüssel</label></th>
                    <td>
                        <input type="password" id="sod_gt_key" name="sod_gt_key" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr($key !== '' ? 'gespeichert (…' . substr($key, -4) . ') – zum Ändern neu eingeben' : 'AIza…'); ?>">
                        <?php if ($key !== '') : ?><label style="margin-left:10px"><input type="checkbox" name="sod_gt_remove" value="1"> Schlüssel entfernen</label><?php endif; ?>
                        <p class="description">Google Cloud Console → „Cloud Translation API“ aktivieren → Anmeldedaten → API-Schlüssel erstellen und auf „Cloud Translation API“ beschränken.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Automatisch</th>
                    <td><label><input type="checkbox" name="sod_gt_auto" value="1" <?php checked(get_option(SOD_GT_AUTO_OPTION, '1'), '1'); ?>> Neue Texte automatisch übersetzen</label></td>
                </tr>
                <tr>
                    <th scope="row">Verbrauch</th>
                    <td><?php echo esc_html(number_format($usage, 0, ',', '.')); ?> von <?php echo esc_html(number_format(SOD_GT_MONTHLY_LIMIT, 0, ',', '.')); ?> Zeichen in diesem Monat (Google-Gratiskontingent: 500.000)
                        <?php if (is_array($status) && !empty($status['message'])) : ?>
                            <br><span style="color:<?php echo !empty($status['ok']) ? '#008a20' : '#b32d2e'; ?>">Zuletzt (<?php echo esc_html(wp_date('d.m.Y H:i', (int)$status['at'])); ?>): <?php echo esc_html((string)$status['message']); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" name="sod_gt_do" value="save" class="button button-primary">Speichern</button>
                <?php if ($key !== '') : ?>
                    <button type="submit" name="sod_gt_do" value="test" class="button">Verbindung testen</button>
                    <button type="submit" name="sod_gt_do" value="run" class="button">Jetzt alle fehlenden übersetzen</button>
                <?php endif; ?>
            </p>
        </form>
    </div>
    <?php
}
