<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main>
    <section class="ob-hero" id="ueber-uns">
        <img fetchpriority="high" decoding="async" alt="" height="925" src="<?php echo esc_url(sod_home_image_url('hero', sod_asset('images/hero-large.jpg'))); ?>" width="1600">
        <div class="container">
            <div class="ob-hero-content">
                <span class="ob-hero-eyebrow"><?php sod_th('home.hero_eyebrow'); ?></span>
                <h1 class="ob-hero-headline"><?php sod_th('home.hero_headline'); ?></h1>
                <p class="ob-hero-lead"><?php sod_te('home.hero_lead'); ?></p>
                <div style="display:flex;gap:var(--sp-3);flex-wrap:wrap;margin-top:var(--sp-8);">
                    <a class="btn btn-secondary btn-lg" href="<?php echo esc_url(sod_page_url('spenden')); ?>"><?php sod_te('home.hero_cta_spenden'); ?></a>
                    <a class="btn btn-white-outline btn-lg" href="<?php echo esc_url(sod_page_url('vermittlung')); ?>"><?php sod_te('home.hero_cta_vermittlung'); ?></a>
                </div>
            </div>
        </div>
        <a aria-label="<?php sod_te('home.teaming_label'); ?>" class="hero-circle" href="<?php echo esc_url(sod_org_donation_url()); ?>" rel="noopener" target="_blank">
            <?php sod_te('home.teaming_ring'); ?>
            <span class="hero-circle-logo"><img src="<?php echo esc_url(sod_asset('images/teaming-logo.png')); ?>" alt="Teaming" loading="lazy" width="136" height="35"></span>
            <div class="hero-circle-core hero-circle-core--logo">
                <span class="hero-circle-amount"><?php sod_te('home.teaming_amount'); ?></span>
                <span class="hero-circle-sub"><?php sod_te('home.teaming_sub'); ?></span>
                <?php $sod_teaming_members = sod_teaming_members(); ?>
                <?php if ($sod_teaming_members > 0) : ?>
                    <span class="hero-circle-members"><?php echo esc_html(number_format_i18n($sod_teaming_members) . ' ' . ($sod_teaming_members === 1 ? sod_t('home.teaming_members_singular') : sod_t('home.teaming_members_plural'))); ?></span>
                    <span class="hero-circle-members-note"><?php sod_te('home.teaming_members_note'); ?></span>
                <?php endif; ?>
            </div>
        </a>
        <div class="ob-scroll">
            <div class="ob-scroll-line"></div>
            <?php sod_te('home.scroll'); ?>
        </div>
    </section>

    <section class="video-highlight" id="highlight-video">
        <div class="video-highlight-inner scroll-reveal">
            <div>
                <h2 class="video-highlight-title"><?php sod_te('home.video_title'); ?></h2>
                <p class="video-highlight-lead"><?php sod_te('home.video_lead'); ?></p>
                <div class="impact-ctas" style="justify-content:flex-start">
                    <a class="btn btn-secondary btn-lg" href="<?php echo esc_url(sod_org_donation_url()); ?>" rel="noopener" target="_blank"><span class="teaming-btn-logo"><img src="<?php echo esc_url(sod_asset('images/teaming-logo.png')); ?>" alt="" loading="lazy" width="70" height="18"></span><?php sod_te('home.video_cta_teaming'); ?></a>
                    <a class="btn btn-white-outline btn-lg" href="<?php echo esc_url(sod_page_url('spenden')); ?>"><?php sod_te('home.video_cta_spenden'); ?></a>
                </div>
            </div>
            <div class="video-frame">
                <span class="video-badge">@shield.of.dogs</span>
                <button class="video-sound-toggle" type="button" data-sound-on="<?php echo esc_attr(sod_t('home.video_sound_on')); ?>" data-sound-off="<?php echo esc_attr(sod_t('home.video_sound_off')); ?>" aria-pressed="false">
                    <?php sod_te('home.video_sound_on'); ?>
                </button>
                <video controls playsinline autoplay muted loop poster="<?php echo esc_url(sod_asset('images/placeholder.svg')); ?>" preload="none" data-src="<?php echo esc_url((string)get_option('sod_home_video_url', '')); ?>">
                    <?php sod_te('home.video_fallback_text'); ?>
                </video>
            </div>
        </div>
    </section>

    <section class="support-paths" id="helfen">
        <div class="container">
            <div class="support-paths-head scroll-reveal">
                <h2><?php sod_te('home.support_head_title'); ?></h2>
                <p><?php sod_te('home.support_head_text'); ?></p>
            </div>
            <div class="support-paths-grid">
                <a class="support-card scroll-reveal" href="<?php echo esc_url(sod_page_url('vermittlung')); ?>">
                    <img decoding="async" alt="Geretteter Welpe mit Hoffnung" loading="lazy" src="<?php echo esc_url(sod_home_image_url('support_vermittlung', sod_asset('images/dog3.jpg'))); ?>">
                    <div class="support-card-content">
                        <div class="support-card-kicker"><?php sod_te('home.support_vermittlung_kicker'); ?></div>
                        <h3><?php sod_te('home.support_vermittlung_title'); ?></h3>
                        <p><?php sod_te('home.support_vermittlung_text'); ?></p>
                        <span><?php sod_te('home.support_vermittlung_link'); ?></span>
                    </div>
                </a>
                <a class="support-card scroll-reveal" href="<?php echo esc_url(sod_page_url('patenschaft')); ?>">
                    <img decoding="async" alt="Welpe wird gerettet" loading="lazy" src="<?php echo esc_url(sod_home_image_url('support_patenschaft', sod_asset('images/dog6.jpg'))); ?>">
                    <div class="support-card-content">
                        <div class="support-card-kicker"><?php sod_te('home.support_patenschaft_kicker'); ?></div>
                        <h3><?php sod_te('home.support_patenschaft_title'); ?></h3>
                        <p><?php sod_te('home.support_patenschaft_text'); ?></p>
                        <span><?php sod_te('home.support_patenschaft_link'); ?></span>
                    </div>
                </a>
                <a class="support-card scroll-reveal" href="<?php echo esc_url(sod_page_url('spenden')); ?>">
                    <img decoding="async" alt="" loading="lazy" src="<?php echo esc_url(sod_home_image_url('support_spenden', sod_asset('images/hero-card.jpg'))); ?>">
                    <div class="support-card-content">
                        <div class="support-card-kicker"><?php sod_te('home.support_spenden_kicker'); ?></div>
                        <h3><?php sod_te('home.support_spenden_title'); ?></h3>
                        <p><?php sod_te('home.support_spenden_text'); ?></p>
                        <span><?php sod_te('home.support_spenden_link'); ?></span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="ob-quote">
        <div class="container">
            <div class="ob-quote-mark">&#8220;</div>
            <blockquote><?php sod_th('home.quote_text'); ?></blockquote>
            <cite><?php sod_te('home.quote_cite'); ?></cite>
        </div>
    </section>

    <section class="ob-story">
        <div class="container">
            <div class="ob-story-row scroll-reveal">
                <div class="ob-story-text">
                    <div class="ob-story-tag"><?php sod_te('home.story1_tag'); ?></div>
                    <h2><?php sod_te('home.story1_title'); ?></h2>
                    <p><?php sod_te('home.story1_p1'); ?></p>
                    <p><?php sod_te('home.story1_p2'); ?></p>
                    <p><?php sod_te('home.story1_p3'); ?></p>
                </div>
                <div class="ob-story-img">
                    <img decoding="async" alt="Ein verwahrloster Streuner sitzt jaulend im Gestrüpp" loading="lazy" src="<?php echo esc_url(sod_home_image_url('story1', wp_get_attachment_image_url(151, 'large') ?: sod_asset('images/dog2.jpg'))); ?>">
                    <div class="ob-story-cap"><?php sod_te('home.story1_caption'); ?></div>
                </div>
            </div>
            <div class="ob-story-row reverse scroll-reveal">
                <div class="ob-story-text">
                    <div class="ob-story-tag"><?php sod_te('home.story2_tag'); ?></div>
                    <h2><?php sod_te('home.story2_title'); ?></h2>
                    <p><?php sod_te('home.story2_p1'); ?></p>
                    <p><?php sod_te('home.story2_p2'); ?></p>
                    <p><?php sod_te('home.story2_p3'); ?></p>
                </div>
                <div class="ob-story-img">
                    <img decoding="async" alt="Gerettete Welpen in einer Notunterkunft" loading="lazy" src="<?php echo esc_url(sod_home_image_url('story2', sod_asset('images/dog4.jpg'))); ?>">
                    <div class="ob-story-cap"><?php sod_te('home.story2_caption'); ?></div>
                </div>
            </div>
            <div class="ob-story-row scroll-reveal">
                <div class="ob-story-text">
                    <div class="ob-story-tag"><?php sod_te('home.story3_tag'); ?></div>
                    <h2><?php sod_te('home.story3_title'); ?></h2>
                    <p><?php sod_te('home.story3_p1'); ?></p>
                    <p><?php sod_te('home.story3_p2'); ?></p>
                </div>
                <div class="ob-story-img">
                    <img decoding="async" alt="Geretteter Welpe mit Hoffnung" loading="lazy" src="<?php echo esc_url(sod_home_image_url('story3', sod_asset('images/dog3.jpg'))); ?>">
                    <div class="ob-story-cap"><?php sod_te('home.story3_caption'); ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="ob-letter">
        <div class="container">
            <div class="ob-letter-inner">
                <div class="ob-letter-photo">
                    <img decoding="async" alt="In Erinnerung an die Hunde, die nicht überlebt haben" loading="lazy" src="<?php echo esc_url(sod_home_image_url('letter', sod_asset('images/dog5.jpg'))); ?>">
                </div>
                <p><?php sod_th('home.letter_quote'); ?></p>
                <div class="ob-letter-sign"><?php sod_te('home.letter_sign'); ?></div>
            </div>
        </div>
    </section>

    <section class="galerie" id="galerie" data-preview-close-label="<?php echo esc_attr(sod_t('common.gallery_close_image')); ?>">
        <div class="galerie-intro">
            <p class="eyebrow"><?php sod_te('home.galerie_eyebrow'); ?></p>
            <h2><?php sod_te('home.galerie_title'); ?></h2>
            <p class="lead"><?php sod_te('home.galerie_lead'); ?></p>
        </div>
        <?php $sod_galerie_photos = SOD_Plugin::galerie_photos(); ?>
        <div class="galerie-grid">
            <?php foreach ($sod_galerie_photos as $sod_photo): ?>
                <?php
                [$sod_att_id, $sod_alt, $sod_cap_key] = $sod_photo;
                $sod_image = wp_get_attachment_image(
                    $sod_att_id,
                    'large',
                    false,
                    [
                        'alt' => $sod_alt,
                        'decoding' => 'async',
                        'loading' => 'lazy',
                        'sizes' => '(max-width: 700px) 100vw, (max-width: 980px) 50vw, 33vw',
                    ]
                );
                if (!$sod_image) {
                    continue;
                }
                ?>
                <figure aria-controls="sod-gallery-preview" aria-expanded="false" aria-label="<?php echo esc_attr(sod_t('common.gallery_open_image') . ': ' . $sod_alt); ?>" class="galerie-card" role="button" tabindex="0">
                    <?php echo $sod_image; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tiktok-section" id="tiktok">
        <div class="tiktok-inner">
            <div class="tiktok-head">
                <div>
                    <p class="tiktok-eyebrow"><?php sod_te('home.tiktok_eyebrow'); ?></p>
                    <h2 class="tiktok-title"><?php sod_te('home.tiktok_title'); ?></h2>
                    <p class="tiktok-lead"><?php sod_te('home.tiktok_lead'); ?></p>
                </div>
                <a class="btn btn-secondary btn-md" href="https://www.tiktok.com/@shield.of.dogs" rel="noopener" target="_blank"><?php sod_te('home.tiktok_open_btn'); ?></a>
            </div>
            <div aria-label="TikTok Video Vorschau" class="tiktok-grid">
                <a class="tiktok-card" href="https://www.tiktok.com/@shield.of.dogs/video/7642717532742372630" rel="noopener" target="_blank">
                    <img decoding="async" alt="" loading="lazy" src="<?php echo esc_url(sod_home_image_url('tiktok1', sod_asset('images/hero-card.jpg'))); ?>">
                    <span class="tiktok-play">▶</span>
                    <div class="tiktok-card-body">
                        <div class="tiktok-card-kicker">@shield.of.dogs</div>
                        <div class="tiktok-card-title"><?php sod_te('home.tiktok_card1'); ?></div>
                    </div>
                </a>
                <a class="tiktok-card" href="https://www.tiktok.com/@shield.of.dogs/video/7629778988684152086" rel="noopener" target="_blank">
                    <img decoding="async" alt="Welpe wird gerettet" loading="lazy" src="<?php echo esc_url(sod_home_image_url('tiktok2', sod_asset('images/dog6.jpg'))); ?>">
                    <span class="tiktok-play">▶</span>
                    <div class="tiktok-card-body">
                        <div class="tiktok-card-kicker">@shield.of.dogs</div>
                        <div class="tiktok-card-title"><?php sod_te('home.tiktok_card2'); ?></div>
                    </div>
                </a>
                <a class="tiktok-card" href="https://www.tiktok.com/@shield.of.dogs/video/7630496833768574211" rel="noopener" target="_blank">
                    <img decoding="async" alt="Welpen in einer Notunterkunft" loading="lazy" src="<?php echo esc_url(sod_home_image_url('tiktok3', sod_asset('images/dog4.jpg'))); ?>">
                    <span class="tiktok-play">▶</span>
                    <div class="tiktok-card-body">
                        <div class="tiktok-card-kicker">@shield.of.dogs</div>
                        <div class="tiktok-card-title"><?php sod_te('home.tiktok_card3'); ?></div>
                    </div>
                </a>
            </div>
            <div class="tiktok-consent">
                <p><?php sod_te('home.tiktok_consent'); ?></p>
                <button class="btn btn-white-outline btn-md tiktok-load" type="button"><?php sod_te('home.tiktok_load_btn'); ?></button>
            </div>
            <div aria-live="polite" class="tiktok-embed-wrap"></div>
        </div>
    </section>

    <section class="section" id="hunde">
        <div class="container">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:var(--sp-4)">
                <div>
                    <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-primary-500);margin-bottom:var(--sp-2)"><?php sod_te('home.hunde_eyebrow'); ?></p>
                    <h2 class="section-title"><?php sod_te('home.hunde_title'); ?></h2>
                    <p class="section-subtitle"><?php sod_te('home.hunde_subtitle'); ?></p>
                </div>
                <a class="btn btn-outline btn-md" href="<?php echo esc_url(sod_page_url('vermittlung')); ?>" style="flex-shrink:0"><?php sod_te('home.hunde_zur_vermittlung'); ?></a>
            </div>
            <?php
            $home_dogs = do_shortcode('[sod_dogs limit="3" mode="adoption"]');
            if (strpos($home_dogs, 'sod-dogs-empty') === false) {
                echo $home_dogs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted shortcode output.
            }
            ?>
        </div>
    </section>

    <section class="impact-section">
        <div class="impact-bg"></div>
        <div class="container impact-inner">
            <h2 class="impact-headline"><?php sod_te('home.impact_headline'); ?></h2>
            <p class="impact-sub"><?php sod_th('home.impact_sub'); ?></p>
            <div class="impact-ctas">
                <a class="btn btn-secondary btn-lg" href="<?php echo esc_url(sod_org_donation_url()); ?>" rel="noopener" target="_blank"><span class="teaming-btn-logo"><img src="<?php echo esc_url(sod_asset('images/teaming-logo.png')); ?>" alt="" loading="lazy" width="70" height="18"></span><?php sod_te('home.impact_cta_teaming'); ?></a>
                <a class="btn btn-white-outline btn-lg" href="<?php echo esc_url(sod_page_url('spenden')); ?>"><?php sod_te('home.impact_cta_spenden'); ?></a>
            </div>
        </div>
    </section>

    <section class="section-sm">
        <div class="container">
            <div class="section-header center" style="margin-bottom:var(--sp-8)">
                <h2 class="section-title" style="font-size:1.5rem"><?php sod_te('home.partners_title'); ?></h2>
            </div>
            <div class="partners-grid">
                <a aria-label="Lauras Pfotenparadies" class="partner-logo partner-logo--link" href="https://lauras-pfotenparadies.at" rel="noopener" target="_blank">
                    <img decoding="async" alt="Lauras Pfotenparadies" src="<?php echo sod_asset('images/pfotenparadies-logo.svg'); ?>">
                </a>
            </div>
        </div>
    </section>

</main>
<?php
get_footer();
