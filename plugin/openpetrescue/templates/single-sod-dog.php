<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main>
    <header class="sod-page-hero">
        <div class="container">
            <h1><?php the_title(); ?></h1>
            <p><?php bloginfo('description'); ?></p>
        </div>
    </header>
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
</main>
<?php
get_footer();
