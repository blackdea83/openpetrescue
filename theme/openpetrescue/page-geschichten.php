<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
Template Name: Shield Geschichten
*/

get_header();

$sod_story_dog = sod_story_current_dog();
?>
<main class="sodst-page">
<?php if (!sod_story_is_public()) : ?><div class="sodst-wrap"><?php sod_story_render_preview_badge(); ?></div><?php endif; ?>
<?php if (!$sod_story_dog) :
    $sod_story_dogs = sod_story_all_dogs();
    $sod_story_need = array_filter($sod_story_dogs, static fn (WP_Post $dog): bool => !sod_story_is_happy($dog->ID));
    $sod_story_happy = array_filter($sod_story_dogs, static fn (WP_Post $dog): bool => sod_story_is_happy($dog->ID));
    ?>
    <section class="sodst-wrap sodst-overview">
        <div class="sodst-ov-head">
            <div>
                <p class="sodst-eyebrow"><?php sod_te('stories.eyebrow'); ?></p>
                <h1><?php sod_te('stories.title'); ?></h1>
                <p class="sodst-lead"><?php sod_te('stories.lead'); ?></p>
            </div>
            <div class="sodst-tabs" role="tablist">
                <button type="button" class="is-on" role="tab" aria-selected="true" data-sodst-filter="need"><?php sod_te('stories.tab_need'); ?> <span><?php echo count($sod_story_need); ?></span></button>
                <button type="button" role="tab" aria-selected="false" data-sodst-filter="happy"><?php sod_te('stories.tab_happy'); ?> <span><?php echo count($sod_story_happy); ?></span></button>
            </div>
        </div>
        <div class="sodst-grid">
            <?php foreach ($sod_story_need as $sod_dog) { sod_story_render_card($sod_dog); } ?>
            <?php foreach ($sod_story_happy as $sod_dog) { sod_story_render_card($sod_dog); } ?>
            <p class="sodst-empty" data-sodst-empty="need"<?php echo $sod_story_need ? ' hidden' : ''; ?>><?php sod_te('stories.empty_need'); ?></p>
            <p class="sodst-empty" data-sodst-empty="happy" hidden><?php echo $sod_story_happy ? '' : esc_html(sod_t('stories.empty_happy')); ?></p>
        </div>
        <?php sod_story_render_sections(); ?>
    </section>
<?php else :
    $sod_dog = $sod_story_dog;
    $sod_name = $sod_dog->post_title;
    $sod_happy = sod_story_is_happy($sod_dog->ID);
    $sod_support = sod_story_support($sod_dog->ID);
    $sod_chapters = sod_story_chapters_with_videos($sod_dog->ID);
    $sod_videos = sod_story_videos($sod_dog->ID);
    $sod_summary = (string)get_post_meta($sod_dog->ID, 'sod_story_summary', true);
    $sod_image = sod_story_image_url($sod_dog, 'full');
    $sod_url = sod_story_url($sod_dog);
    $sod_needs_sponsor = !$sod_happy && $sod_support['sponsorable'] && $sod_support['target'] > 0 && $sod_support['remaining'] > 0;
    $sod_others = $sod_needs_sponsor ? [] : sod_story_dogs_needing_sponsors($sod_dog->ID, 2);
    $sod_playlist = sod_story_playlist_id($sod_dog->ID);
    $sod_first_date = '';
    foreach ($sod_videos as $sod_v) {
        if ($sod_v['date'] !== '' && ($sod_first_date === '' || $sod_v['date'] < $sod_first_date)) {
            $sod_first_date = $sod_v['date'];
        }
    }
    $sod_share_text = rawurlencode(sod_story_title($sod_dog) . ' ' . $sod_url);
    ?>
    <section class="sodst-wrap">
        <a class="sodst-back" href="<?php echo esc_url(sod_page_url('geschichten')); ?>">&larr; <?php sod_te('stories.back'); ?></a>
        <div class="sodst-hero">
            <?php if ($sod_image !== '') : ?><img src="<?php echo esc_url($sod_image); ?>" alt="<?php echo esc_attr($sod_name); ?>"><?php endif; ?>
            <div class="sodst-hero-body">
                <p class="sodst-eyebrow"><?php sod_te('stories.told_by'); ?></p>
                <h1><?php echo esc_html(sod_story_title($sod_dog)); ?></h1>
                <p>
                    <?php
                    $sod_meta = [];
                    // "seit" nur, wenn die Kapitel datiert sind – sonst waere es nur das Datum des ersten Videos.
                    if ($sod_first_date !== '' && array_filter($sod_chapters, static fn (array $c): bool => $c['date'] !== '')) {
                        $sod_meta[] = sprintf(sod_t('stories.since'), sod_i18n_day_month((int)strtotime($sod_first_date), true));
                    }
                    $sod_real_chapters = array_filter($sod_chapters, static fn (array $c): bool => $c['title'] !== '');
                    if ($sod_real_chapters) {
                        $sod_meta[] = sprintf(sod_t('stories.chapter_count'), count($sod_real_chapters) + 1);
                    }
                    if ($sod_videos) {
                        $sod_meta[] = sod_story_video_label(count($sod_videos));
                    }
                    echo esc_html(implode(' · ', $sod_meta));
                    ?>
                </p>
            </div>
        </div>

        <div class="sodst-layout">
            <div class="sodst-main">
                <?php if ($sod_summary !== '') : ?>
                    <div class="sodst-tldr"><b><?php sod_te('stories.tldr'); ?></b><p><?php echo esc_html($sod_summary); ?></p></div>
                <?php endif; ?>

                <?php $sod_n = 0; $sod_mid_done = false; $sod_total = count($sod_chapters);
                foreach ($sod_chapters as $sod_index => $sod_chapter) :
                    $sod_n++; ?>
                    <div class="sodst-chapter" data-n="<?php echo (int)$sod_n; ?>">
                        <?php if ($sod_chapter['date'] !== '') : ?>
                            <div class="sodst-chapter-date"><?php echo esc_html(sprintf(sod_t('stories.chapter_from'), $sod_n, sod_i18n_day_month((int)strtotime($sod_chapter['date'])))); ?></div>
                        <?php elseif ($sod_chapter['title'] !== '') : ?>
                            <div class="sodst-chapter-date"><?php echo esc_html(sprintf(sod_t('stories.chapter_n'), $sod_n)); ?></div>
                        <?php endif; ?>
                        <?php if ($sod_chapter['title'] !== '') : ?><h2><?php echo esc_html($sod_chapter['title']); ?></h2><?php endif; ?>
                        <?php if ($sod_chapter['text'] !== '') : ?><?php echo wp_kses_post(wpautop(esc_html($sod_chapter['text']))); ?><?php endif; ?>
                        <?php if ($sod_chapter['quote'] !== '') : ?>
                            <figure class="sodst-quote"><span class="sodst-avatar" aria-hidden="true">D</span><blockquote><p>„<?php echo esc_html($sod_chapter['quote']); ?>“</p><figcaption><?php sod_te('stories.quote_by'); ?></figcaption></blockquote></figure>
                        <?php endif; ?>
                        <?php sod_story_render_videos($sod_chapter['videos'], $sod_name); ?>
                    </div>
                    <?php if (!$sod_mid_done && $sod_total >= 3 && $sod_index === (int)floor($sod_total / 2)) : $sod_mid_done = true; ?>
                        <div class="sodst-mid-cta">
                            <p><?php echo esc_html($sod_needs_sponsor ? sprintf(sod_t('stories.mid_sponsor'), $sod_name) : sod_t('stories.mid_others')); ?></p>
                            <?php if ($sod_needs_sponsor) : ?>
                                <a class="btn btn-primary btn-sm" href="<?php echo esc_url(get_permalink($sod_dog) . '#pate-werden'); ?>"><?php echo esc_html(sprintf(sod_t('stories.sponsor'), $sod_name)); ?></a>
                            <?php else : ?>
                                <a class="btn btn-primary btn-sm" href="#sodst-help"><?php sod_te('stories.others_btn'); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="sodst-chapter is-now<?php echo $sod_happy ? ' is-happy' : ''; ?>" data-n="<?php echo (int)$sod_n + 1; ?>" id="sodst-now">
                    <div class="sodst-chapter-date"><?php echo esc_html(sprintf(sod_t('stories.chapter_now'), $sod_n + 1)); ?></div>
                    <?php if ($sod_happy) : ?>
                        <h2><?php echo esc_html(sprintf(sod_t('stories.now_happy_title'), $sod_name)); ?></h2>
                        <p><?php echo esc_html(sprintf(sod_t('stories.now_happy_text'), $sod_name)); ?></p>
                        <a class="btn btn-primary" href="<?php echo esc_url(sod_page_url('geschichten')); ?>"><?php sod_te('stories.others_btn'); ?></a>
                    <?php elseif ($sod_needs_sponsor) : ?>
                        <h2><?php echo esc_html(sprintf(sod_t('stories.now_sponsor_title'), $sod_name)); ?></h2>
                        <p><?php echo esc_html(sprintf(sod_t('stories.now_sponsor_text'), $sod_name)); ?></p>
                        <?php sod_story_render_progress($sod_support, true); ?>
                        <div class="sodst-actions">
                            <a class="btn btn-primary" href="<?php echo esc_url(get_permalink($sod_dog) . '#pate-werden'); ?>"><?php echo esc_html(sprintf(sod_t('stories.sponsor'), $sod_name)); ?></a>
                            <?php if ($sod_support['adoptable']) : ?><a class="btn btn-secondary" href="<?php echo esc_url(get_permalink($sod_dog)); ?>"><?php echo esc_html(sprintf(sod_t('stories.adopt'), $sod_name)); ?></a><?php endif; ?>
                        </div>
                    <?php else : ?>
                        <h2><?php sod_te('stories.now_home_title'); ?></h2>
                        <p><?php echo esc_html(sprintf(sod_t('stories.now_home_text'), $sod_name)); ?></p>
                        <?php if ($sod_support['target'] > 0) { sod_story_render_progress($sod_support, true); } ?>
                        <div class="sodst-actions">
                            <a class="btn btn-primary" href="<?php echo esc_url(get_permalink($sod_dog)); ?>"><?php echo esc_html(sprintf(sod_t('stories.adopt'), $sod_name)); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="sodst-side">
                <?php if ($sod_needs_sponsor) : ?>
                    <div class="sodst-box">
                        <h3><?php echo esc_html(sprintf(sod_t('stories.sponsor'), $sod_name)); ?></h3>
                        <p><?php sod_te('stories.side_sponsor_text'); ?></p>
                        <?php sod_story_render_progress($sod_support, false); ?>
                        <a class="btn btn-primary" href="<?php echo esc_url(get_permalink($sod_dog) . '#pate-werden'); ?>"><?php sod_te('stories.side_sponsor_btn'); ?></a>
                    </div>
                <?php elseif ($sod_others) : ?>
                    <div class="sodst-box" id="sodst-help">
                        <h3><?php echo esc_html($sod_happy ? sod_t('stories.side_others_title_happy') : sprintf(sod_t('stories.side_covered_title'), $sod_name)); ?></h3>
                        <p><?php sod_te('stories.side_others_text'); ?></p>
                        <?php foreach ($sod_others as $sod_other) : ?>
                            <a class="sodst-mini" href="<?php echo esc_url(get_permalink($sod_other['dog']) . '#pate-werden'); ?>">
                                <?php echo get_the_post_thumbnail($sod_other['dog']->ID, 'thumbnail', ['alt' => '']); ?>
                                <span><b><?php echo esc_html($sod_other['dog']->post_title); ?></b><span class="sodst-bar"><i style="width:<?php echo (int)$sod_other['support']['percent']; ?>%"></i></span><small><?php echo esc_html(sprintf(sod_t('stories.remaining'), sod_story_money($sod_other['support']['remaining']))); ?></small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($sod_playlist !== '') : ?>
                    <div class="sodst-box">
                        <h3><?php echo esc_html(sprintf(sod_t('stories.playlist_title'), $sod_name)); ?></h3>
                        <p><?php echo esc_html(sprintf(sod_t('stories.playlist_text'), count($sod_videos))); ?></p>
                        <a class="btn btn-secondary btn-sm" href="<?php echo esc_url('https://www.youtube.com/playlist?list=' . $sod_playlist); ?>" target="_blank" rel="noopener"><?php sod_te('stories.playlist_btn'); ?></a>
                    </div>
                <?php endif; ?>
                <div class="sodst-box">
                    <h3><?php echo esc_html(sprintf(sod_t('stories.share_title'), $sod_name)); ?></h3>
                    <p><?php sod_te('stories.share_text'); ?></p>
                    <div class="sodst-share">
                        <a class="is-wa" href="https://wa.me/?text=<?php echo esc_attr($sod_share_text); ?>" target="_blank" rel="noopener">WhatsApp</a>
                        <a class="is-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr(rawurlencode($sod_url)); ?>" target="_blank" rel="noopener">Facebook</a>
                        <button type="button" data-sodst-copy="<?php echo esc_url($sod_url); ?>" data-sodst-copied="<?php echo esc_attr(sod_t('stories.copied')); ?>"><?php sod_te('stories.copy'); ?></button>
                    </div>
                </div>
            </aside>
        </div>
    </section>
<?php endif; ?>
    <div class="sodst-consent-template" hidden data-sodst-consent-text="<?php echo esc_attr(sod_t('stories.consent_text')); ?>" data-sodst-consent-btn="<?php echo esc_attr(sod_t('stories.consent_btn')); ?>" data-sodst-close="<?php echo esc_attr(sod_t('stories.close')); ?>"></div>
</main>
<?php
get_footer();
