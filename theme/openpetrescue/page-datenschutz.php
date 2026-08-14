<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="legal-hero">
    <div class="container"><h1><?php sod_te('datenschutz.hero_title'); ?></h1></div>
</div>
<main class="legal">
    <?php sod_th('datenschutz.body'); ?>
</main>
<?php
get_footer();
