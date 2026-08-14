<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#204060">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url(get_template_directory_uri() . '/assets/images/app-icons/shield-logo-192.png'); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo esc_url(get_template_directory_uri() . '/assets/images/app-icons/shield-logo-192.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(get_template_directory_uri() . '/assets/images/app-icons/shield-logo-apple.png'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<nav class="site-nav">
    <div class="nav-inner">
        <a href="<?php echo esc_url(admin_url()); ?>" class="nav-admin-link" aria-label="Admin-Bereich">Admin</a>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-logo">
            <?php $custom_logo_id = get_theme_mod('custom_logo'); ?>
            <?php if ($custom_logo_id) : ?>
                <?php echo wp_get_attachment_image((int)$custom_logo_id, 'thumbnail', false, ['alt' => get_bloginfo('name')]); ?>
            <?php else : ?>
                <img src="<?php echo sod_asset('images/logo.png'); ?>" alt="<?php bloginfo('name'); ?>">
            <?php endif; ?>
            <div class="nav-logo-text"><?php echo esc_html(sod_org_name()); ?><small><?php sod_te('common.tagline'); ?></small></div>
        </a>

        <?php
        $mobile_menu_ctas = sprintf(
            '<li class="nav-mobile-lang"><div class="lang-switch">%1$s</div></li><li class="nav-mobile-cta nav-mobile-contact"><a href="%2$s">%3$s</a></li><li class="nav-mobile-cta nav-mobile-donate"><a href="%4$s">%5$s</a></li>',
            sod_lang_switcher_items(),
            esc_url(sod_page_url('kontakt')),
            esc_html(sod_t('common.nav_kontakt')),
            esc_url(sod_page_url('spenden')),
            esc_html(sod_t('common.nav_spenden'))
        );
        if (has_nav_menu('primary')) {
            wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'nav-links',
                'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s' . $mobile_menu_ctas . '</ul>',
                'depth' => 1,
            ]);
        } else {
            sod_nav_fallback();
        }
        ?>

        <div class="nav-actions">
            <?php sod_lang_switcher(); ?>
            <a href="<?php echo esc_url(sod_page_url('kontakt')); ?>" class="btn btn-ghost btn-sm"><?php sod_te('common.nav_kontakt'); ?></a>
            <a href="<?php echo esc_url(sod_page_url('spenden')); ?>" class="btn btn-secondary btn-sm"><?php sod_te('common.nav_spenden'); ?></a>
        </div>
        <button class="nav-hamburger" type="button" aria-label="<?php sod_te('common.nav_menu_label'); ?>" aria-expanded="false">&#9776;</button>
    </div>
</nav>
