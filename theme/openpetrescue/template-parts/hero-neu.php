<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Neuer Hero-Bereich (29.09.2026): Text links, wechselnde Hunde rechts, die gerade Paten suchen.
 * Sichtbarkeit steuert sod_hero_new_visible() (vorerst nur Admins).
 */
$sod_hero_dogs = sod_hero_new_dogs(5);
$sod_hero_members = function_exists('sod_teaming_members') ? sod_teaming_members() : 0;
?>
<section class="sodh" id="hero">
    <div class="sodh-in">
        <div class="sodh-text">
            <?php if (!sod_hero_new_is_public()) : ?>
                <p class="sodh-preview"><?php sod_te('heronew.preview_badge'); ?></p>
            <?php endif; ?>
            <p class="sodh-eyebrow"><?php sod_te('heronew.eyebrow'); ?></p>
            <h1 class="sodh-title"><?php sod_te('heronew.line1'); ?><br><?php sod_te('heronew.line2'); ?><span><?php sod_te('heronew.line3'); ?></span></h1>
            <p class="sodh-lead"><?php sod_te('heronew.lead'); ?></p>
            <div class="sodh-ctas">
                <a class="sodh-btn sodh-btn-gold" href="<?php echo esc_url(sod_page_url('patenschaft')); ?>"><?php sod_te('heronew.cta_sponsor'); ?> <span aria-hidden="true">↗</span></a>
                <a class="sodh-btn sodh-btn-outline" href="<?php echo esc_url(sod_page_url('spenden')); ?>" data-sod-donate-trigger><?php sod_te('heronew.cta_donate'); ?></a>
            </div>
            <p class="sodh-trust"><?php sod_te('heronew.trust'); ?></p>
            <?php if (function_exists('sod_rescue_banner')) { sod_rescue_banner(); } ?>
        </div>

        <div class="sodh-media">
            <?php if (sod_org_donation_url() !== '') : ?>
            <a class="sodh-teaming" href="<?php echo esc_url(sod_org_donation_url()); ?>" rel="noopener" target="_blank" aria-label="<?php echo esc_attr(sod_t('home.teaming_label')); ?>">
                <img src="<?php echo esc_url(sod_asset('images/teaming-logo.png')); ?>" alt="Teaming" width="136" height="35" loading="lazy">
                <b><?php sod_te('home.teaming_amount'); ?></b>
                <span><?php sod_te('home.teaming_sub'); ?></span>
                <?php if ($sod_hero_members > 0) : ?><small><?php echo esc_html(number_format_i18n($sod_hero_members) . ' ' . ($sod_hero_members === 1 ? sod_t('home.teaming_members_singular') : sod_t('home.teaming_members_plural'))); ?></small><?php endif; ?>
            </a>
            <?php endif; ?>
            <div class="sodh-frame">
                <?php foreach ($sod_hero_dogs as $sod_i => $sod_d) : ?>
                    <img class="<?php echo $sod_i === 0 ? 'is-on' : ''; ?>" src="<?php echo esc_url($sod_d['image']); ?>" alt="<?php echo esc_attr($sod_d['name']); ?>" <?php echo $sod_i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async">
                <?php endforeach; ?>
            </div>
            <?php if (count($sod_hero_dogs) > 1) : ?>
                <div class="sodh-dots">
                    <?php foreach ($sod_hero_dogs as $sod_i => $sod_d) : ?><button type="button" class="<?php echo $sod_i === 0 ? 'is-on' : ''; ?>" aria-label="<?php echo esc_attr($sod_d['name']); ?>"></button><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($sod_hero_dogs) : ?>
                <?php foreach ($sod_hero_dogs as $sod_i => $sod_d) : ?>
                    <div class="sodh-card<?php echo $sod_i === 0 ? ' is-on' : ''; ?>">
                        <div>
                            <h3><?php echo esc_html(sprintf(sod_t('heronew.card_title'), $sod_d['name'])); ?></h3>
                            <?php if ($sod_d['line'] !== '') : ?><p><?php echo esc_html($sod_d['line']); ?></p><?php endif; ?>
                            <?php if ($sod_d['target'] > 0) : ?>
                                <div class="sodh-bar"><i style="width:<?php echo (int)$sod_d['percent']; ?>%"></i></div>
                                <small><?php echo esc_html(sprintf(sod_t('heronew.card_meta'), sod_story_money($sod_d['secured']), sod_story_money($sod_d['target']))); ?></small>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url($sod_d['url']); ?>"><?php sod_te('heronew.card_link'); ?> ↗</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<style>
.sodh { position: relative; overflow: hidden; padding: 72px 0 84px;
  background: radial-gradient(900px 500px at 85% 20%, rgba(32,64,96,.55), transparent 60%), radial-gradient(700px 400px at 0% 100%, rgba(228,169,31,.08), transparent 60%), var(--bg-page, #0b141b); }
.sodh-in { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: grid; grid-template-columns: 1.02fr 1fr; gap: 56px; align-items: center; }
.sodh-preview { display: inline-block; margin: 0 0 16px; padding: 4px 10px; border: 1px dashed rgba(243,199,79,.6); border-radius: 999px; font-size: 12px; color: var(--color-accent-300, #f3c74f); }
.sodh-eyebrow { margin: 0 0 22px; font-size: 12px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--color-accent-300, #f3c74f); }
.sodh-title { margin: 0; font-family: var(--font-display, inherit); font-size: clamp(40px, 5.4vw, 70px); line-height: 1.02; letter-spacing: -.025em; font-weight: 800; color: var(--text-primary, #f8fafc); }
.sodh-title span { display: block; color: var(--color-accent-300, #f3c74f); }
.sodh-lead { margin: 26px 0 0; max-width: 470px; font-size: 18px; line-height: 1.65; color: #cbd5e1; }
.sodh-ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
.sodh-btn { display: inline-flex; align-items: center; gap: 10px; height: 54px; padding: 0 24px; border-radius: 10px; font-weight: 700; font-size: 16px; text-decoration: none; transition: transform .15s, background .15s; }
.sodh-btn:hover { transform: translateY(-1px); }
.sodh-btn-gold { background: var(--color-accent-500, #e4a91f); color: var(--color-primary-950, #06111a); }
.sodh-btn-gold:hover { background: var(--color-accent-300, #f3c74f); color: var(--color-primary-950, #06111a); }
.sodh-btn-outline { border: 1.5px solid rgba(248,250,252,.7); color: var(--text-primary, #f8fafc); }
.sodh-btn-outline:hover { background: rgba(255,255,255,.06); color: var(--text-primary, #f8fafc); }
.sodh-trust { margin: 18px 0 0; font-size: 13px; color: var(--text-secondary, #94a3b8); }
.sodh-media { position: relative; }
.sodh-frame { position: relative; aspect-ratio: 1 / 1; border-radius: 160px 18px 18px 18px; overflow: hidden; background: var(--bg-surface, #101c26); box-shadow: 0 30px 80px rgba(0,0,0,.45); }
.sodh-frame img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale(1.04); transition: opacity .9s ease, transform 6s ease; }
.sodh-frame img.is-on { opacity: 1; transform: scale(1); }
.sodh-frame::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 55%, rgba(6,17,26,.55)); pointer-events: none; }
.sodh-dots { position: absolute; top: 22px; right: 22px; z-index: 2; display: flex; gap: 6px; }
.sodh-dots button { width: 9px; height: 9px; padding: 0; border: 0; border-radius: 50%; background: rgba(255,255,255,.45); cursor: pointer; transition: width .3s; }
.sodh-dots button.is-on { width: 24px; border-radius: 5px; background: var(--color-accent-300, #f3c74f); }
.sodh-card { position: absolute; left: 22px; right: 22px; bottom: 22px; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; border-radius: 14px; background: rgba(248,250,252,.97); color: var(--color-primary-950, #06111a); box-shadow: 0 12px 30px rgba(0,0,0,.25); opacity: 0; visibility: hidden; transition: opacity .45s ease, visibility .45s; }
.sodh-card.is-on { opacity: 1; visibility: visible; }
.sodh-card h3 { margin: 0 0 4px; font-size: 20px; font-weight: 800; color: var(--color-primary-950, #06111a); }
.sodh-card p { margin: 0; font-size: 14px; line-height: 1.45; color: #475569; }
.sodh-bar { max-width: 260px; height: 6px; margin-top: 10px; overflow: hidden; border-radius: 3px; background: #e2e8f0; }
.sodh-bar i { display: block; height: 100%; background: var(--color-accent-500, #e4a91f); }
.sodh-card small { display: block; margin-top: 5px; font-size: 12px; color: #64748b; }
.sodh-card a { flex: 0 0 auto; font-weight: 700; font-size: 14px; color: var(--color-primary-500, #204060); text-decoration: underline; text-underline-offset: 3px; white-space: nowrap; }
.sodh-teaming { position: absolute; left: -52px; top: 24px; z-index: 3; width: 132px; height: 132px; gap: 1px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: var(--color-accent-300, #f3c74f); color: var(--color-primary-950, #06111a); text-decoration: none; font-weight: 800; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
.sodh-teaming, .sodh-teaming:hover, .sodh-teaming * { text-decoration: none; }
.sodh-teaming img { width: 70px; height: auto; margin-bottom: 4px; }
.sodh-teaming b { font-size: 30px; line-height: 1; }
.sodh-teaming span { font-size: 10px; letter-spacing: .06em; text-transform: uppercase; }
.sodh-teaming small { font-size: 9px; font-weight: 600; opacity: .8; }
@media (max-width: 900px) {
  .sodh { padding: 36px 0 56px; }
  .sodh-in { grid-template-columns: 1fr; gap: 36px; }
  .sodh-frame { border-radius: 90px 16px 16px 16px; }
  .sodh-teaming { left: auto; right: 14px; top: 14px; width: 100px; height: 100px; }
  .sodh-teaming img { width: 54px; margin-bottom: 2px; }
  .sodh-teaming b { font-size: 22px; }
  .sodh-dots { right: auto; left: 22px; }
  .sodh-card { left: 14px; right: 14px; bottom: 14px; padding: 14px 16px; }
  .sodh-card h3 { font-size: 17px; }
  .sodh-lead { font-size: 16px; }
  .sodh-btn { height: 50px; font-size: 15px; }
}
@media (prefers-reduced-motion: reduce) { .sodh-frame img { transition: opacity .3s; transform: none; } }
</style>
<script>
(function () {
  var root = document.querySelector('.sodh');
  if (!root) { return; }
  var imgs = root.querySelectorAll('.sodh-frame img'), cards = root.querySelectorAll('.sodh-card'), dots = root.querySelectorAll('.sodh-dots button');
  if (imgs.length < 2) { return; }
  var cur = 0, timer;
  function show(i) {
    [imgs, cards, dots].forEach(function (list) { list.forEach(function (el, k) { el.classList.toggle('is-on', k === i); }); });
    cur = i;
  }
  function restart() { clearInterval(timer); timer = setInterval(function () { show((cur + 1) % imgs.length); }, 6000); }
  dots.forEach(function (d, k) { d.addEventListener('click', function () { show(k); restart(); }); });
  root.addEventListener('mouseenter', function () { clearInterval(timer); });
  root.addEventListener('mouseleave', restart);
  restart();
})();
</script>
