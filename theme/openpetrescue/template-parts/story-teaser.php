<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Startseiten-Teaser "Schicksale": zeigt eine Geschichte (wechselt bei jedem Aufruf),
 * bevorzugt Hunde, die noch nicht vermittelt sind. Ohne Geschichten: kein Abschnitt.
 */
if (!function_exists('sod_story_visible') || !sod_story_visible()) {
    return;
}
$sod_teaser_dogs = sod_story_all_dogs();
if (!$sod_teaser_dogs) {
    return;
}
$sod_teaser_open = array_values(array_filter($sod_teaser_dogs, static fn (WP_Post $dog): bool => !sod_story_is_happy($dog->ID)));
$sod_teaser_pool = $sod_teaser_open ?: $sod_teaser_dogs;
$sod_teaser_dog = $sod_teaser_pool[array_rand($sod_teaser_pool)];
$sod_teaser_chapters = sod_story_chapters($sod_teaser_dog->ID);
$sod_teaser_videos = sod_story_videos($sod_teaser_dog->ID);
$sod_teaser_quote = (string)get_post_meta($sod_teaser_dog->ID, 'sod_story_quote', true);
$sod_teaser_summary = (string)get_post_meta($sod_teaser_dog->ID, 'sod_story_summary', true);
$sod_teaser_support = sod_story_support($sod_teaser_dog->ID);
$sod_teaser_happy = sod_story_is_happy($sod_teaser_dog->ID);
$sod_teaser_image = sod_story_image_url($sod_teaser_dog, 'large');
?>
<section class="sodst-teaser-section">
    <div class="sodst-wrap">
        <?php sod_story_render_preview_badge(); ?>
        <div class="sodst-teaser">
            <a class="sodst-teaser-media" href="<?php echo esc_url(sod_story_url($sod_teaser_dog)); ?>" tabindex="-1" aria-hidden="true">
                <?php if ($sod_teaser_image !== '') : ?><img src="<?php echo esc_url($sod_teaser_image); ?>" alt="" loading="lazy"><?php endif; ?>
            </a>
            <div class="sodst-teaser-body">
                <p class="sodst-eyebrow"><?php
                    $sod_teaser_kicker = [sod_t('stories.eyebrow'), $sod_teaser_dog->post_title];
                    if ($sod_teaser_videos) {
                        $sod_teaser_kicker[] = sod_story_video_label(count($sod_teaser_videos));
                    }
                    echo esc_html(implode(' · ', $sod_teaser_kicker));
                ?></p>
                <h2><?php echo esc_html(sod_story_title($sod_teaser_dog)); ?></h2>
                <?php if ($sod_teaser_quote !== '') : ?>
                    <blockquote class="sodst-teaser-quote">„<?php echo esc_html($sod_teaser_quote); ?>“<small><?php sod_te('stories.quote_by_short'); ?></small></blockquote>
                <?php elseif ($sod_teaser_summary !== '') : ?>
                    <p class="sodst-teaser-summary"><?php echo esc_html(wp_trim_words($sod_teaser_summary, 30)); ?></p>
                <?php endif; ?>
                <?php if ($sod_teaser_chapters) : ?>
                    <div class="sodst-chips">
                        <?php foreach ($sod_teaser_chapters as $sod_i => $sod_c) : ?><span class="is-on"><?php echo esc_html(($sod_i + 1) . ' ' . $sod_c['title']); ?></span><?php endforeach; ?>
                        <span><?php echo esc_html((count($sod_teaser_chapters) + 1) . ' ' . ($sod_teaser_happy ? sod_t('stories.chip_happy') : sod_t('stories.chip_now'))); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!$sod_teaser_happy && $sod_teaser_support['target'] > 0) { sod_story_render_progress($sod_teaser_support, false); } ?>
                <div class="sodst-actions">
                    <a class="btn btn-primary btn-md" href="<?php echo esc_url(sod_story_url($sod_teaser_dog)); ?>"><?php echo esc_html(sprintf(sod_t('stories.teaser_btn'), $sod_teaser_dog->post_title)); ?> ▶</a>
                    <?php
                    // Patenschaft noch offen: "Pate werden", sonst (voll versorgt oder vermittelt): "Spenden".
                    $sod_teaser_need = !$sod_teaser_happy && $sod_teaser_support['sponsorable'] && $sod_teaser_support['target'] > 0 && $sod_teaser_support['remaining'] > 0;
                    if ($sod_teaser_need) : ?>
                        <a class="btn btn-secondary btn-md" href="<?php echo esc_url(get_permalink($sod_teaser_dog) . '#pate-werden'); ?>"><?php echo esc_html(sprintf(sod_t('stories.cta_sponsor'), $sod_teaser_dog->post_title)); ?></a>
                    <?php else : ?>
                        <a class="btn btn-secondary btn-md" href="<?php echo esc_url(sod_page_url('spenden')); ?>" data-sod-donate-trigger><?php sod_te('stories.cta_donate'); ?></a>
                    <?php endif; ?>
                    <a class="btn btn-white-outline btn-md" href="<?php echo esc_url(sod_page_url('geschichten')); ?>"><?php sod_te('stories.teaser_all'); ?></a>
                </div>
            </div>
        </div>

        <?php
        // Vorschau der weiteren Schicksale (ohne den grossen Teaser-Hund), offene zuerst.
        $sod_more = array_values(array_filter($sod_teaser_dogs, static fn (WP_Post $dog): bool => $dog->ID !== $sod_teaser_dog->ID));
        usort($sod_more, static fn (WP_Post $a, WP_Post $b): int => (int)sod_story_is_happy($a->ID) <=> (int)sod_story_is_happy($b->ID));
        $sod_more = array_slice($sod_more, 0, 4);
        if ($sod_more) : ?>
            <div class="sodst-more">
                <div class="sodst-more-head">
                    <h3><?php sod_te('stories.more_title'); ?></h3>
                    <a href="<?php echo esc_url(sod_page_url('geschichten')); ?>"><?php sod_te('stories.teaser_all'); ?> &rarr;</a>
                </div>
                <div class="sodst-more-row">
                    <?php foreach ($sod_more as $sod_m) :
                        $sod_m_support = sod_story_support($sod_m->ID);
                        $sod_m_happy = sod_story_is_happy($sod_m->ID);
                        $sod_m_need = !$sod_m_happy && $sod_m_support['sponsorable'] && $sod_m_support['target'] > 0 && $sod_m_support['remaining'] > 0;
                        $sod_m_image = sod_story_image_url($sod_m, 'medium_large');
                        $sod_m_badge = $sod_m_happy ? ['happy', sod_t('stories.badge_happy')] : ($sod_m_need ? ['need', sod_t('stories.badge_sponsor')] : ['home', sod_t('stories.badge_home')]);
                        $sod_m_url = get_permalink($sod_m) . '#geschichte';
                        ?>
                        <a class="sodst-mini-card" href="<?php echo esc_url($sod_m_url); ?>">
                            <span class="sodst-mini-media">
                                <?php if ($sod_m_image !== '') : ?><img src="<?php echo esc_url($sod_m_image); ?>" alt="" loading="lazy"><?php endif; ?>
                                <span class="sodst-badge sodst-badge-<?php echo esc_attr($sod_m_badge[0]); ?>"><?php echo esc_html($sod_m_badge[1]); ?></span>
                            </span>
                            <span class="sodst-mini-body">
                                <strong><?php echo esc_html($sod_m->post_title); ?></strong>
                                <span class="sodst-mini-title"><?php echo esc_html(sod_story_title($sod_m)); ?></span>
                                <?php if (!$sod_m_happy && $sod_m_support['target'] > 0) : ?>
                                    <span class="sodst-bar"><i style="width:<?php echo (int)$sod_m_support['percent']; ?>%"></i></span>
                                    <small><?php echo esc_html($sod_m_need ? sprintf(sod_t('stories.remaining'), sod_story_money($sod_m_support['remaining'])) : sod_t('stories.covered')); ?></small>
                                <?php endif; ?>
                                <span class="sodst-mini-link"><?php sod_te('stories.schicksal_link'); ?> &rarr;</span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
