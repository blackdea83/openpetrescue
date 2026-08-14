<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
Template Name: Shield Kontakt
*/

get_header();
?>
<main>
    <div class="container">
        <div class="grid-2" style="align-items:start;gap:var(--sp-10)">
            <section class="contact-card">
                <p class="contact-eyebrow"><?php sod_te('kontakt.eyebrow'); ?></p>
                <h2 class="section-title"><?php sod_te('kontakt.title'); ?></h2>
                <p class="section-subtitle"><?php sod_te('kontakt.subtitle'); ?></p>
                <div class="contact-list">
                    <div class="contact-item">
                        <div class="contact-icon">A</div>
                        <div>
                            <div class="contact-label"><?php sod_te('kontakt.label_adresse'); ?></div>
                            <div class="contact-value"><?php echo esc_html(sod_org_address()); ?></div>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">@</div>
                        <div>
                            <div class="contact-label"><?php sod_te('kontakt.label_email'); ?></div>
                            <div class="contact-value"><a href="mailto:<?php echo esc_attr(sod_org_email()); ?>"><?php echo esc_html(sod_org_email()); ?></a></div>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">T</div>
                        <div>
                            <div class="contact-label"><?php sod_te('kontakt.label_telefon'); ?></div>
                            <div class="contact-value"><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', (string)get_option('sod_org_phone', ''))); ?>"><?php echo esc_html(get_option('sod_org_phone', '')); ?></a></div>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">D</div>
                        <div>
                            <div class="contact-label"><?php sod_te('kontakt.label_ansprechpartner'); ?></div>
                            <div class="contact-value"><?php echo esc_html(get_option('sod_org_chairperson', '')); ?></div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="contact-card">
                <p class="contact-eyebrow"><?php sod_te('kontakt.message_eyebrow'); ?></p>
                <h2 class="section-title"><?php sod_te('kontakt.message_title'); ?></h2>
                <?php echo do_shortcode('[sod_application_form]'); ?>
            </section>
        </div>
    </div>
</main>
<?php
get_footer();
