<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
Template Name: Shield Vermittlung
*/

get_header();
?>
<main>
    <div class="page-hero">
        <div class="container">
            <div class="page-hero-inner">
                <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-accent-300);margin-bottom:var(--sp-3)"><?php sod_te('vermittlung.eyebrow'); ?></p>
                <h1><?php sod_te('vermittlung.title'); ?></h1>
                <p><?php sod_te('vermittlung.lead'); ?></p>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="container">
            <?php echo do_shortcode('[sod_dogs mode="adoption"]'); ?>

            <div aria-label="<?php sod_te('vermittlung.steps_aria_label'); ?>" class="adoption-steps">
                <div class="adoption-step">
                    <span>1</span>
                    <strong><?php sod_te('vermittlung.step1_title'); ?></strong>
                    <p><?php sod_te('vermittlung.step1_text'); ?></p>
                </div>
                <div class="adoption-step">
                    <span>2</span>
                    <strong><?php sod_te('vermittlung.step2_title'); ?></strong>
                    <p><?php sod_te('vermittlung.step2_text'); ?></p>
                </div>
                <div class="adoption-step">
                    <span>3</span>
                    <strong><?php sod_te('vermittlung.step3_title'); ?></strong>
                    <p><?php sod_te('vermittlung.step3_text'); ?></p>
                </div>
                <div class="adoption-step">
                    <span>4</span>
                    <strong><?php sod_te('vermittlung.step4_title'); ?></strong>
                    <p><?php sod_te('vermittlung.step4_text'); ?></p>
                </div>
            </div>
        </div>
    </section>
</main>
<?php
get_footer();
