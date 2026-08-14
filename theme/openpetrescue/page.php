<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$content = '';
if (have_posts()) {
    the_post();
    $content = (string)get_the_content();
    rewind_posts();
}
$has_imported_hero = preg_match('/class="[^"]*(page-hero|legal-hero)[^"]*"/', $content) === 1;
$content_has_main = preg_match('/<main\b/i', $content) === 1;
?>
<?php if ($content_has_main) : ?>
    <?php
    while (have_posts()) {
        the_post();
        the_content();
    }
    ?>
<?php else : ?>
<main>
    <?php if (!$has_imported_hero) : ?>
        <header class="sod-page-hero">
            <div class="container">
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?>
                    <p><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>
    <?php if ($has_imported_hero) : ?>
        <?php
        while (have_posts()) {
            the_post();
            the_content();
        }
        ?>
    <?php else : ?>
        <section class="section">
            <div class="container sod-content">
                <?php
                while (have_posts()) {
                    the_post();
                    the_content();
                }
                ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php endif; ?>
<?php
get_footer();
