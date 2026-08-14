<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
Template Name: Shield Patenschaft
*/

get_header();
?>
<main>
    <div class="page-hero">
        <div class="container">
            <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-accent-300);margin-bottom:var(--sp-3)"><?php sod_te('patenschaft.eyebrow'); ?></p>
            <h1><?php sod_te('patenschaft.title'); ?></h1>
            <p><?php sod_te('patenschaft.lead'); ?></p>
        </div>
    </div>
    <section class="teaming-band">
        <div class="container">
            <div class="teaming-inner">
                <div class="teaming-logo-card"><img src="<?php echo esc_url(sod_asset('images/teaming-logo.png')); ?>" alt="Teaming" loading="lazy" width="136" height="35"></div>
                <h2 class="teaming-title"><?php sod_te('patenschaft.teaming_title'); ?></h2>
                <p class="teaming-text"><?php sod_th('patenschaft.teaming_text'); ?></p>
                <a class="btn btn-secondary btn-lg" href="<?php echo esc_url(sod_org_donation_url()); ?>" rel="noopener" target="_blank"><?php sod_te('patenschaft.teaming_btn'); ?></a>
            </div>
        </div>
    </section>

    <section class="section" id="patenschaft">
        <div class="container">
            <div class="section-header center">
                <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-primary-500);margin-bottom:var(--sp-2)"><?php sod_te('patenschaft.dogs_eyebrow'); ?></p>
                <h2 class="section-title"><?php sod_te('patenschaft.dogs_title'); ?></h2>
                <p class="section-subtitle"><?php sod_te('patenschaft.dogs_subtitle'); ?></p>
            </div>
            <?php echo do_shortcode('[sod_dogs mode="sponsorship"]'); ?>
        </div>
    </section>
    <?php echo do_shortcode('[sod_active_sponsorships]'); ?>
    <section class="section">
        <div class="container">
            <div class="donation-layout" style="grid-template-columns:1fr;max-width:760px;margin:0 auto;">
                <div class="donation-copy">
                    <h2 class="section-title" style="margin-bottom:var(--sp-4)"><?php sod_te('patenschaft.why_title'); ?></h2>
                    <p style="font-size:1.0625rem;color:var(--text-secondary);line-height:1.7;margin-bottom:var(--sp-8)"><?php sod_te('patenschaft.why_lead'); ?></p>
                    <div style="display:flex;flex-direction:column;gap:var(--sp-4);margin-bottom:var(--sp-12)">
                        <div style="display:flex;gap:var(--sp-4);align-items:flex-start">
                            <div style="width:48px;height:48px;background:var(--color-primary-100);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--color-primary-950);flex-shrink:0">1</div>
                            <div>
                                <div style="font-weight:700;margin-bottom:2px"><?php sod_te('patenschaft.why1_title'); ?></div>
                                <p style="font-size:.9rem;color:var(--text-secondary);line-height:1.6"><?php sod_te('patenschaft.why1_text'); ?></p>
                            </div>
                        </div>
                        <div style="display:flex;gap:var(--sp-4);align-items:flex-start">
                            <div style="width:48px;height:48px;background:var(--color-accent-100,#fff8eb);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--color-primary-950);flex-shrink:0">2</div>
                            <div>
                                <div style="font-weight:700;margin-bottom:2px"><?php sod_te('patenschaft.why2_title'); ?></div>
                                <p style="font-size:.9rem;color:var(--text-secondary);line-height:1.6"><?php sod_te('patenschaft.why2_text'); ?></p>
                            </div>
                        </div>
                        <div style="display:flex;gap:var(--sp-4);align-items:flex-start">
                            <div style="width:48px;height:48px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">3</div>
                            <div>
                                <div style="font-weight:700;margin-bottom:2px"><?php sod_te('patenschaft.why3_title'); ?></div>
                                <p style="font-size:.9rem;color:var(--text-secondary);line-height:1.6"><?php sod_te('patenschaft.why3_text'); ?></p>
                            </div>
                        </div>
                        <div style="display:flex;gap:var(--sp-4);align-items:flex-start">
                            <div style="width:48px;height:48px;background:var(--color-success-light);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--color-primary-950);flex-shrink:0">4</div>
                            <div>
                                <div style="font-weight:700;margin-bottom:2px"><?php sod_te('patenschaft.why4_title'); ?></div>
                                <p style="font-size:.9rem;color:var(--text-secondary);line-height:1.6"><?php sod_te('patenschaft.why4_text'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php
get_footer();
