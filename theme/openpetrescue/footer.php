<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-logo">
                    <img src="<?php echo sod_asset('images/logo.png'); ?>" alt="<?php echo esc_attr(sod_org_name()); ?>">
                    <span class="footer-logo-name"><?php echo esc_html(sod_org_name()); ?></span>
                </div>
                <p class="footer-desc"><?php sod_te('common.footer_desc'); ?></p>
                <div class="footer-social">
                    <a href="<?php echo esc_url(get_option('sod_org_instagram', '#')); ?>" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <rect x="4" y="4" width="16" height="16" rx="5"></rect>
                            <circle cx="12" cy="12" r="3.5"></circle>
                            <circle cx="16.7" cy="7.3" r="1"></circle>
                        </svg>
                    </a>
                    <a href="https://www.tiktok.com/@shield.of.dogs" target="_blank" rel="noopener" aria-label="TikTok">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M14 4v9.2a4.2 4.2 0 1 1-4.2-4.2"></path>
                            <path d="M14 4c.6 3.3 2.5 5.2 5.5 5.5"></path>
                        </svg>
                    </a>
                </div>
            </div>
            <div>
                <div class="footer-col-title"><?php sod_te('common.footer_org_title'); ?></div>
                <?php sod_footer_menu('footer_org', [
                    [sod_t('common.nav_ueber_uns'), home_url('/#ueber-uns')],
                    [sod_t('common.nav_region'), home_url('/#galerie')],
                    [sod_t('common.footer_impressum'), sod_page_url('impressum')],
                    [sod_t('common.footer_datenschutz'), sod_page_url('datenschutz')],
                ]); ?>
            </div>
            <div>
                <div class="footer-col-title"><?php sod_te('common.footer_help_title'); ?></div>
                <?php sod_footer_menu('footer_help', [
                    [sod_t('common.nav_vermittlung'), sod_page_url('vermittlung')],
                    [sod_t('common.nav_patenschaft'), sod_page_url('patenschaft')],
                    [sod_t('common.nav_spenden'), sod_page_url('spenden')],
                    [sod_t('common.nav_kontakt'), sod_page_url('kontakt')],
                ]); ?>
            </div>
            <div>
                <div class="footer-col-title"><?php sod_te('common.footer_contact_title'); ?></div>
                <ul class="footer-col-links">
                    <li><?php echo esc_html(sod_org_address()); ?></li>
                    <li><?php echo esc_html(get_option('sod_org_registration', '')); ?></li>
                    <li><a href="mailto:<?php echo esc_attr(sod_org_email()); ?>"><?php echo esc_html(sod_org_email()); ?></a></li>
                    <li><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', (string)get_option('sod_org_phone', ''))); ?>"><?php echo esc_html(get_option('sod_org_phone', '')); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php sod_te('common.footer_bottom_copyright'); ?></span>
            <span><?php sod_te('common.footer_bottom_tagline'); ?></span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
