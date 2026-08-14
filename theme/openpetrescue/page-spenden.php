<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main>
    <div class="page-hero">
        <div class="container">
            <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-accent-300);margin-bottom:var(--sp-3)"><?php sod_te('spenden.hero_eyebrow'); ?></p>
            <h1><?php sod_te('spenden.hero_title'); ?></h1>
            <p><?php sod_te('spenden.hero_lead'); ?></p>
        </div>
    </div>
    <section class="teaming-band">
        <div class="container">
            <div class="teaming-inner">
                <div class="teaming-logo-card"><img src="<?php echo esc_url(sod_asset('images/teaming-logo.png')); ?>" alt="Teaming" loading="lazy" width="136" height="35"></div>
                <h2 class="teaming-title"><?php sod_te('spenden.teaming_title'); ?></h2>
                <p class="teaming-text"><?php sod_th('spenden.teaming_text'); ?></p>
                <a class="btn btn-secondary btn-lg" href="<?php echo esc_url(sod_org_donation_url()); ?>" rel="noopener" target="_blank"><?php sod_te('spenden.teaming_btn'); ?></a>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="donation-layout">
                <div>
                    <h2 class="section-title" style="margin-bottom:var(--sp-4)"><?php sod_te('spenden.usage_title'); ?></h2>
                    <p style="font-size:1.0625rem;color:var(--text-secondary);line-height:1.7;margin-bottom:var(--sp-6)"><?php sod_te('spenden.usage_lead'); ?></p>
                    <div class="transparency-grid">
                        <div class="transparency-card">
                            <strong><?php sod_te('spenden.trans1_title'); ?></strong>
                            <p><?php sod_te('spenden.trans1_text'); ?></p>
                        </div>
                        <div class="transparency-card">
                            <strong><?php sod_te('spenden.trans2_title'); ?></strong>
                            <p><?php sod_te('spenden.trans2_text'); ?></p>
                        </div>
                    </div>
                    <div class="bank-card" style="margin:0 0 var(--sp-10)">
                        <h3 style="margin:0 0 var(--sp-3);font-size:1.15rem"><?php sod_te('spenden.track_title'); ?></h3>
                        <p style="font-size:.95rem;color:var(--text-secondary);line-height:1.7;margin-bottom:var(--sp-4)"><?php sod_te('spenden.track_text'); ?></p>
                        <div class="transparency-grid" style="margin:0">
                            <div class="transparency-card">
                                <strong><?php sod_te('spenden.track1_title'); ?></strong>
                                <p><?php sod_te('spenden.track1_text'); ?></p>
                            </div>
                            <div class="transparency-card">
                                <strong><?php sod_te('spenden.track2_title'); ?></strong>
                                <p><?php sod_te('spenden.track2_text'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="use-list">
                        <div class="use-item">
                            <div class="use-num">1</div>
                            <div>
                                <strong><?php sod_te('spenden.use1_title'); ?></strong>
                                <p><?php sod_te('spenden.use1_text'); ?></p>
                            </div>
                        </div>
                        <div class="use-item">
                            <div class="use-num">2</div>
                            <div>
                                <strong><?php sod_te('spenden.use2_title'); ?></strong>
                                <p><?php sod_te('spenden.use2_text'); ?></p>
                            </div>
                        </div>
                        <div class="use-item">
                            <div class="use-num">3</div>
                            <div>
                                <strong><?php sod_te('spenden.use3_title'); ?></strong>
                                <p><?php sod_te('spenden.use3_text'); ?></p>
                            </div>
                        </div>
                        <div class="use-item">
                            <div class="use-num">4</div>
                            <div>
                                <strong><?php sod_te('spenden.use4_title'); ?></strong>
                                <p><?php sod_te('spenden.use4_text'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="donation-side">
                    <div class="donation-widget" id="spenden-widget"
                        data-cta-once-tpl="<?php echo esc_attr(sod_t('spenden.cta_once_tpl')); ?>"
                        data-cta-monthly-tpl="<?php echo esc_attr(sod_t('spenden.cta_monthly_tpl')); ?>"
                        data-aria-paypal-tpl="<?php echo esc_attr(sod_t('spenden.aria_paypal_tpl')); ?>"
                        data-aria-bank-tpl="<?php echo esc_attr(sod_t('spenden.aria_bank_tpl')); ?>"
                        data-note-paypal="<?php echo esc_attr(sod_t('spenden.note_paypal')); ?>"
                        data-note-bank="<?php echo esc_attr(sod_t('spenden.note_bank')); ?>"
                        data-amount-input-label="<?php echo esc_attr(sod_t('spenden.amount_input_aria')); ?>"
                        data-paypal-disabled-title="<?php echo esc_attr(sod_t('spenden.paypal_disabled_title')); ?>">
                        <div class="widget-title"><?php sod_te('spenden.widget_title'); ?></div>
                        <div class="widget-sub"><?php sod_te('spenden.widget_sub'); ?></div>
                        <div class="type-tabs">
                            <button class="type-tab active" data-cadence="once"><?php sod_te('spenden.tab_once'); ?></button>
                            <button class="type-tab" data-cadence="monthly"><?php sod_te('spenden.tab_monthly'); ?></button>
                        </div>
                        <div class="amounts">
                            <button class="amount-btn">10 €</button>
                            <button class="amount-btn">25 €</button>
                            <button class="amount-btn active">50 €</button>
                            <button class="amount-btn">100 €</button>
                            <button class="amount-btn">200 €</button>
                            <button class="amount-btn"><?php sod_te('spenden.amount_free_label'); ?></button>
                        </div>
                        <div class="form-group" style="margin-bottom:var(--sp-2)">
                            <label class="form-label" style="font-size:.8125rem"><?php sod_te('spenden.custom_amount_label'); ?></label>
                            <div class="custom-input-wrap">
                                <span class="currency-prefix">€</span>
                            </div>
                        </div>
                        <div class="impact-box" id="impact-box"><?php sod_te('spenden.widget_impact'); ?></div>
                        <div class="payment-methods">
                            <button type="button" class="pay-method active" data-method="bank"><?php sod_te('spenden.pay_bank'); ?></button>
                            <button type="button" class="pay-method" data-method="paypal"><?php sod_te('spenden.pay_paypal'); ?></button>
                        </div>
                        <a class="btn btn-secondary btn-lg" href="#sod-bank-qr" id="widget-cta" style="width:100%;margin-bottom:var(--sp-3);justify-content:center"><?php sod_te('spenden.cta_once_tpl'); ?></a>
                        <div class="secure-note"><?php sod_te('spenden.widget_secure_note'); ?></div>
                    </div>
                    <?php echo do_shortcode('[sod_donation_options]'); ?>
                </div>
            </div>
        </div>
    </section>
    <section class="section" id="sachspenden">
        <div class="container" style="max-width:900px">
            <div class="section-header center">
                <p style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-primary-500);margin-bottom:var(--sp-2)"><?php sod_te('spenden.sachspenden_eyebrow'); ?></p>
                <h2 class="section-title"><?php sod_te('spenden.sachspenden_title'); ?></h2>
                <p class="section-subtitle"><?php sod_te('spenden.sachspenden_lead'); ?></p>
            </div>
            <div class="bank-card">
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.sachspenden_abgabeadresse_label'); ?></span><span class="bank-val"><?php echo esc_html(sod_org_address()); ?></span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.sachspenden_vorher_melden_label'); ?></span><span class="bank-val"><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', (string)get_option('sod_org_phone', ''))); ?>"><?php echo esc_html(get_option('sod_org_phone', '')); ?></a></span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.sachspenden_email_label'); ?></span><span class="bank-val"><a href="mailto:<?php echo esc_attr(sod_org_email()); ?>"><?php echo esc_html(sod_org_email()); ?></a></span></div>
            </div>
            <p style="text-align:center;font-size:.875rem;color:var(--text-secondary);line-height:1.6;margin-top:var(--sp-4)"><?php sod_te('spenden.sachspenden_note'); ?></p>
        </div>
    </section>
    <section class="section-sm bg-subtle" id="bank">
        <div class="container" style="max-width:700px">
            <div class="section-header center" style="margin-bottom:var(--sp-8)">
                <h2 class="section-title" style="font-size:1.75rem"><?php sod_te('spenden.bank_title'); ?></h2>
                <p class="section-subtitle"><?php sod_te('spenden.bank_subtitle'); ?></p>
            </div>
            <div class="bank-card">
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.bank_kontoinhaber_label'); ?></span><span class="bank-val"><?php echo esc_html(sod_org_name()); ?></span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.bank_zvr_label'); ?></span><span class="bank-val"><?php echo esc_html(get_option('sod_org_registration', '')); ?></span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.bank_iban_label'); ?></span><span class="bank-val"><?php echo esc_html(get_option('sod_org_iban', '')); ?></span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.bank_bic_label'); ?></span><span class="bank-val"><?php echo esc_html(get_option('sod_org_bic', '')); ?></span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.bank_bank_label'); ?></span><span class="bank-val">Oberbank AG</span></div>
                <div class="bank-row"><span class="bank-key"><?php sod_te('spenden.bank_verwendungszweck_label'); ?></span><span class="bank-val"><?php sod_te('spenden.bank_verwendungszweck_val'); ?></span></div>
            </div>
            <p style="text-align:center;font-size:.8125rem;color:var(--text-secondary);margin-top:var(--sp-4)"><?php sod_te('spenden.bank_note'); ?></p>
        </div>
    </section>
</main>
<?php
get_footer();
