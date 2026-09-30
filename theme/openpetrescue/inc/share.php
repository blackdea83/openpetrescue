<?php
/**
 * Teilen-Funktion (14.09.2026)
 *
 * - Schwebender Button "Seite teilen" auf allen Seiten (ueber dem Hilfe-Button).
 * - Teilen-Symbol auf jeder Hundekarte (per JS in .dog-card eingesetzt).
 * - Kasten "<Name> teilen" auf jeder Hundeseite (nach der Beschreibung).
 * - Dienste: WhatsApp, Facebook, Instagram, TikTok. Am Handy zuerst das Teilen-Menue des
 *   Telefons (dort sind Instagram/TikTok direkt dabei), sonst ein Fenster mit den vier Diensten.
 *   Instagram und TikTok haben keinen Link zum Teilen: Text + Link werden kopiert und die App geoeffnet.
 * - Keine Verbindung zu den Diensten, bevor jemand selbst klickt.
 * - SICHTBARKEIT: nur fuer das Team, bis unter Hunde → Teilen-Funktion freigeschaltet.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function sod_share_is_public(): bool
{
    return get_option('sod_share_public') === '1';
}

function sod_share_visible(): bool
{
    return !is_admin() && (sod_share_is_public() || current_user_can('edit_others_posts'));
}

/**
 * Geschlechtsform fuer die Teilen-Texte: m, f oder n (unbekannt).
 * Die Werte im Hundeprofil sind immer deutsch gespeichert, auch in den anderen Sprachen.
 */
function sod_share_gender_key(int $dog_id): string
{
    $gender = mb_strtolower(trim(function_exists('sod_i18n_raw_meta') ? sod_i18n_raw_meta($dog_id, 'sod_gender') : (string)get_post_meta($dog_id, 'sod_gender', true)));
    if (str_starts_with($gender, 'w') || str_starts_with($gender, 'f')) {
        return 'f';
    }
    if (str_starts_with($gender, 'm')) {
        return 'm';
    }

    return 'n';
}

/**
 * Kurzer Ausschnitt aus der Geschichte des Hundes ("Das Wichtigste in 20 Sekunden"),
 * moeglichst am Satzende abgeschnitten. Leer, wenn keine Geschichte hinterlegt ist.
 */
function sod_share_story_excerpt(int $dog_id, int $max = 150): string
{
    // Absichtlich unabhaengig davon, ob die Schicksale-Seite des Hundes freigeschaltet ist:
    // Die Kurzfassung ist ein eigener Text und steht fuer sich.
    $summary = trim((string)get_post_meta($dog_id, 'sod_story_summary', true));
    if ($summary === '') {
        return '';
    }
    $summary = trim(preg_replace('/\s+/u', ' ', $summary) ?? '');
    if (mb_strlen($summary) <= $max) {
        return $summary;
    }

    // Bis zum letzten vollstaendigen Satz kuerzen, sonst nach dem letzten Wort.
    $cut = mb_substr($summary, 0, $max);
    $end = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, '! ') ?: 0, mb_strrpos($cut, '? ') ?: 0);
    if ($end > 60) {
        return mb_substr($cut, 0, $end + 1);
    }
    $space = mb_strrpos($cut, ' ');

    return rtrim($space ? mb_substr($cut, 0, $space) : $cut, " ,;:-") . ' …';
}

/** Teilen-Daten eines Hundes (Titel und Text je nach Lage). */
function sod_share_dog_data(int $dog_id): array
{
    $name = sod_ct((string)get_post_field('post_title', $dog_id));
    $status = (string)get_post_meta($dog_id, 'sod_status', true);
    $needs_sponsor = false;
    if (class_exists('SOD_Plugin') && method_exists('SOD_Plugin', 'dog_support_summary') && get_post_meta($dog_id, 'sod_show_sponsorship', true) === '1') {
        $support = SOD_Plugin::dog_support_summary($dog_id);
        $needs_sponsor = (float)($support['target'] ?? 0) > 0 && (float)($support['remaining'] ?? 0) > 0;
    }
    if ($status === 'vermittelt') {
        $key = 'happy';
    } elseif ($needs_sponsor) {
        $key = 'sponsor';
    } elseif (get_post_meta($dog_id, 'sod_show_adoption', true) !== '1') {
        // Hunde, die (noch) nicht vermittelt werden, etwa weil sie in Behandlung sind.
        $key = 'care';
    } else {
        $key = 'home';
    }
    $image = has_post_thumbnail($dog_id) ? (string)get_the_post_thumbnail_url($dog_id, 'medium_large') : '';
    $g = sod_share_gender_key($dog_id);
    $title_key = $key === 'happy' ? 'share.dog_title_happy_' . $g : 'share.dog_title_' . $key;

    // Mit Geschichte: Ausschnitt voran, danach ein kurzer Satz zur Lage.
    // Ohne Geschichte: der vollstaendige Satz zur Lage.
    $excerpt = sod_share_story_excerpt($dog_id);
    $text = $excerpt !== ''
        ? $excerpt . ' ' . sod_t('share.dog_cta_' . $key . '_' . $g)
        : sprintf(sod_t('share.dog_text_' . $key . '_' . $g), $name);

    return [
        'title' => sprintf(sod_t($title_key), $name),
        'text' => $text,
        'url' => (string)get_permalink($dog_id),
        'image' => $image,
        'name' => $name,
    ];
}

/* ------------------------------------------------------------------ Assets & Beschriftungen */

add_action('wp_enqueue_scripts', static function (): void {
    if (!sod_share_visible()) {
        return;
    }
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('sod-share', get_template_directory_uri() . '/assets/css/share.css', [], $version);
    wp_enqueue_script('sod-share', get_template_directory_uri() . '/assets/js/share.js', [], $version, true);
    wp_localize_script('sod-share', 'sodShare', [
        'preview' => !sod_share_is_public(),
        'orgName' => sod_org_name(),
        'site' => [
            'title' => sod_t('share.site_title'),
            'text' => sod_t('share.site_text'),
        ],
        // Auf den Karten steht das Geschlecht als Text ("Geschlecht: Weiblich"),
        // daraus waehlt share.js die passende Form.
        'cardText' => [
            'm' => sod_t('share.card_text_m'),
            'f' => sod_t('share.card_text_f'),
            'n' => sod_t('share.card_text_n'),
        ],
        'l' => [
            'share' => sod_t('share.share'),
            'shareDog' => sod_t('share.share_dog'),
            'sitebtn' => sod_t('share.site_button'),
            'close' => sod_t('share.close'),
            'copy' => sod_t('share.copy'),
            'copied' => sod_t('share.copied'),
            'igHint' => sod_t('share.ig_hint'),
            'ttHint' => sod_t('share.tt_hint'),
            'preview' => sod_t('share.preview_badge'),
        ],
    ]);
});

/* ------------------------------------------------------------------ Hundeseite */

add_filter('sod_dog_detail_story', static function (string $html, int $dog_id): string {
    if (!sod_share_visible()) {
        return $html;
    }
    $d = sod_share_dog_data($dog_id);
    ob_start();
    ?>
    <div class="sodsh-box" data-sodsh-title="<?php echo esc_attr($d['title']); ?>" data-sodsh-text="<?php echo esc_attr($d['text']); ?>" data-sodsh-url="<?php echo esc_url($d['url']); ?>" data-sodsh-image="<?php echo esc_url($d['image']); ?>">
        <?php if (!sod_share_is_public()) : ?><span class="sodsh-preview"><?php sod_te('share.preview_badge'); ?></span><?php endif; ?>
        <strong class="sodsh-box-title"><?php echo esc_html(sprintf(sod_t('share.share_dog'), $d['name'])); ?></strong>
        <p><?php sod_te('share.box_text'); ?></p>
        <div class="sodsh-buttons">
            <button type="button" class="sodsh-btn is-wa" data-sodsh-service="whatsapp"><?php echo sod_share_icon('whatsapp'); ?>WhatsApp</button>
            <button type="button" class="sodsh-btn is-fb" data-sodsh-service="facebook"><?php echo sod_share_icon('facebook'); ?>Facebook</button>
            <button type="button" class="sodsh-btn is-ig" data-sodsh-service="instagram"><?php echo sod_share_icon('instagram'); ?>Instagram</button>
            <button type="button" class="sodsh-btn is-tt" data-sodsh-service="tiktok"><?php echo sod_share_icon('tiktok'); ?>TikTok</button>
        </div>
    </div>
    <?php
    return $html . (string)ob_get_clean();
}, 5, 2);

/* ------------------------------------------------------------------ Icons */

function sod_share_icon(string $name): string
{
    $paths = [
        'share' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7M16 6l-4-4-4 4M12 2v13"/>',
        'whatsapp' => '<path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .3-3.4-.7-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.2-1.6-1.2-3s.7-2.2 1-2.5c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.2.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.8-.2 1.4Z"/>',
        'facebook' => '<path fill="currentColor" d="M14 8V6.5c0-.7.5-1 1-1h2V2h-3c-3 0-4 2-4 4.5V8H8v3.5h2V22h4V11.5h2.7L17 8h-3Z"/>',
        'instagram' => '<path fill="none" stroke="currentColor" stroke-width="2" d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>',
        'tiktok' => '<path fill="currentColor" d="M16.5 3c.4 2.3 1.9 3.9 4.2 4.1v3.2c-1.5 0-2.9-.4-4.2-1.2v6.2a6 6 0 1 1-6-6h.6v3.3a2.8 2.8 0 1 0 2.2 2.7V3h3.2Z"/>',
    ];
    return '<svg class="sodsh-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Icons fuer JS (Hundekarten, Fenster, schwebender Button). */
add_action('wp_footer', static function (): void {
    if (!sod_share_visible()) {
        return;
    }
    echo '<template id="sodsh-icons">';
    foreach (['share', 'whatsapp', 'facebook', 'instagram', 'tiktok'] as $icon) {
        echo '<span data-icon="' . esc_attr($icon) . '">' . sod_share_icon($icon) . '</span>'; // phpcs:ignore -- statisches SVG
    }
    echo '</template>';
}, 5);

/* ------------------------------------------------------------------ Backend-Schalter */

/**
 * Format, in dem WhatsApp, Facebook und Co. Vorschaubilder erwarten (1200 x 630).
 * Bilder in anderen Seitenverhaeltnissen werden sonst am Handy beschnitten.
 */
const SOD_SHARE_IMAGE_SIZE = ['name' => 'sod-share', 'width' => 1200, 'height' => 630];

add_action('after_setup_theme', static function (): void {
    add_image_size(SOD_SHARE_IMAGE_SIZE['name'], SOD_SHARE_IMAGE_SIZE['width'], SOD_SHARE_IMAGE_SIZE['height'], true);
});

/**
 * Sorgt dafuer, dass es zu einem Bild die Fassung im Teilen-Format gibt, und legt
 * sie bei Bedarf an. Rueckgabe: Adresse der Teilen-Fassung oder '' wenn nicht moeglich.
 */
function sod_share_prepare_image(int $id): string
{
    $meta = wp_get_attachment_metadata($id);
    if (!is_array($meta)) {
        return '';
    }
    $name = SOD_SHARE_IMAGE_SIZE['name'];
    if (!empty($meta['sizes'][$name])) {
        return (string)(wp_get_attachment_image_url($id, $name) ?: '');
    }

    // Kleinere Bilder nicht hochrechnen - das wuerde nur unscharf aussehen.
    if ((int)($meta['width'] ?? 0) < SOD_SHARE_IMAGE_SIZE['width']) {
        return '';
    }
    $file = get_attached_file($id);
    if (!is_string($file) || !file_exists($file)) {
        return '';
    }
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return '';
    }
    $editor->resize(SOD_SHARE_IMAGE_SIZE['width'], SOD_SHARE_IMAGE_SIZE['height'], true);
    $saved = $editor->save();
    if (is_wp_error($saved) || empty($saved['file'])) {
        return '';
    }
    $meta['sizes'][$name] = [
        'file' => $saved['file'],
        'width' => (int)$saved['width'],
        'height' => (int)$saved['height'],
        'mime-type' => (string)$saved['mime-type'],
    ];
    wp_update_attachment_metadata($id, $meta);

    return (string)(wp_get_attachment_image_url($id, $name) ?: '');
}

/**
 * Ausgewaehlte Vorschaubilder (Mediathek-IDs) in gespeicherter Reihenfolge.
 */
function sod_share_image_ids(): array
{
    $raw = get_option('sod_share_image_ids', '');
    if (!is_string($raw) || $raw === '') {
        // Rueckfall auf die fruehere Einzelauswahl.
        $single = (int)get_option('sod_share_image_id', 0);
        return $single > 0 ? [$single] : [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));

    return $ids;
}

/**
 * Vorschaubild, das beim Teilen von Seiten ohne eigenes Bild erscheint.
 * Waehlbar unter "Hunde -> Teilen-Funktion"; ohne Auswahl bleibt es beim Hero-Bild.
 * Sind mehrere Bilder hinterlegt, wechseln sie automatisch: pro Tag und pro Seite ein
 * anderes Motiv, aber innerhalb eines Tages fuer dieselbe Seite immer dasselbe - sonst
 * wuerden Facebook und WhatsApp bei jedem Abruf ein anderes Bild sehen.
 */
function sod_share_default_image(): string
{
    $ids = sod_share_image_ids();
    if ($ids !== []) {
        $day = (int)floor((int)current_time('timestamp') / DAY_IN_SECONDS);
        $path = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $index = abs((int)crc32($path) + $day) % count($ids);
        // Fehlt ein Bild in der Mediathek, das naechste nehmen.
        for ($i = 0; $i < count($ids); $i++) {
            $id = $ids[($index + $i) % count($ids)];
            // Bevorzugt die Fassung im Teilen-Format, sonst das Bild selbst.
            $url = (string)(wp_get_attachment_image_url($id, SOD_SHARE_IMAGE_SIZE['name']) ?: '');
            if ($url === '') {
                $url = (string)(wp_get_attachment_image_url($id, 'full') ?: '');
            }
            if ($url !== '') {
                return $url;
            }
        }
    }

    return sod_asset('images/hero-large.jpg');
}

add_action('admin_menu', static function (): void {
    $hook = add_submenu_page('edit.php?post_type=sod_dog', 'Teilen-Funktion', 'Teilen-Funktion', 'manage_options', 'sod-share', 'sod_share_settings_page');
    add_action('admin_print_scripts-' . $hook, static function (): void {
        wp_enqueue_media();
    });
});

function sod_share_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['sod_share_nonce']) && wp_verify_nonce((string)$_POST['sod_share_nonce'], 'sod_share')) {
        update_option('sod_share_public', isset($_POST['sod_share_public']) ? '1' : '0', false);
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['sod_share_image_ids'] ?? ''))))));
        update_option('sod_share_image_ids', implode(',', $ids), false);
        update_option('sod_share_image_id', 0, false);

        // Fuer jedes Bild automatisch die Fassung im Teilen-Format anlegen.
        $ready = 0;
        $too_small = [];
        foreach ($ids as $id) {
            if (sod_share_prepare_image($id) !== '') {
                $ready++;
                continue;
            }
            $meta = wp_get_attachment_metadata($id);
            $too_small[] = get_the_title($id) . ' (' . (int)($meta['width'] ?? 0) . ' px)';
        }
        printf('<div class="notice notice-success"><p>Gespeichert. %d von %d Bildern stehen im Teilen-Format 1200 × 630 bereit.</p></div>', $ready, count($ids));
        if ($too_small) {
            printf('<div class="notice notice-warning"><p>Zu klein für das Teilen-Format und deshalb unverändert übernommen: %s. Am Handy werden solche Bilder klein oder beschnitten angezeigt.</p></div>', esc_html(implode(', ', $too_small)));
        }
    }
    $image_ids = sod_share_image_ids();
    ?>
    <div class="wrap">
        <h1>Teilen-Funktion</h1>
        <form method="post" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo sod_share_is_public() ? '#00a32a' : '#dba617'; ?>;padding:14px 16px;max-width:900px">
            <?php wp_nonce_field('sod_share', 'sod_share_nonce'); ?>
            <label style="font-size:15px"><input type="checkbox" name="sod_share_public" value="1" <?php checked(sod_share_is_public()); ?>> <strong>Teilen-Funktion öffentlich anzeigen</strong></label>
            <p>Aus: Nur angemeldete Redakteure und Admins sehen den Button „Seite teilen“, die Teilen-Symbole auf den Hundekarten und den Teilen-Kasten auf den Hundeseiten (mit Hinweis „Vorschau“). Ein: für alle sichtbar.</p>
            <p>Dienste: WhatsApp, Facebook, Instagram, TikTok – in Deutsch, Englisch und Bosnisch. Am Handy öffnet sich zuerst das Teilen-Menü des Telefons. Instagram und TikTok bieten keinen Teilen-Link an: Text und Link werden kopiert und die App geöffnet, dort einfach einfügen.</p>
            <p><strong>Aktuell:</strong> <?php echo sod_share_is_public() ? 'öffentlich' : 'nur für das Team sichtbar'; ?></p>
            <hr style="margin:18px 0">
            <h2 style="font-size:15px;margin:0 0 6px">Vorschaubilder beim Teilen</h2>
            <p style="margin-top:0">Diese Bilder zeigen WhatsApp, Facebook und Co. für alle Seiten ohne eigenes Bild (Startseite, Spenden, Patenschaft …). Hundeseiten verwenden weiterhin das erste Foto des Hundes. Empfohlen: mindestens 1200 × 630 Pixel, quer.</p>
            <p style="margin-top:0"><strong>Mehrere Bilder wechseln automatisch:</strong> Jeden Tag ist ein anderes Motiv an der Reihe, und verschiedene Seiten zeigen am selben Tag verschiedene Bilder. Innerhalb eines Tages bleibt eine Seite bei ihrem Bild, damit Facebook und WhatsApp keine wechselnden Vorschauen zwischenspeichern.</p>
            <input type="hidden" name="sod_share_image_ids" id="sod_share_image_ids" value="<?php echo esc_attr(implode(',', $image_ids)); ?>">
            <div id="sod_share_image_list" style="display:flex;flex-wrap:wrap;gap:12px;margin:10px 0">
                <?php
                foreach ($image_ids as $id) {
                    $thumb = wp_get_attachment_image_url($id, 'medium') ?: wp_get_attachment_image_url($id, 'full');
                    if (!$thumb) {
                        continue;
                    }
                    $meta = wp_get_attachment_metadata($id);
                    $width = (int)($meta['width'] ?? 0);
                    printf(
                        '<figure data-id="%1$d" style="margin:0;width:230px"><img src="%2$s" alt="" style="width:230px;height:121px;object-fit:cover;border:1px solid #c3c4c7;border-radius:4px"><figcaption style="display:flex;justify-content:space-between;align-items:center;gap:6px;margin-top:4px"><span style="%4$s">%3$s</span><button type="button" class="button-link sod-share-remove" style="color:#b32d2e">Entfernen</button></figcaption></figure>',
                        $id,
                        esc_url($thumb),
                        $width > 0 ? esc_html($width . ' px breit') : 'Größe unbekannt',
                        $width > 0 && $width < 1200 ? 'color:#b32d2e' : 'color:#50575e'
                    );
                }
                ?>
            </div>
            <p id="sod_share_image_empty" style="<?php echo $image_ids === [] ? '' : 'display:none'; ?>"><img src="<?php echo esc_url(sod_asset('images/hero-large.jpg')); ?>" alt="" style="max-width:300px;height:auto;border:1px solid #c3c4c7;border-radius:4px;opacity:.75"><br><span class="description">Kein eigenes Bild gewählt – es wird das Standardbild des Themes verwendet.</span></p>
            <p>
                <button type="button" class="button" id="sod_share_image_pick">Bilder auswählen</button>
                <button type="button" class="button button-link-delete" id="sod_share_image_reset" <?php disabled($image_ids === []); ?>>Alle entfernen</button>
            </p>
            <p class="description">Beim Speichern wird von jedem Bild automatisch eine Fassung im Format 1200 × 630 erzeugt und beim Teilen verwendet – so bleibt am Handy nichts Wichtiges weg. Bilder schmäler als 1200 Pixel sind rot markiert; sie können nicht umgerechnet werden und werden beim Teilen klein oder unscharf angezeigt. Nach dem Speichern zeigen Facebook und WhatsApp ein bereits geteiltes Bild oft noch einige Zeit weiter; das ist deren Zwischenspeicher.</p>
            <script>
            (function () {
                var field = document.getElementById('sod_share_image_ids');
                var list = document.getElementById('sod_share_image_list');
                var empty = document.getElementById('sod_share_image_empty');
                var reset = document.getElementById('sod_share_image_reset');
                var frame;

                function ids() {
                    return field.value ? field.value.split(',').filter(Boolean) : [];
                }
                function sync() {
                    var values = [];
                    list.querySelectorAll('figure').forEach(function (fig) { values.push(fig.dataset.id); });
                    field.value = values.join(',');
                    empty.style.display = values.length ? 'none' : '';
                    reset.disabled = values.length === 0;
                }
                function add(img) {
                    if (ids().indexOf(String(img.id)) !== -1) { return; }
                    var url = (img.sizes && img.sizes.medium ? img.sizes.medium.url : img.url);
                    var width = img.width || 0;
                    var fig = document.createElement('figure');
                    fig.dataset.id = img.id;
                    fig.style.cssText = 'margin:0;width:230px';
                    fig.innerHTML = '<img alt="" style="width:230px;height:121px;object-fit:cover;border:1px solid #c3c4c7;border-radius:4px">'
                        + '<figcaption style="display:flex;justify-content:space-between;align-items:center;gap:6px;margin-top:4px">'
                        + '<span style="color:' + (width && width < 1200 ? '#b32d2e' : '#50575e') + '"></span>'
                        + '<button type="button" class="button-link sod-share-remove" style="color:#b32d2e">Entfernen</button></figcaption>';
                    fig.querySelector('img').src = url;
                    fig.querySelector('span').textContent = width ? width + ' px breit' : 'Größe unbekannt';
                    list.appendChild(fig);
                }

                list.addEventListener('click', function (e) {
                    if (e.target.classList.contains('sod-share-remove')) {
                        e.target.closest('figure').remove();
                        sync();
                    }
                });
                document.getElementById('sod_share_image_pick').addEventListener('click', function () {
                    if (!frame) {
                        frame = wp.media({title: 'Vorschaubilder beim Teilen', library: {type: 'image'}, button: {text: 'Übernehmen'}, multiple: 'add'});
                        frame.on('select', function () {
                            frame.state().get('selection').map(function (item) { add(item.toJSON()); });
                            sync();
                        });
                    }
                    frame.open();
                });
                reset.addEventListener('click', function () {
                    list.innerHTML = '';
                    sync();
                });
            })();
            </script>
            <?php submit_button('Speichern', 'primary', 'submit', false); ?>
        </form>
    </div>
    <?php
}
