<?php
/**
 * Rettungs-Zaehler im Startseiten-Hero (28.09.2026)
 *
 * Zwei Aussagen:
 * - "allzeit gerettet": Gesamtzahl seit Gruendung. Wird im Backend gepflegt (Hunde ->
 *   Rettungs-Zaehler), weil fruehere Hunde nicht alle in der Seite stehen. Start: 50.
 * - "gerade beschuetzt": alle aktuell veroeffentlichten Hunde - automatisch gezaehlt.
 *   Davon versorgt = vermittelt oder Patenschaft zu 100 % gedeckt, die uebrigen
 *   brauchen noch Paten (je Hund eine Pfote, gruen oder orange).
 *
 * SICHTBARKEIT: vorerst nur fuer Admins. Freischalten unter Hunde -> Rettungs-Zaehler.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SOD_RESCUE_CACHE_KEY = 'sod_rescue_counts';

function sod_rescue_is_public(): bool
{
    return get_option('sod_rescue_counter_public') === '1';
}

function sod_rescue_visible(): bool
{
    return sod_rescue_is_public() || current_user_can('manage_options');
}

/** Frueher gerettete Hunde, die nicht (mehr) auf der Seite stehen - im Backend gepflegt. */
function sod_rescue_alltime_base(): int
{
    return max(0, (int)get_option('sod_rescue_alltime', 50));
}

/**
 * Allzeit gerettet = frueher gerettet (Backend) + alle auf der Seite als vermittelt
 * markierten Hunde. Wird ein Hund vermittelt, zaehlt er ab dann automatisch hier mit
 * und verschwindet aus "gerade beschuetzt" (28.09.2026).
 */
function sod_rescue_alltime(): int
{
    return sod_rescue_alltime_base() + (int)(sod_rescue_counts()['adopted'] ?? 0);
}

/**
 * Zahlen fuer den Zaehler. Fuenf Minuten zwischengespeichert, beim Speichern eines
 * Hundes oder einer Patenschaft sofort neu berechnet.
 *
 * @return array{rescued: int, need: int, total: int, rescued_names: array<int, string>, need_names: array<int, string>}
 */
function sod_rescue_counts(): array
{
    $cached = get_transient(SOD_RESCUE_CACHE_KEY);
    if (is_array($cached) && isset($cached['rescued'], $cached['need'], $cached['adopted'])) {
        return $cached;
    }

    $rescued = [];
    $need = [];
    $adopted = [];
    $dogs = get_posts([
        'post_type' => 'sod_dog',
        'post_status' => 'publish',
        'numberposts' => -1,
        'no_found_rows' => true,
    ]);
    foreach ($dogs as $dog) {
        if (get_post_meta($dog->ID, 'sod_deceased', true) === '1') {
            // Verstorbene Hunde zaehlen weder als gerettet noch als hilfsbeduerftig.
            continue;
        }
        $status = (string)get_post_meta($dog->ID, 'sod_status', true);
        if ($status !== 'vermittelt' && get_post_meta($dog->ID, 'sod_story_only', true) === '1') {
            // "Nur Schicksale": kein aktueller Schuetzling.
            continue;
        }
        $funded = false;
        if (class_exists('SOD_Plugin') && method_exists('SOD_Plugin', 'dog_support_summary')) {
            $support = SOD_Plugin::dog_support_summary($dog->ID);
            $funded = (float)($support['target'] ?? 0) > 0 && (float)($support['remaining'] ?? 0) < 1;
        }
        if ($status === 'vermittelt') {
            // Vermittelt: zaehlt zu "allzeit gerettet", nicht mehr zu "gerade beschuetzt".
            $adopted[] = $dog->post_title;
        } elseif ($funded) {
            $rescued[] = $dog->post_title;
        } else {
            $need[] = $dog->post_title;
        }
    }

    $counts = [
        'rescued' => count($rescued),
        'need' => count($need),
        'total' => count($rescued) + count($need),
        'rescued_names' => $rescued,
        'need_names' => $need,
        'adopted' => count($adopted),
        'adopted_names' => $adopted,
    ];
    set_transient(SOD_RESCUE_CACHE_KEY, $counts, 5 * MINUTE_IN_SECONDS);

    return $counts;
}

// Aenderungen an Hunden oder Patenschaften: Zaehler sofort neu berechnen.
foreach (['save_post_sod_dog', 'save_post_sod_sponsor', 'deleted_post', 'updated_post_meta', 'added_post_meta'] as $sod_rescue_hook) {
    add_action($sod_rescue_hook, static function ($id = 0, $post_or_meta = null, $meta_key = ''): void {
        // Bei Meta-Aenderungen nur reagieren, wenn es um Status, Patenschaft oder Zahlung geht.
        if (is_string($meta_key) && $meta_key !== '' && !preg_match('/^sod_(status|sponsor_|show_sponsorship|sponsorship_amount|monthly_)/', $meta_key)) {
            return;
        }
        delete_transient(SOD_RESCUE_CACHE_KEY);
    }, 10, 3);
}

/**
 * Ausgabe im Hero. $place = 'inline' (unter den Knoepfen, kleinere Bildschirme) oder
 * 'side' (rechts unter dem Teaming-Kreis, ab 1440 px). Beide werden ausgegeben, das
 * CSS zeigt je nach Bildschirmbreite genau eine davon.
 */
function sod_rescue_banner(string $place = 'inline'): void
{
    if (!sod_rescue_visible()) {
        return;
    }
    $c = sod_rescue_counts();
    $alltime = sod_rescue_alltime();
    if ($c['total'] === 0 && $alltime === 0) {
        return;
    }
    $paw = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 13.2c-2.6 0-5.4 2.6-5.4 5 0 1.5 1.3 2.3 2.7 2.3 1 0 1.8-.5 2.7-.5s1.7.5 2.7.5c1.4 0 2.7-.8 2.7-2.3 0-2.4-2.8-5-5.4-5ZM6.2 11.6c1.2-.3 1.8-1.9 1.4-3.5-.4-1.6-1.8-2.6-3-2.3-1.2.3-1.8 1.9-1.4 3.5.4 1.6 1.8 2.6 3 2.3Zm11.6 0c1.2.3 2.6-.7 3-2.3.4-1.6-.2-3.2-1.4-3.5-1.2-.3-2.6.7-3 2.3-.4 1.6.2 3.2 1.4 3.5ZM9.4 8.4c1.4 0 2.4-1.6 2.3-3.4-.1-1.8-1.2-3.2-2.6-3.1-1.4 0-2.4 1.6-2.3 3.4.1 1.8 1.2 3.2 2.6 3.1Zm5.2 0c1.4.1 2.5-1.3 2.6-3.1.1-1.8-.9-3.4-2.3-3.4-1.4-.1-2.5 1.3-2.6 3.1-.1 1.8.9 3.4 2.3 3.4Z"/></svg>';
    ?>
    <div class="sod-rescue sod-rescue--<?php echo $place === 'side' ? 'side' : 'inline'; ?>" data-sod-rescue aria-label="<?php echo esc_attr(sprintf(sod_t('rescue.aria_tpl'), $alltime, $c['total'], $c['need'])); ?>">
        <?php if (!sod_rescue_is_public()) : ?>
            <span class="sod-rescue-preview"><?php sod_te('rescue.preview'); ?></span>
        <?php endif; ?>
        <div class="sod-rescue-kicker"><span class="sod-rescue-live" aria-hidden="true"></span><?php sod_te('rescue.kicker'); ?></div>
        <div class="sod-rescue-title">
            <span class="sod-rescue-green"><span data-sod-count="<?php echo esc_attr((string)$alltime); ?>"><?php echo esc_html((string)$alltime); ?></span> <?php sod_te('rescue.alltime_unit'); ?></span>
            <?php sod_te('rescue.alltime_text'); ?>
        </div>
        <?php if ($c['total'] > 0) : ?>
            <p class="sod-rescue-sub">
                <?php
                echo wp_kses(sprintf(
                    $c['need'] > 0 ? sod_t('rescue.current_tpl') : sod_t('rescue.current_all_tpl'),
                    '<strong class="sod-rescue-white">' . (int)$c['total'] . '</strong>',
                    '<strong class="sod-rescue-amber">' . (int)$c['need'] . '</strong>'
                ), ['strong' => ['class' => true]]);
                ?>
            </p>
            <div class="sod-rescue-paws" role="img" aria-label="<?php echo esc_attr(sprintf(sod_t('rescue.paws_label'), $c['rescued'], $c['need'])); ?>">
                <?php for ($i = 0; $i < $c['total']; $i++) : ?>
                    <span class="sod-rescue-paw <?php echo $i < $c['rescued'] ? 'is-safe' : 'is-waiting'; ?>"><?php echo $paw; // statisches SVG ?></span>
                <?php endfor; ?>
            </div>
            <div class="sod-rescue-legend">
                <span><i class="is-safe"></i><?php sod_te('rescue.legend_safe'); ?></span>
                <span><i class="is-waiting"></i><?php sod_te('rescue.legend_waiting'); ?></span>
            </div>
        <?php endif; ?>
        <div class="sod-rescue-foot">
            <?php if ($c['need'] > 0) : ?>
                <a class="sod-rescue-cta" href="<?php echo esc_url(sod_page_url('patenschaft')); ?>#patenschaft"><?php echo esc_html(sprintf(sod_t('rescue.cta_tpl'), $c['need'])); ?></a>
            <?php endif; ?>
            <span class="sod-rescue-note"><?php sod_te('rescue.note'); ?></span>
        </div>
    </div>
    <?php
}

add_action('wp_head', static function (): void {
    if (!is_front_page() || !sod_rescue_visible()) {
        return;
    }
    ?>
    <style>
    .sod-rescue{position:relative;margin-top:var(--sp-8,32px);max-width:620px;padding:20px 22px 18px;border-radius:20px;background:linear-gradient(135deg,rgba(10,28,43,.9),rgba(10,28,43,.62));border:1px solid rgba(255,255,255,.14);box-shadow:0 20px 60px rgba(0,0,0,.35);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
    .sod-rescue-preview{position:absolute;top:-10px;right:14px;font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;background:#ffb02e;color:#0b1c2b;border-radius:999px;padding:2px 9px}
    .sod-rescue-kicker{display:flex;align-items:center;gap:8px;font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9fb0bf}
    .sod-rescue-live{width:8px;height:8px;border-radius:50%;background:#7fe0a0;animation:sodLive 2.2s infinite}
    @keyframes sodLive{0%{box-shadow:0 0 0 0 rgba(127,224,160,.6)}70%{box-shadow:0 0 0 10px rgba(127,224,160,0)}100%{box-shadow:0 0 0 0 rgba(127,224,160,0)}}
    .sod-rescue-title{font-size:clamp(1.5rem,3vw,2.1rem);font-weight:900;line-height:1.12;letter-spacing:-.01em;margin:10px 0 6px;color:#fff}
    .sod-rescue-green{color:#7fe0a0;font-variant-numeric:tabular-nums}
    .sod-rescue-sub{margin:0 0 14px;font-size:1.02rem;color:#dbe6ee}
    .sod-rescue-white{color:#fff}
    .sod-rescue-amber{color:#ffb02e}
    .sod-rescue-paws{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:7px;margin-bottom:8px}
    .sod-rescue-paw{aspect-ratio:1;border-radius:11px;display:flex;align-items:center;justify-content:center}
    .sod-rescue-paw svg{width:60%;height:60%}
    .sod-rescue-paw.is-safe{background:rgba(127,224,160,.16);color:#7fe0a0;border:1px solid rgba(127,224,160,.4)}
    .sod-rescue-paw.is-waiting{background:rgba(255,176,46,.08);color:#ffb02e;border:1.5px dashed rgba(255,176,46,.75);animation:sodWait 2.6s ease-in-out infinite}
    .sod-rescue-paw.is-waiting:nth-child(2n){animation-delay:.6s}
    @keyframes sodWait{0%,100%{opacity:.55}50%{opacity:1}}
    .sod-rescue-legend{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:.78rem;color:#b5c3d0;margin-bottom:14px}
    .sod-rescue-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:6px;vertical-align:-1px}
    .sod-rescue-legend i.is-safe{background:#7fe0a0}
    .sod-rescue-legend i.is-waiting{border:1.5px dashed #ffb02e}
    .sod-rescue-foot{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;justify-content:space-between}
    .sod-rescue-cta{background:#ffb02e;color:#1a1204;font-weight:800;padding:12px 18px;border-radius:12px;text-decoration:none;box-shadow:0 8px 24px rgba(255,176,46,.18)}
    .sod-rescue-cta:hover{filter:brightness(1.05)}
    .sod-rescue-note{font-size:.75rem;color:#9fb0bf;max-width:36ch}
    @media(prefers-reduced-motion:reduce){.sod-rescue-live,.sod-rescue-paw.is-waiting{animation:none}}
    .sod-rescue--side{display:none}
    @media(min-width:1440px){
      .sod-rescue--inline{display:none}
      .sod-rescue--side{display:block;position:absolute;z-index:3;right:77px;top:330px;width:340px;margin:0;padding:16px 16px 14px;border-radius:18px}
      .sod-rescue--side .sod-rescue-kicker{font-size:.66rem}
      .sod-rescue--side .sod-rescue-title{font-size:1.3rem;margin:8px 0 4px}
      .sod-rescue--side .sod-rescue-sub{font-size:.88rem;margin-bottom:10px}
      .sod-rescue--side .sod-rescue-paws{gap:4px;margin-bottom:6px}
      .sod-rescue--side .sod-rescue-paw{border-radius:7px}
      .sod-rescue--side .sod-rescue-legend{font-size:.7rem;gap:4px 12px;margin-bottom:10px}
      .sod-rescue--side .sod-rescue-foot{flex-direction:column;align-items:stretch;gap:8px}
      .sod-rescue--side .sod-rescue-cta{text-align:center;padding:10px 14px;font-size:.92rem}
      .sod-rescue--side .sod-rescue-note{font-size:.68rem;max-width:none}
    }
    @media(max-width:640px){.sod-rescue{padding:18px 16px}.sod-rescue-paws{gap:4px}.sod-rescue-paw{border-radius:7px}}
    </style>
    <?php
});

add_action('wp_footer', static function (): void {
    if (!is_front_page() || !sod_rescue_visible()) {
        return;
    }
    ?>
    <script>
    (function () {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
      // Zahlen einmal von 0 hochzaehlen lassen (in beiden Platzierungen).
      document.querySelectorAll('[data-sod-rescue] [data-sod-count]').forEach(function (el) {
        var end = parseInt(el.dataset.sodCount, 10) || 0;
        var start = null;
        el.textContent = '0';
        function step(t) {
          if (start === null) { start = t; }
          var p = Math.min(1, (t - start) / 1200);
          el.textContent = String(Math.round(end * (1 - Math.pow(1 - p, 3))));
          if (p < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
      });
    })();
    </script>
    <?php
});

/* ------------------------------------------------------------------ Backend-Schalter */

add_action('admin_menu', static function (): void {
    add_submenu_page('edit.php?post_type=sod_dog', 'Rettungs-Zähler', 'Rettungs-Zähler', 'manage_options', 'sod-rescue-counter', 'sod_rescue_settings_page');
});

function sod_rescue_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['sod_rescue_nonce']) && wp_verify_nonce((string)$_POST['sod_rescue_nonce'], 'sod_rescue')) {
        update_option('sod_rescue_counter_public', isset($_POST['sod_rescue_public']) ? '1' : '0', false);
        update_option('sod_rescue_alltime', max(0, absint($_POST['sod_rescue_alltime'] ?? sod_rescue_alltime_base())), false);
        delete_transient(SOD_RESCUE_CACHE_KEY);
        echo '<div class="notice notice-success"><p>Gespeichert.</p></div>';
    }
    $c = sod_rescue_counts();
    ?>
    <div class="wrap">
        <h1>Rettungs-Zähler</h1>
        <form method="post" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo sod_rescue_is_public() ? '#00a32a' : '#dba617'; ?>;padding:14px 16px;max-width:900px">
            <?php wp_nonce_field('sod_rescue', 'sod_rescue_nonce'); ?>
            <label style="font-size:15px"><input type="checkbox" name="sod_rescue_public" value="1" <?php checked(sod_rescue_is_public()); ?>> <strong>Zähler auf der Startseite öffentlich anzeigen</strong></label>
            <p>Aus: Nur angemeldete Admins sehen den Zähler im oberen Bereich der Startseite (mit Hinweis „Vorschau“). Ein: für alle sichtbar.</p>
            <p style="margin-top:14px"><label><strong>Früher gerettete Hunde (nicht auf der Seite)</strong><br>
                <input type="number" name="sod_rescue_alltime" min="0" step="1" value="<?php echo esc_attr((string)sod_rescue_alltime_base()); ?>" style="width:120px;font-size:16px"></label><br>
                <span class="description">Hunde, die ihr gerettet habt, bevor es die Seite gab oder die hier nicht eingetragen sind. Dazu zählt der Zähler automatisch jeden Hund, der auf der Seite als <em>vermittelt</em> markiert ist.<br>
                <strong>Allzeit gerettet im Zähler:</strong> <?php echo (int)sod_rescue_alltime_base(); ?> früher + <?php echo (int)($c['adopted'] ?? 0); ?> vermittelt = <?php echo (int)sod_rescue_alltime(); ?></span></p>
            <p><strong>Gerade beschützt</strong> zählt automatisch alle veröffentlichten Hunde, die noch nicht vermittelt sind. Davon gilt als <em>versorgt</em> (grüne Pfote), wer eine zu 100 % gedeckte Patenschaft hat; die übrigen <em>brauchen noch Paten</em> (orange Pfote). Wird ein Hund vermittelt, wandert er automatisch zu „allzeit gerettet“. Der Zähler rechnet automatisch mit, sobald ein Hund oder eine Patenschaft gespeichert wird.</p>
            <p><strong>Aktuell:</strong> <?php echo (int)sod_rescue_alltime(); ?> allzeit gerettet · <?php echo (int)$c['total']; ?> gerade beschützt · davon <?php echo (int)$c['rescued']; ?> versorgt und <?php echo (int)$c['need']; ?> brauchen noch Paten</p>
            <details><summary>Welche Hunde zählen wohin?</summary>
                <p><strong>Versorgt:</strong> <?php echo esc_html(implode(', ', $c['rescued_names']) ?: '–'); ?></p>
                <p><strong>Brauchen noch Paten:</strong> <?php echo esc_html(implode(', ', $c['need_names']) ?: '–'); ?></p>
                <p><strong>Vermittelt (zählen zu allzeit gerettet):</strong> <?php echo esc_html(implode(', ', $c['adopted_names'] ?? []) ?: '–'); ?></p>
            </details>
            <?php submit_button('Speichern', 'primary', 'submit', false); ?>
        </form>
    </div>
    <?php
}
