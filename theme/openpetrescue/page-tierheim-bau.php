<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
Template Name: Shield Tierheim-Bau
*/

get_header();
$sod_tierheim_bau_gallery = do_shortcode('[sod_tierheim_bau]');
?>
<main data-shelter-popup-title="<?php echo esc_attr(sod_t('tierheim_bau.popup_title')); ?>">
    <div class="page-hero">
        <div class="container">
            <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-accent-300);margin-bottom:var(--sp-3)"><?php sod_te('tierheim_bau.eyebrow'); ?></p>
            <h1><?php sod_te('tierheim_bau.title'); ?></h1>
            <p><?php sod_te('tierheim_bau.lead'); ?></p>
        </div>
    </div>
    <section class="section">
        <div class="container">
            <?php if ($sod_tierheim_bau_gallery !== '') : ?>
                <?php echo $sod_tierheim_bau_gallery; ?>
            <?php else : ?>
                <div class="tierheim-bau-wrap">
                    <p><?php sod_te('tierheim_bau.empty'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
get_footer();
