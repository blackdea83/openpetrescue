<?php
/**
 * Plugin Name: OpenPetRescue
 * Description: Verwaltung für Tierschutzorganisationen: Tiere, Vermittlung, Patenschaften, Lager und Finanzen.
 * Version: 1.39.13
 * Requires PHP: 8.0
 * Author: Peter Lehner / Shield of Dogs
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: openpetrescue
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class SOD_Plugin
{
    private const VERSION = '1.39.13';
    private const PRIVACY_NOTICE_VERSION = '2026-07-17';
    private const GENERAL_RECORD_RETENTION_YEARS = 3;
    private const PENDING_SPONSOR_RETENTION_DAYS = 30;
    private const PUBLIC_PHOTO_DELETION_DAYS = 30;

    /**
     * Gültigkeitsdauer des Double-Opt-In-Links bei der Mitgliederregistrierung.
     */
    private const MEMBER_VERIFY_TTL_HOURS = 48;

    /**
     * Technischer Support (Hilfe-Button unten rechts): Meldungen gehen an die
     * technische Betreuung, NICHT an die Vereins-Postfaecher - inhaltliche Anfragen
     * laufen weiterhin ueber das normale Kontaktformular.
     */
    private const SUPPORT_MAX_SCREENSHOTS = 3;
    private const SUPPORT_MAX_SCREENSHOT_BYTES = 6 * 1024 * 1024;

    private const DOG_DATE_META = [
        'sod_vaccination_date',
        'sod_next_vaccination',
        'sod_deworming_date',
    ];

    private const DOG_META = [
        'sod_status',
        'sod_age',
        'sod_breed',
        'sod_gender',
        'sod_weight',
        'sod_location',
        'sod_health',
        'sod_character',
        'sod_needs',
        'sod_show_adoption',
        'sod_show_sponsorship',
        'sod_photos_verified',
        'sod_video_url',
        'sod_sponsorship_amount',
        'sod_sponsorship_text',
        'sod_show_name_sponsorship',
        'sod_name_sponsorship_amount',
        'sod_name_sponsorship_text',
        'sod_name_sponsor_credit',
        'sod_monthly_food_need',
        'sod_monthly_food_secured',
        'sod_internal_area',
        'sod_internal_notes',
        'sod_microchip',
        'sod_passport_number',
        'sod_vaccination_date',
        'sod_next_vaccination',
        'sod_deworming_date',
        'sod_castration_status',
        'sod_medical_history',
        'sod_medication_plan',
        'sod_feeding_plan',
        'sod_document_links',
        'sod_status_history',
        'sod_quarantine_status',
        'sod_compatibility',
        'sod_transport_notes',
        'sod_followup_notes',
    ];

    /**
     * Datentypen, die per REST-API (Anwendungspasswort) abrufbar sind - bewusst kuratiert
     * statt pauschal alle internen Datentypen freizugeben. Paten/Finanzen enthalten
     * personenbezogene bzw. finanzielle Daten und wurden erst nach ausdruecklicher
     * Rueckfrage beim Verein freigegeben.
     */
    private const REST_ENABLED_RECORD_TYPES = ['sod_task', 'sod_interest', 'sod_sponsor', 'sod_finance'];

    public static function init(): void
    {
        add_action('init', [self::class, 'register_post_types']);
        add_action('init', [self::class, 'register_rest_meta'], 11);
        add_filter('dra_allow_rest_api', [self::class, 'allow_rest_api_for_app_passwords']);
        add_action('template_redirect', [self::class, 'staff_app_router'], 0);
        add_action('template_redirect', [self::class, 'public_pwa_router'], 0);
        add_action('template_redirect', [self::class, 'member_area_router'], 0);
        add_action('admin_post_nopriv_sod_member_register', [self::class, 'handle_member_register']);
        add_action('admin_post_sod_member_register', [self::class, 'handle_member_register']);
        add_action('admin_post_nopriv_sod_member_resend_verify', [self::class, 'handle_member_resend_verify']);
        add_action('admin_post_sod_member_resend_verify', [self::class, 'handle_member_resend_verify']);
        add_action('admin_post_sod_member_delete_account', [self::class, 'handle_member_delete_account']);
        add_filter('wp_authenticate_user', [self::class, 'block_unverified_member_login'], 10, 2);
        add_action('save_post_sod_dog_update', [self::class, 'save_dog_update'], 10, 2);
        add_action('admin_menu', [self::class, 'register_member_admin_menu']);
        add_action('admin_post_sod_member_manual_link', [self::class, 'handle_member_manual_link']);
        add_action('admin_post_sod_member_unlink', [self::class, 'handle_member_unlink']);
        add_action('sod_dog_photo_reminder_check', [self::class, 'check_dog_photo_reminders']);
        add_action('sod_check_inbound_replies', [self::class, 'check_inbound_application_replies']);
        add_filter('cron_schedules', [self::class, 'register_cron_schedules']);
        add_action('admin_post_sod_unmatched_reply_assign', [self::class, 'handle_unmatched_reply_assign']);
        add_action('admin_post_sod_unmatched_reply_dismiss', [self::class, 'handle_unmatched_reply_dismiss']);
        add_action('admin_post_sod_member_comment', [self::class, 'handle_member_comment']);
        add_action('admin_post_sod_staff_photo_upload', [self::class, 'handle_staff_photo_upload']);
        add_action('wp_head', [self::class, 'output_public_pwa_tags']);
        add_action('wp_footer', [self::class, 'output_public_pwa_script']);
        add_action('wp_footer', [self::class, 'output_support_widget'], 20);
        add_action('admin_post_nopriv_sod_support_request', [self::class, 'handle_support_request']);
        add_action('admin_post_sod_support_request', [self::class, 'handle_support_request']);
        add_action('admin_post_sod_support_reply', [self::class, 'handle_support_reply']);
        add_action('admin_post_sod_support_set_status', [self::class, 'handle_support_set_status']);
        add_action('admin_post_sod_support_screenshot', [self::class, 'serve_support_screenshot']);
        add_filter('manage_sod_support_posts_columns', [self::class, 'support_admin_columns']);
        add_action('manage_sod_support_posts_custom_column', [self::class, 'support_admin_column_content'], 10, 2);
        add_action('wp_abilities_api_init', [self::class, 'register_abilities']);
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('post_edit_form_tag', [self::class, 'post_edit_form_tag']);
        add_action('save_post_sod_dog', [self::class, 'save_dog'], 10, 2);
        add_action('save_post_sod_inventory', [self::class, 'save_inventory'], 10, 2);
        foreach (array_keys(self::admin_record_configs()) as $post_type) {
            add_action('save_post_' . $post_type, [self::class, 'save_admin_record'], 10, 2);
        }
        add_shortcode('sod_dogs', [self::class, 'dogs_shortcode']);
        add_shortcode('sod_application_form', [self::class, 'application_form_shortcode']);
        add_shortcode('sod_donation_options', [self::class, 'donation_options_shortcode']);
        add_shortcode('sod_current_needs', [self::class, 'current_needs_shortcode']);
        add_shortcode('sod_active_sponsorships', [self::class, 'active_sponsorships_shortcode']);
        add_shortcode('sod_tierheim_bau', [self::class, 'shelter_build_shortcode']);
        add_action('admin_post_nopriv_sod_paypal_ipn', [self::class, 'handle_paypal_ipn']);
        add_action('admin_post_sod_paypal_ipn', [self::class, 'handle_paypal_ipn']);
        add_action('admin_post_nopriv_sod_application', [self::class, 'handle_application']);
        add_action('admin_post_sod_application', [self::class, 'handle_application']);
        add_action('admin_post_nopriv_sod_sponsor_signup', [self::class, 'handle_sponsor_signup']);
        add_action('admin_post_sod_sponsor_signup', [self::class, 'handle_sponsor_signup']);
        add_action('admin_post_nopriv_sod_sponsor_bank_confirm', [self::class, 'handle_sponsor_bank_confirm']);
        add_action('admin_post_sod_sponsor_bank_confirm', [self::class, 'handle_sponsor_bank_confirm']);
        add_action('admin_post_nopriv_sod_sponsor_bank_cancel', [self::class, 'handle_sponsor_bank_cancel']);
        add_action('admin_post_sod_sponsor_bank_cancel', [self::class, 'handle_sponsor_bank_cancel']);
        add_action('admin_post_sod_application_toggle_status', [self::class, 'handle_application_toggle_status']);
        add_action('admin_post_sod_application_reply', [self::class, 'handle_application_reply']);
        add_action('admin_post_sod_application_add_note', [self::class, 'handle_application_add_note']);
        add_action('admin_post_sod_application_to_interest', [self::class, 'application_to_interest']);
        add_action('admin_post_sod_application_to_case', [self::class, 'application_to_case']);
        add_action('admin_post_sod_interest_to_case', [self::class, 'interest_to_case']);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_menu', [self::class, 'reorder_dog_submenu'], 999);
        add_action('admin_init', [self::class, 'maybe_import_json']);
        add_action('admin_post_sod_inventory_export', [self::class, 'export_inventory_csv']);
        add_action('admin_post_sod_dogs_export', [self::class, 'export_dogs_csv']);
        add_action('admin_post_sod_photo_needs_export', [self::class, 'export_photo_needs_csv']);
        add_action('admin_post_sod_case_contract', [self::class, 'print_case_contract']);
        add_action('admin_post_sod_case_precheck_pdf_dl', [self::class, 'download_case_precheck_pdf']);
        add_action('admin_post_sod_case_precheck_pdf_send', [self::class, 'send_case_precheck_pdf']);
        add_action('admin_post_sod_case_precheck_pdf_save_dog', [self::class, 'save_case_precheck_pdf_to_dog']);
        add_action('admin_post_sod_sponsor_certificate_pdf', [self::class, 'download_sponsor_certificate_pdf']);
        add_action('admin_post_sod_sponsor_certificate_send', [self::class, 'send_sponsor_certificate_pdf_manual']);
        add_action('admin_post_sod_precheck_document', [self::class, 'view_precheck_document']);
        add_action('admin_post_sod_dog_health_document', [self::class, 'view_dog_health_document']);
        add_action('admin_post_sod_case_contract_pdf_dl', [self::class, 'download_case_contract_pdf']);
        add_action('admin_post_sod_case_contract_pdf_save', [self::class, 'save_case_contract_pdf']);
        add_action('admin_post_sod_case_contract_pdf_send', [self::class, 'send_case_contract_pdf']);
        add_action('admin_post_sod_contract_document', [self::class, 'view_contract_document']);
        add_action('admin_post_sod_finance_report_export', [self::class, 'export_finance_report_csv']);
        add_action('admin_post_sod_finance_entries_export', [self::class, 'export_finance_entries_csv']);
        add_action('admin_post_sod_finance_income_export', [self::class, 'export_finance_income_csv']);
        add_action('admin_post_sod_save_settings', [self::class, 'save_settings']);
        add_action('admin_post_sod_send_daily_reminders_now', [self::class, 'send_daily_reminders_now']);
        add_action('admin_post_sod_check_inbound_replies_now', [self::class, 'check_inbound_application_replies_now']);
        add_action('init', [self::class, 'schedule_application_cleanup']);
        add_action('init', [self::class, 'maybe_upgrade'], 20);
        add_action('sod_cleanup_applications', [self::class, 'cleanup_applications']);
        add_action('sod_daily_reminders', [self::class, 'send_daily_reminders']);
        add_action('sod_daily_teaming_sync', [self::class, 'sync_teaming_members']);
        add_action('sod_sponsor_payment_deadline', [self::class, 'check_sponsor_payment_deadline']);
        add_filter('the_content', [self::class, 'dog_detail_content']);
        add_filter('template_include', [self::class, 'dog_single_template']);
        add_action('template_redirect', [self::class, 'restrict_dog_page']);
        add_action('template_redirect', [self::class, 'maybe_cancel_paypal_signup_return']);
        add_action('restrict_manage_posts', [self::class, 'dog_list_filters']);
        add_action('pre_get_posts', [self::class, 'apply_dog_list_filters']);
        add_action('restrict_manage_posts', [self::class, 'sponsor_list_filters']);
        add_action('pre_get_posts', [self::class, 'apply_sponsor_list_filters']);
        add_action('restrict_manage_posts', [self::class, 'application_list_filters']);
        add_action('pre_get_posts', [self::class, 'apply_application_list_filters']);
        add_filter('manage_edit-sod_application_columns', [self::class, 'application_admin_columns']);
        add_action('manage_sod_application_posts_custom_column', [self::class, 'application_admin_column_content'], 10, 2);
        add_filter('manage_edit-sod_sponsor_sortable_columns', [self::class, 'sponsor_sortable_columns']);
        add_filter('posts_join', [self::class, 'sponsor_dog_sort_join'], 10, 2);
        add_filter('posts_orderby', [self::class, 'sponsor_dog_sort_orderby'], 10, 2);
        add_action('admin_post_sod_dog_flyer', [self::class, 'print_dog_flyer']);
        add_action('admin_post_sod_finance_receipt', [self::class, 'print_finance_receipt']);
        add_action('admin_post_sod_finance_receipt_send', [self::class, 'send_finance_receipt']);
        add_action('admin_init', [self::class, 'maybe_redirect_staff_app_admin_page'], 1);
        add_action('admin_init', [self::class, 'restrict_staff_admin_pages']);
        add_action('admin_menu', [self::class, 'brand_admin_menu'], 999);
        add_action('admin_bar_menu', [self::class, 'brand_admin_bar'], 999);
        add_action('admin_head', [self::class, 'admin_brand_head']);
        add_action('admin_footer', [self::class, 'staff_admin_back_button']);
        add_action('login_enqueue_scripts', [self::class, 'login_branding']);
        add_action('login_header', [self::class, 'login_custom_header']);
        add_filter('login_headerurl', [self::class, 'login_header_url']);
        add_filter('login_headertext', [self::class, 'login_header_text']);
        add_action('login_footer', [self::class, 'member_login_footer_links']);
        add_filter('admin_body_class', [self::class, 'admin_body_class']);
        add_filter('admin_footer_text', [self::class, 'admin_footer_text']);
        add_filter('update_footer', [self::class, 'admin_version_footer'], 99);
        add_action('wp_dashboard_setup', [self::class, 'dashboard_widgets']);
        add_action('wp_dashboard_setup', [self::class, 'remove_staff_dashboard_widgets'], 99);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('admin_init', [self::class, 'maybe_rebuild_sponsor_index']);
        add_action('trashed_post', [self::class, 'on_sponsor_removed']);
        add_action('untrashed_post', [self::class, 'on_sponsor_removed']);
        add_action('before_delete_post', [self::class, 'on_sponsor_removed']);
        add_action('admin_notices', [self::class, 'admin_notices']);
        add_action('admin_notices', [self::class, 'setup_notice']);
        add_action('wp_enqueue_scripts', [self::class, 'public_assets']);
        add_filter('redirect_post_location', [self::class, 'redirect_post_location'], 10, 2);
        add_filter('manage_sod_dog_posts_columns', [self::class, 'dog_admin_columns']);
        add_action('manage_sod_dog_posts_custom_column', [self::class, 'dog_admin_column_content'], 10, 2);
        add_filter('manage_sod_interest_posts_columns', [self::class, 'interest_admin_columns']);
        add_action('manage_sod_interest_posts_custom_column', [self::class, 'interest_admin_column_content'], 10, 2);
        add_filter('manage_sod_sponsor_posts_columns', [self::class, 'sponsor_admin_columns']);
        add_action('manage_sod_sponsor_posts_custom_column', [self::class, 'sponsor_admin_column_content'], 10, 2);
        add_filter('manage_sod_inventory_posts_columns', [self::class, 'inventory_admin_columns']);
        add_action('manage_sod_inventory_posts_custom_column', [self::class, 'inventory_admin_column_content'], 10, 2);
        add_filter('manage_sod_finance_posts_columns', [self::class, 'finance_admin_columns']);
        add_action('manage_sod_finance_posts_custom_column', [self::class, 'finance_admin_column_content'], 10, 2);
    }

    public static function activate(): void
    {
        self::register_post_types();
        self::sync_roles();
        self::backfill_application_retention_dates();
        self::backfill_privacy_retention_dates();
        self::migrate_sponsor_payment_deadlines();
        update_option('sod_plugin_version', self::VERSION);
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('sod_cleanup_applications');
        wp_clear_scheduled_hook('sod_daily_reminders');
        wp_clear_scheduled_hook('sod_daily_teaming_sync');
        wp_clear_scheduled_hook('sod_sponsor_payment_deadline');
        wp_clear_scheduled_hook('sod_dog_photo_reminder_check');
        wp_clear_scheduled_hook('sod_check_inbound_replies');
        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void
    {
        if (get_option('sod_plugin_version') === self::VERSION) {
            return;
        }
        self::sync_roles();
        self::backfill_application_retention_dates();
        self::backfill_privacy_retention_dates();
        self::migrate_sponsor_payment_deadlines();
        self::maybe_reschedule_daily_reminders_to_seven();
        self::schedule_ticket_imap_check();
        self::maybe_backfill_finance_payment_details();
        flush_rewrite_rules();
        update_option('sod_plugin_version', self::VERSION);
    }

    /**
     * Einmalige Nachbefuellung: Zahlungsart und Hund wurden erst nachtraeglich als eigene
     * Felder bei sod_finance eingefuehrt und werden seither nur bei NEU erstellten
     * Eintraegen automatisch gesetzt. Bereits bestehende Eintraege blieben sonst leer -
     * hier einmalig aus vorhandenen Hinweisen (PayPal-Transaktions-ID, verknuepfter Pate,
     * Spendername) rekonstruiert, soweit moeglich.
     */
    private static function maybe_backfill_finance_payment_details(): void
    {
        if (get_option('sod_finance_payment_details_backfilled_v1')) {
            return;
        }
        self::backfill_finance_payment_details();
        update_option('sod_finance_payment_details_backfilled_v1', '1', false);
    }

    private static function backfill_finance_payment_details(): void
    {
        $query = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $sponsor_id = absint(get_post_meta($post_id, 'sod_finance_sponsor_initial_payment_id', true));
            $has_txn = trim((string)get_post_meta($post_id, 'sod_finance_paypal_txn_id', true)) !== '';

            if (trim((string)get_post_meta($post_id, 'sod_finance_payment_method', true)) === '') {
                if ($sponsor_id > 0) {
                    // sod_finance_sponsor_initial_payment_id wird ausschliesslich von der
                    // Dauerauftrag-Erstzahlung gesetzt (siehe create_sponsor_bank_finance_receipt()).
                    update_post_meta($post_id, 'sod_finance_payment_method', 'dauerauftrag');
                } elseif ($has_txn) {
                    update_post_meta($post_id, 'sod_finance_payment_method', 'paypal');
                }
            }

            if (absint(get_post_meta($post_id, 'sod_finance_dog', true)) === 0) {
                $dog_id = 0;
                if ($sponsor_id > 0 && get_post_type($sponsor_id) === 'sod_sponsor') {
                    $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
                } elseif ($has_txn) {
                    // Wiederkehrende PayPal-Abozahlungen speichern keine Paten-ID direkt,
                    // aber der Spendername entspricht exakt dem Titel des Paten-Datensatzes.
                    $donor = trim((string)get_post_meta($post_id, 'sod_finance_donor', true));
                    if ($donor !== '') {
                        $matches = get_posts([
                            'post_type' => 'sod_sponsor',
                            'post_status' => 'any',
                            'title' => $donor,
                            'posts_per_page' => 1,
                            'fields' => 'ids',
                        ]);
                        if ($matches) {
                            $dog_id = absint(get_post_meta((int)$matches[0], 'sod_sponsor_dog', true));
                        }
                    }
                }
                if ($dog_id > 0) {
                    update_post_meta($post_id, 'sod_finance_dog', $dog_id);
                }
            }
        }
    }

    /**
     * Einmalige Umstellung beim Versionswechsel auf 1.26.21: Der taegliche Versand lief
     * bisher um 06:00 (Server-Standardzeitzone, nicht die Website-Zeitzone). Schedule
     * loeschen und ueber schedule_daily_reminders() neu mit korrekter Zeitzone auf
     * 07:00 setzen. Betrifft nur den bereits laufenden Live-Zeitplan; ein neu
     * installiertes Plugin landet ueber schedule_application_cleanup() ohnehin direkt
     * bei 07:00.
     */
    private static function maybe_reschedule_daily_reminders_to_seven(): void
    {
        if (get_option('sod_daily_reminders_seven_v')) {
            return;
        }
        wp_clear_scheduled_hook('sod_daily_reminders');
        self::schedule_daily_reminders();
        update_option('sod_daily_reminders_seven_v', '1', false);
    }

    private static function migrate_sponsor_payment_deadlines(): void
    {
        $sponsor_ids = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => 'any',
            'posts_per_page' => 500,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        foreach ($sponsor_ids as $sponsor_id) {
            $sponsor_id = (int)$sponsor_id;
            delete_post_meta($sponsor_id, 'sod_privacy_delete_after');
            $confirmed = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1'
                || trim((string)get_post_meta($sponsor_id, 'sod_sponsor_paypal_first_txn_id', true)) !== ''
                || trim((string)get_post_meta($sponsor_id, 'sod_sponsor_since', true)) !== '';
            if ($confirmed) {
                update_post_meta($sponsor_id, 'sod_sponsor_payment_received', '1');
                if (trim((string)get_post_meta($sponsor_id, 'sod_sponsor_payment_confirmed_at', true)) === '') {
                    update_post_meta($sponsor_id, 'sod_sponsor_payment_confirmed_at', gmdate('c'));
                }
                continue;
            }
            $status = (string)get_post_meta($sponsor_id, 'sod_sponsor_status', true);
            if (!in_array($status, ['', 'ausstehend', 'aktiv', 'inaktiv'], true)) {
                continue;
            }
            update_post_meta($sponsor_id, 'sod_sponsor_status', 'aktiv');
            if (trim((string)get_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', true)) === '') {
                $created = (int)get_post_time('U', true, $sponsor_id);
                update_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', gmdate('c', $created > 0 ? $created : time()));
            }
            self::schedule_sponsor_payment_deadline($sponsor_id);
            self::sync_dog_public_sponsors((int)get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
        }
    }

    private static function area_caps(string $singular, string $plural): array
    {
        return [
            'edit_' . $singular,
            'read_' . $singular,
            'delete_' . $singular,
            'edit_' . $plural,
            'edit_others_' . $plural,
            'edit_private_' . $plural,
            'edit_published_' . $plural,
            'publish_' . $plural,
            'read_private_' . $plural,
            'delete_' . $plural,
            'delete_private_' . $plural,
            'delete_published_' . $plural,
            'delete_others_' . $plural,
            'create_' . $plural,
        ];
    }

    private static function sync_roles(): void
    {
        $dog_caps = self::area_caps('sod_dog', 'sod_dogs');
        $item_caps = self::area_caps('sod_item', 'sod_items');
        $finance_caps = self::area_caps('sod_finance', 'sod_finances');
        $dog_update_caps = self::area_caps('sod_dog_update', 'sod_dog_updates');

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (array_merge($dog_caps, $item_caps, $finance_caps, $dog_update_caps) as $cap) {
                $administrator->add_cap($cap);
            }
        }

        $editor = get_role('editor');
        if ($editor) {
            foreach (array_merge($dog_caps, $item_caps, $dog_update_caps) as $cap) {
                $editor->add_cap($cap);
            }
            foreach ($finance_caps as $cap) {
                $editor->remove_cap($cap);
            }
        }

        $base = ['read' => true, 'upload_files' => true];
        $staff_roles = [
            'sod_staff_dogs' => ['SOD Mitarbeiter Hunde', array_merge($dog_caps, $dog_update_caps)],
            'sod_staff_inventory' => ['SOD Mitarbeiter Lager', $item_caps],
            'sod_staff_all' => ['SOD Mitarbeiter', array_merge($dog_caps, $item_caps, $finance_caps, $dog_update_caps)],
        ];
        foreach ($staff_roles as $role_key => [$label, $caps]) {
            remove_role($role_key);
            add_role($role_key, $label, array_merge($base, array_fill_keys($caps, true)));
        }

        // Vereinsmitglieder (Paten mit Login) bekommen bewusst nur "read" - sie sehen nie
        // den wp-admin-Bereich, sondern ausschließlich die eigene Mitgliederbereich-Seite.
        remove_role('sod_member');
        add_role('sod_member', 'Vereinsmitglied (Pate)', ['read' => true]);
    }

    public static function schedule_application_cleanup(): void
    {
        if (!wp_next_scheduled('sod_cleanup_applications')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sod_cleanup_applications');
        }
        self::schedule_daily_reminders();
        if (!wp_next_scheduled('sod_daily_teaming_sync')) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'daily', 'sod_daily_teaming_sync');
        }
        self::schedule_photo_reminder_check();
        self::schedule_ticket_imap_check();
    }

    public static function register_cron_schedules(array $schedules): array
    {
        $schedules['sod_every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Alle 15 Minuten (SOD Ticket-Abruf)',
        ];
        return $schedules;
    }

    private static function schedule_ticket_imap_check(): void
    {
        if (wp_next_scheduled('sod_check_inbound_replies')) {
            return;
        }
        wp_schedule_event(time() + 2 * MINUTE_IN_SECONDS, 'sod_every_15_minutes', 'sod_check_inbound_replies');
    }

    /**
     * Taegliche Pruefung, ob fuer aktiv gesponserte Hunde seit 30 Tagen kein Foto-Update
     * mehr hochgeladen wurde - gleiches Zeitzone-korrekte Muster wie schedule_daily_reminders().
     */
    private static function schedule_photo_reminder_check(): void
    {
        if (wp_next_scheduled('sod_dog_photo_reminder_check')) {
            return;
        }
        $next_seven = new DateTime('tomorrow 07:00', wp_timezone());
        wp_schedule_event($next_seven->getTimestamp(), 'daily', 'sod_dog_photo_reminder_check');
    }

    /**
     * Versandzeit fuer die taegliche Erinnerungs-Mail: 07:00 in der auf der Website
     * eingestellten Zeitzone (nicht der Server-Standardzeitzone von PHP - sonst haette
     * "07:00" bei abweichender Servereinstellung zur falschen echten Uhrzeit gefuehrt).
     */
    private static function schedule_daily_reminders(): void
    {
        if (wp_next_scheduled('sod_daily_reminders')) {
            return;
        }
        $next_seven = new DateTime('tomorrow 07:00', wp_timezone());
        wp_schedule_event($next_seven->getTimestamp(), 'daily', 'sod_daily_reminders');
    }

    public static function cleanup_applications(): void
    {
        $query = new WP_Query([
            'post_type' => 'sod_application',
            'post_status' => 'any',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'delete_after',
                    'value' => gmdate('Y-m-d'),
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
            ],
        ]);
        foreach ($query->posts as $post_id) {
            wp_delete_post((int)$post_id, true);
        }
        self::cleanup_privacy_records();
    }

    public static function sync_teaming_members(): void
    {
        // Optional: laeuft nur, wenn eine Spenden-/Mitgliederplattform hinterlegt ist.
        $donation_url = self::org()['donation_url'];
        if ($donation_url === '') {
            return;
        }
        $response = wp_remote_get($donation_url, [
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (compatible; OpenPetRescueBot/1.0)',
        ]);
        if (is_wp_error($response)) {
            return;
        }
        $body = wp_remote_retrieve_body($response);
        if ($body === '' || !preg_match('/class="teamers"\s*>\s*<span class="numero"\s*>\s*([\d.,]+)\s*<\/span>/u', $body, $match)) {
            return;
        }
        $members = absint(preg_replace('/[^\d]/', '', $match[1]));
        if ($members <= 0) {
            return;
        }
        update_option('sod_teaming_members', $members);
    }

    private static function backfill_application_retention_dates(): void
    {
        $query = new WP_Query([
            'post_type' => 'sod_application',
            'post_status' => 'any',
            'posts_per_page' => 200,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'delete_after',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);
        foreach ($query->posts as $post_id) {
            $created = (int)get_post_time('U', true, (int)$post_id);
            if ($created <= 0) {
                $created = time();
            }
            update_post_meta((int)$post_id, 'delete_after', gmdate('Y-m-d', $created + 30 * DAY_IN_SECONDS));
        }
    }

    private static function backfill_privacy_retention_dates(): void
    {
        $legacy_consents = new WP_Query([
            'post_type' => 'sod_sponsor',
            'post_status' => 'any',
            'posts_per_page' => 200,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'sod_sponsor_show_wish', 'value' => '1'],
                ['key' => 'sod_sponsor_public_consent', 'compare' => 'NOT EXISTS'],
            ],
        ]);
        foreach ($legacy_consents->posts as $sponsor_id) {
            $created = (int)get_post_time('U', true, (int)$sponsor_id);
            update_post_meta((int)$sponsor_id, 'sod_sponsor_public_consent', '1');
            update_post_meta((int)$sponsor_id, 'sod_sponsor_public_consent_at', gmdate('c', $created > 0 ? $created : time()));
            update_post_meta((int)$sponsor_id, 'sod_sponsor_public_consent_version', 'vor-2026-07-17');
            update_post_meta((int)$sponsor_id, 'sod_sponsor_public_consent_source', 'online-formular-altbestand');
        }

        $finances = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => 'any',
            'posts_per_page' => 200,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => 'sod_privacy_delete_after',
                'compare' => 'NOT EXISTS',
            ]],
        ]);
        foreach ($finances->posts as $finance_id) {
            self::update_finance_retention((int)$finance_id);
        }

        $rules = [
            'sod_interest' => ['sod_interest_status', ['abgesagt', 'archiv']],
            'sod_case' => ['sod_case_status', ['abgeschlossen', 'abgebrochen']],
            'sod_sponsor' => ['sod_sponsor_status', ['beendet']],
        ];
        foreach ($rules as $post_type => [$status_key, $terminal_statuses]) {
            $query = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 200,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => $status_key, 'value' => $terminal_statuses, 'compare' => 'IN'],
                    ['key' => 'sod_privacy_delete_after', 'compare' => 'NOT EXISTS'],
                ],
            ]);
            foreach ($query->posts as $post_id) {
                $modified = (int)get_post_modified_time('U', true, (int)$post_id);
                self::set_privacy_delete_after((int)$post_id, $modified > 0 ? $modified : time(), self::GENERAL_RECORD_RETENTION_YEARS, 0);
            }
        }

        $pending = new WP_Query([
            'post_type' => 'sod_sponsor',
            'post_status' => 'any',
            'posts_per_page' => 200,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'sod_sponsor_status', 'value' => 'ausstehend'],
                ['key' => 'sod_privacy_delete_after', 'compare' => 'NOT EXISTS'],
            ],
        ]);
        foreach ($pending->posts as $post_id) {
            $created = (int)get_post_time('U', true, (int)$post_id);
            self::set_privacy_delete_after((int)$post_id, $created > 0 ? $created : time(), 0, self::PENDING_SPONSOR_RETENTION_DAYS);
        }
    }

    private static function set_privacy_delete_after(int $post_id, int $from, int $years, int $days): void
    {
        $modifier = $years > 0 ? '+' . $years . ' years' : '+' . $days . ' days';
        $delete_at = strtotime($modifier, $from);
        if ($delete_at !== false) {
            update_post_meta($post_id, 'sod_privacy_delete_after', gmdate('Y-m-d', $delete_at));
        }
    }

    private static function update_record_retention(int $post_id, string $post_type, string $status): void
    {
        $terminal = ($post_type === 'sod_interest' && in_array($status, ['abgesagt', 'archiv'], true))
            || ($post_type === 'sod_case' && in_array($status, ['abgeschlossen', 'abgebrochen'], true));
        if ($terminal) {
            self::set_privacy_delete_after($post_id, time(), self::GENERAL_RECORD_RETENTION_YEARS, 0);
            return;
        }
        delete_post_meta($post_id, 'sod_privacy_delete_after');
    }

    private static function update_finance_retention(int $finance_id): void
    {
        $date = self::iso_date((string)get_post_meta($finance_id, 'sod_finance_date', true));
        $timestamp = $date !== '' ? strtotime($date . ' 00:00:00 UTC') : (int)get_post_time('U', true, $finance_id);
        if ($timestamp === false || $timestamp <= 0) {
            $timestamp = time();
        }
        $delete_year = (int)gmdate('Y', $timestamp) + 8;
        update_post_meta($finance_id, 'sod_privacy_delete_after', sprintf('%04d-01-01', $delete_year));
    }

    private static function update_sponsor_retention(int $sponsor_id, string $status): void
    {
        // Patenschaftseinträge bleiben als Vereins- und Zahlungsnachweis erhalten.
        delete_post_meta($sponsor_id, 'sod_privacy_delete_after');
        if ($status === 'beendet') {
            update_post_meta($sponsor_id, 'sod_sponsor_photo_approved', '0');
            update_post_meta($sponsor_id, 'sod_sponsor_photo_delete_after', gmdate('Y-m-d', time() + self::PUBLIC_PHOTO_DELETION_DAYS * DAY_IN_SECONDS));
            return;
        }
        if (get_post_meta($sponsor_id, 'sod_sponsor_public_consent', true) === '1') {
            delete_post_meta($sponsor_id, 'sod_sponsor_photo_delete_after');
        }
    }

    private static function cleanup_privacy_records(): void
    {
        $today = gmdate('Y-m-d');
        foreach (['sod_interest', 'sod_case', 'sod_sponsor', 'sod_finance'] as $post_type) {
            $query = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 100,
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => 'sod_privacy_delete_after',
                    'value' => $today,
                    'compare' => '<=',
                    'type' => 'DATE',
                ]],
            ]);
            foreach ($query->posts as $post_id) {
                $post_id = (int)$post_id;
                if (get_post_meta($post_id, 'sod_privacy_legal_hold', true) === 'ja') {
                    continue;
                }
                if ($post_type === 'sod_sponsor') {
                    $status = (string)get_post_meta($post_id, 'sod_sponsor_status', true);
                    if ($status === 'ausstehend' && (get_post_meta($post_id, 'sod_sponsor_paypal_first_txn_id', true) !== '' || get_post_meta($post_id, 'sod_sponsor_since', true) !== '')) {
                        delete_post_meta($post_id, 'sod_privacy_delete_after');
                        continue;
                    }
                    self::delete_sponsor_photo($post_id);
                }
                if ($post_type === 'sod_case') {
                    foreach (['contract', 'precheck'] as $kind) {
                        $path = self::private_document_path($kind, $post_id);
                        if ($path !== '' && is_file($path)) {
                            @unlink($path);
                        }
                    }
                }
                wp_delete_post($post_id, true);
            }
        }

        $photos = new WP_Query([
            'post_type' => 'sod_sponsor',
            'post_status' => 'any',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => 'sod_sponsor_photo_delete_after',
                'value' => $today,
                'compare' => '<=',
                'type' => 'DATE',
            ]],
        ]);
        foreach ($photos->posts as $sponsor_id) {
            self::delete_sponsor_photo((int)$sponsor_id);
            delete_post_meta((int)$sponsor_id, 'sod_sponsor_photo_delete_after');
        }
    }

    private static function delete_sponsor_photo(int $sponsor_id): void
    {
        $photo_id = (int)get_post_meta($sponsor_id, 'sod_sponsor_photo_id', true);
        if ($photo_id > 0 && (int)wp_get_post_parent_id($photo_id) === $sponsor_id) {
            wp_delete_attachment($photo_id, true);
        }
        delete_post_meta($sponsor_id, 'sod_sponsor_photo_id');
        delete_post_meta($sponsor_id, 'sod_sponsor_photo_approved');
    }

    private static function iso_date(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $match)) {
            return sprintf('%04d-%02d-%02d', (int)$match[3], (int)$match[2], (int)$match[1]);
        }
        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    private static function display_date(string $value): string
    {
        $iso = self::iso_date($value);
        if ($iso === '') {
            return trim($value);
        }
        return date_i18n('d.m.Y', strtotime($iso) ?: null);
    }

    private static function date_field(int $post_id, string $key, string $label, string $importance = ''): void
    {
        printf(
            '<p class="%4$s"><label for="%1$s"><strong>%2$s</strong>%5$s</label><br><input type="date" id="%1$s" name="%1$s" value="%3$s"></p>',
            esc_attr($key),
            esc_html($label),
            esc_attr(self::iso_date((string)get_post_meta($post_id, $key, true))),
            esc_attr(self::field_class($importance)),
            self::field_badge($importance)
        );
    }

    private static function notification_emails(): array
    {
        $raw = (string)get_option('sod_notification_emails', '');
        $emails = array_values(array_filter(array_map('sanitize_email', preg_split('/[\r\n,;]+/', $raw) ?: [])));
        return $emails ?: [(string)get_option('admin_email')];
    }

    private static function home_image_slots(): array
    {
        return [
            'hero' => 'Hero-Bild ganz oben (Kopfbereich + Vorschaubild vor dem Video)',
            'support_vermittlung' => 'Karte "Wie du helfen kannst" – Vermittlung',
            'support_patenschaft' => 'Karte "Wie du helfen kannst" – Patenschaft',
            'support_spenden' => 'Karte "Wie du helfen kannst" – Spenden',
            'story1' => 'Story 1 – "Warum wir nicht wegsehen."',
            'story2' => 'Story 2 – "Hilfe vor Ort braucht Verlässlichkeit."',
            'story3' => 'Story 3 – "Wofür sich die Arbeit lohnt."',
            'letter' => 'Gedenk-Bereich (Zitat für Hunde, die nicht überlebt haben)',
            'tiktok1' => 'TikTok-Vorschau, Karte 1',
            'tiktok2' => 'TikTok-Vorschau, Karte 2',
            'tiktok3' => 'TikTok-Vorschau, Karte 3',
        ];
    }

    private static function galerie_photo_defaults(): array
    {
        return [
            'galerie1' => [143, 'Bildbeschreibung 1', 'galerie_cap1'],
            'galerie2' => [144, 'Zwei Helfer tragen gerettete Welpen von der Mülldeponie', 'galerie_cap2'],
            'galerie3' => [145, 'Gerettete Welpen werden von der Deponie weggetragen', 'galerie_cap3'],
            'galerie4' => [146, 'Ein Helfer füttert zwei Straßenhunde aus einem roten Eimer', 'galerie_cap4'],
            'galerie5' => [147, 'Ein Straßenhund frisst vorsichtig aus der Hand', 'galerie_cap5'],
            'galerie6' => [148, 'Zwei Hunde werden im Dorf mit Futtersäcken versorgt', 'galerie_cap6'],
            'galerie7' => [149, 'Zwei Welpen sitzen allein auf einer riesigen Mülldeponie', 'galerie_cap7'],
            'galerie8' => [150, 'Ein einzelner Hund sitzt inmitten einer riesigen Mülldeponie', 'galerie_cap8'],
            'galerie9' => [151, 'Ein verwahrloster Streuner sitzt jaulend im Gestrüpp', 'galerie_cap9'],
            'galerie10' => [152, 'Ein Welpe schaut direkt in die Kamera, im Hintergrund seine Geschwister', 'galerie_cap10'],
            'galerie11' => [153, 'Eine Gruppe Welpen frisst gemeinsam ihr Futter', 'galerie_cap11'],
            'galerie12' => [154, 'Bildbeschreibung 12', 'galerie_cap12'],
        ];
    }

    private static function galerie_photo_slot_labels(): array
    {
        $labels = [];
        $i = 1;
        foreach (array_keys(self::galerie_photo_defaults()) as $slot_key) {
            $labels[$slot_key] = 'Galerie – Bild ' . $i;
            $i++;
        }
        return $labels;
    }

    public static function galerie_photo_attachment_id(string $slot_key): int
    {
        $override = absint(get_option('sod_home_image_' . $slot_key, 0));
        if ($override > 0) {
            return $override;
        }
        $defaults = self::galerie_photo_defaults();
        return (int)($defaults[$slot_key][0] ?? 0);
    }

    public static function galerie_photos(): array
    {
        $photos = [];
        foreach (self::galerie_photo_defaults() as $slot_key => $data) {
            [, $alt, $cap_key] = $data;
            $photos[] = [self::galerie_photo_attachment_id($slot_key), $alt, $cap_key];
        }
        return $photos;
    }

    public static function teaming_members(): int
    {
        return absint(get_option('sod_teaming_members', 0));
    }

    public static function home_image_url(string $key, string $fallback_url): string
    {
        $id = absint(get_option('sod_home_image_' . $key, 0));
        if ($id > 0) {
            $url = wp_get_attachment_image_url($id, 'large');
            if ($url) {
                return $url;
            }
        }
        return $fallback_url;
    }

    private static function shelter_build_photos(): array
    {
        $raw = get_option('sod_shelter_build_photos', []);
        if (!is_array($raw)) {
            return [];
        }
        $photos = [];
        foreach ($raw as $entry) {
            $id = absint($entry['id'] ?? 0);
            if ($id <= 0 || get_post_type($id) !== 'attachment') {
                continue;
            }
            $photos[] = [
                'id' => $id,
                'caption' => sanitize_text_field((string)($entry['caption'] ?? '')),
                'date' => self::iso_date((string)($entry['date'] ?? '')) ?: gmdate('Y-m-d'),
            ];
        }
        usort($photos, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));
        return $photos;
    }

    private static function store_shelter_build_photo(): ?int
    {
        if (empty($_FILES['sod_shelter_new_photo']) || !is_array($_FILES['sod_shelter_new_photo'])) {
            return null;
        }
        $file = $_FILES['sod_shelter_new_photo'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return null;
        }

        $size = (int)($file['size'] ?? 0);
        $max_size = min((int)wp_max_upload_size(), 10 * 1024 * 1024);
        if ($size <= 0 || $size > $max_size) {
            return null;
        }

        $original_name = sanitize_file_name((string)($file['name'] ?? 'tierheim-bau'));
        $allowed = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ];
        $check = wp_check_filetype_and_ext((string)$file['tmp_name'], $original_name, $allowed);
        $ext = strtolower((string)($check['ext'] ?? ''));
        if ($ext === '' || !isset($allowed[$ext])) {
            return null;
        }
        $dims = @getimagesize((string)$file['tmp_name']);
        if ($dims === false || (int)($dims[0] ?? 0) < 1 || (int)($dims[1] ?? 0) < 1) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $moved = wp_handle_upload($file, ['test_form' => false, 'mimes' => $allowed]);
        if (!is_array($moved) || !empty($moved['error']) || empty($moved['file'])) {
            return null;
        }

        $attach_id = wp_insert_attachment([
            'post_mime_type' => (string)($moved['type'] ?? $allowed[$ext]),
            'post_title'     => 'Tierheim-Bau ' . gmdate('Y-m-d'),
            'post_status'    => 'inherit',
        ], (string)$moved['file']);
        if (!$attach_id || is_wp_error($attach_id)) {
            return null;
        }
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, (string)$moved['file']));

        return (int)$attach_id;
    }

    public static function shelter_build_shortcode(): string
    {
        $photos = self::shelter_build_photos();
        if (!$photos) {
            return '';
        }
        ob_start();
        ?>
        <div class="tierheim-bau-wrap">
            <div class="tierheim-bau-grid">
                <?php foreach ($photos as $photo) : ?>
                    <?php
                    $img = wp_get_attachment_image($photo['id'], 'large', false, ['loading' => 'lazy']);
                    if ($img === '') {
                        continue;
                    }
                    $date_display = date_i18n('d.m.Y', strtotime($photo['date']));
                    ?>
                    <button type="button" class="tierheim-bau-card" data-date="<?php echo esc_attr($date_display); ?>" data-caption="<?php echo esc_attr($photo['caption']); ?>" aria-label="<?php echo esc_attr(trim($date_display . ' — ' . $photo['caption'])); ?>">
                        <?php echo $img; ?>
                        <span class="tierheim-bau-card-meta">
                            <span class="tierheim-bau-card-date"><?php echo esc_html($date_display); ?></span>
                            <?php if ($photo['caption'] !== '') : ?><span class="tierheim-bau-card-caption"><?php echo esc_html($photo['caption']); ?></span><?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    public static function settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $emails = (string)get_option('sod_notification_emails', '');
        $paypal_email = (string)get_option('sod_paypal_email', '');
        $name_sponsorship_paypal_email = (string)get_option('sod_name_sponsorship_paypal_email', '');
        $teaming_members = absint(get_option('sod_teaming_members', 0));
        $saved = sanitize_text_field((string)($_GET['sod_saved'] ?? ''));
        $ipn_url = admin_url('admin-post.php?action=sod_paypal_ipn');
        $imap_host = (string)get_option('sod_ticket_imap_host', '');
        $imap_port = absint(get_option('sod_ticket_imap_port', 993));
        $imap_encryption = (string)get_option('sod_ticket_imap_encryption', 'ssl');
        $imap_user = (string)get_option('sod_ticket_imap_user', '');
        $imap_pass_set = (string)get_option('sod_ticket_imap_pass', '') !== '';
        $imap_ext_missing = !function_exists('imap_open');
        $imap_last_check = (string)get_option('sod_ticket_imap_last_check', '');
        $imap_last_error = (string)get_option('sod_ticket_imap_last_error', '');
        $imap_inbox_diag = (string)get_option('sod_ticket_imap_inbox_diag', '');
        $imap_sent_diag = (string)get_option('sod_ticket_imap_sent_diag', '');
        $unmatched_count = count(self::inbound_unmatched_emails());
        ?>
        <div class="wrap">
            <h1>SOD Einstellungen</h1>
            <?php if ($saved === '1') : ?><div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div><?php endif; ?>
            <?php if ($saved === 'mail_sent') : ?><div class="notice notice-success is-dismissible"><p>Tägliche Erinnerungs-Mail wurde soeben gesendet.</p></div><?php endif; ?>
            <?php if ($saved === 'imap_checked') : ?><div class="notice notice-success is-dismissible"><p>Ticket-Postfach wurde soeben geprüft. Ergebnis unten bei „Letzter Abruf“.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px">
                <input type="hidden" name="action" value="sod_send_daily_reminders_now">
                <?php wp_nonce_field('sod_send_daily_reminders_now', 'sod_daily_reminders_nonce'); ?>
                <button type="submit" class="button">Tägliche Erinnerungs-Mail jetzt senden</button>
                <span class="description"> Nützlich zum Testen oder wenn der geplante Versand einmal ausgefallen ist.</span>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px">
                <input type="hidden" name="action" value="sod_check_inbound_replies_now">
                <?php wp_nonce_field('sod_check_inbound_replies_now', 'sod_ticket_imap_nonce'); ?>
                <button type="submit" class="button">Ticket-Postfach jetzt prüfen</button>
                <span class="description"> Ruft das Ticket-Postfach sofort ab, statt bis zu 15 Minuten auf den nächsten automatischen Abruf zu warten.</span>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="sod_save_settings">
                <?php wp_nonce_field('sod_save_settings', 'sod_settings_nonce'); ?>
                <h2>Organisation</h2>
                <p class="description" style="max-width:640px">
                    Diese Angaben erscheinen auf der Website, in E-Mails, auf Spendenbestätigungen
                    und Patenschafts-Zertifikaten. Solange ein Feld leer ist, wird es überall
                    ausgelassen bzw. durch einen neutralen Platzhalter ersetzt.
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sod_org_name">Name der Organisation</label></th>
                        <td><input type="text" id="sod_org_name" name="sod_org_name" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_name', '')); ?>" placeholder="Mein Tierschutzverein"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_registration">Registernummer</label></th>
                        <td><input type="text" id="sod_org_registration" name="sod_org_registration" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_registration', '')); ?>" placeholder="z. B. ZVR-Zahl 123456789">
                        <p class="description">Je nach Land z. B. ZVR-Zahl, Vereinsregisternummer oder Charity-Number.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_chairperson">Vertretungsberechtigte Person</label></th>
                        <td><input type="text" id="sod_org_chairperson" name="sod_org_chairperson" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_chairperson', '')); ?>">
                        <p class="description">Erscheint als Unterschrift auf dem Patenschafts-Zertifikat.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_street">Straße und Hausnummer</label></th>
                        <td><input type="text" id="sod_org_street" name="sod_org_street" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_street', '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_zip">PLZ und Ort</label></th>
                        <td>
                            <input type="text" id="sod_org_zip" name="sod_org_zip" style="width:100px" value="<?php echo esc_attr((string)get_option('sod_org_zip', '')); ?>" placeholder="PLZ">
                            <input type="text" id="sod_org_city" name="sod_org_city" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_city', '')); ?>" placeholder="Ort">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_email">Öffentliche E-Mail-Adresse</label></th>
                        <td><input type="email" id="sod_org_email" name="sod_org_email" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_email', '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_phone">Telefon</label></th>
                        <td><input type="text" id="sod_org_phone" name="sod_org_phone" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_phone', '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_account_holder">Kontoinhaber</label></th>
                        <td><input type="text" id="sod_org_account_holder" name="sod_org_account_holder" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_account_holder', '')); ?>">
                        <p class="description">Leer lassen, wenn identisch mit dem Namen der Organisation.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_iban">IBAN</label></th>
                        <td><input type="text" id="sod_org_iban" name="sod_org_iban" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_iban', '')); ?>">
                        <p class="description">Wird für Überweisungen und den QR-Code auf der Spendenseite verwendet.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_bic">BIC</label></th>
                        <td><input type="text" id="sod_org_bic" name="sod_org_bic" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_bic', '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_donation_url">Spenden-/Mitgliederplattform</label></th>
                        <td><input type="url" id="sod_org_donation_url" name="sod_org_donation_url" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_donation_url', '')); ?>" placeholder="https://...">
                        <p class="description">Optional. Externe Plattform für monatliche Kleinbeiträge. Leer lassen, wenn nicht genutzt.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_instagram">Instagram</label></th>
                        <td><input type="url" id="sod_org_instagram" name="sod_org_instagram" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_instagram', '')); ?>" placeholder="https://..."></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sod_org_support_email">Technischer Support</label></th>
                        <td><input type="email" id="sod_org_support_email" name="sod_org_support_email" class="regular-text" value="<?php echo esc_attr((string)get_option('sod_org_support_email', '')); ?>">
                        <p class="description">Empfänger der Meldungen aus dem Hilfe-Button. Leer = WordPress-Admin-Adresse.</p></td>
                    </tr>
                </table>

                <h2>Benachrichtigungen</h2>
                <p><label for="sod_notification_emails"><strong>Empfänger für neue Anfragen und tägliche Erinnerungen</strong></label><br>
                <textarea id="sod_notification_emails" name="sod_notification_emails" rows="5" class="large-text" placeholder="eine E-Mail-Adresse pro Zeile"><?php echo esc_textarea($emails); ?></textarea></p>
                <p class="description">Eine Adresse pro Zeile. Wenn leer, wird die WordPress-Admin-Adresse verwendet. Die tägliche Erinnerungs-Mail enthält fällige Impfungen, Aufgaben, Wiedervorlagen und offene Nachkontrollen.</p>
                <p class="description">
                    <?php $sod_next_reminder = wp_next_scheduled('sod_daily_reminders'); ?>
                    Nächster geplanter Versand: <strong><?php echo $sod_next_reminder ? esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $sod_next_reminder), 'd.m.Y H:i') . ' Uhr') : 'nicht geplant (kein Zeitplan gefunden)'; ?></strong>
                </p>

                <h2>Ticket-System (Antworten auf Anfragen)</h2>
                <p class="description">
                    Damit Antworten der anfragenden Personen automatisch der richtigen Anfrage zugeordnet werden, ruft SOD dieses Postfach regelmäßig per IMAP ab.
                    Jede Anfrage-E-Mail (Bestätigung und Mitarbeiter-Antwort) bekommt dafür eine Ticket-Nummer im Betreff, z. B. <code>[Anfrage #211]</code>.
                    Antwortet ihr direkt aus diesem Postfach per E-Mail statt über den „Antwort senden“-Button, wird das ebenfalls automatisch im Verlauf erfasst (dafür wird auch der Gesendet-Ordner geprüft).
                </p>
                <?php if ($imap_ext_missing) : ?>
                    <p class="description" style="color:#a00"><strong>Achtung:</strong> Die PHP-IMAP-Erweiterung ist auf diesem Server nicht verfügbar. Der automatische Abruf kann daher nicht funktionieren, auch wenn hier alles korrekt eingetragen ist. Bitte beim Hoster aktivieren lassen.</p>
                <?php endif; ?>
                <p>
                    <label for="sod_ticket_imap_user"><strong>Postfach (Benutzername)</strong></label><br>
                    <input type="email" id="sod_ticket_imap_user" name="sod_ticket_imap_user" class="regular-text" value="<?php echo esc_attr($imap_user); ?>">
                </p>
                <p>
                    <label for="sod_ticket_imap_pass"><strong>Passwort</strong></label><br>
                    <input type="password" id="sod_ticket_imap_pass" name="sod_ticket_imap_pass" class="regular-text" autocomplete="new-password" placeholder="<?php echo $imap_pass_set ? 'Gespeichert – nur bei Änderung ausfüllen' : 'noch nicht hinterlegt'; ?>">
                    <span class="description">Wird nur bei Eingabe überschrieben. Aktuell <?php echo $imap_pass_set ? 'hinterlegt.' : 'nicht hinterlegt.'; ?></span>
                </p>
                <p>
                    <label for="sod_ticket_imap_host"><strong>IMAP-Server</strong></label><br>
                    <input type="text" id="sod_ticket_imap_host" name="sod_ticket_imap_host" class="regular-text" value="<?php echo esc_attr($imap_host); ?>">
                    &nbsp; Port
                    <input type="number" min="1" max="65535" step="1" id="sod_ticket_imap_port" name="sod_ticket_imap_port" class="small-text" value="<?php echo esc_attr((string)$imap_port); ?>">
                    &nbsp;
                    <select name="sod_ticket_imap_encryption">
                        <option value="ssl" <?php selected($imap_encryption, 'ssl'); ?>>SSL/TLS</option>
                        <option value="tls" <?php selected($imap_encryption, 'tls'); ?>>STARTTLS</option>
                        <option value="" <?php selected($imap_encryption, ''); ?>>keine Verschlüsselung</option>
                    </select>
                </p>
                <p class="description">
                    Letzter Abruf: <strong><?php echo $imap_last_check !== '' ? esc_html(self::format_utc_datetime($imap_last_check)) : 'noch nie'; ?></strong>
                    <?php if ($imap_last_error !== '') : ?><br><span style="color:#a00">Letzter Fehler: <?php echo esc_html($imap_last_error); ?></span><?php endif; ?>
                    <?php if ($imap_inbox_diag !== '') : ?><br><span class="sod-muted">Posteingang (Diagnose): <?php echo esc_html($imap_inbox_diag); ?></span><?php endif; ?>
                    <?php if ($imap_sent_diag !== '') : ?><br><span class="sod-muted">Gesendet-Ordner (Diagnose): <?php echo esc_html($imap_sent_diag); ?></span><?php endif; ?>
                    <?php if ($unmatched_count > 0) : ?>
                        <br><a href="<?php echo esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-unmatched-replies')); ?>"><?php echo esc_html((string)$unmatched_count); ?> eingegangene Antwort(en) konnten nicht automatisch zugeordnet werden – hier prüfen</a>
                    <?php endif; ?>
                </p>

                <h2>Online-Spenden (PayPal)</h2>
                <p><label for="sod_paypal_email"><strong>PayPal-E-Mail für Spenden</strong></label><br>
                <input type="email" id="sod_paypal_email" name="sod_paypal_email" class="regular-text" placeholder="spenden@example.org" value="<?php echo esc_attr($paypal_email); ?>"></p>
                <p class="description">
                    E-Mail-Adresse eures PayPal-Kontos, an das Spenden gehen sollen. Für reduzierte Gebühren als Verein: PayPal-Konto unter
                    <a href="https://www.paypal.com/at/webapps/mpp/charity" target="_blank" rel="noopener">paypal.com/at/webapps/mpp/charity</a>
                    als gemeinnützige Organisation verifizieren lassen. Solange dieses Feld leer ist, wird auf der Spenden-Seite kein PayPal-Button angezeigt.
                </p>
                <p class="description">
                    Damit Spenden automatisch unter „Spenden &amp; Ausgaben" erfasst werden, muss <strong>keine</strong> zusätzliche Einstellung im PayPal-Konto vorgenommen werden – die Benachrichtigungs-Adresse wird automatisch mitgeschickt:<br>
                    <code><?php echo esc_html($ipn_url); ?></code>
                </p>

                <p><label for="sod_name_sponsorship_paypal_email"><strong>Eigene PayPal-E-Mail für Namenspatenschaften (optional)</strong></label><br>
                <input type="email" id="sod_name_sponsorship_paypal_email" name="sod_name_sponsorship_paypal_email" class="regular-text" placeholder="leer lassen, um obige PayPal-Adresse zu verwenden" value="<?php echo esc_attr($name_sponsorship_paypal_email); ?>"></p>
                <p class="description">
                    Nur ausfüllen, wenn Zahlungen aus Namenspatenschaften auf ein anderes PayPal-Konto gehen sollen als normale Spenden. Bleibt das Feld leer, wird die PayPal-Adresse oben verwendet.
                </p>

                <h3>PayPal-Diagnose</h3>
                <?php
                $ipn_log = get_option('sod_paypal_ipn_log', []);
                $ipn_log = is_array($ipn_log) ? $ipn_log : [];
                $ipn_unverified = get_option('sod_paypal_ipn_unverified', []);
                $unverified_count = (int)(is_array($ipn_unverified) ? ($ipn_unverified['count'] ?? 0) : 0);
                ?>
                <p class="description">
                    Hier stehen PayPal-Meldungen, die eingegangen sind, aber <strong>nicht</strong> verarbeitet werden konnten – mit dem jeweiligen Grund.
                    Bleibt diese Liste leer, obwohl jemand eine Patenschaft über PayPal abgeschlossen hat, kommt bei uns gar keine Meldung von PayPal an.
                </p>
                <?php if ($ipn_log) : ?>
                    <table class="widefat striped" style="max-width:900px;margin-top:8px">
                        <thead><tr><th style="width:150px">Zeitpunkt</th><th>Grund</th><th style="width:150px">Pate</th></tr></thead>
                        <tbody>
                        <?php foreach ($ipn_log as $entry) : ?>
                            <?php
                            $entry_sponsor = absint($entry['sponsor'] ?? 0);
                            $entry_context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
                            $context_parts = [];
                            foreach ($entry_context as $context_key => $context_value) {
                                if ((string)$context_value !== '') {
                                    $context_parts[] = $context_key . ': ' . $context_value;
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html(self::format_utc_datetime((string)($entry['time'] ?? ''))); ?></td>
                                <td>
                                    <?php echo esc_html((string)($entry['reason'] ?? '')); ?>
                                    <?php if ($context_parts) : ?>
                                        <br><span class="sod-muted"><?php echo esc_html(implode(' · ', $context_parts)); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entry_sponsor > 0 && get_post_type($entry_sponsor) === 'sod_sponsor') : ?>
                                        <a href="<?php echo esc_url((string)get_edit_post_link($entry_sponsor)); ?>"><?php echo esc_html(get_the_title($entry_sponsor) ?: ('Pate #' . $entry_sponsor)); ?></a>
                                    <?php else : ?>
                                        <span class="sod-muted">nicht zuordenbar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="description"><em>Bisher wurde keine PayPal-Meldung abgelehnt.</em></p>
                <?php endif; ?>
                <?php if ($unverified_count > 0) : ?>
                    <p class="description">
                        Zusätzlich wurden <strong><?php echo esc_html((string)$unverified_count); ?></strong> Anfragen abgewiesen, weil PayPal sie nicht als echt bestätigt hat
                        (zuletzt <?php echo esc_html(self::format_utc_datetime((string)($ipn_unverified['last'] ?? ''))); ?> Uhr).
                        Einzelne solche Anfragen sind normal – an diese offene Adresse kann jeder senden.
                    </p>
                <?php endif; ?>

                <h2>Teaming</h2>
                <p><label for="sod_teaming_members"><strong>Aktuelle Mitgliederzahl bei Teaming</strong></label><br>
                <input type="number" min="0" step="1" id="sod_teaming_members" name="sod_teaming_members" class="small-text" value="<?php echo esc_attr((string)$teaming_members); ?>"></p>
                <p class="description">
                    Wird einmal täglich automatisch von der Teaming-Gruppenseite abgerufen und aktualisiert. Du kannst die Zahl hier trotzdem jederzeit von Hand überschreiben. Solange das Feld leer bzw. 0 ist, wird auf der Startseite keine Mitgliederzahl angezeigt.
                </p>

                <h2>Startseiten-Bilder</h2>
                <p class="description">Hier siehst du genau, wo jedes Bild auf der Startseite erscheint, und kannst es direkt über die Mediathek austauschen.</p>
                <table class="widefat" style="max-width:900px;margin-top:12px">
                    <tbody>
                        <?php foreach (self::home_image_slots() as $slot_key => $slot_label) : ?>
                            <?php
                            $attachment_id = absint(get_option('sod_home_image_' . $slot_key, 0));
                            $thumb_url = $attachment_id > 0 ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
                            ?>
                            <tr data-sod-home-image-row="<?php echo esc_attr($slot_key); ?>">
                                <td style="width:72px">
                                    <div class="sod-home-image-thumb" style="width:64px;height:64px;border-radius:6px;overflow:hidden;background:#1b1b1b;display:flex;align-items:center;justify-content:center">
                                        <?php if ($thumb_url !== '') : ?>
                                            <img src="<?php echo esc_url($thumb_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                                        <?php else : ?>
                                            <span style="font-size:.6875rem;color:#888;text-align:center">Standard</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($slot_label); ?></strong><br>
                                    <span class="description"><?php echo $attachment_id > 0 ? 'Eigenes Bild ausgewählt.' : 'Standardbild wird verwendet.'; ?></span>
                                </td>
                                <td style="width:220px;text-align:right">
                                    <input type="hidden" name="sod_home_image_<?php echo esc_attr($slot_key); ?>" value="<?php echo esc_attr((string)$attachment_id); ?>" data-sod-home-image-input="<?php echo esc_attr($slot_key); ?>">
                                    <button type="button" class="button" data-sod-home-image-select="<?php echo esc_attr($slot_key); ?>">Bild auswählen</button>
                                    <button type="button" class="button-link" data-sod-home-image-clear="<?php echo esc_attr($slot_key); ?>" style="margin-left:8px;color:#a00">Zurücksetzen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>Galerie (Projektseite)</h2>
                <p class="description">Hier kannst du die 12 Bilder der Galerie austauschen, ohne den Code zu ändern. Alt-Text und Bildunterschrift bleiben wie hinterlegt.</p>
                <table class="widefat" style="max-width:900px;margin-top:12px">
                    <tbody>
                        <?php foreach (self::galerie_photo_slot_labels() as $slot_key => $slot_label) : ?>
                            <?php
                            $attachment_id = absint(get_option('sod_home_image_' . $slot_key, 0));
                            $effective_id = self::galerie_photo_attachment_id($slot_key);
                            $thumb_url = $effective_id > 0 ? wp_get_attachment_image_url($effective_id, 'thumbnail') : '';
                            ?>
                            <tr data-sod-home-image-row="<?php echo esc_attr($slot_key); ?>">
                                <td style="width:72px">
                                    <div class="sod-home-image-thumb" style="width:64px;height:64px;border-radius:6px;overflow:hidden;background:#1b1b1b;display:flex;align-items:center;justify-content:center">
                                        <?php if ($thumb_url !== '') : ?>
                                            <img src="<?php echo esc_url($thumb_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                                        <?php else : ?>
                                            <span style="font-size:.6875rem;color:#888;text-align:center">kein Bild</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($slot_label); ?></strong><br>
                                    <span class="description"><?php echo $attachment_id > 0 ? 'Eigenes Bild ausgewählt.' : 'Aktuelles Bild wird verwendet.'; ?></span>
                                </td>
                                <td style="width:220px;text-align:right">
                                    <input type="hidden" name="sod_home_image_<?php echo esc_attr($slot_key); ?>" value="<?php echo esc_attr((string)$attachment_id); ?>" data-sod-home-image-input="<?php echo esc_attr($slot_key); ?>">
                                    <button type="button" class="button" data-sod-home-image-select="<?php echo esc_attr($slot_key); ?>">Bild auswählen</button>
                                    <button type="button" class="button-link" data-sod-home-image-clear="<?php echo esc_attr($slot_key); ?>" style="margin-left:8px;color:#a00">Zurücksetzen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>Tierheim-Baufortschritt</h2>
                <p class="description">Fotos, die auf der öffentlichen Seite „Tierheim-Bau" den aktuellen Baufortschritt zeigen. Neuestes Foto zuerst, wird automatisch nach Datum sortiert.</p>
                <?php $shelter_photos = self::shelter_build_photos(); ?>
                <?php if ($shelter_photos) : ?>
                    <table class="widefat" style="max-width:900px;margin-top:12px">
                        <thead>
                            <tr><th style="width:72px"></th><th>Bildunterschrift</th><th style="width:150px">Datum</th><th style="width:60px">Löschen</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shelter_photos as $i => $photo) : ?>
                                <?php $thumb_url = wp_get_attachment_image_url($photo['id'], 'thumbnail'); ?>
                                <tr>
                                    <td>
                                        <div style="width:64px;height:64px;border-radius:6px;overflow:hidden;background:#1b1b1b">
                                            <?php if ($thumb_url) : ?><img src="<?php echo esc_url($thumb_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?>
                                        </div>
                                        <input type="hidden" name="sod_shelter_photo_id[]" value="<?php echo esc_attr((string)$photo['id']); ?>">
                                    </td>
                                    <td><input type="text" class="regular-text" name="sod_shelter_photo_caption[]" maxlength="160" value="<?php echo esc_attr($photo['caption']); ?>" placeholder="z.B. Baugrund planiert"></td>
                                    <td><input type="date" name="sod_shelter_photo_date[]" value="<?php echo esc_attr($photo['date']); ?>"></td>
                                    <td style="text-align:center"><label><input type="checkbox" name="sod_shelter_photo_delete[]" value="<?php echo esc_attr((string)$i); ?>"></label></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="description">Noch keine Baufortschritt-Fotos hinterlegt.</p>
                <?php endif; ?>
                <table class="widefat" style="max-width:900px;margin-top:16px">
                    <tbody>
                        <tr>
                            <td style="width:220px"><strong>Neues Foto hinzufügen</strong><br><input type="file" name="sod_shelter_new_photo" accept="image/jpeg,image/png,image/webp"></td>
                            <td><label>Bildunterschrift<br><input type="text" class="regular-text" name="sod_shelter_new_caption" maxlength="160" placeholder="z.B. Fundament wird gegossen"></label></td>
                            <td style="width:180px"><label>Datum<br><input type="date" name="sod_shelter_new_date" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>"></label></td>
                        </tr>
                    </tbody>
                </table>
                <p class="description">JPG, PNG oder WEBP, max. 10 MB. Das Foto erscheint sofort nach dem Speichern auf der öffentlichen Seite „Tierheim-Bau".</p>

                <p><button class="button button-primary" type="submit" style="margin-top:16px">Speichern</button></p>
            </form>
        </div>
        <?php
    }

    /**
     * Erstinstallation: solange der Name der Organisation fehlt, ist das System
     * noch nicht einsatzbereit (Website, E-Mails und Belege zeigen dann nur
     * Platzhalter). Deshalb ein deutlicher Hinweis mit Direktlink.
     */
    public static function setup_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (trim((string)get_option('sod_org_name', '')) !== '') {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && (string)$screen->id === 'sod_dog_page_sod-settings') {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('OpenPetRescue ist noch nicht eingerichtet.', 'openpetrescue'),
            esc_html__('Trage zuerst die Daten deiner Organisation ein - sie erscheinen auf der Website, in E-Mails und auf Belegen.', 'openpetrescue'),
            esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-settings')),
            esc_html__('Jetzt einrichten', 'openpetrescue')
        );
    }

    public static function save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_save_settings', 'sod_settings_nonce');

        // Organisationsdaten: bewusst einzeln behandelt, damit E-Mail/URL-Felder
        // die passende Bereinigung bekommen und nichts ungeprueft gespeichert wird.
        $org_text_fields = ['name', 'registration', 'chairperson', 'street', 'zip', 'city', 'phone', 'account_holder', 'iban', 'bic'];
        foreach ($org_text_fields as $field) {
            update_option('sod_org_' . $field, sanitize_text_field((string)($_POST['sod_org_' . $field] ?? '')));
        }
        update_option('sod_org_email', sanitize_email((string)($_POST['sod_org_email'] ?? '')));
        update_option('sod_org_support_email', sanitize_email((string)($_POST['sod_org_support_email'] ?? '')));
        update_option('sod_org_donation_url', esc_url_raw((string)($_POST['sod_org_donation_url'] ?? '')));
        update_option('sod_org_instagram', esc_url_raw((string)($_POST['sod_org_instagram'] ?? '')));
        $raw = sanitize_textarea_field((string)($_POST['sod_notification_emails'] ?? ''));
        update_option('sod_notification_emails', $raw);
        update_option('sod_paypal_email', sanitize_email((string)($_POST['sod_paypal_email'] ?? '')));
        update_option('sod_name_sponsorship_paypal_email', sanitize_email((string)($_POST['sod_name_sponsorship_paypal_email'] ?? '')));
        update_option('sod_teaming_members', absint($_POST['sod_teaming_members'] ?? 0));
        update_option('sod_ticket_imap_host', sanitize_text_field((string)($_POST['sod_ticket_imap_host'] ?? '')));
        update_option('sod_ticket_imap_port', absint($_POST['sod_ticket_imap_port'] ?? 993));
        $imap_encryption = sanitize_text_field((string)($_POST['sod_ticket_imap_encryption'] ?? ''));
        update_option('sod_ticket_imap_encryption', in_array($imap_encryption, ['ssl', 'tls', ''], true) ? $imap_encryption : 'ssl');
        update_option('sod_ticket_imap_user', sanitize_email((string)($_POST['sod_ticket_imap_user'] ?? '')));
        $imap_pass = (string)($_POST['sod_ticket_imap_pass'] ?? '');
        if ($imap_pass !== '') {
            update_option('sod_ticket_imap_pass', $imap_pass, false);
        }
        foreach (array_keys(self::home_image_slots()) as $slot_key) {
            update_option('sod_home_image_' . $slot_key, absint($_POST['sod_home_image_' . $slot_key] ?? 0));
        }
        foreach (array_keys(self::galerie_photo_defaults()) as $slot_key) {
            update_option('sod_home_image_' . $slot_key, absint($_POST['sod_home_image_' . $slot_key] ?? 0));
        }

        $ids = (array)($_POST['sod_shelter_photo_id'] ?? []);
        $captions = (array)($_POST['sod_shelter_photo_caption'] ?? []);
        $dates = (array)($_POST['sod_shelter_photo_date'] ?? []);
        $deleted = array_map('absint', (array)($_POST['sod_shelter_photo_delete'] ?? []));
        $photos = [];
        foreach ($ids as $i => $id) {
            if (in_array((int)$i, $deleted, true)) {
                continue;
            }
            $id = absint($id);
            if ($id <= 0) {
                continue;
            }
            $photos[] = [
                'id' => $id,
                'caption' => sanitize_text_field((string)($captions[$i] ?? '')),
                'date' => self::iso_date((string)($dates[$i] ?? '')) ?: gmdate('Y-m-d'),
            ];
        }
        $new_photo_id = self::store_shelter_build_photo();
        if ($new_photo_id !== null) {
            $photos[] = [
                'id' => $new_photo_id,
                'caption' => sanitize_text_field((string)($_POST['sod_shelter_new_caption'] ?? '')),
                'date' => self::iso_date((string)($_POST['sod_shelter_new_date'] ?? '')) ?: gmdate('Y-m-d'),
            ];
        }
        update_option('sod_shelter_build_photos', $photos, false);

        wp_safe_redirect(add_query_arg(['post_type' => 'sod_dog', 'page' => 'sod-settings', 'sod_saved' => '1'], admin_url('edit.php')));
        exit;
    }

    private static function due_items(): array
    {
        $today = gmdate('Y-m-d');
        $soon = gmdate('Y-m-d', time() + 7 * DAY_IN_SECONDS);
        $items = [];

        $vaccinations = new WP_Query([
            'post_type' => 'sod_dog',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'sod_next_vaccination', 'value' => ['0001-01-01', $soon], 'compare' => 'BETWEEN', 'type' => 'DATE'],
            ],
        ]);
        foreach ($vaccinations->posts as $post_id) {
            $date = self::display_date((string)get_post_meta((int)$post_id, 'sod_next_vaccination', true));
            $items[] = [
                'label' => 'Impfung: ' . get_the_title((int)$post_id),
                'info' => 'fällig ' . $date,
                'url' => get_edit_post_link((int)$post_id, 'raw') ?: admin_url('edit.php?post_type=sod_dog'),
            ];
        }

        $tasks = new WP_Query([
            'post_type' => 'sod_task',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'sod_task_due', 'value' => ['0001-01-01', $today], 'compare' => 'BETWEEN', 'type' => 'DATE'],
                ['key' => 'sod_task_status', 'value' => ['erledigt'], 'compare' => 'NOT IN'],
            ],
        ]);
        foreach ($tasks->posts as $post_id) {
            $items[] = [
                'label' => 'Aufgabe: ' . get_the_title((int)$post_id),
                'info' => 'fällig ' . self::display_date((string)get_post_meta((int)$post_id, 'sod_task_due', true)),
                'url' => get_edit_post_link((int)$post_id, 'raw') ?: admin_url('edit.php?post_type=sod_task'),
            ];
        }

        $followups = new WP_Query([
            'post_type' => 'sod_interest',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'sod_interest_followup', 'value' => ['0001-01-01', $today], 'compare' => 'BETWEEN', 'type' => 'DATE'],
                ['key' => 'sod_interest_status', 'value' => ['abgesagt', 'archiv'], 'compare' => 'NOT IN'],
            ],
        ]);
        foreach ($followups->posts as $post_id) {
            $items[] = [
                'label' => 'Wiedervorlage: ' . get_the_title((int)$post_id),
                'info' => 'fällig ' . self::display_date((string)get_post_meta((int)$post_id, 'sod_interest_followup', true)),
                'url' => get_edit_post_link((int)$post_id, 'raw') ?: admin_url('edit.php?post_type=sod_interest'),
            ];
        }

        $aftercare = new WP_Query([
            'post_type' => 'sod_case',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'sod_case_status', 'value' => 'nachkontrolle'],
            ],
        ]);
        foreach ($aftercare->posts as $post_id) {
            $items[] = [
                'label' => 'Nachkontrolle offen: ' . get_the_title((int)$post_id),
                'info' => 'Vermittlungsakte',
                'url' => get_edit_post_link((int)$post_id, 'raw') ?: admin_url('edit.php?post_type=sod_case'),
            ];
        }

        $applications = new WP_Query([
            'post_type' => 'sod_application',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'sod_application_status', 'value' => 'offen'],
                ['key' => 'sod_application_status', 'compare' => 'NOT EXISTS'],
            ],
        ]);
        foreach ($applications->posts as $post_id) {
            $items[] = [
                'label' => get_the_title((int)$post_id) ?: 'Anfrage',
                'info' => 'eingegangen ' . self::display_date(get_the_date('Y-m-d', (int)$post_id)),
                'url' => get_edit_post_link((int)$post_id, 'raw') ?: admin_url('edit.php?post_type=sod_application'),
            ];
        }

        return $items;
    }

    public static function send_daily_reminders(): void
    {
        $items = self::due_items();
        $sponsor_data = self::sponsor_daily_summary_data();
        wp_mail(
            self::notification_emails(),
            'SOD Tagesübersicht: ' . count($items) . ' fällige Einträge, ' . $sponsor_data['new_total'] . ' neue Paten',
            self::daily_reminders_email_html($items, $sponsor_data),
            array_merge(['Content-Type: text/html; charset=UTF-8'], self::mail_headers())
        );
    }

    public static function send_daily_reminders_now(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_send_daily_reminders_now', 'sod_daily_reminders_nonce');
        self::send_daily_reminders();
        wp_safe_redirect(add_query_arg('sod_saved', 'mail_sent', admin_url('edit.php?post_type=sod_dog&page=sod-settings')));
        exit;
    }

    public static function check_inbound_application_replies_now(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_check_inbound_replies_now', 'sod_ticket_imap_nonce');
        self::check_inbound_application_replies();
        wp_safe_redirect(add_query_arg('sod_saved', 'imap_checked', admin_url('edit.php?post_type=sod_dog&page=sod-settings')));
        exit;
    }

    private static function sponsor_ids_since(int $timestamp): array
    {
        return get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'date_query' => [['after' => gmdate('Y-m-d H:i:s', $timestamp), 'inclusive' => true, 'column' => 'post_date_gmt']],
        ]);
    }

    /**
     * Datengrundlage fuer die taegliche Mail: neue Paten der letzten 24 Stunden nach
     * Zahlungsart, der monatliche Gesamtwert aller aktiven Patenschaften, und eine Liste
     * der aktiven Patenschaften, deren Zahlungseingang noch nicht bestaetigt ist.
     */
    private static function sponsor_daily_summary_data(): array
    {
        $methods = self::sponsor_payment_method_options();
        $new_by_method = [];
        foreach ($methods as $method_key => $label) {
            $new_by_method[$method_key] = ['label' => $label, 'count' => 0, 'amount' => 0.0];
        }
        foreach (self::sponsor_ids_since(strtotime('-1 day')) as $sponsor_id) {
            $method = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true);
            if (!isset($new_by_method[$method])) {
                continue;
            }
            $new_by_method[$method]['count']++;
            $new_by_method[$method]['amount'] += self::money_number((string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true));
        }

        $active_sponsors = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [['key' => 'sod_sponsor_status', 'value' => 'aktiv']],
        ]);
        $total_amount = 0.0;
        $pending = [];
        foreach ($active_sponsors as $sponsor_id) {
            $total_amount += self::money_number((string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true));
            if ((string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1') {
                continue;
            }
            $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
            $pending[] = [
                'name' => get_the_title($sponsor_id) ?: 'Pate/Patin',
                'dog' => $dog_id > 0 ? (get_the_title($dog_id) ?: 'Hund') : 'Hund',
                'method' => $methods[(string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true)] ?? '-',
                'amount' => (string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true),
                'url' => get_edit_post_link($sponsor_id, 'raw') ?: admin_url('edit.php?post_type=sod_sponsor'),
            ];
        }

        return [
            'new_total' => array_sum(array_column($new_by_method, 'count')),
            'new_by_method' => $new_by_method,
            'total_amount' => $total_amount,
            'pending' => $pending,
        ];
    }

    private static function daily_reminders_email_html(array $items, array $sponsor_data): string
    {
        $logo_url = get_theme_file_uri('assets/images/logo.png');
        ob_start();
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>SOD Tagesübersicht</title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.55;margin:0;background:#f3f5f6}
                .page{max-width:640px;margin:0 auto;background:#fff;padding:28px 30px;box-sizing:border-box}
                .head{display:flex;align-items:center;gap:14px;border-bottom:3px solid #f3c74f;padding-bottom:16px;margin-bottom:22px}
                .logo{width:56px;height:56px;object-fit:contain;flex:0 0 auto}
                .brand{font-weight:700;color:#204060;font-size:15px}
                .brand small{display:block;font-weight:400;color:#68808c;font-size:12px}
                h1{font-size:19px;margin:0 0 18px;color:#204060}
                h2{font-size:14px;margin:26px 0 8px;color:#204060;border-bottom:1px solid #e4e9eb;padding-bottom:5px}
                .stat-row{display:flex;gap:12px;margin:6px 0 4px;flex-wrap:wrap}
                .stat{flex:1 1 150px;background:#f7f9fa;border:1px solid #e4e9eb;border-radius:8px;padding:10px 12px}
                .stat .num{font-size:19px;font-weight:800;color:#204060}
                .stat .lbl{font-size:11px;color:#68808c;text-transform:uppercase;letter-spacing:.03em}
                ul{margin:6px 0;padding-left:18px}
                li{margin:4px 0}
                a{color:#204060}
                .pending{border:1px solid #f3c74f;background:#fffaf0;border-radius:8px;padding:4px 14px;margin-top:6px}
                .pending .row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f2e2b8}
                .pending .row:last-child{border-bottom:0}
                .pending a{font-weight:700;text-decoration:none;white-space:nowrap}
                .ok{color:#2f7d4f;font-weight:600}
                .empty{color:#68808c}
                .foot{margin-top:26px;padding-top:14px;border-top:1px solid #e4e9eb;font-size:11px;color:#8a9aa3}
            </style>
        </head>
        <body>
            <div class="page">
                <div class="head">
                    <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                    <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                </div>
                <h1>Tagesübersicht — <?php echo esc_html(date_i18n('d.m.Y')); ?></h1>

                <h2>Fällige Termine &amp; Aufgaben</h2>
                <?php if ($items) : ?>
                    <ul>
                        <?php foreach ($items as $item) : ?>
                            <li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a> — <?php echo esc_html($item['info']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="empty">Keine fälligen Termine oder Aufgaben heute.</p>
                <?php endif; ?>

                <h2>Patenschaften</h2>
                <div class="stat-row">
                    <div class="stat"><div class="num"><?php echo esc_html((string)$sponsor_data['new_total']); ?></div><div class="lbl">Neue Paten seit gestern</div></div>
                    <div class="stat"><div class="num"><?php echo esc_html(self::money_plain($sponsor_data['total_amount'])); ?> €</div><div class="lbl">Monatlicher Gesamtwert aktiv</div></div>
                </div>
                <?php foreach ($sponsor_data['new_by_method'] as $method_data) : ?>
                    <?php if ($method_data['count'] > 0) : ?>
                        <p style="margin:4px 0"><strong><?php echo esc_html((string)$method_data['count']); ?></strong> über <?php echo esc_html($method_data['label']); ?> (<?php echo esc_html(self::money_plain($method_data['amount'])); ?> € monatlich)</p>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($sponsor_data['pending']) : ?>
                    <div class="pending">
                        <p><strong>Noch zu bestätigen (<?php echo esc_html((string)count($sponsor_data['pending'])); ?>)</strong></p>
                        <?php foreach ($sponsor_data['pending'] as $pending_item) : ?>
                            <div class="row">
                                <span><?php echo esc_html($pending_item['name']); ?> für <?php echo esc_html($pending_item['dog']); ?> (<?php echo esc_html($pending_item['method']); ?><?php echo $pending_item['amount'] !== '' ? ', ' . esc_html($pending_item['amount']) . ' €' : ''; ?>)</span>
                                <a href="<?php echo esc_url($pending_item['url']); ?>">Öffnen</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="ok">Alle aktiven Patenschaften sind bestätigt.</p>
                <?php endif; ?>

                <div class="foot">Diese Übersicht findest du auch im WordPress-Dashboard unter „SOD Heute fällig" bzw. unter „Paten".</div>
            </div>
        </body>
        </html>
        <?php
        return (string)ob_get_clean();
    }

    /**
     * Automatische Eingangsbestaetigung an die Person, die das Kontaktformular abgeschickt
     * hat - bewusst nur eine kurze Bestaetigung mit den wichtigsten Angaben, keine
     * vollstaendige Kopie der Nachricht (Datensparsamkeit).
     */
    private static function application_confirmation_email_html(array $values): string
    {
        $logo_url = get_theme_file_uri('assets/images/logo.png');
        $name = trim((string)($values['first_name'] ?? ''));
        $greeting = $name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,';
        $interest = trim((string)($values['interest'] ?? ''));
        $dog_name = trim((string)($values['dog_name'] ?? ''));
        ob_start();
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Ihre Anfrage bei <?php echo esc_html(self::org()['name']); ?></title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.6;margin:0;background:#f3f5f6}
                .page{max-width:560px;margin:0 auto;background:#fff;padding:28px 30px;box-sizing:border-box}
                .head{display:flex;align-items:center;gap:14px;border-bottom:3px solid #f3c74f;padding-bottom:16px;margin-bottom:22px}
                .logo{width:56px;height:56px;object-fit:contain;flex:0 0 auto}
                .brand{font-weight:700;color:#204060;font-size:15px}
                .brand small{display:block;font-weight:400;color:#68808c;font-size:12px}
                h1{font-size:20px;margin:0 0 16px;color:#204060}
                p{margin:0 0 14px}
                .summary{background:#f7f9fa;border:1px solid #e4e9eb;border-radius:8px;padding:14px 16px;margin:0 0 18px}
                .summary p{margin:0 0 4px}
                .summary p:last-child{margin-bottom:0}
                .summary strong{color:#204060}
                .foot{margin-top:26px;padding-top:14px;border-top:1px solid #e4e9eb;font-size:12px;color:#8a9aa3}
            </style>
        </head>
        <body>
            <div class="page">
                <div class="head">
                    <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                    <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                </div>
                <h1>Vielen Dank für Ihre Anfrage!</h1>
                <p><?php echo esc_html($greeting); ?></p>
                <p>wir haben Ihre Anfrage erhalten und melden uns so schnell wie möglich bei Ihnen.</p>
                <?php if ($interest !== '' || $dog_name !== '') : ?>
                    <div class="summary">
                        <?php if ($interest !== '') : ?><p><strong>Betreff:</strong> <?php echo esc_html($interest); ?></p><?php endif; ?>
                        <?php if ($dog_name !== '') : ?><p><strong>Hund:</strong> <?php echo esc_html($dog_name); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <p>Herzliche Grüße<br>Ihr Shield-of-Dogs-Team</p>
                <div class="foot">Diese E-Mail bestätigt nur den Eingang Ihrer Anfrage. Bitte antworten Sie nicht direkt auf diese automatische Nachricht.</div>
            </div>
        </body>
        </html>
        <?php
        return (string)ob_get_clean();
    }

    private static function application_reply_email_html(string $reply_text): string
    {
        $logo_url = get_theme_file_uri('assets/images/logo.png');
        ob_start();
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Antwort von <?php echo esc_html(self::org()['name']); ?></title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.6;margin:0;background:#f3f5f6}
                .page{max-width:560px;margin:0 auto;background:#fff;padding:28px 30px;box-sizing:border-box}
                .head{display:flex;align-items:center;gap:14px;border-bottom:3px solid #f3c74f;padding-bottom:16px;margin-bottom:22px}
                .logo{width:56px;height:56px;object-fit:contain;flex:0 0 auto}
                .brand{font-weight:700;color:#204060;font-size:15px}
                .brand small{display:block;font-weight:400;color:#68808c;font-size:12px}
                h1{font-size:20px;margin:0 0 16px;color:#204060}
                p{margin:0 0 14px}
                .reply-text{white-space:pre-line}
                .foot{margin-top:26px;padding-top:14px;border-top:1px solid #e4e9eb;font-size:12px;color:#8a9aa3}
            </style>
        </head>
        <body>
            <div class="page">
                <div class="head">
                    <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                    <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                </div>
                <h1>Antwort auf Ihre Anfrage</h1>
                <p class="reply-text"><?php echo nl2br(esc_html($reply_text)); ?></p>
                <p>Herzliche Grüße<br><?php echo esc_html(self::org()['name']); ?></p>
                <div class="foot">Sie können auf diese E-Mail ganz normal antworten.</div>
            </div>
        </body>
        </html>
        <?php
        return (string)ob_get_clean();
    }

    public static function handle_application_reply(): void
    {
        $application_id = absint($_POST['application_id'] ?? 0);
        if (!$application_id || !current_user_can('edit_post', $application_id)) {
            wp_die('Keine Berechtigung.');
        }
        if (!isset($_POST['sod_application_reply_nonce']) || !wp_verify_nonce((string)$_POST['sod_application_reply_nonce'], 'sod_application_reply_' . $application_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }

        $application = get_post($application_id);
        if (!$application instanceof WP_Post || $application->post_type !== 'sod_application') {
            wp_die('Anfrage nicht gefunden.');
        }

        $edit_link = get_edit_post_link($application_id, 'raw') ?: admin_url('edit.php?post_type=sod_application');
        $reply = sanitize_textarea_field((string)($_POST['reply_message'] ?? ''));
        $to_email = sanitize_email((string)get_post_meta($application_id, 'email', true));

        if ($reply === '' || $to_email === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'application_reply_failed', $edit_link));
            exit;
        }

        $user = wp_get_current_user();

        // wp_mail() liefert bei Zustellfehlern false, aber ohne diesen Hook bleibt
        // die eigentliche PHPMailer-/SMTP-Fehlermeldung unsichtbar - man wuesste nur
        // "hat nicht geklappt", nicht warum (z.B. vom Zielserver abgelehnt, ungueltige
        // Adresse, Verbindungsfehler des lokalen Mailservers).
        $mail_error = '';
        $capture_mail_error = static function (WP_Error $error) use (&$mail_error): void {
            $mail_error = $error->get_error_message();
        };
        add_action('wp_mail_failed', $capture_mail_error);
        $sent = wp_mail(
            $to_email,
            'Antwort auf Ihre Anfrage bei ' . self::org()['name'] . self::application_ticket_tag($application_id),
            self::application_reply_email_html($reply),
            array_merge(['Content-Type: text/html; charset=UTF-8'], self::application_mail_headers())
        );
        remove_action('wp_mail_failed', $capture_mail_error);

        wp_insert_comment([
            'comment_post_ID' => $application_id,
            'comment_content' => $reply,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'user_id' => $user->ID,
            'comment_approved' => 1,
            'comment_type' => 'comment',
        ]);

        // Ohne diese Pruefung wuerde die Antwort im Verlauf auftauchen und "gesendet"
        // gemeldet, obwohl beim Empfaenger nichts ankam. Der Kommentar bleibt trotzdem
        // erhalten, damit der Text nicht verloren geht und erneut manuell verschickt
        // werden kann.
        if (!$sent) {
            update_post_meta($application_id, 'sod_application_reply_mail_error', $mail_error !== '' ? $mail_error : 'Unbekannter Fehler beim Mailversand.');
            update_post_meta($application_id, 'sod_application_reply_mail_error_at', current_time('mysql'));
            wp_safe_redirect(add_query_arg('sod_notice', 'application_reply_mail_failed', $edit_link));
            exit;
        }
        delete_post_meta($application_id, 'sod_application_reply_mail_error');
        delete_post_meta($application_id, 'sod_application_reply_mail_error_at');

        wp_safe_redirect(add_query_arg('sod_notice', 'application_reply_sent', $edit_link));
        exit;
    }

    /**
     * Interne Notizen werden bewusst NICHT ueber die WP-Kommentar-API gespeichert
     * (die fuer den oeffentlich sichtbaren Nachrichtenverlauf genutzt wird), sondern
     * als eigenes Postmeta-Array - damit es strukturell unmoeglich ist, dass eine
     * interne Notiz versehentlich im Antwort-Thread der anfragenden Person auftaucht.
     */
    private static function application_internal_notes(int $application_id): array
    {
        $notes = get_post_meta($application_id, 'sod_application_internal_notes', true);
        return is_array($notes) ? $notes : [];
    }

    private static function render_application_internal_notes(WP_Post $post): void
    {
        $notes = self::application_internal_notes($post->ID);

        echo '<div class="sod-internal-notes">';
        echo '<h3>Interne Notizen <span class="sod-internal-notes-badge">Nur für Mitarbeiter</span></h3>';
        echo '<p class="description">Diese Notizen sind ausschließlich intern sichtbar und werden der anfragenden Person niemals angezeigt oder per E-Mail zugestellt.</p>';

        if ($notes) {
            echo '<div class="sod-internal-notes-list">';
            foreach (array_reverse($notes) as $note) {
                $author = esc_html((string)($note['author'] ?? ''));
                $date = esc_html((string)($note['date'] ?? ''));
                $text = nl2br(esc_html((string)($note['text'] ?? '')));
                echo '<div class="sod-internal-note">';
                printf('<div class="sod-message-meta"><strong>%s</strong> · %s</div>', $author, $date);
                printf('<div class="sod-message-body">%s</div>', $text);
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<div class="sod-admin-field-wide sod-ajax-box" data-sod-action="sod_application_add_note" data-sod-post-id="' . esc_attr((string)$post->ID) . '">';
        wp_nonce_field('sod_application_add_note_' . $post->ID, 'sod_application_add_note_nonce');
        echo '<textarea name="note_text" rows="3" style="width:100%;max-width:640px;" placeholder="Interne Notiz hinzufügen…"></textarea><br>';
        echo '<button type="button" class="button sod-ajax-submit" style="margin-top:8px;">Notiz speichern</button>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Reply- und Notiz-Boxen duerfen KEIN eigenes <form> verwenden: WordPress
     * umschliesst alle Metabox-Inhalte auf dem Edit-Screen bereits mit einem
     * einzigen grossen <form id="post">. Ein verschachteltes <form> ist ungueltiges
     * HTML - der Browser verwirft das innere <form>-Start-Tag, wodurch der
     * "Absenden"-Button stattdessen das AEUSSERE Formular (post.php, Beitrag
     * speichern) auslöst und die eigenen verstecken Felder mit übernommen werden.
     * Deshalb hier stattdessen ein per fetch() an admin-post.php gesendeter Request.
     */
    private static function print_sod_ajax_box_script(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <script>
        (function () {
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.sod-ajax-submit');
                if (!btn) {
                    return;
                }
                var box = btn.closest('.sod-ajax-box');
                if (!box) {
                    return;
                }
                var body = new URLSearchParams();
                body.set('action', box.getAttribute('data-sod-action'));
                body.set('application_id', box.getAttribute('data-sod-post-id'));
                box.querySelectorAll('textarea, input[type=hidden]').forEach(function (el) {
                    if (el.name) {
                        body.set(el.name, el.value);
                    }
                });
                var originalLabel = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Wird gesendet…';
                fetch('<?php echo esc_js(admin_url('admin-post.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (response) {
                    // Der Server antwortet mit einem Redirect auf die Bearbeiten-Seite
                    // inkl. ?sod_notice=... - fetch folgt dem automatisch, aber ein
                    // reines reload() der aktuellen Adresse (ohne den Notice-Parameter)
                    // wuerde die Erfolgs-/Fehlermeldung verschlucken. response.url ist
                    // die tatsaechliche Ziel-URL nach dem Redirect.
                    window.location.href = response.url || window.location.href;
                }).catch(function () {
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                    alert('Fehler beim Senden. Bitte erneut versuchen.');
                });
            });
        })();
        </script>
        <?php
    }

    public static function handle_application_add_note(): void
    {
        $application_id = absint($_POST['application_id'] ?? 0);
        if (!$application_id || !current_user_can('edit_post', $application_id)) {
            wp_die('Keine Berechtigung.');
        }
        if (!isset($_POST['sod_application_add_note_nonce']) || !wp_verify_nonce((string)$_POST['sod_application_add_note_nonce'], 'sod_application_add_note_' . $application_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }

        $application = get_post($application_id);
        if (!$application instanceof WP_Post || $application->post_type !== 'sod_application') {
            wp_die('Anfrage nicht gefunden.');
        }

        $edit_link = get_edit_post_link($application_id, 'raw') ?: admin_url('edit.php?post_type=sod_application');
        $text = sanitize_textarea_field((string)($_POST['note_text'] ?? ''));

        if ($text === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'application_note_empty', $edit_link));
            exit;
        }

        $user = wp_get_current_user();
        $notes = self::application_internal_notes($application_id);
        $notes[] = [
            'author' => $user->display_name,
            'date' => current_time('d.m.Y H:i'),
            'text' => $text,
        ];
        update_post_meta($application_id, 'sod_application_internal_notes', $notes);

        wp_safe_redirect(add_query_arg('sod_notice', 'application_note_added', $edit_link));
        exit;
    }

    /**
     * Ticket-System: periodischer IMAP-Abruf des Anfrage-Postfachs (siehe Einstellungen),
     * um Antworten der anfragenden Person automatisch der richtigen Anfrage zuzuordnen.
     * Zuordnung primaer ueber die Ticket-Nummer im Betreff ("[Anfrage #123]", wird beim
     * Versand von Bestaetigungs-/Antwortmails automatisch angehaengt), Fallback ueber die
     * Absender-Adresse auf eine eindeutig offene Anfrage. Bleibt beides erfolglos, landet
     * die Mail in der "Nicht zugeordnet"-Liste zur manuellen Zuordnung - nichts geht verloren.
     */
    public static function check_inbound_application_replies(): void
    {
        if (!function_exists('imap_open')) {
            return;
        }
        $host = trim((string)get_option('sod_ticket_imap_host', ''));
        $user = trim((string)get_option('sod_ticket_imap_user', ''));
        $pass = (string)get_option('sod_ticket_imap_pass', '');
        $port = absint(get_option('sod_ticket_imap_port', 993));
        $encryption = (string)get_option('sod_ticket_imap_encryption', 'ssl');
        if ($host === '' || $user === '' || $pass === '') {
            return;
        }

        $flags = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl/novalidate-cert';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls/novalidate-cert';
        } else {
            $flags .= '/notls';
        }
        $mailbox = '{' . $host . ':' . $port . $flags . '}INBOX';

        update_option('sod_ticket_imap_last_check', gmdate('c'), false);

        $imap = @imap_open($mailbox, $user, $pass, OP_SILENT);
        if ($imap === false) {
            $error = imap_last_error();
            update_option('sod_ticket_imap_last_error', $error !== false && $error !== '' ? (string)$error : 'Verbindung fehlgeschlagen.', false);
            return;
        }
        update_option('sod_ticket_imap_last_error', '', false);

        /*
         * Bewusst NICHT nach "UNSEEN" gesucht: sobald jemand das Ticket-Postfach per
         * Webmail/Mailprogramm oeffnet, sind eingegangene Antworten als gelesen markiert
         * und wuerden nie mehr abgeholt - sie fehlten dann dauerhaft im Anfrage-Verlauf.
         * Stattdessen ein Zeitfenster wie beim Gesendet-Ordner; gegen Doppelverarbeitung
         * schuetzt ohnehin die persistente $processed_ids-Liste (Message-ID bzw. UID).
         *
         * imap_search() liefert bei manchen Servern "false" statt eines leeren Arrays,
         * wenn nichts passt (kein echter Fehler) - das darf die Funktion NICHT vorzeitig
         * beenden, sonst wird der Gesendet-Ordner nie geprueft.
         */
        $inbox_since = gmdate('d-M-Y', time() - 3 * DAY_IN_SECONDS);
        $uids = imap_search($imap, 'SINCE "' . $inbox_since . '"', SE_UID);
        $uids = is_array($uids) ? $uids : [];
        sort($uids);
        $uids = array_slice($uids, -25);

        $inbox_diag_found = count($uids);
        $inbox_diag_matched = 0;
        $inbox_diag_unmatched = 0;
        $inbox_diag_known = 0;

        $processed_ids = get_option('sod_ticket_imap_processed_ids', []);
        $processed_ids = is_array($processed_ids) ? $processed_ids : [];

        foreach ($uids as $uid) {
            $overview_list = imap_fetch_overview($imap, (string)$uid, FT_UID);
            $overview = $overview_list[0] ?? null;
            if (!$overview) {
                continue;
            }
            $message_id = trim((string)($overview->message_id ?? ''));
            // Manche Absender liefern keinen Message-ID-Header - Dedup darf sich dann NICHT
            // allein auf das \Seen-Flag verlassen (dessen Rueckgabewert wir nicht pruefen/loggen);
            // sonst wuerde eine solche Mail bei jedem Cron-Lauf erneut verarbeitet, falls das
            // Setzen des Flags auf dem Mailserver je fehlschlaegt. Fallback-Schluessel ueber die UID.
            $dedupe_key = $message_id !== '' ? $message_id : ('uid:' . $uid);
            if (in_array($dedupe_key, $processed_ids, true)) {
                $inbox_diag_known++;
                imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
                continue;
            }

            $subject = isset($overview->subject) ? sanitize_text_field(mb_decode_mimeheader((string)$overview->subject)) : '';
            $from_raw = (string)($overview->from ?? '');
            $sender_email = '';
            if (preg_match('/<([^>]+)>/', $from_raw, $m)) {
                $sender_email = sanitize_email($m[1]);
            } elseif ($from_raw !== '') {
                $sender_email = sanitize_email($from_raw);
            }
            $sender_name = trim((string)preg_replace('/<[^>]*>/', '', $from_raw));
            $sender_name = trim((string)preg_replace('/^"|"$/', '', $sender_name));
            $sender_name = sanitize_text_field(mb_decode_mimeheader($sender_name));
            if ($sender_name === '') {
                $sender_name = $sender_email;
            }

            $body = self::imap_extract_plain_body($imap, (int)$uid);
            $body = self::limited_text(trim(wp_strip_all_tags($body)), 4000);

            $application_id = 0;
            // Support-Meldungen tragen ein eigenes Kuerzel im Betreff und haengen an
            // einem sod_support-Datensatz - sonst landete die Antwort im falschen Verlauf.
            $support_id = 0;
            if (preg_match('/\[Support #(\d+)\]/', $subject, $support_match)) {
                $candidate = absint($support_match[1]);
                if ($candidate > 0 && get_post_type($candidate) === 'sod_support') {
                    $support_id = $candidate;
                }
            }
            if ($support_id === 0 && preg_match('/\[Anfrage #(\d+)\]/', $subject, $tag_match)) {
                $candidate = absint($tag_match[1]);
                if ($candidate > 0 && get_post_type($candidate) === 'sod_application') {
                    $application_id = $candidate;
                }
            }
            if ($support_id === 0 && $application_id === 0 && $sender_email !== '') {
                $application_id = self::find_open_application_by_email($sender_email);
            }

            if ($support_id > 0) {
                wp_insert_comment([
                    'comment_post_ID' => $support_id,
                    'comment_content' => $body !== '' ? $body : '(kein Text erkannt)',
                    'comment_author' => $sender_name !== '' ? $sender_name : 'Meldende Person',
                    'comment_author_email' => $sender_email,
                    'comment_approved' => 1,
                    'comment_type' => 'sod_inbound_reply',
                ]);
                if ((string)get_post_meta($support_id, 'sod_support_status', true) === 'erledigt') {
                    update_post_meta($support_id, 'sod_support_status', 'offen');
                }
                $inbox_diag_matched++;
                $processed_ids[] = $dedupe_key;
                imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
                continue;
            }

            if ($application_id > 0) {
                wp_insert_comment([
                    'comment_post_ID' => $application_id,
                    'comment_content' => $body !== '' ? $body : '(kein Text erkannt)',
                    'comment_author' => $sender_name !== '' ? $sender_name : 'Anfragende Person',
                    'comment_author_email' => $sender_email,
                    'comment_approved' => 1,
                    'comment_type' => 'sod_inbound_reply',
                ]);
                update_post_meta($application_id, 'sod_application_last_inbound_at', current_time('mysql'));
                $inbox_diag_matched++;
            } else {
                self::store_inbound_unmatched_email([
                    'from_name' => $sender_name,
                    'from_email' => $sender_email,
                    'subject' => $subject,
                    'body' => $body,
                    'date' => gmdate('c'),
                ]);
                $inbox_diag_unmatched++;
            }

            $processed_ids[] = $dedupe_key;
            imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
        }

        /*
         * Mitarbeiter antworten teils nicht ueber den "Antwort senden"-Button im Admin,
         * sondern direkt per E-Mail aus dem eigenen Postfach. Solche Antworten landen im
         * Gesendet-Ordner desselben Postfachs (nicht im Posteingang) - deshalb hier
         * zusaetzlich dieser Ordner geprueft und passende Antworten dem Verlauf hinzugefuegt.
         */
        $sent_folder = self::imap_find_sent_folder($imap, $host, $port, $flags);
        $sent_diag_folder = $sent_folder !== null ? preg_replace('/^\{[^}]*\}/', '', $sent_folder) : '(nicht gefunden)';
        $sent_diag_found = 0;
        $sent_diag_matched = 0;
        $sent_diag_last_unmatched = '';
        if ($sent_folder !== null && imap_reopen($imap, $sent_folder)) {
            $since = gmdate('d-M-Y', time() - 3 * DAY_IN_SECONDS);
            $sent_uids = imap_search($imap, 'SINCE "' . $since . '"', SE_UID);
            if (is_array($sent_uids)) {
                sort($sent_uids);
                $sent_uids = array_slice($sent_uids, -25);
                $sent_diag_found = count($sent_uids);

                foreach ($sent_uids as $uid) {
                    $overview_list = imap_fetch_overview($imap, (string)$uid, FT_UID);
                    $overview = $overview_list[0] ?? null;
                    if (!$overview) {
                        continue;
                    }
                    $message_id = trim((string)($overview->message_id ?? ''));
                    $dedupe_key = 'sent:' . ($message_id !== '' ? $message_id : ('uid:' . $uid));
                    if (in_array($dedupe_key, $processed_ids, true)) {
                        continue;
                    }

                    $subject = isset($overview->subject) ? sanitize_text_field(mb_decode_mimeheader((string)$overview->subject)) : '';
                    $to_raw = (string)($overview->to ?? '');
                    $to_email = '';
                    if (preg_match('/<([^>]+)>/', $to_raw, $m)) {
                        $to_email = sanitize_email($m[1]);
                    } elseif ($to_raw !== '') {
                        $to_email = sanitize_email($to_raw);
                    }

                    $application_id = 0;
                    if (preg_match('/\[Anfrage #(\d+)\]/', $subject, $tag_match)) {
                        $candidate = absint($tag_match[1]);
                        if ($candidate > 0 && get_post_type($candidate) === 'sod_application') {
                            $application_id = $candidate;
                        }
                    }
                    if ($application_id === 0 && $to_email !== '') {
                        $application_id = self::find_open_application_by_email($to_email);
                    }

                    if ($application_id > 0) {
                        $body = self::imap_extract_plain_body($imap, (int)$uid);
                        $body = self::limited_text(trim(wp_strip_all_tags($body)), 4000);
                        if ($body !== '') {
                            wp_insert_comment([
                                'comment_post_ID' => $application_id,
                                'comment_content' => $body,
                                'comment_author' => self::org()['name'],
                                'comment_author_email' => '',
                                'comment_approved' => 1,
                                'comment_type' => 'comment',
                            ]);
                            $sent_diag_matched++;
                        } else {
                            // Sonst wuerde die Mail hier still verschwinden: zugeordnet, aber
                            // ohne lesbaren Text - im Verlauf taucht dann nichts auf, ohne dass
                            // die Diagnose einen Grund nennt.
                            $sent_diag_last_unmatched = sprintf('Betreff "%s" – zugeordnet zu #%d, aber kein lesbarer Text gefunden', $subject, $application_id);
                        }
                    } else {
                        $sent_diag_last_unmatched = sprintf('Betreff "%s" an "%s"', $subject, $to_email !== '' ? $to_email : $to_raw);
                    }

                    $processed_ids[] = $dedupe_key;
                }
            }
        }

        $processed_ids = array_slice(array_values(array_unique($processed_ids)), -200);
        update_option('sod_ticket_imap_processed_ids', $processed_ids, false);
        update_option(
            'sod_ticket_imap_inbox_diag',
            sprintf(
                'Mails der letzten 3 Tage: %d · neu zugeordnet: %d · neu ohne Zuordnung: %d · bereits früher verarbeitet: %d',
                $inbox_diag_found,
                $inbox_diag_matched,
                $inbox_diag_unmatched,
                $inbox_diag_known
            ),
            false
        );
        update_option(
            'sod_ticket_imap_sent_diag',
            sprintf('Ordner: %s · Gefundene Mails: %d · Zugeordnet: %d', $sent_diag_folder, $sent_diag_found, $sent_diag_matched)
                . ($sent_diag_last_unmatched !== '' ? ' · Zuletzt nicht zugeordnet: ' . $sent_diag_last_unmatched : ''),
            false
        );

        imap_close($imap);
    }

    /**
     * Ordnername fuer "Gesendet" ist je nach Mailserver unterschiedlich (z.B. "Sent",
     * "INBOX.Sent", "Gesendet") - deshalb automatisch unter den vorhandenen Ordnern
     * gesucht statt fest verdrahtet, damit keine zusaetzliche Einstellung noetig ist.
     */
    private static function imap_find_sent_folder($imap, string $host, int $port, string $flags): ?string
    {
        $root = '{' . $host . ':' . $port . $flags . '}';
        $folders = @imap_list($imap, $root, '*');
        if (!is_array($folders)) {
            return null;
        }
        $candidates = ['sent', 'gesendet', 'sent items', 'sent mail', 'sentmail'];
        foreach ($folders as $folder) {
            $short = (string)preg_replace('/^\{[^}]*\}/', '', $folder);
            $short = strtolower(str_replace(['INBOX.', 'INBOX/'], '', $short));
            if (in_array(trim($short), $candidates, true)) {
                return $folder;
            }
        }
        foreach ($folders as $folder) {
            if (stripos($folder, 'sent') !== false || stripos($folder, 'gesendet') !== false) {
                return $folder;
            }
        }
        return null;
    }

    private static function imap_extract_plain_body($imap, int $uid): string
    {
        $structure = @imap_fetchstructure($imap, $uid, FT_UID);
        if (!$structure) {
            return '';
        }
        if (isset($structure->parts) && is_array($structure->parts)) {
            $plain = self::imap_find_text_part($imap, $uid, $structure->parts, '', 'PLAIN');
            if ($plain !== null) {
                return $plain;
            }
            $html = self::imap_find_text_part($imap, $uid, $structure->parts, '', 'HTML');
            return $html !== null ? $html : '';
        }
        $body = imap_fetchbody($imap, $uid, '1', FT_UID);
        return self::imap_mime_decode((string)$body, (int)($structure->encoding ?? 0));
    }

    private static function imap_find_text_part($imap, int $uid, array $parts, string $prefix, string $subtype): ?string
    {
        foreach ($parts as $i => $part) {
            $section = $prefix === '' ? (string)($i + 1) : $prefix . '.' . ($i + 1);
            if (isset($part->parts) && is_array($part->parts)) {
                $nested = self::imap_find_text_part($imap, $uid, $part->parts, $section, $subtype);
                if ($nested !== null) {
                    return $nested;
                }
                continue;
            }
            if ((int)($part->type ?? -1) === 0 && strtoupper((string)($part->subtype ?? '')) === $subtype) {
                $raw = imap_fetchbody($imap, $uid, $section, FT_UID);
                return self::imap_mime_decode((string)$raw, (int)($part->encoding ?? 0));
            }
        }
        return null;
    }

    private static function imap_mime_decode(string $data, int $encoding): string
    {
        switch ($encoding) {
            case 3:
                return (string)base64_decode($data);
            case 4:
                return quoted_printable_decode($data);
            default:
                return $data;
        }
    }

    private static function find_open_application_by_email(string $email): int
    {
        $query = new WP_Query([
            'post_type' => 'sod_application',
            'post_status' => 'any',
            'posts_per_page' => 2,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => 'email', 'value' => $email],
                ['key' => 'sod_application_status', 'value' => 'beendet', 'compare' => '!='],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $ids = $query->posts;
        return count($ids) === 1 ? (int)$ids[0] : 0;
    }

    private static function inbound_unmatched_emails(): array
    {
        $items = get_option('sod_inbound_unmatched_emails', []);
        return is_array($items) ? $items : [];
    }

    private static function store_inbound_unmatched_email(array $entry): void
    {
        $entry['id'] = wp_generate_password(12, false, false);
        $items = self::inbound_unmatched_emails();
        $items[] = $entry;
        $items = array_slice($items, -50);
        update_option('sod_inbound_unmatched_emails', $items, false);
    }

    public static function handle_unmatched_reply_assign(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_unmatched_reply_assign', 'sod_unmatched_reply_nonce');
        $entry_id = sanitize_text_field((string)($_POST['entry_id'] ?? ''));
        $application_id = absint($_POST['application_id'] ?? 0);
        $items = self::inbound_unmatched_emails();
        $target = null;
        foreach ($items as $i => $item) {
            if (($item['id'] ?? '') === $entry_id) {
                $target = $item;
                unset($items[$i]);
                break;
            }
        }
        if ($target !== null && $application_id > 0 && get_post_type($application_id) === 'sod_application') {
            wp_insert_comment([
                'comment_post_ID' => $application_id,
                'comment_content' => (string)($target['body'] ?? ''),
                'comment_author' => (string)($target['from_name'] ?? 'Anfragende Person'),
                'comment_author_email' => (string)($target['from_email'] ?? ''),
                'comment_approved' => 1,
                'comment_type' => 'sod_inbound_reply',
            ]);
            update_post_meta($application_id, 'sod_application_last_inbound_at', current_time('mysql'));
            update_option('sod_inbound_unmatched_emails', array_values($items), false);
        }
        wp_safe_redirect(admin_url('edit.php?post_type=sod_dog&page=sod-unmatched-replies&sod_notice=assigned'));
        exit;
    }

    public static function handle_unmatched_reply_dismiss(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_unmatched_reply_dismiss', 'sod_unmatched_reply_nonce');
        $entry_id = sanitize_text_field((string)($_POST['entry_id'] ?? ''));
        $items = array_values(array_filter(
            self::inbound_unmatched_emails(),
            static function (array $item) use ($entry_id): bool {
                return ($item['id'] ?? '') !== $entry_id;
            }
        ));
        update_option('sod_inbound_unmatched_emails', $items, false);
        wp_safe_redirect(admin_url('edit.php?post_type=sod_dog&page=sod-unmatched-replies&sod_notice=dismissed'));
        exit;
    }

    public static function unmatched_replies_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $items = array_reverse(self::inbound_unmatched_emails());
        $notice = sanitize_text_field((string)($_GET['sod_notice'] ?? ''));
        $open_applications = get_posts([
            'post_type' => 'sod_application',
            'post_status' => 'any',
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        ?>
        <div class="wrap">
            <h1>Nicht zugeordnete Antworten</h1>
            <?php if ($notice === 'assigned') : ?><div class="notice notice-success is-dismissible"><p>Antwort wurde zugeordnet.</p></div><?php endif; ?>
            <?php if ($notice === 'dismissed') : ?><div class="notice notice-success is-dismissible"><p>Eintrag entfernt.</p></div><?php endif; ?>
            <p class="description">Diese eingegangenen E-Mails konnten nicht automatisch einer Anfrage zugeordnet werden (keine erkennbare Ticket-Nummer im Betreff und keine eindeutig offene Anfrage zu dieser Absender-Adresse). Bitte manuell zuordnen oder verwerfen.</p>
            <?php if (!$items) : ?>
                <p><em>Aktuell keine offenen Fälle.</em></p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:1100px">
                    <thead><tr><th>Von</th><th>Betreff</th><th>Datum</th><th>Auszug</th><th style="width:320px">Aktion</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item) : ?>
                        <?php $entry_id = (string)($item['id'] ?? ''); ?>
                        <tr>
                            <td><?php echo esc_html((string)($item['from_name'] ?? '')); ?><br><span class="sod-muted"><?php echo esc_html((string)($item['from_email'] ?? '')); ?></span></td>
                            <td><?php echo esc_html((string)($item['subject'] ?? '')); ?></td>
                            <td><?php echo esc_html(self::format_utc_datetime((string)($item['date'] ?? ''))); ?></td>
                            <td><?php echo esc_html(mb_substr((string)($item['body'] ?? ''), 0, 160)); ?>…</td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;align-items:center;margin-bottom:6px">
                                    <input type="hidden" name="action" value="sod_unmatched_reply_assign">
                                    <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry_id); ?>">
                                    <?php wp_nonce_field('sod_unmatched_reply_assign', 'sod_unmatched_reply_nonce'); ?>
                                    <select name="application_id" style="max-width:180px">
                                        <option value="">Anfrage wählen…</option>
                                        <?php foreach ($open_applications as $app_id) : ?>
                                            <option value="<?php echo esc_attr((string)$app_id); ?>">#<?php echo esc_html((string)$app_id); ?> – <?php echo esc_html(get_the_title($app_id) ?: ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-primary">Zuordnen</button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="sod_unmatched_reply_dismiss">
                                    <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry_id); ?>">
                                    <?php wp_nonce_field('sod_unmatched_reply_dismiss', 'sod_unmatched_reply_nonce'); ?>
                                    <button type="submit" class="button-link" style="color:#a00" onclick="return confirm('Eintrag wirklich verwerfen?');">Verwerfen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function register_post_types(): void
    {
        register_post_type('sod_dog', [
            'labels' => [
                'name' => 'Hunde',
                'singular_name' => 'Hund',
                'add_new_item' => 'Hund anlegen',
                'edit_item' => 'Hund bearbeiten',
            ],
            'public' => false,
            'publicly_queryable' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'hund', 'with_front' => false],
            'has_archive' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 2,
            'menu_icon' => 'dashicons-pets',
            'supports' => ['title', 'thumbnail'],
            'capability_type' => ['sod_dog', 'sod_dogs'],
            'map_meta_cap' => true,
            'show_in_rest' => true,
        ]);

        register_post_type('sod_application', [
            'labels' => [
                'name' => 'Anfragen',
                'singular_name' => 'Anfrage',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=sod_dog',
            'menu_icon' => 'dashicons-email-alt',
            'supports' => ['title', 'editor', 'comments'],
            'capability_type' => ['sod_dog', 'sod_dogs'],
            'map_meta_cap' => true,
        ]);

        register_post_type('sod_inventory', [
            'labels' => [
                'name' => 'Futter & Zubehör',
                'singular_name' => 'Artikel',
                'add_new_item' => 'Artikel anlegen',
                'edit_item' => 'Artikel bearbeiten',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-archive',
            'supports' => ['title'],
            'capability_type' => ['sod_item', 'sod_items'],
            'map_meta_cap' => true,
            'show_in_rest' => false,
        ]);

        register_post_type('sod_dog_update', [
            'labels' => [
                'name' => 'Hunde-Updates',
                'singular_name' => 'Hunde-Update',
                'add_new_item' => 'Update anlegen',
                'edit_item' => 'Update bearbeiten',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=sod_dog',
            'menu_icon' => 'dashicons-images-alt2',
            'supports' => ['title', 'comments'],
            'capability_type' => ['sod_dog_update', 'sod_dog_updates'],
            'map_meta_cap' => true,
            'show_in_rest' => false,
        ]);

        register_post_type('sod_support', [
            'labels' => [
                'name' => 'Support-Anfragen',
                'singular_name' => 'Support-Anfrage',
                'edit_item' => 'Support-Anfrage bearbeiten',
                'search_items' => 'Support-Anfragen durchsuchen',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=sod_dog',
            'menu_icon' => 'dashicons-sos',
            'supports' => ['title', 'comments'],
            'capability_type' => ['sod_dog', 'sod_dogs'],
            'map_meta_cap' => true,
            'show_in_rest' => false,
        ]);

        foreach (self::admin_record_configs() as $post_type => $config) {
            $cap_type = $post_type === 'sod_finance' ? ['sod_finance', 'sod_finances'] : ['sod_dog', 'sod_dogs'];
            register_post_type($post_type, [
                'labels' => [
                    'name' => $config['name'],
                    'singular_name' => $config['singular'],
                    'add_new_item' => $config['add_new'],
                    'edit_item' => $config['edit'],
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'edit.php?post_type=sod_dog',
                'menu_icon' => $config['icon'],
                'supports' => ['title'],
                'capability_type' => $cap_type,
                'map_meta_cap' => true,
                'show_in_rest' => in_array($post_type, self::REST_ENABLED_RECORD_TYPES, true),
            ]);
        }
    }

    /**
     * Macht die Custom-Felder der REST-freigegebenen Datentypen ueber die REST-API sichtbar.
     * Ohne register_post_meta() liefert die API nur die WordPress-Kernfelder (Titel, Datum,
     * Status) - die eigentlich interessanten sod_*-Felder blieben sonst unsichtbar.
     * auth_callback erzwingt dieselbe Berechtigung wie im normalen Admin-Bereich, damit per
     * REST niemand mehr sieht als über die gewohnte WordPress-Oberfläche.
     */
    public static function register_rest_meta(): void
    {
        $dog_auth = static fn (): bool => current_user_can('edit_sod_dogs');
        $finance_auth = static fn (): bool => current_user_can('edit_sod_finances');

        foreach (self::DOG_META as $key) {
            register_post_meta('sod_dog', $key, [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => $dog_auth,
            ]);
        }

        $configs = self::admin_record_configs();
        foreach (['sod_task', 'sod_interest', 'sod_sponsor'] as $post_type) {
            foreach (array_keys($configs[$post_type]['fields']) as $key) {
                register_post_meta($post_type, $key, [
                    'show_in_rest' => true,
                    'single' => true,
                    'type' => 'string',
                    'auth_callback' => $dog_auth,
                ]);
            }
        }
        foreach (array_keys($configs['sod_finance']['fields']) as $key) {
            register_post_meta('sod_finance', $key, [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => $finance_auth,
            ]);
        }
    }

    /**
     * Das Plugin "Disable REST API" prueft fuer den Zugriff nur is_user_logged_in() und
     * kennt Anwendungspasswoerter (Basic-Auth) nicht - damit waeren authentifizierte
     * REST-Aufrufe per Anwendungspasswort immer blockiert. Ueber den vom Plugin selbst
     * bereitgestellten Filter pruefen wir die Basic-Auth-Zugangsdaten hier direkt nach
     * und lassen die Anfrage nur durch, wenn WordPress sie als echtes Anwendungspasswort
     * eines gueltigen Benutzers erkennt.
     */
    public static function allow_rest_api_for_app_passwords(bool $allow): bool
    {
        if ($allow) {
            return $allow;
        }
        [$auth_user, $auth_pass] = self::extract_basic_auth_credentials();
        if ($auth_user === '' || $auth_pass === '') {
            return $allow;
        }
        // Manche Hosting-Setups erkennen HTTPS hinter einem Proxy nicht zuverlaessig,
        // wodurch WordPress Anwendungspasswoerter faelschlich als nicht verfuegbar
        // einstuft. Fuer diese eine Pruefung erzwingen wir "verfuegbar".
        add_filter('wp_is_application_passwords_available', '__return_true');
        $user = wp_authenticate_application_password(null, $auth_user, $auth_pass);
        if (!$user instanceof WP_User) {
            return false;
        }
        wp_set_current_user($user->ID);
        return true;
    }

    /**
     * Liest Basic-Auth-Zugangsdaten aus. Auf PHP-FPM/CGI-Setups (statt mod_php) fuellt
     * Apache/PHP die Variablen PHP_AUTH_USER/PHP_AUTH_PW oft nicht automatisch - der
     * Authorization-Header kommt dort stattdessen nur als HTTP_AUTHORIZATION durch (die
     * .htaccess-Regel dieser Installation leitet ihn genau dorthin weiter).
     *
     * @return array{0: string, 1: string}
     */
    private static function extract_basic_auth_credentials(): array
    {
        if (!empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            return [(string)$_SERVER['PHP_AUTH_USER'], (string)$_SERVER['PHP_AUTH_PW']];
        }
        $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' || stripos($header, 'basic ') !== 0) {
            return ['', ''];
        }
        $decoded = base64_decode(trim(substr($header, 6)), true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return ['', ''];
        }
        [$user, $pass] = explode(':', $decoded, 2);
        return [$user, $pass];
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box('sod_dog_details', 'Hundedaten', [self::class, 'dog_meta_box'], 'sod_dog', 'normal', 'high');
        add_meta_box('sod_dog_images', 'Hundebilder', [self::class, 'dog_images_meta_box'], 'sod_dog', 'normal', 'default');
        add_meta_box('sod_inventory_details', 'Bestandsdaten', [self::class, 'inventory_meta_box'], 'sod_inventory', 'normal', 'high');
        add_meta_box('sod_application_details', 'Anfragedaten', [self::class, 'application_meta_box'], 'sod_application', 'normal', 'high');
        add_meta_box('sod_dog_update_details', 'Update-Daten', [self::class, 'dog_update_meta_box'], 'sod_dog_update', 'normal', 'high');
        add_meta_box('sod_support_details', 'Support-Anfrage', [self::class, 'support_meta_box'], 'sod_support', 'normal', 'high');
        foreach (self::admin_record_configs() as $post_type => $config) {
            add_meta_box('sod_admin_record_details', $config['box_title'], [self::class, 'admin_record_meta_box'], $post_type, 'normal', 'high');
        }
    }

    public static function post_edit_form_tag(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'sod_dog') {
            echo ' enctype="multipart/form-data"';
        }
    }

    public static function dog_meta_box(WP_Post $post): void
    {
        wp_nonce_field('sod_save_dog', 'sod_dog_nonce');
        $status = (string)get_post_meta($post->ID, 'sod_status', true) ?: 'verfuegbar';
        $flyer_urls = [];
        foreach (['de' => 'Flyer DE', 'en' => 'Flyer EN', 'bs' => 'Flyer BS'] as $lang => $label) {
            $flyer_urls[$label] = wp_nonce_url(
                add_query_arg(['action' => 'sod_dog_flyer', 'dog_id' => $post->ID, 'lang' => $lang], admin_url('admin-post.php')),
                'sod_dog_flyer_' . $post->ID
            );
        }
        $contact_url = add_query_arg([
            'sod_dog_id' => $post->ID,
            'sod_dog_name' => self::dog_public_name($post->ID),
            'sod_interest' => 'Vermittlung',
        ], self::page_url('kontakt')) . '#anfrageformular';
        ?>
        <div class="sod-quick-actions">
            <?php foreach ($flyer_urls as $label => $url) : ?>
                <a class="button button-secondary" target="_blank" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
            <button type="button" class="button" data-sod-copy="<?php echo esc_attr($contact_url); ?>">Anfrage-Link kopieren</button>
            <a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=sod_inventory&sod_prefill_dog=' . $post->ID)); ?>">Zubehör zuweisen</a>
            <a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=sod_task&sod_prefill_dog=' . $post->ID)); ?>">Aufgabe anlegen</a>
            <?php if (get_post_meta($post->ID, 'sod_show_adoption', true) === '1' || get_post_meta($post->ID, 'sod_show_sponsorship', true) === '1') : ?>
                <a class="button" target="_blank" href="<?php echo esc_url(get_permalink($post->ID)); ?>">Öffentliche Seite ansehen</a>
            <?php endif; ?>
        </div>
        <div class="sod-wizard" data-sod-wizard>
            <?php self::wizard_nav(['Basis', 'Bilder', 'Gesundheit', 'Vermittlung', 'Tierheim', 'Intern']); ?>
            <section class="sod-wizard-step is-active" data-sod-step>
                <h3>Basisdaten</h3>
                <p class="description">Diese Felder reichen für einen ersten sauberen Hundeeintrag.</p>
                <div class="sod-admin-grid">
                    <?php self::select_field('sod_status', 'Status', self::dog_statuses(), $status, true, 'required'); ?>
                    <?php self::text_field($post->ID, 'sod_age', 'Alter', 'z.B. 2 Jahre', 'recommended'); ?>
                    <?php self::text_field($post->ID, 'sod_breed', 'Rasse / Typ', 'z.B. Mischling', 'recommended'); ?>
                    <?php self::select_field('sod_gender', 'Geschlecht', self::dog_gender_options(), (string)get_post_meta($post->ID, 'sod_gender', true), true, 'recommended'); ?>
                    <?php self::text_field($post->ID, 'sod_weight', 'Gewicht', 'z.B. 12 kg'); ?>
                    <?php self::text_field($post->ID, 'sod_location', 'Aufenthaltsort', 'z.B. Tierheim / Pflegestelle', 'recommended'); ?>
                </div>
            </section>
            <section class="sod-wizard-step" data-sod-step>
                <h3>Bilder</h3>
                <p class="description">Wenn ein echtes Video zu diesem Hund vorhanden ist, wird es öffentlich zuerst angezeigt. Die Bilder bleiben darunter als Vorschau.</p>
                <div class="sod-admin-grid">
                    <?php self::textarea_field($post->ID, 'sod_video_url', 'Hundevideos', 3); ?>
                    <p class="sod-admin-field"><label><strong>Videos aus Mediathek</strong></label><br><button type="button" class="button button-secondary" data-sod-select-video="sod_video_url">Videos auswählen</button><br><span class="description">Eine Adresse pro Zeile. Das erste Video ist das Hauptvideo und startet wie bisher stumm von selbst. Jedes weitere erscheint in der Bildauswahl darunter und startet erst, wenn es angeklickt wird. Bitte nur echte Videos dieses Hundes verwenden: MP4/WebM oder ein von WordPress unterstützter Video-Link.</span></p>
                </div>
                <button type="button" class="button button-secondary" data-sod-open-images>Bilder auswählen</button>
                <p class="sod-checkbox-list"><label><input type="checkbox" name="sod_photos_verified" value="1" <?php checked(get_post_meta($post->ID, 'sod_photos_verified', true), '1'); ?>> Fotos geprüft: Sie gehören wirklich zu diesem Hund</label></p>
                <p class="description">Bitte nur echte Fotos dieses Hundes verwenden. Wenn noch kein sicheres Foto vorhanden ist, lieber den Platzhalter nutzen.</p>
            </section>
            <section class="sod-wizard-step" data-sod-step>
                <h3>Gesundheit</h3>
                <div class="sod-admin-grid">
                    <?php self::select_field('sod_health', 'Gesundheit', self::dog_health_options(), (string)get_post_meta($post->ID, 'sod_health', true), true, 'recommended'); ?>
                    <?php self::text_field($post->ID, 'sod_microchip', 'Chipnummer', 'z.B. 688052...'); ?>
                    <?php self::text_field($post->ID, 'sod_passport_number', 'Pass / Dokumentnummer', 'EU-Heimtierausweis, Labor, Akte'); ?>
                    <?php self::date_field($post->ID, 'sod_vaccination_date', 'Letzte Impfung'); ?>
                    <?php self::date_field($post->ID, 'sod_next_vaccination', 'Nächste Impfung fällig'); ?>
                    <?php self::date_field($post->ID, 'sod_deworming_date', 'Letzte Entwurmung'); ?>
                    <?php self::select_field('sod_castration_status', 'Kastration', self::castration_options(), (string)get_post_meta($post->ID, 'sod_castration_status', true)); ?>
                </div>
                <details class="sod-advanced"><summary>Medizinische Details</summary><div class="sod-admin-grid">
                    <?php self::textarea_field($post->ID, 'sod_medical_history', 'Medizinische Historie', 4); ?>
                    <?php self::textarea_field($post->ID, 'sod_medication_plan', 'Medikamentenplan', 4); ?>
                    <?php self::textarea_field($post->ID, 'sod_document_links', 'Dokumente / Links', 4); ?>
                    <?php self::file_field('sod_health_document_upload', 'Gesundheitsdokument hochladen', '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx', 'PDF, Bild, DOC oder DOCX auswählen und den Hund aktualisieren. Die Datei wird geschützt gespeichert und automatisch in „Dokumente / Links“ ergänzt.'); ?>
                </div></details>
            </section>
            <section class="sod-wizard-step" data-sod-step>
                <h3>Vermittlung</h3>
                <p class="sod-checkbox-list sod-visibility-required">
                    <label><input type="checkbox" name="sod_show_adoption" value="1" data-sod-visibility-required <?php checked(get_post_meta($post->ID, 'sod_show_adoption', true), '1'); ?>> In Vermittlung anzeigen <span class="sod-required-star">*</span></label>
                    <label><input type="checkbox" name="sod_show_sponsorship" value="1" data-sod-visibility-required <?php checked(get_post_meta($post->ID, 'sod_show_sponsorship', true), '1'); ?>> Für Patenschaft anzeigen <span class="sod-required-star">*</span></label>
                    <span class="sod-field-badge sod-field-required">Pflicht</span>
                </p>
                <p class="description">Bitte mindestens Vermittlung oder Patenschaft auswählen.</p>
                <div class="sod-admin-grid">
                    <?php self::post_content_field($post, 'sod_dog_description', 'Beschreibung für Website', 4, 'recommended'); ?>
                    <?php self::textarea_field($post->ID, 'sod_character', 'Charakter', 3, 'recommended'); ?>
                    <?php self::textarea_field($post->ID, 'sod_needs', 'Braucht / Zuhause', 3); ?>
                    <?php self::text_field($post->ID, 'sod_sponsorship_amount', 'Patenschaftsbetrag', 'z.B. 25'); ?>
                    <?php self::textarea_field($post->ID, 'sod_sponsorship_text', 'Patenschaftstext', 3); ?>
                    <?php self::text_field($post->ID, 'sod_monthly_food_need', 'Monatlicher Futterbedarf', 'z.B. 80'); ?>
                    <?php self::text_field($post->ID, 'sod_monthly_food_secured', 'Zusätzlich manuell gesichert', 'z.B. 10'); ?>
                </div>
                <p class="sod-checkbox-list">
                    <label><input type="checkbox" name="sod_show_name_sponsorship" value="1" <?php checked(get_post_meta($post->ID, 'sod_show_name_sponsorship', true), '1'); ?>> Namenspatenschaft anbieten</label>
                </p>
                <p class="description">Nur sinnvoll, solange der Hund oben noch keinen Namen (Titel) hat — die Namenspatenschaft verschwindet auf der Website automatisch, sobald ein Name eingetragen wird.</p>
                <div class="sod-admin-grid">
                    <?php self::text_field($post->ID, 'sod_name_sponsorship_amount', 'Namenspatenschaft – Betrag', 'z.B. 50'); ?>
                    <?php self::textarea_field($post->ID, 'sod_name_sponsorship_text', 'Namenspatenschaft – Text', 3); ?>
                    <?php self::text_field($post->ID, 'sod_name_sponsor_credit', 'Namenspatenschaft – Dank an (nach Zahlungseingang manuell eintragen)', 'z.B. "Name gespendet von Familie Mayer"'); ?>
                </div>
                <?php $sod_name_suggestion = trim((string)get_post_meta($post->ID, 'sod_name_sponsor_suggestion', true)); ?>
                <?php if ($sod_name_suggestion !== '') : ?>
                    <p class="description"><strong>Zuletzt eingegangener Namensvorschlag (aus PayPal-Zahlung):</strong> <?php echo esc_html($sod_name_suggestion); ?></p>
                <?php endif; ?>
            </section>
            <section class="sod-wizard-step" data-sod-step>
                <h3>Tierheim-Alltag</h3>
                <div class="sod-admin-grid">
                    <?php self::textarea_field($post->ID, 'sod_feeding_plan', 'Fütterungsplan', 4); ?>
                    <?php self::select_field('sod_quarantine_status', 'Quarantäne', self::quarantine_options(), (string)get_post_meta($post->ID, 'sod_quarantine_status', true)); ?>
                    <?php self::textarea_field($post->ID, 'sod_compatibility', 'Verträglichkeit', 4); ?>
                    <?php self::textarea_field($post->ID, 'sod_transport_notes', 'Transport / Ausreise', 4); ?>
                    <?php self::textarea_field($post->ID, 'sod_followup_notes', 'Vor-/Nachkontrolle', 4); ?>
                </div>
            </section>
            <section class="sod-wizard-step" data-sod-step>
                <h3>Interne Informationen</h3>
                <div class="sod-admin-grid">
                    <?php self::text_field($post->ID, 'sod_internal_area', 'Bereichsnummer', 'z.B. Bereich 4 / Zwinger 12', 'recommended'); ?>
                    <?php self::textarea_field($post->ID, 'sod_status_history', 'Status-Verlauf', 4); ?>
                    <?php self::textarea_field($post->ID, 'sod_internal_notes', 'Interne Infos', 3); ?>
                </div>
            </section>
            <?php self::wizard_controls(); ?>
        </div>
        <?php
        self::status_log_box($post->ID);
    }

    public static function dog_images_meta_box(WP_Post $post): void
    {
        $image_ids = self::dog_image_ids($post->ID);
        ?>
        <input type="hidden" id="sod_dog_image_ids" name="sod_dog_image_ids" value="<?php echo esc_attr(implode(',', $image_ids)); ?>">
        <div class="sod-dog-images" id="sodDogImagesPreview">
            <?php foreach ($image_ids as $image_id) : ?>
                <div class="sod-dog-image-thumb" data-image-id="<?php echo esc_attr((string)$image_id); ?>">
                    <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="sod-dog-image-actions">
            <button type="button" class="button button-secondary" id="sodSelectDogImages">Bilder auswählen</button>
            <button type="button" class="button" id="sodClearDogImages">Bilder entfernen</button>
        </p>
        <p class="description">Das erste Bild wird automatisch als Hauptbild verwendet, falls kein Beitragsbild gesetzt ist. Die Reihenfolge entspricht der Auswahl in der Mediathek.</p>
        <?php
    }

    public static function inventory_meta_box(WP_Post $post): void
    {
        wp_nonce_field('sod_save_inventory', 'sod_inventory_nonce');
        $locations = self::parse_locations((string)get_post_meta($post->ID, 'sod_inventory_locations', true));
        $locations = array_pad($locations, 3, ['name' => '', 'amount' => '']);
        echo '<div class="sod-wizard" data-sod-wizard>';
        self::wizard_nav(['Artikel', 'Lagerort', 'Bewegung', 'Zuteilung', 'Notiz']);
        echo '<section class="sod-wizard-step is-active" data-sod-step><h3>Artikel</h3><div class="sod-admin-grid">';
        self::text_field($post->ID, 'sod_inventory_number', 'Inventarnummer', 'automatisch');
        self::select_field('sod_inventory_category', 'Kategorie', self::inventory_category_options(), (string)get_post_meta($post->ID, 'sod_inventory_category', true), true, 'required');
        self::select_field('sod_inventory_unit', 'Einheit', self::inventory_unit_options(), (string)get_post_meta($post->ID, 'sod_inventory_unit', true), true, 'recommended');
        self::text_field($post->ID, 'sod_inventory_total', 'Gesamtbestand', 'wird aus Lagerorten berechnet oder manuell');
        self::text_field($post->ID, 'sod_inventory_min', 'Mindestbestand (Warnung)', 'z.B. 5 - leer = Standard 3');
        echo '</div></section>';
        echo '<section class="sod-wizard-step" data-sod-step><h3>Lagerorte / Teilmengen</h3><p class="description">Der Gesamtbestand wird automatisch aus den eingetragenen Mengen berechnet.</p><div class="sod-storage-grid">';
        foreach (array_slice($locations, 0, 3) as $index => $location) {
            echo '<div class="sod-storage-row">';
            self::select_field('sod_inventory_storage_location[]', 'Lagerort ' . ($index + 1), self::storage_location_options(), (string)($location['name'] ?? ''), false);
            printf(
                '<p><label><span>Menge</span><input type="text" name="sod_inventory_storage_quantity[]" value="%s" placeholder="z.B. 5"></label></p>',
                esc_attr((string)($location['amount'] ?? ''))
            );
            echo '</div>';
        }
        echo '</div><p class="description">Bis zu drei Lagerorte können direkt gepflegt werden.</p></section>';
        echo '<section class="sod-wizard-step" data-sod-step><h3>Bewegung erfassen</h3><p class="description">Zugang oder Abgang wird beim Speichern protokolliert. Ohne Lagerorte wird der Gesamtbestand automatisch angepasst; mit Lagerorten bitte die Teilmengen selbst aktualisieren.</p><div class="sod-admin-grid">';
        self::select_field('sod_movement_type', 'Art der Bewegung', ['' => 'Keine Bewegung', 'zugang' => 'Zugang (+)', 'abgang' => 'Abgang (−)'], '');
        self::raw_text_field('sod_movement_qty', 'Menge', '', 'z.B. 5');
        self::raw_text_field('sod_movement_note', 'Grund / Notiz', '', 'z.B. Transport ins Tierheim');
        echo '</div>';
        self::inventory_movements_box($post->ID);
        echo '</section>';
        echo '<section class="sod-wizard-step" data-sod-step><h3>Zuteilung</h3><p class="description">Für Zubehör wie Halsband, Geschirr oder Transportbox kann ein Hund gewählt werden. Futter muss nicht zugeteilt werden.</p><div class="sod-admin-grid">';
        $assigned_dog_selected = (string)get_post_meta($post->ID, 'sod_inventory_assigned_dog', true);
        if ($assigned_dog_selected === '') {
            $assigned_dog_selected = self::prefill_dog_id_param();
        }
        self::select_field('sod_inventory_assigned_dog', 'Zugeordneter Hund', self::dog_assignment_options(), $assigned_dog_selected);
        echo '</div></section>';
        echo '<section class="sod-wizard-step" data-sod-step><h3>Notiz</h3><div class="sod-admin-grid">';
        self::post_content_field($post, 'sod_inventory_notes', 'Notiz', 3);
        echo '</div></section>';
        self::wizard_controls();
        echo '</div>';
        if (!get_post_meta($post->ID, 'sod_inventory_number', true)) {
            echo '<p class="description">Beim ersten Speichern wird automatisch eine Inventarnummer vergeben.</p>';
        }
    }

    private static function application_status_options(): array
    {
        return ['offen' => 'Offen', 'beendet' => 'Beendet'];
    }

    public static function application_meta_box(WP_Post $post): void
    {
        $meta = get_post_meta($post->ID);
        $dog_id = absint(get_post_meta($post->ID, 'dog_id', true));
        $dog_title = $dog_id > 0 && get_post_type($dog_id) === 'sod_dog' ? get_the_title($dog_id) : (string)get_post_meta($post->ID, 'dog_name', true);
        $interest_id = absint(get_post_meta($post->ID, 'sod_created_interest_id', true));
        $case_url = wp_nonce_url(
            add_query_arg(['action' => 'sod_application_to_case', 'application_id' => $post->ID], admin_url('admin-post.php')),
            'sod_application_to_case_' . $post->ID
        );
        $status = (string)get_post_meta($post->ID, 'sod_application_status', true) ?: 'offen';
        $status_options = self::application_status_options();
        $toggle_url = wp_nonce_url(
            add_query_arg(['action' => 'sod_application_toggle_status', 'application_id' => $post->ID], admin_url('admin-post.php')),
            'sod_application_toggle_status_' . $post->ID
        );
        echo '<div class="sod-record-summary">';
        printf('<div><span>Hund</span><strong>%s</strong></div>', esc_html($dog_title !== '' ? $dog_title : 'nicht angegeben'));
        printf('<div><span>Interesse</span><strong>%s</strong></div>', esc_html((string)get_post_meta($post->ID, 'interest', true) ?: '-'));
        printf('<div><span>E-Mail</span><strong>%s</strong></div>', esc_html((string)get_post_meta($post->ID, 'email', true) ?: '-'));
        printf('<div><span>Status</span><strong>%s</strong></div>', esc_html($status_options[$status] ?? $status));
        echo '</div>';
        echo '<div class="sod-quick-actions">';
        if ($interest_id > 0 && get_post_type($interest_id) === 'sod_interest') {
            printf('<a class="button button-primary" href="%s">Interessent öffnen</a>', esc_url(get_edit_post_link($interest_id)));
        } else {
            $transfer_url = wp_nonce_url(
                add_query_arg(['action' => 'sod_application_to_interest', 'application_id' => $post->ID], admin_url('admin-post.php')),
                'sod_application_to_interest_' . $post->ID
            );
            printf('<a class="button button-primary" href="%s">Als Interessent übernehmen</a>', esc_url($transfer_url));
        }
        printf('<a class="button" href="%s">Vermittlungsakte starten</a>', esc_url($case_url));
        if ($status === 'beendet') {
            printf('<a class="button" href="%s">Anfrage wieder öffnen</a>', esc_url($toggle_url));
        } else {
            printf('<a class="button" href="%s">Anfrage beenden</a>', esc_url($toggle_url));
        }
        echo '</div>';

        self::render_application_message_thread($post);
        self::render_application_internal_notes($post);

        echo '<table class="widefat striped"><tbody>';
        foreach ($meta as $key => $values) {
            if (str_starts_with($key, '_')) {
                continue;
            }
            printf('<tr><th>%s</th><td>%s</td></tr>', esc_html($key), esc_html((string)($values[0] ?? '')));
        }
        echo '</tbody></table>';
    }

    /**
     * Nachrichtenverlauf: die urspruengliche Anfrage plus alle bisher gesendeten Antworten
     * (als native WP-Kommentare am Anfrage-Datensatz gespeichert, gleiche Technik wie die
     * Foto-Kommentare im Mitgliederbereich). Eingehende Antworten der anfragenden Person
     * per E-Mail erscheinen hier NICHT automatisch - dafuer waere ein E-Mail-Abruf noetig,
     * den es bewusst (noch) nicht gibt. Antworten laufen weiterhin im normalen Postfach ein.
     */
    private static function render_application_message_thread(WP_Post $post): void
    {
        $sender_name = trim((string)get_post_meta($post->ID, 'first_name', true) . ' ' . get_post_meta($post->ID, 'last_name', true));
        $original_message = trim((string)get_post_meta($post->ID, 'message', true));
        $reply_email = trim((string)get_post_meta($post->ID, 'email', true));

        echo '<div class="sod-message-thread">';
        echo '<h3>Nachrichtenverlauf</h3>';
        echo '<div class="sod-message-list">';
        echo '<div class="sod-message sod-message-in"><div class="sod-message-bubble">';
        printf(
            '<div class="sod-message-meta"><strong>%s</strong><span class="sod-message-time">%s</span></div>',
            esc_html($sender_name !== '' ? $sender_name : 'Anfragende Person'),
            esc_html(get_the_date('d.m.Y H:i', $post))
        );
        printf('<div class="sod-message-body">%s</div>', nl2br(esc_html($original_message !== '' ? $original_message : '(keine Nachricht angegeben)')));
        echo '</div></div>';

        foreach (get_comments(['post_id' => $post->ID, 'status' => 'approve', 'order' => 'ASC', 'type__in' => ['comment', 'sod_inbound_reply']]) as $comment) {
            $is_inbound = $comment->comment_type === 'sod_inbound_reply';
            echo '<div class="sod-message ' . ($is_inbound ? 'sod-message-in' : 'sod-message-out') . '"><div class="sod-message-bubble">';
            printf('<div class="sod-message-body">%s</div>', nl2br(esc_html($comment->comment_content)));
            echo '</div></div>';
        }
        echo '</div>';

        self::print_sod_ajax_box_script();

        if ($reply_email === '') {
            echo '<p class="description">Ohne hinterlegte E-Mail-Adresse kann keine Antwort gesendet werden.</p>';
            return;
        }

        echo '<div class="sod-admin-field-wide sod-ajax-box" data-sod-action="sod_application_reply" data-sod-post-id="' . esc_attr((string)$post->ID) . '">';
        wp_nonce_field('sod_application_reply_' . $post->ID, 'sod_application_reply_nonce');
        echo '<label for="sod_application_reply_message"><strong>Antwort per E-Mail an ' . esc_html($reply_email) . '</strong></label><br>';
        echo '<textarea name="reply_message" id="sod_application_reply_message" rows="4" style="width:100%;max-width:640px;" placeholder="Ihre Antwort an die anfragende Person…"></textarea><br>';
        echo '<button type="button" class="button button-primary sod-ajax-submit" style="margin-top:8px;">Antwort senden</button>';
        echo '</div>';
    }

    public static function admin_record_meta_box(WP_Post $post): void
    {
        $configs = self::admin_record_configs();
        $config = $configs[$post->post_type] ?? null;
        if (!$config) {
            return;
        }
        wp_nonce_field('sod_save_admin_record', 'sod_admin_record_nonce');
        if ($post->post_type === 'sod_interest') {
            $case_url = wp_nonce_url(
                add_query_arg(['action' => 'sod_interest_to_case', 'interest_id' => $post->ID], admin_url('admin-post.php')),
                'sod_interest_to_case_' . $post->ID
            );
            echo '<div class="sod-quick-actions">';
            printf('<a class="button button-secondary" href="%s">Vermittlungsakte starten</a>', esc_url($case_url));
            echo '<button type="button" class="button" data-sod-focus="sod_interest_followup">Wiedervorlage setzen</button>';
            echo '</div>';
            echo '<div class="sod-wizard" data-sod-wizard>';
            self::wizard_nav(['Kontakt', 'Hund', 'Status', 'Notizen']);
            echo '<section class="sod-wizard-step is-active" data-sod-step><h3>Kontakt</h3><div class="sod-admin-grid">';
            self::render_admin_record_fields($post, $config, ['sod_interest_email', 'sod_interest_phone', 'sod_interest_location']);
            echo '</div></section>';
            echo '<section class="sod-wizard-step" data-sod-step><h3>Hund & Interesse</h3><div class="sod-admin-grid">';
            self::render_admin_record_fields($post, $config, ['sod_interest_type', 'sod_interest_dog']);
            echo '</div></section>';
            echo '<section class="sod-wizard-step" data-sod-step><h3>Status & Wiedervorlage</h3><div class="sod-admin-grid">';
            self::render_admin_record_fields($post, $config, ['sod_interest_status', 'sod_interest_followup']);
            echo '</div></section>';
            echo '<section class="sod-wizard-step" data-sod-step><h3>Notizen</h3><div class="sod-admin-grid">';
            self::render_admin_record_fields($post, $config, ['sod_interest_notes', 'sod_interest_reject_reason']);
            echo '</div></section>';
            self::wizard_controls();
            echo '</div>';
        } else {
            echo '<div class="sod-admin-grid">';
            self::render_admin_record_fields($post, $config, array_keys($config['fields']));
            echo '</div>';
        }
        if (!empty($config['hint'])) {
            printf('<p class="description">%s</p>', esc_html($config['hint']));
        }
        if (in_array($post->post_type, ['sod_case', 'sod_interest'], true)) {
            self::status_log_box($post->ID);
        }
        if ($post->post_type === 'sod_case' && $post->ID > 0) {
            $contract_url = wp_nonce_url(
                add_query_arg(['action' => 'sod_case_contract', 'case_id' => $post->ID], admin_url('admin-post.php')),
                'sod_case_contract_' . $post->ID
            );
            $contract_pdf_url = wp_nonce_url(
                add_query_arg(['action' => 'sod_case_contract_pdf_dl', 'case_id' => $post->ID], admin_url('admin-post.php')),
                'sod_case_contract_pdf_dl_' . $post->ID
            );
            echo '<div class="sod-quick-actions">';
            printf('<a class="button button-secondary" target="_blank" href="%s">Schutzvertrag-Druckvorlage öffnen</a>', esc_url($contract_url));
            printf('<a class="button" href="%s">Schutzvertrag-PDF herunterladen</a>', esc_url($contract_pdf_url));
            self::post_action_button('sod_case_contract_pdf_save', 'case_id', $post->ID, 'sod_case_contract_pdf_save_' . $post->ID, 'Schutzvertrag-PDF ablegen', 'button button-primary', 'Schutzvertrag-PDF jetzt in der Akte und beim Hund ablegen?');
            self::post_action_button('sod_case_contract_pdf_send', 'case_id', $post->ID, 'sod_case_contract_pdf_send_' . $post->ID, 'Schutzvertrag-PDF senden', 'button', 'Schutzvertrag-PDF jetzt per E-Mail an den Adoptanten senden?');
            echo '</div>';
            echo '<p class="description">Bitte die Akte nach Änderungen zuerst speichern, damit die PDF die aktuellen Daten enthält. Für den Versand muss im Feld „Adoptant / Kontakt“ eine E-Mail-Adresse stehen.</p>';
            self::case_precheck_questionnaire($post);
        }
        if ($post->post_type === 'sod_sponsor' && $post->ID > 0) {
            $wish = get_post_meta($post->ID, 'sod_sponsor_show_wish', true) === '1';
            $photo_id = (int)get_post_meta($post->ID, 'sod_sponsor_photo_id', true);
            $dog_photo_id = (int)get_post_meta($post->ID, 'sod_sponsor_dog_photo_id', true);
            $sponsor_dog_id = (int)get_post_meta($post->ID, 'sod_sponsor_dog', true);
            $dog_fallback_id = $sponsor_dog_id > 0 ? (int)get_post_thumbnail_id($sponsor_dog_id) : 0;
            echo '<div class="sod-sponsor-images">';
            echo '<p class="description">' . esc_html($wish
                ? 'Der Pate möchte öffentlich als Pate erscheinen. Prüfe das Foto, gib es über „Foto öffentlich zeigen" frei und wähle bei Bedarf ein anderes Paten- oder Hundefoto.'
                : 'Hier kannst du das Paten- und das Hundefoto festlegen, die öffentlich in der Patenschafts-Übersicht erscheinen.') . '</p>';
            echo '<div class="sod-sponsor-images-grid" style="display:flex;gap:20px;flex-wrap:wrap;">';
            self::sponsor_image_picker('sod_sponsor_photo_id', 'Patenfoto', $photo_id, 0);
            self::sponsor_image_picker('sod_sponsor_dog_photo_id', 'Hundefoto', $dog_photo_id, $dog_fallback_id);
            echo '</div></div>';

            $consent_at = (string)get_post_meta($post->ID, 'sod_sponsor_public_consent_at', true);
            $consent_version = (string)get_post_meta($post->ID, 'sod_sponsor_public_consent_version', true);
            $consent_source = (string)get_post_meta($post->ID, 'sod_sponsor_public_consent_source', true);
            if ($consent_at !== '') {
                printf(
                    '<p class="description"><strong>Öffentliche Einwilligung dokumentiert:</strong> %s · Fassung %s · Quelle %s</p>',
                    esc_html($consent_at),
                    esc_html($consent_version ?: 'unbekannt'),
                    esc_html($consent_source ?: 'unbekannt')
                );
            }

            $certificate_url = wp_nonce_url(
                add_query_arg(['action' => 'sod_sponsor_certificate_pdf', 'sponsor_id' => $post->ID], admin_url('admin-post.php')),
                'sod_sponsor_certificate_pdf_' . $post->ID
            );
            echo '<div class="sod-quick-actions">';
            printf('<a class="button button-primary" href="%s">Patenschaftszertifikat-PDF herunterladen</a>', esc_url($certificate_url));
            self::post_action_button(
                'sod_sponsor_certificate_send',
                'sponsor_id',
                $post->ID,
                'sod_sponsor_certificate_send_' . $post->ID,
                'Zertifikat jetzt senden',
                'button',
                'Zertifikat jetzt per E-Mail an den Paten senden?'
            );
            echo '</div>';
            $certificate_sent_at = self::format_utc_datetime((string)get_post_meta($post->ID, 'sod_sponsor_certificate_sent_at', true));
            echo '<p class="description">Bitte den Paten-Eintrag nach Änderungen zuerst speichern, damit das Zertifikat Name, Hund, Betrag und Datum aktuell zeigt.';
            if ($certificate_sent_at !== '') {
                echo '<br><strong>Zuletzt per E-Mail versendet:</strong> ' . esc_html($certificate_sent_at . ' Uhr');
            }
            echo '</p>';
        }
        if ($post->post_type === 'sod_finance' && $post->ID > 0) {
            $receipt_url = wp_nonce_url(
                add_query_arg(['action' => 'sod_finance_receipt', 'finance_id' => $post->ID], admin_url('admin-post.php')),
                'sod_finance_receipt_' . $post->ID
            );
            echo '<div class="sod-quick-actions sod-receipt-actions">';
            printf('<a class="button button-secondary" target="_blank" href="%s">Spendenbestätigung öffnen</a>', esc_url($receipt_url));
            self::post_action_button('sod_finance_receipt_send', 'finance_id', $post->ID, 'sod_finance_receipt_send_' . $post->ID, 'Spendenbestätigung senden', 'button button-primary', 'Spendenbestätigung jetzt per E-Mail senden?');
            echo '</div>';
            $receipt_sent_at = trim((string)get_post_meta($post->ID, 'sod_finance_receipt_sent_at', true));
            echo '<p class="description">Für den Versand muss beim Spender eine gültige E-Mail-Adresse eingetragen sein. Bitte vor Verwendung für Finanzamt/Steuerzwecke prüfen, ob der Verein für steuerlich absetzbare Spenden begünstigt ist.';
            if ($receipt_sent_at !== '') {
                echo '<br><strong>Zuletzt per E-Mail versendet:</strong> ' . esc_html(mysql2date('d.m.Y H:i', $receipt_sent_at) . ' Uhr');
            }
            echo '</p>';
        }
    }

    public static function save_dog(int $post_id, WP_Post $post): void
    {
        if (!self::can_save($post_id, 'sod_dog_nonce', 'sod_save_dog')) {
            return;
        }
        $has_public_visibility = isset($_POST['sod_show_adoption']) || isset($_POST['sod_show_sponsorship']);
        if (!$has_public_visibility) {
            update_post_meta($post_id, '_sod_visibility_missing', '1');
            if ($post->post_status !== 'draft') {
                remove_action('save_post_sod_dog', [self::class, 'save_dog'], 10);
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'draft',
                ]);
                add_action('save_post_sod_dog', [self::class, 'save_dog'], 10, 2);
            }
        } else {
            delete_post_meta($post_id, '_sod_visibility_missing');
        }
        self::log_status_change($post_id, 'sod_status', sanitize_text_field((string)($_POST['sod_status'] ?? '')), self::dog_statuses());
        foreach (self::DOG_META as $key) {
            if (in_array($key, ['sod_show_adoption', 'sod_show_sponsorship', 'sod_photos_verified', 'sod_show_name_sponsorship'], true)) {
                update_post_meta($post_id, $key, isset($_POST[$key]) ? '1' : '0');
                continue;
            }
            if ($key === 'sod_video_url') {
                update_post_meta($post_id, $key, implode("\n", self::sanitize_url_lines((string)($_POST[$key] ?? ''))));
                continue;
            }
            $value = sanitize_textarea_field((string)($_POST[$key] ?? ''));
            if ($key === 'sod_document_links') {
                $value = self::canonicalize_private_document_urls($value);
            }
            if (in_array($key, self::DOG_DATE_META, true)) {
                $value = self::iso_date($value);
            }
            update_post_meta($post_id, $key, $value);
        }
        self::update_post_content($post_id, sanitize_textarea_field((string)($_POST['sod_dog_description'] ?? '')), 'save_dog');
        $image_ids = self::sanitize_id_list((string)($_POST['sod_dog_image_ids'] ?? ''));
        update_post_meta($post_id, 'sod_dog_image_ids', implode(',', $image_ids));
        if ($image_ids && !has_post_thumbnail($post_id)) {
            set_post_thumbnail($post_id, $image_ids[0]);
        }
        self::handle_dog_health_document_upload($post_id);
    }

    public static function save_inventory(int $post_id, WP_Post $post): void
    {
        if (!self::can_save($post_id, 'sod_inventory_nonce', 'sod_save_inventory')) {
            return;
        }
        $fields = ['sod_inventory_category', 'sod_inventory_unit', 'sod_inventory_total', 'sod_inventory_assigned_dog'];
        foreach ($fields as $key) {
            update_post_meta($post_id, $key, sanitize_textarea_field((string)($_POST[$key] ?? '')));
        }
        self::update_post_content($post_id, sanitize_textarea_field((string)($_POST['sod_inventory_notes'] ?? '')), 'save_inventory');
        $locations = [];
        $location_names = array_map('sanitize_text_field', (array)($_POST['sod_inventory_storage_location'] ?? []));
        $location_quantities = array_map('sanitize_text_field', (array)($_POST['sod_inventory_storage_quantity'] ?? []));
        foreach ($location_names as $index => $name) {
            $quantity = $location_quantities[$index] ?? '';
            if ($name === '' && $quantity === '') {
                continue;
            }
            $locations[] = ['location' => $name, 'quantity' => $quantity];
        }
        update_post_meta($post_id, 'sod_inventory_locations', wp_json_encode($locations, JSON_UNESCAPED_UNICODE));
        if ($locations) {
            $total = array_sum(array_map(static fn (array $item): float => self::numeric_quantity((string)($item['quantity'] ?? '')), $locations));
            update_post_meta($post_id, 'sod_inventory_total', self::format_quantity($total));
        }
        update_post_meta($post_id, 'sod_inventory_min', sanitize_text_field((string)($_POST['sod_inventory_min'] ?? '')));

        $movement_type = (string)($_POST['sod_movement_type'] ?? '');
        $movement_qty = self::numeric_quantity(sanitize_text_field((string)($_POST['sod_movement_qty'] ?? '')));
        if (in_array($movement_type, ['zugang', 'abgang'], true) && $movement_qty > 0) {
            $user = wp_get_current_user();
            $movements = self::inventory_movements($post_id);
            $movements[] = [
                'date' => date_i18n('d.m.Y H:i'),
                'user' => $user && $user->display_name !== '' ? $user->display_name : 'System',
                'type' => $movement_type,
                'qty' => self::format_quantity($movement_qty),
                'note' => sanitize_text_field((string)($_POST['sod_movement_note'] ?? '')),
            ];
            update_post_meta($post_id, 'sod_inventory_movements', wp_json_encode($movements, JSON_UNESCAPED_UNICODE));
            if (!$locations) {
                $total = self::numeric_quantity((string)get_post_meta($post_id, 'sod_inventory_total', true));
                $total += $movement_type === 'zugang' ? $movement_qty : -$movement_qty;
                update_post_meta($post_id, 'sod_inventory_total', self::format_quantity(max(0, $total)));
            }
        }

        $number = sanitize_text_field((string)($_POST['sod_inventory_number'] ?? ''));
        if ($number === '') {
            $number = 'SOD-' . str_pad((string)$post_id, 5, '0', STR_PAD_LEFT);
        }
        update_post_meta($post_id, 'sod_inventory_number', $number);
    }

    private static function inventory_movements(int $post_id): array
    {
        $decoded = json_decode((string)get_post_meta($post_id, 'sod_inventory_movements', true), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function inventory_movements_box(int $post_id): void
    {
        $movements = array_reverse(array_slice(self::inventory_movements($post_id), -8));
        if (!$movements) {
            return;
        }
        echo '<h4>Letzte Bewegungen</h4><ul class="sod-movement-list">';
        foreach ($movements as $movement) {
            $sign = ($movement['type'] ?? '') === 'zugang' ? '+' : '−';
            printf(
                '<li><strong>%s%s</strong> %s <span class="sod-muted">(%s, %s)</span></li>',
                esc_html($sign),
                esc_html((string)($movement['qty'] ?? '')),
                esc_html((string)($movement['note'] ?? '') !== '' ? (string)$movement['note'] : 'ohne Notiz'),
                esc_html((string)($movement['date'] ?? '')),
                esc_html((string)($movement['user'] ?? ''))
            );
        }
        echo '</ul>';
    }

    private static function low_stock_threshold(int $post_id): float
    {
        $min = self::numeric_quantity((string)get_post_meta($post_id, 'sod_inventory_min', true));
        return $min > 0 ? $min : 3.0;
    }

    public static function save_admin_record(int $post_id, WP_Post $post): void
    {
        if (!self::can_save($post_id, 'sod_admin_record_nonce', 'sod_save_admin_record')) {
            return;
        }
        $configs = self::admin_record_configs();
        $config = $configs[$post->post_type] ?? null;
        if (!$config) {
            return;
        }
        $previous_sponsor_dog_id = $post->post_type === 'sod_sponsor'
            ? (int)get_post_meta($post_id, 'sod_sponsor_dog', true)
            : 0;
        $previous_public_consent = $post->post_type === 'sod_sponsor'
            ? (string)get_post_meta($post_id, 'sod_sponsor_public_consent', true)
            : '';
        $previous_payment_received = $post->post_type === 'sod_sponsor'
            ? (string)get_post_meta($post_id, 'sod_sponsor_payment_received', true)
            : '';
        $requested_sponsor_status = null;
        $requested_payment_received = null;
        foreach ($config['fields'] as $key => $field) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = self::sanitize_admin_record_value($field, $_POST[$key]);
            if ($value === null) {
                continue;
            }
            if ($key === 'sod_case_documents') {
                $value = self::canonicalize_private_document_urls($value);
            }
            if ($post->post_type === 'sod_case' && $key === 'sod_case_status') {
                self::log_status_change($post_id, 'sod_case_status', $value, self::case_status_options());
            }
            if ($post->post_type === 'sod_interest' && $key === 'sod_interest_status') {
                self::log_status_change($post_id, 'sod_interest_status', $value, self::interest_status_options());
            }
            if ($post->post_type === 'sod_sponsor' && $key === 'sod_sponsor_status') {
                $requested_sponsor_status = $value;
            }
            if ($post->post_type === 'sod_sponsor' && $key === 'sod_sponsor_payment_received') {
                if ($previous_payment_received === '1') {
                    $value = '1';
                }
                $requested_payment_received = $value;
                if ($value === '1' && $previous_payment_received !== '1') {
                    // Den Wechsel hier bewusst NICHT speichern: confirm_sponsor_payment() unten
                    // liest diesen Wert, um eine Erstbestätigung zu erkennen, und setzt ihn selbst.
                    // Wird er vorher geschrieben, gilt die Zahlung dort als längst bestätigt und
                    // Notiz, Zertifikat sowie Spendenbeleg werden übersprungen.
                    continue;
                }
            }
            if ($post->post_type === 'sod_sponsor' && $key === 'sod_sponsor_photo_approved'
                && $value === '1' && (string)get_post_meta($post_id, 'sod_sponsor_public_consent', true) !== '1') {
                $value = '0';
            }
            update_post_meta($post_id, $key, $value);
        }
        if ($post->post_type === 'sod_interest') {
            self::update_record_retention($post_id, 'sod_interest', (string)get_post_meta($post_id, 'sod_interest_status', true));
        }
        if ($post->post_type === 'sod_case') {
            self::update_record_retention($post_id, 'sod_case', (string)get_post_meta($post_id, 'sod_case_status', true));
        }
        if ($post->post_type === 'sod_finance') {
            self::update_finance_retention($post_id);
        }
        if ($post->post_type === 'sod_case') {
            foreach (array_keys(self::case_precheck_fields()) as $key) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                update_post_meta($post_id, $key, sanitize_textarea_field((string)($_POST[$key] ?? '')));
            }
        }
        if ($post->post_type === 'sod_sponsor') {
            if ($requested_payment_received === '1' && $previous_payment_received !== '1') {
                self::confirm_sponsor_payment($post_id);
            } elseif ($requested_sponsor_status !== null) {
                self::maybe_activate_sponsor($post_id, $requested_sponsor_status);
            }
            $public_consent = (string)get_post_meta($post_id, 'sod_sponsor_public_consent', true);
            if ($public_consent === '1' && $previous_public_consent !== '1') {
                update_post_meta($post_id, 'sod_sponsor_public_consent_at', gmdate('c'));
                update_post_meta($post_id, 'sod_sponsor_public_consent_version', self::PRIVACY_NOTICE_VERSION);
                update_post_meta($post_id, 'sod_sponsor_public_consent_source', 'admin-dokumentiert');
                delete_post_meta($post_id, 'sod_sponsor_public_consent_withdrawn_at');
                delete_post_meta($post_id, 'sod_sponsor_photo_delete_after');
            } elseif ($public_consent !== '1' && $previous_public_consent === '1') {
                update_post_meta($post_id, 'sod_sponsor_public_consent_withdrawn_at', gmdate('c'));
                update_post_meta($post_id, 'sod_sponsor_photo_approved', '0');
                update_post_meta($post_id, 'sod_sponsor_photo_delete_after', gmdate('Y-m-d', time() + self::PUBLIC_PHOTO_DELETION_DAYS * DAY_IN_SECONDS));
            }
            self::update_sponsor_retention($post_id, (string)get_post_meta($post_id, 'sod_sponsor_status', true));
            foreach (['sod_sponsor_photo_id', 'sod_sponsor_dog_photo_id'] as $img_key) {
                if (!array_key_exists($img_key, $_POST)) {
                    continue;
                }
                $img_id = absint($_POST[$img_key]);
                if ($img_id > 0 && get_post_type($img_id) === 'attachment') {
                    update_post_meta($post_id, $img_key, $img_id);
                } else {
                    delete_post_meta($post_id, $img_key);
                }
            }
            $sponsor_dog_id = (int)get_post_meta($post_id, 'sod_sponsor_dog', true);
            if ($previous_sponsor_dog_id > 0 && $previous_sponsor_dog_id !== $sponsor_dog_id) {
                self::sync_dog_public_sponsors($previous_sponsor_dog_id);
            }
            if ($sponsor_dog_id > 0) {
                self::sync_dog_public_sponsors($sponsor_dog_id);
            } else {
                self::sync_active_sponsorships_index();
            }
        }
    }

    private static function sanitize_admin_record_value(array $field, mixed $raw_value): ?string
    {
        $type = (string)($field['type'] ?? 'text');
        $value = sanitize_textarea_field((string)$raw_value);
        if ($type === 'date') {
            return self::iso_date($value);
        }
        if ($type === 'select') {
            $options = (array)($field['options'] ?? []);
            return array_key_exists($value, $options) ? $value : null;
        }
        return $value;
    }

    private static function log_status_change(int $post_id, string $meta_key, string $new_value, array $labels = []): void
    {
        $old_value = (string)get_post_meta($post_id, $meta_key, true);
        if ($old_value === $new_value) {
            return;
        }
        $user = wp_get_current_user();
        $line = sprintf(
            '%s – %s: %s → %s',
            date_i18n('d.m.Y H:i'),
            $user && $user->display_name !== '' ? $user->display_name : 'System',
            $labels[$old_value] ?? ($old_value !== '' ? $old_value : '—'),
            $labels[$new_value] ?? ($new_value !== '' ? $new_value : '—')
        );
        $log = trim((string)get_post_meta($post_id, 'sod_status_log', true));
        update_post_meta($post_id, 'sod_status_log', trim($log . "\n" . $line));
    }

    private static function status_log_box(int $post_id): void
    {
        $log = trim((string)get_post_meta($post_id, 'sod_status_log', true));
        if ($log === '') {
            return;
        }
        echo '<details class="sod-advanced sod-status-log"><summary>Automatischer Status-Verlauf</summary><pre>' . esc_html($log) . '</pre></details>';
    }

    private static function t(string $key, string $fallback): string
    {
        return function_exists('sod_t') ? sod_t($key) : $fallback;
    }

    private static function tpl(string $key, string $fallback, array $replacements): string
    {
        $text = self::t($key, $fallback);
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace('{' . $placeholder . '}', $value, $text);
        }
        return $text;
    }

    public static function dogs_shortcode(array $atts): string
    {
        $atts = shortcode_atts(['limit' => 0, 'mode' => 'adoption'], $atts, 'sod_dogs');
        $meta_key = $atts['mode'] === 'sponsorship' ? 'sod_show_sponsorship' : 'sod_show_adoption';
        $meta_query = [
            'relation' => 'AND',
            [
                'key' => $meta_key,
                'value' => '1',
            ],
        ];
        $query = new WP_Query([
            'post_type' => 'sod_dog',
            'posts_per_page' => (int)$atts['limit'] > 0 ? (int)$atts['limit'] : -1,
            'post_status' => 'publish',
            'meta_query' => $meta_query,
            'orderby' => 'menu_order date',
            'order' => 'DESC',
        ]);

        if (!$query->have_posts()) {
            return self::dogs_empty_state((string)$atts['mode']);
        }

        ob_start();
        echo '<div class="grid-3 sod-dogs-grid">';
        $visible_cards = 0;
        while ($query->have_posts()) {
            $query->the_post();
            $has_name = self::dog_has_public_name(get_the_ID());
            $name_sponsorship_open = get_post_meta(get_the_ID(), 'sod_show_name_sponsorship', true) === '1';
            if (!$has_name && !($atts['mode'] === 'sponsorship' && $name_sponsorship_open)) {
                continue;
            }
            self::dog_card(get_the_ID(), $atts['mode']);
            $visible_cards++;
        }
        wp_reset_postdata();
        echo '</div>';
        $html = (string)ob_get_clean();
        return $visible_cards > 0 ? $html : self::dogs_empty_state((string)$atts['mode']);
    }

    private static function dogs_empty_state(string $mode): string
    {
        if ($mode === 'sponsorship') {
            $title = self::t('dogcard.empty_sponsorship_title', 'Patenschaften werden gerade geprüft');
            $text = self::t('dogcard.empty_sponsorship_text', 'Wir zeigen hier nur Hunde, deren Daten und Fotos zuverlässig gepflegt sind. Du kannst trotzdem helfen: mit Futter, Sachspenden oder einer allgemeinen Patenschaftsanfrage.');
            $primary = [self::t('dogcard.empty_sponsorship_primary', 'Patenschaft anfragen'), add_query_arg(['sod_interest' => 'Patenschaft'], self::page_url('kontakt')) . '#anfrageformular'];
            $secondary = [self::t('dogcard.empty_sponsorship_secondary', 'Spendenmöglichkeiten ansehen'), self::page_url('spenden')];
        } else {
            $title = self::t('dogcard.empty_adoption_title', 'Die Vermittlungsliste wird gerade sorgfältig gepflegt');
            $text = self::t('dogcard.empty_adoption_text', 'Wir veröffentlichen Hunde erst, wenn Foto, Charakter und die wichtigsten Angaben zuverlässig geprüft sind. So bleibt die Vermittlung ehrlich und niemand entscheidet auf Basis unsicherer Daten.');
            $primary = [self::t('dogcard.empty_adoption_primary', 'Kontakt aufnehmen'), self::page_url('kontakt') . '#anfrageformular'];
            $secondary = [self::t('dogcard.empty_adoption_secondary', 'Patenschaft ansehen'), self::page_url('patenschaft')];
        }

        return sprintf(
            '<div class="empty-state sod-dogs-empty"><div class="empty-title">%1$s</div><p class="empty-desc">%2$s</p><div class="empty-actions"><a class="btn btn-primary btn-md" href="%3$s">%4$s</a><a class="btn btn-outline btn-md" href="%5$s">%6$s</a></div></div>',
            esc_html($title),
            esc_html($text),
            esc_url($primary[1]),
            esc_html($primary[0]),
            esc_url($secondary[1]),
            esc_html($secondary[0])
        );
    }

    private static function dog_card(int $post_id, string $mode): void
    {
        $name_sponsorship_open = $mode === 'sponsorship' && !self::dog_has_public_name($post_id) && get_post_meta($post_id, 'sod_show_name_sponsorship', true) === '1';
        $dog_name = $name_sponsorship_open
            ? self::t('dogcard.name_sponsorship_card_label', 'Noch ohne Namen')
            : self::dog_public_name($post_id);
        $status = (string)get_post_meta($post_id, 'sod_status', true) ?: 'verfuegbar';
        $statuses = self::dog_statuses();
        $interest = $mode === 'sponsorship' ? 'Patenschaft' : 'Vermittlung';
        $contact = self::dog_contact_url($post_id, $interest);
        $detail = get_permalink($post_id);
        $support = self::dog_support_amounts($post_id);
        $sponsorship_amount_raw = self::money_plain($support['target']);
        $sponsorship_taken = $support['target'] > 0 && $support['remaining'] < 1;
        $sponsor_signup_ready = $mode === 'sponsorship' && $support['target'] > 0 && !$sponsorship_taken;
        // Voll gedeckte Monatsversorgung: statt "Verfügbar" ein Dank-Badge. Nur in der
        // Patenschafts-Ansicht - in der Vermittlung sagt der Status etwas anderes aus.
        $goal_reached = $mode === 'sponsorship' && $sponsorship_taken;
        $badge_class = $name_sponsorship_open ? 'namenspatenschaft' : ($goal_reached ? 'ziel-erreicht' : $status);
        $badge_label = $name_sponsorship_open
            ? self::t('dogcard.name_sponsorship_title', 'Namenspatenschaft')
            : ($goal_reached
                ? self::t('dogcard.status_ziel_erreicht', 'Danke ❤️ Ziel erreicht')
                : ($statuses[$status] ?? self::t('dogcard.status_verfuegbar', 'Verfügbar')));
        $image_ids = self::dog_image_ids($post_id);
        $video_urls = self::dog_video_urls($post_id);
        $video_url = (string)($video_urls[0] ?? '');
        $extra_videos = array_slice($video_urls, 1);
        $poster = self::dog_poster_url($post_id, 'large');
        ?>
        <article class="dog-card">
            <div class="dog-card-media" data-sod-gallery>
                <?php if ($video_url !== '') : ?>
                    <div class="dog-card-video">
                        <?php echo self::dog_video_embed($video_url, 'dog-card-video-frame', $poster); ?>
                    </div>
                <?php else : ?>
                    <a class="dog-card-image" href="<?php echo esc_url($detail); ?>" aria-label="<?php echo esc_attr(self::tpl('dogcard.aria_mehr_erfahren_tpl', 'Mehr über {name} erfahren', ['name' => $dog_name])); ?>">
                        <?php if (has_post_thumbnail($post_id)) : ?>
                            <?php echo get_the_post_thumbnail($post_id, 'large', ['loading' => 'lazy']); ?>
                        <?php elseif ($image_ids) : ?>
                            <?php echo wp_get_attachment_image($image_ids[0], 'large', false, ['loading' => 'lazy']); ?>
                        <?php else : ?>
                            <span class="dog-card-image-placeholder"><?php echo esc_html(self::t('dogcard.image_placeholder', 'Bild folgt')); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <div class="dog-card-badge-wrap"><span class="badge badge-<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_label); ?></span></div>
                <?php self::dog_thumbnail_strip($image_ids, $dog_name, 'card', $extra_videos); ?>
            </div>
            <div class="dog-card-body">
                <div>
                    <div class="dog-card-name"><a class="dog-card-name-link" href="<?php echo esc_url($detail); ?>"><?php echo esc_html($dog_name); ?></a></div>
                    <div class="dog-card-sub"><?php echo esc_html((string)get_post_meta($post_id, 'sod_location', true) ?: self::t('dogcard.location_fallback', 'Aufenthaltsort auf Anfrage')); ?></div>
                </div>
                <div class="dog-card-meta">
                    <?php foreach (['sod_age' => self::t('dogcard.meta_alter', 'Alter'), 'sod_breed' => self::t('dogcard.meta_typ', 'Typ'), 'sod_gender' => self::t('dogcard.meta_geschlecht', 'Geschlecht'), 'sod_weight' => self::t('dogcard.meta_gewicht', 'Gewicht')] as $key => $label) : ?>
                        <?php $value = trim((string)get_post_meta($post_id, $key, true)); ?>
                        <?php if ($value !== '') : ?><span class="dog-card-meta-item"><?php echo esc_html($label . ': ' . $value); ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php foreach (['sod_health' => self::t('dogcard.section_gesundheit', 'Gesundheit')] as $key => $label) : ?>
                    <?php $value = trim((string)get_post_meta($post_id, $key, true)); ?>
                    <?php if ($value !== '') : ?><div class="dog-card-section"><strong><?php echo esc_html($label); ?></strong><p><?php echo esc_html($value); ?></p></div><?php endif; ?>
                <?php endforeach; ?>
                <?php if (get_the_excerpt($post_id)) : ?><p class="dog-card-desc"><?php echo esc_html(get_the_excerpt($post_id)); ?></p><?php endif; ?>
                <?php foreach (['sod_character' => self::t('dogcard.section_charakter', 'Charakter'), 'sod_needs' => self::t('dogcard.section_braucht', 'Braucht')] as $key => $label) : ?>
                    <?php $value = trim((string)get_post_meta($post_id, $key, true)); ?>
                    <?php if ($value !== '') : ?><div class="dog-card-section"><strong><?php echo esc_html($label); ?></strong><p><?php echo esc_html($value); ?></p></div><?php endif; ?>
                <?php endforeach; ?>
                <?php self::dog_support_progress($post_id, 'card'); ?>
            </div>
            <div class="dog-card-footer<?php echo $mode === 'sponsorship' ? ' dog-card-footer-sponsorship' : ''; ?>">
                <a class="btn btn-primary btn-sm dog-card-detail-button" href="<?php echo esc_url($detail); ?>"><?php echo esc_html(self::t('dogcard.profil_ansehen', 'Profil ansehen')); ?></a>
                <?php if ($name_sponsorship_open) : ?>
                    <a class="btn btn-secondary btn-sm dog-card-inquiry-button" href="<?php echo esc_url($detail); ?>"><?php echo esc_html(self::t('dogcard.name_sponsorship_btn', 'Namenspatenschaft übernehmen')); ?></a>
                <?php elseif ($sponsor_signup_ready) : ?>
                    <a class="btn btn-secondary btn-sm dog-card-inquiry-button" href="<?php echo esc_url(add_query_arg('sod_amount', 'full', $detail) . '#pate-werden'); ?>"><?php echo esc_html(self::t('dogcard.pate_werden_full_short', 'Patenschaft')); ?></a>
                    <a class="btn btn-secondary btn-sm dog-card-inquiry-button" href="<?php echo esc_url(add_query_arg('sod_amount', '5', $detail) . '#pate-werden'); ?>"><?php echo esc_html(self::t('dogcard.pate_werden_partial_from_five', 'Teilpatenschaft ab 5 €')); ?></a>
                <?php elseif ($mode === 'sponsorship' && $sponsorship_taken) : ?>
                    <span class="btn btn-ghost btn-sm dog-card-inquiry-button is-disabled" aria-disabled="true"><?php echo esc_html(self::t('dogcard.sponsorship_covered', 'Monatsversorgung gedeckt')); ?></span>
                <?php else : ?>
                    <a class="btn btn-secondary btn-sm dog-card-inquiry-button" href="<?php echo esc_url($contact); ?>"><?php echo esc_html($mode === 'sponsorship' ? self::t('dogcard.patenschaft_anfragen', 'Patenschaft anfragen') : self::t('dogcard.anfrage_stellen', 'Anfrage stellen')); ?></a>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    public static function dog_single_template(string $template): string
    {
        if (!is_singular('sod_dog')) {
            return $template;
        }
        $theme_template = locate_template(['single-sod_dog.php']);
        if ($theme_template !== '') {
            return $theme_template;
        }
        return plugin_dir_path(__FILE__) . 'templates/single-sod-dog.php';
    }

    public static function restrict_dog_page(): void
    {
        if (!is_singular('sod_dog')) {
            return;
        }
        $dog_id = get_queried_object_id();
        if (current_user_can('edit_post', $dog_id)) {
            return;
        }
        if (!self::dog_has_public_name($dog_id)) {
            if (get_post_meta($dog_id, 'sod_show_name_sponsorship', true) === '1') {
                return;
            }
            wp_safe_redirect(self::page_url('vermittlung'));
            exit;
        }
        if (get_post_meta($dog_id, 'sod_show_adoption', true) === '1' || get_post_meta($dog_id, 'sod_show_sponsorship', true) === '1') {
            return;
        }
        wp_safe_redirect(self::page_url('vermittlung'));
        exit;
    }

    public static function dog_detail_content(string $content): string
    {
        if (!is_singular('sod_dog') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $dog_id = get_the_ID();
        $status = (string)get_post_meta($dog_id, 'sod_status', true) ?: 'verfuegbar';
        $statuses = self::dog_statuses();
        $show_adoption = get_post_meta($dog_id, 'sod_show_adoption', true) === '1';
        $show_sponsorship = get_post_meta($dog_id, 'sod_show_sponsorship', true) === '1';
        $name_sponsorship_active = get_post_meta($dog_id, 'sod_show_name_sponsorship', true) === '1' && !self::dog_has_public_name($dog_id);
        $name_sponsor_credit = trim((string)get_post_meta($dog_id, 'sod_name_sponsor_credit', true));
        $name_sponsor_credit_visible = $name_sponsor_credit !== '' && self::dog_has_public_name($dog_id);
        $support = self::dog_support_amounts($dog_id);
        $sponsorship_amount_raw = self::money_plain($support['target']);
        $sponsorship_taken = $support['target'] > 0 && $support['remaining'] < 1;
        $sponsor_signup_ready = $show_sponsorship && $support['target'] > 0 && !$sponsorship_taken;
        $goal_reached = $show_sponsorship && $sponsorship_taken;
        $badge_class = $goal_reached ? 'ziel-erreicht' : $status;
        $badge_label = $goal_reached
            ? self::t('dogcard.status_ziel_erreicht', 'Danke ❤️ Ziel erreicht')
            : ($statuses[$status] ?? self::t('dogcard.status_verfuegbar', 'Verfügbar'));
        $image_ids = self::dog_image_ids($dog_id);
        $video_urls = self::dog_video_urls($dog_id);
        $video_url = (string)($video_urls[0] ?? '');
        $extra_videos = array_slice($video_urls, 1);
        $hero = '';
        if ($video_url !== '') {
            $hero = self::dog_video_embed($video_url, 'sod-dog-detail-video-frame', self::dog_poster_url($dog_id, 'large'));
        } elseif (has_post_thumbnail($dog_id)) {
            $hero = (string)get_the_post_thumbnail($dog_id, 'large', ['class' => 'sod-dog-detail-hero-img', 'loading' => 'eager']);
        } elseif ($image_ids) {
            $hero = (string)wp_get_attachment_image($image_ids[0], 'large', false, ['class' => 'sod-dog-detail-hero-img', 'loading' => 'eager']);
        }
        $thumbnail_id = (int)get_post_thumbnail_id($dog_id);
        $gallery_ids = $video_url !== '' ? $image_ids : array_values(array_filter($image_ids, static fn (int $id): bool => $id !== $thumbnail_id));

        ob_start();
        ?>
        <div class="sod-dog-detail">
            <div class="sod-dog-detail-hero">
                <?php echo $hero !== '' ? $hero : '<div class="sod-dog-detail-hero-img sod-dog-detail-placeholder">' . esc_html(self::t('dogcard.detail_placeholder', 'Hund')) . '</div>'; ?>
                <span class="badge badge-<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_label); ?></span>
            </div>
            <?php if ($gallery_ids || $extra_videos) : ?>
                <div class="sod-dog-detail-thumbs" data-sod-gallery aria-label="<?php echo esc_attr(self::tpl('dogcard.gallery_aria_tpl', 'Bilder von {name}', ['name' => self::dog_public_name($dog_id)])); ?>">
                    <?php self::dog_thumbnail_strip($gallery_ids, self::dog_public_name($dog_id), 'detail', $extra_videos); ?>
                </div>
            <?php endif; ?>
            <div class="sod-dog-detail-meta">
                <?php foreach (['sod_age' => self::t('dogcard.meta_alter', 'Alter'), 'sod_breed' => self::t('dogcard.meta_rasse_typ', 'Rasse / Typ'), 'sod_gender' => self::t('dogcard.meta_geschlecht', 'Geschlecht'), 'sod_weight' => self::t('dogcard.meta_gewicht', 'Gewicht'), 'sod_location' => self::t('dogcard.meta_aufenthaltsort', 'Aufenthaltsort'), 'sod_health' => self::t('dogcard.section_gesundheit', 'Gesundheit')] as $key => $label) : ?>
                    <?php $value = trim((string)get_post_meta($dog_id, $key, true)); ?>
                    <?php if ($value !== '') : ?>
                        <div class="sod-dog-detail-fact"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if (trim($content) !== '') : ?>
                <div class="sod-dog-detail-desc"><?php echo wp_kses_post(wpautop($content)); ?></div>
            <?php endif; ?>
            <?php foreach (['sod_character' => self::t('dogcard.section_charakter', 'Charakter'), 'sod_needs' => self::tpl('dogcard.detail_needs_title_tpl', 'Das braucht {name}', ['name' => get_the_title($dog_id)])] as $key => $label) : ?>
                <?php $value = trim((string)get_post_meta($dog_id, $key, true)); ?>
                <?php if ($value !== '') : ?>
                    <div class="sod-dog-detail-section"><h2><?php echo esc_html($label); ?></h2><p><?php echo nl2br(esc_html($value)); ?></p></div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($show_sponsorship) : ?>
                <?php $sponsorship_text = trim((string)get_post_meta($dog_id, 'sod_sponsorship_text', true)); ?>
                <div class="sod-dog-detail-section">
                    <h2><?php echo esc_html(self::t('dogcard.sponsorship_title', 'Patenschaft')); ?></h2>
                    <p><?php echo $sponsorship_text !== '' ? nl2br(esc_html($sponsorship_text)) : esc_html(self::t('dogcard.sponsorship_default_text', 'Mit einer Patenschaft hilfst du bei Futter, medizinischer Versorgung und Unterbringung.')); ?></p>
                    <?php if ($support['target'] > 0) : ?><p><strong><?php echo esc_html(self::tpl('dogcard.sponsorship_target_tpl', 'Monatsbedarf: {amount} €', ['amount' => $sponsorship_amount_raw])); ?></strong></p><?php endif; ?>
                    <?php if ($sponsorship_taken) : ?><p class="sod-sponsorship-taken-note"><?php echo esc_html(self::t('dogcard.sponsorship_covered_note', 'Die Monatsversorgung ist derzeit vollständig durch Patenschaften gedeckt.')); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($name_sponsorship_active) : ?>
                <?php
                $name_sponsorship_amount_value = self::numeric_quantity((string)get_post_meta($dog_id, 'sod_name_sponsorship_amount', true)) ?: 50.0;
                $name_sponsorship_amount = self::money_plain($name_sponsorship_amount_value);
                $name_sponsorship_text = trim((string)get_post_meta($dog_id, 'sod_name_sponsorship_text', true));
                $name_sponsorship_paypal_email = (string)get_option('sod_name_sponsorship_paypal_email', '') ?: (string)get_option('sod_paypal_email', '');
                $name_sponsorship_ipn_url = admin_url('admin-post.php?action=sod_paypal_ipn');
                $name_sponsorship_reference = self::tpl('dogcard.name_sponsorship_reference_tpl', 'Namenspatenschaft #{id}', ['id' => (string)$dog_id]);
                $name_sponsorship_bank = self::bank_details();
                $name_sponsorship_qr = self::amount_qr_data_uri($name_sponsorship_amount_value, $name_sponsorship_reference);
                ?>
                <div class="sod-dog-detail-section sod-name-sponsorship">
                    <h2><?php echo esc_html(self::t('dogcard.name_sponsorship_title', 'Namenspatenschaft')); ?></h2>
                    <p><?php echo $name_sponsorship_text !== '' ? nl2br(esc_html($name_sponsorship_text)) : esc_html(self::t('dogcard.name_sponsorship_default_text', 'Dieser Hund hat noch keinen Namen. Mit einer Namenspatenschaft schenkst du ihm seinen Namen und unterstützt gleichzeitig seine Versorgung.')); ?></p>
                    <p><strong><?php echo esc_html(self::tpl('dogcard.name_sponsorship_amount_tpl', 'Vorschlag: {amount} €', ['amount' => $name_sponsorship_amount])); ?></strong></p>
                    <div class="sod-donation-options sod-name-sponsorship-options">
                        <?php if ($name_sponsorship_paypal_email !== '') : ?>
                            <div class="sod-donation-card">
                                <h3><?php echo esc_html(self::t('dogcard.name_sponsorship_paypal_title', 'Mit PayPal bezahlen')); ?></h3>
                                <p><?php echo esc_html(self::t('dogcard.name_sponsorship_paypal_text', 'Schnell und direkt per Karte oder PayPal-Konto.')); ?></p>
                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" class="sod-name-sponsorship-form">
                                    <input type="hidden" name="cmd" value="_xclick">
                                    <input type="hidden" name="business" value="<?php echo esc_attr($name_sponsorship_paypal_email); ?>">
                                    <input type="hidden" name="item_name" value="<?php echo esc_attr('Namenspatenschaft - Hund #' . $dog_id); ?>">
                                    <input type="hidden" name="currency_code" value="EUR">
                                    <input type="hidden" name="no_shipping" value="1">
                                    <input type="hidden" name="no_note" value="1">
                                    <input type="hidden" name="charset" value="UTF-8">
                                    <input type="hidden" name="notify_url" value="<?php echo esc_attr($name_sponsorship_ipn_url); ?>">
                                    <input type="hidden" name="custom" value="<?php echo esc_attr('sod_name_sponsorship:' . $dog_id); ?>">
                                    <input type="hidden" name="amount" value="<?php echo esc_attr($name_sponsorship_amount); ?>">
                                    <p class="sod-name-sponsorship-name-field">
                                        <label for="sod-name-suggestion-<?php echo esc_attr((string)$dog_id); ?>"><?php echo esc_html(self::t('dogcard.name_sponsorship_name_label', 'Dein Namensvorschlag (optional)')); ?></label>
                                        <input type="text" id="sod-name-suggestion-<?php echo esc_attr((string)$dog_id); ?>" name="sod_name_suggestion_input" maxlength="60" class="regular-text" placeholder="<?php echo esc_attr(self::t('dogcard.name_sponsorship_name_placeholder', 'z. B. Luna')); ?>">
                                    </p>
                                    <button class="btn btn-primary btn-md" type="submit"><?php echo esc_html(self::t('dogcard.name_sponsorship_btn', 'Namenspatenschaft übernehmen')); ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <div class="sod-donation-card">
                            <h3><?php echo esc_html(self::t('dogcard.name_sponsorship_qr_title', 'Per Banking-App scannen')); ?></h3>
                            <p><?php echo esc_html(self::t('dogcard.name_sponsorship_qr_text', 'Betrag und Verwendungszweck sind im QR-Code bereits ausgefüllt.')); ?></p>
                            <?php if ($name_sponsorship_qr !== '') : ?>
                                <img class="sod-donation-qr" src="<?php echo esc_attr($name_sponsorship_qr); ?>" alt="<?php echo esc_attr(self::t('dogcard.name_sponsorship_qr_alt', 'QR-Code für Namenspatenschaft')); ?>" width="180" height="180" loading="lazy">
                            <?php else : ?>
                                <img class="sod-donation-qr" src="<?php echo esc_attr(self::epc_qr_url()); ?>" alt="<?php echo esc_attr(self::t('dogcard.name_sponsorship_qr_alt', 'QR-Code für Namenspatenschaft')); ?>" width="180" height="180" loading="lazy">
                            <?php endif; ?>
                            <p class="sod-donation-qr-note">
                                <?php echo esc_html(self::t('spenden.qr_kontoinhaber', 'Kontoinhaber')); ?>: <?php echo esc_html($name_sponsorship_bank['name']); ?><br>
                                IBAN: <?php echo esc_html($name_sponsorship_bank['iban']); ?> · BIC: <?php echo esc_html($name_sponsorship_bank['bic']); ?><br>
                                <?php echo esc_html(self::t('dogcard.name_sponsorship_reference_label', 'Verwendungszweck')); ?>: <?php echo esc_html($name_sponsorship_reference); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php elseif ($name_sponsor_credit_visible) : ?>
                <div class="sod-dog-detail-section sod-name-sponsorship sod-name-sponsorship-done">
                    <p><?php echo esc_html($name_sponsor_credit); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($show_sponsorship) { echo self::render_sponsor_wall($dog_id); } ?>
            <?php self::dog_support_progress($dog_id, 'detail'); ?>
            <div class="sod-dog-detail-cta">
                <?php if ($show_adoption) : ?>
                    <a class="btn btn-primary btn-md" href="<?php echo esc_url(self::dog_contact_url($dog_id, 'Vermittlung')); ?>"><?php echo esc_html(self::t('dogcard.anfrage_stellen', 'Anfrage stellen')); ?></a>
                <?php endif; ?>
                <?php if ($show_sponsorship && $sponsor_signup_ready) : ?>
                    <a class="btn <?php echo $show_adoption ? 'btn-secondary' : 'btn-primary'; ?> btn-md" href="<?php echo esc_url(add_query_arg('sod_amount', 'full', get_permalink($dog_id)) . '#pate-werden'); ?>"><?php echo esc_html(self::tpl('dogcard.pate_werden_full_tpl', 'Patenschaft – {amount} € monatlich', ['amount' => self::money_plain($support['remaining'])])); ?></a>
                    <a class="btn btn-secondary btn-md" href="<?php echo esc_url(add_query_arg('sod_amount', '5', get_permalink($dog_id)) . '#pate-werden'); ?>"><?php echo esc_html(self::t('dogcard.pate_werden_partial_from_five', 'Teilpatenschaft ab 5 €')); ?></a>
                <?php elseif ($show_sponsorship && $sponsorship_taken) : ?>
                    <span class="btn btn-ghost btn-md is-disabled" aria-disabled="true"><?php echo esc_html(self::t('dogcard.sponsorship_covered', 'Monatsversorgung gedeckt')); ?></span>
                <?php elseif ($show_sponsorship) : ?>
                    <a class="btn <?php echo $show_adoption ? 'btn-secondary' : 'btn-primary'; ?> btn-md" href="<?php echo esc_url(self::dog_contact_url($dog_id, 'Patenschaft')); ?>"><?php echo esc_html(self::t('dogcard.patenschaft_anfragen', 'Patenschaft anfragen')); ?></a>
                <?php endif; ?>
                <a class="btn btn-ghost btn-md" href="<?php echo esc_url(self::page_url($show_adoption ? 'vermittlung' : 'patenschaft')); ?>"><?php echo esc_html(self::t('dogcard.zurueck_zur_uebersicht', 'Zurück zur Übersicht')); ?></a>
            </div>
            <?php if ($sponsor_signup_ready) : ?>
                <?php echo self::render_sponsor_signup_form($dog_id); ?>
            <?php endif; ?>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    private static function dog_contact_url(int $dog_id, string $interest): string
    {
        return add_query_arg([
            'sod_dog_id' => $dog_id,
            'sod_dog_name' => self::dog_public_name($dog_id),
            'sod_interest' => $interest,
        ], self::page_url('kontakt')) . '#anfrageformular';
    }

    private static function dog_public_name(int $dog_id): string
    {
        $title = trim((string)get_the_title($dog_id));
        return $title !== '' ? $title : self::t('dogcard.hund_in_pruefung', 'Hund in Prüfung');
    }

    private static function dog_has_public_name(int $dog_id): bool
    {
        return trim((string)get_the_title($dog_id)) !== '';
    }

    /**
     * Videos werden als eine Adresse pro Zeile gespeichert. Die erste Zeile ist das
     * Hauptvideo, alle weiteren erscheinen in der Bildauswahl und starten erst auf Klick.
     */
    private static function dog_video_urls(int $dog_id): array
    {
        return self::sanitize_url_lines((string)get_post_meta($dog_id, 'sod_video_url', true));
    }

    private static function sanitize_url_lines(string $raw): array
    {
        $urls = [];
        foreach (preg_split('/[\r\n]+/', $raw) ?: [] as $line) {
            $url = esc_url_raw(trim($line));
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    private static function dog_video_url(int $dog_id): string
    {
        return (string)(self::dog_video_urls($dog_id)[0] ?? '');
    }

    private static function is_direct_video_url(string $url): bool
    {
        $extension = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($extension, ['mp4', 'webm', 'ogg', 'ogv'], true);
    }

    private static function dog_poster_url(int $dog_id, string $size = 'large'): string
    {
        if (has_post_thumbnail($dog_id)) {
            return (string)(get_the_post_thumbnail_url($dog_id, $size) ?: '');
        }
        $image_ids = self::dog_image_ids($dog_id);
        if ($image_ids) {
            return (string)(wp_get_attachment_image_url($image_ids[0], $size) ?: '');
        }
        return '';
    }

    private static function dog_video_embed(string $url, string $class, string $poster = ''): string
    {
        $extension = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($extension, ['mp4', 'webm', 'ogg', 'ogv'], true)) {
            $type = $extension === 'webm' ? 'video/webm' : ($extension === 'ogg' || $extension === 'ogv' ? 'video/ogg' : 'video/mp4');
            return sprintf(
                '<video class="%1$s" controls playsinline autoplay muted loop preload="metadata"%2$s><source src="%3$s" type="%4$s">%5$s</video>',
                esc_attr($class),
                $poster !== '' ? ' poster="' . esc_url($poster) . '"' : '',
                esc_url($url),
                esc_attr($type),
                esc_html(self::t('dogcard.video_fallback', 'Dein Browser kann dieses Video nicht abspielen.'))
            );
        }

        $embed = wp_oembed_get($url);
        if (is_string($embed) && trim($embed) !== '') {
            return '<div class="' . esc_attr($class . ' sod-video-consent') . '">'
                . '<p>' . esc_html(self::t('dogcard.video_external_consent', 'Dieses Video wird von einem externen Anbieter geladen. Erst nach deinem Klick wird eine Verbindung zum Anbieter hergestellt.')) . '</p>'
                . '<button class="btn btn-secondary btn-md sod-video-consent-load" type="button">'
                . esc_html(self::t('dogcard.video_external_load', 'Externes Video laden'))
                . '</button><template class="sod-video-embed-template">' . $embed . '</template></div>';
        }

        return '<a class="' . esc_attr($class . ' sod-video-link') . '" href="' . esc_url($url) . '" target="_blank" rel="noopener">Video öffnen</a>';
    }

    private static function dog_thumbnail_strip(array $image_ids, string $dog_name, string $context = 'card', array $video_urls = []): void
    {
        if (!$image_ids && !$video_urls) {
            return;
        }
        $class = $context === 'detail' ? 'sod-dog-thumb-strip sod-dog-thumb-strip-detail' : 'sod-dog-thumb-strip';
        echo '<div class="' . esc_attr($class) . '">';
        foreach (array_slice($video_urls, 0, 4) as $video_index => $video_url) {
            $label = self::tpl('dogcard.thumb_video_aria_tpl', 'Weiteres Video von {name} abspielen ({n})', [
                'name' => $dog_name,
                'n' => (string)($video_index + 1),
            ]);
            if (!self::is_direct_video_url($video_url)) {
                // Externe Anbieter nicht in die Lightbox holen - der Aufruf soll bewusst
                // erst nach einem Klick und dann beim Anbieter selbst passieren.
                printf(
                    '<a class="sod-dog-thumb-video-link" href="%1$s" target="_blank" rel="noopener" aria-label="%2$s"><span class="sod-dog-thumb-video"><span class="sod-dog-thumb-play" aria-hidden="true">&#9654;</span></span></a>',
                    esc_url($video_url),
                    esc_attr($label)
                );
                continue;
            }
            printf(
                '<a class="sod-dog-gallery-link sod-dog-thumb-video-link" href="%1$s" data-sod-full="%1$s" data-sod-type="video" data-sod-caption="%2$s" aria-label="%3$s"><span class="sod-dog-thumb-video"><span class="sod-dog-thumb-play" aria-hidden="true">&#9654;</span></span></a>',
                esc_url($video_url),
                esc_attr($dog_name),
                esc_attr($label)
            );
        }
        foreach (array_slice($image_ids, 0, 6) as $index => $image_id) {
            $full = (string)wp_get_attachment_image_url($image_id, 'full');
            if ($full === '') {
                continue;
            }
            printf(
                '<a class="sod-dog-gallery-link" href="%1$s" data-sod-full="%1$s" data-sod-caption="%2$s" aria-label="%3$s">%4$s</a>',
                esc_url($full),
                esc_attr($dog_name),
                esc_attr(self::tpl('dogcard.thumb_aria_tpl', '{name} Bild {n} groß ansehen', ['name' => $dog_name, 'n' => (string)($index + 1)])),
                wp_get_attachment_image($image_id, 'thumbnail', false, ['loading' => 'lazy'])
            );
        }
        echo '</div>';
    }

    private static function dog_active_sponsorship_amount(int $dog_id, int $exclude_id = 0): float
    {
        if ($dog_id <= 0) {
            return 0.0;
        }
        $query = [
            'post_type'      => 'sod_sponsor',
            'post_status'    => ['publish', 'private'],
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'sod_sponsor_dog', 'value' => (string)$dog_id],
                ['key' => 'sod_sponsor_status', 'value' => 'aktiv'],
            ],
        ];
        if ($exclude_id > 0) {
            $query['post__not_in'] = [$exclude_id];
        }
        $amount = 0.0;
        foreach (get_posts($query) as $sponsor_id) {
            $amount += self::money_number((string)get_post_meta((int)$sponsor_id, 'sod_sponsor_amount', true));
        }
        return $amount;
    }

    private static function dog_support_amounts(int $dog_id, int $exclude_id = 0): array
    {
        $target = self::money_number((string)get_post_meta($dog_id, 'sod_monthly_food_need', true));
        if ($target <= 0) {
            $target = self::money_number((string)get_post_meta($dog_id, 'sod_sponsorship_amount', true));
        }
        $manual = self::money_number((string)get_post_meta($dog_id, 'sod_monthly_food_secured', true));
        $automatic = self::dog_active_sponsorship_amount($dog_id, $exclude_id);
        $secured = min($target, max(0.0, $manual + $automatic));
        return [
            'target' => $target,
            'manual' => $manual,
            'automatic' => $automatic,
            'secured' => $secured,
            'remaining' => max(0.0, $target - $secured),
        ];
    }

    private static function dog_support_progress(int $dog_id, string $context = 'card'): void
    {
        $support = self::dog_support_amounts($dog_id);
        $need = $support['target'];
        $secured = $support['secured'];
        if ($need <= 0) {
            return;
        }
        $percent = min(100, max(0, (int)round(($secured / $need) * 100)));
        $remaining = max(0, $need - $secured);
        $class = $context === 'detail' ? 'sod-support-progress sod-support-progress-detail' : 'sod-support-progress';
        $remaining_text = $remaining > 0
            ? self::tpl('dogcard.support_progress_remaining_tpl', 'Noch {amount} € offen', ['amount' => self::money_plain($remaining)])
            : self::t('dogcard.support_progress_covered', 'Versorgung für diesen Monat gedeckt');
        printf(
            '<div class="%1$s"><div class="sod-support-progress-head"><strong>%2$s</strong><span>%3$s</span></div><div class="sod-support-bar" aria-label="%4$s"><span style="width:%5$d%%"></span></div><p>%6$s</p></div>',
            esc_attr($class),
            esc_html(self::t('dogcard.support_progress_title', 'Monatsversorgung')),
            esc_html(self::tpl('dogcard.support_progress_percent_tpl', '{percent}% gesichert', ['percent' => (string)$percent])),
            esc_attr(self::tpl('dogcard.support_progress_aria_tpl', '{percent} Prozent der Monatsversorgung gesichert', ['percent' => (string)$percent])),
            $percent,
            esc_html(self::tpl('dogcard.support_progress_text_tpl', '{secured} von {need} € sind gesichert. {remaining}.', [
                'secured' => self::money_plain($secured),
                'need' => self::money_plain($need),
                'remaining' => $remaining_text,
            ]))
        );
    }

    private static function money_number(string $value): float
    {
        $value = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $value));
        return is_numeric($value) ? max(0.0, (float)$value) : 0.0;
    }

    private static function money_plain(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return (string)(int)round($value);
        }
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    public static function application_form_shortcode(): string
    {
        $dog_id = absint($_GET['sod_dog_id'] ?? ($_GET['dog'] ?? 0));
        $dog_name = sanitize_text_field((string)($_GET['sod_dog_name'] ?? ($_GET['name'] ?? '')));
        if ($dog_name === '' && $dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
            $dog_name = get_the_title($dog_id);
        }
        $interest = sanitize_text_field((string)($_GET['sod_interest'] ?? ($_GET['interest'] ?? 'Vermittlung')));
        $t = static function (string $key, string $fallback): string {
            return function_exists('sod_t') ? sod_t($key) : $fallback;
        };
        $interest_options = [
            'Vermittlung' => $t('kontakt.form_interest_vermittlung', 'Vermittlung / Adoption'),
            'Patenschaft' => $t('kontakt.form_interest_patenschaft', 'Patenschaft'),
            'Sachspende' => $t('kontakt.form_interest_sachspende', 'Sachspende'),
            'Allgemeine Anfrage' => $t('kontakt.form_interest_allgemein', 'Allgemeine Anfrage'),
        ];
        if (!isset($interest_options[$interest])) {
            $interest = 'Vermittlung';
        }
        $started_at = time();
        $notice = sanitize_text_field((string)($_GET['sod_status'] ?? ''));
        ob_start();
        ?>
        <?php if ($notice === 'gesendet') : ?>
            <div class="sod-sponsor-signup-notice sod-sponsor-signup-notice-success">
                <strong><?php echo esc_html($t('kontakt.form_sent_title', 'Wir haben deine Anfrage erhalten.')); ?></strong>
                <span><?php echo esc_html($t('kontakt.form_sent_text', 'Danke! Wir melden uns so bald wie möglich persönlich bei dir.')); ?></span>
            </div>
        <?php endif; ?>
        <div class="sod-application-note">
            <strong><?php echo esc_html($t('kontakt.form_note_title', 'Deine Anfrage ist unverbindlich.')); ?></strong>
            <span><?php echo esc_html($t('kontakt.form_note_text', 'Wir melden uns persönlich bei dir und speichern Anfragen maximal 30 Tage.')); ?></span>
        </div>
        <form id="anfrageformular" class="sod-application-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="sod_application">
            <?php wp_nonce_field('sod_public_application', 'sod_application_nonce'); ?>
            <input type="hidden" name="started_at" value="<?php echo esc_attr((string)$started_at); ?>">
            <input type="hidden" name="form_token" value="<?php echo esc_attr(self::application_form_token($started_at)); ?>">
            <input type="hidden" name="dog_id" value="<?php echo esc_attr((string)$dog_id); ?>">
            <p class="sod-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
            <div class="grid-2">
                <p><label class="form-label"><?php echo esc_html($t('kontakt.form_vorname', 'Vorname')); ?><input class="form-input" name="first_name" maxlength="80" autocomplete="given-name"></label></p>
                <p><label class="form-label"><?php echo esc_html($t('kontakt.form_nachname', 'Nachname')); ?><input class="form-input" name="last_name" maxlength="80" autocomplete="family-name"></label></p>
            </div>
            <p><label class="form-label"><?php echo esc_html($t('kontakt.form_email', 'E-Mail')); ?> <span class="req">*</span><input class="form-input" type="email" name="email" maxlength="160" required autocomplete="email"></label></p>
            <p><label class="form-label"><?php echo esc_html($t('kontakt.form_telefon', 'Telefon')); ?><input class="form-input" name="phone" maxlength="60" autocomplete="tel"></label></p>
            <p><label class="form-label"><?php echo esc_html($t('kontakt.form_interesse', 'Ich interessiere mich für')); ?><select class="form-select" name="interest" data-sod-interest-select>
                <?php foreach ($interest_options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($interest, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></label></p>
            <p data-sod-adoption-only<?php echo $interest === 'Vermittlung' ? '' : ' hidden'; ?>><label class="form-label"><?php echo esc_html($t('kontakt.form_hund', 'Hund')); ?><input class="form-input" name="dog_name" maxlength="120" value="<?php echo esc_attr($dog_name); ?>" placeholder="<?php echo esc_attr($t('kontakt.form_hund_placeholder', 'Name des Hundes')); ?>"></label></p>
            <p data-sod-adoption-only<?php echo $interest === 'Vermittlung' ? '' : ' hidden'; ?>><label class="form-label"><?php echo esc_html($t('kontakt.form_wohnort', 'Wohnort / Umgebung')); ?><input class="form-input" name="address" maxlength="220"></label></p>
            <p data-sod-adoption-only<?php echo $interest === 'Vermittlung' ? '' : ' hidden'; ?>><label class="form-label"><?php echo esc_html($t('kontakt.form_erfahrung', 'Erfahrung mit Hunden')); ?><textarea class="form-textarea" name="experience" rows="3" maxlength="1500"></textarea></label></p>
            <p><label class="form-label"><?php echo esc_html($t('kontakt.form_nachricht', 'Nachricht')); ?><textarea class="form-textarea" name="message" rows="5" maxlength="2500"></textarea></label></p>
            <p><label style="display:flex;gap:12px;align-items:flex-start;"><input type="checkbox" name="consent" value="1" required> <span><?php echo self::privacy_consent_html($t('kontakt.form_consent', 'Ich habe die Datenschutzerklärung zur Kenntnis genommen.')); ?></span></label></p>
            <button class="btn btn-primary btn-md" type="submit"><?php echo esc_html($t('kontakt.form_submit', 'Nachricht senden')); ?></button>
        </form>
        <?php
        return (string)ob_get_clean();
    }

    private static function render_sponsor_signup_form(int $dog_id): string
    {
        $support = self::dog_support_amounts($dog_id);
        if ($support['target'] <= 0 || $support['remaining'] < 1) {
            return '';
        }
        $remaining_raw = self::money_plain($support['remaining']);
        $minimum_amount = min(5.0, $support['remaining']);
        $minimum_amount_raw = self::money_plain($minimum_amount);
        $requested_default = sanitize_text_field((string)($_GET['sod_amount'] ?? ''));
        $default_amount = $requested_default === 'full'
            ? $support['remaining']
            : min(10.0, $support['remaining']);
        if (is_numeric(str_replace(',', '.', $requested_default))) {
            $default_amount = min($support['remaining'], max($minimum_amount, (float)str_replace(',', '.', $requested_default)));
        }
        $default_amount_raw = self::money_plain($default_amount);
        $dog_name = self::dog_public_name($dog_id);
        $started_at = time();
        $notice = sanitize_text_field((string)($_GET['patenschaft'] ?? ''));
        ob_start();
        ?>
        <div id="pate-werden" class="sod-sponsor-signup">
            <?php if ($notice === 'danke') : ?>
                <div class="sod-sponsor-signup-notice sod-sponsor-signup-notice-success">
                    <?php echo esc_html(self::t('dogcard.signup_thanks', 'Danke! Deine Patenschaft ist sofort vorläufig aktiv. Dein Zertifikat kommt automatisch per E-Mail, sobald PayPal die Zahlung bestätigt hat.')); ?>
                </div>
            <?php elseif ($notice === 'abgebrochen') : ?>
                <div class="sod-sponsor-signup-notice sod-sponsor-signup-notice-info">
                    <?php echo esc_html(self::t('dogcard.signup_cancelled', 'Du hast den Vorgang bei PayPal abgebrochen. Du kannst jederzeit erneut Pate werden.')); ?>
                </div>
            <?php elseif ($notice === 'spam') : ?>
                <div class="sod-sponsor-signup-notice sod-sponsor-signup-notice-error">
                    <?php echo esc_html(self::t('dogcard.signup_error', 'Deine Anmeldung konnte nicht verarbeitet werden. Bitte versuche es erneut.')); ?>
                </div>
            <?php endif; ?>
            <h2><?php echo esc_html(self::t('dogcard.signup_title', 'Pate werden')); ?></h2>
            <p><?php echo esc_html(self::tpl('dogcard.signup_lead_partial_tpl', 'Übernimm die volle monatliche Patenschaft oder eine Teilpatenschaft ab 5 € für {name}. Jeder Beitrag hilft und ist jederzeit kündbar.', ['name' => $dog_name])); ?></p>
            <form class="sod-application-form sod-sponsor-signup-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="sod_sponsor_signup">
                <?php wp_nonce_field('sod_public_sponsor_signup', 'sod_sponsor_signup_nonce'); ?>
                <input type="hidden" name="started_at" value="<?php echo esc_attr((string)$started_at); ?>">
                <input type="hidden" name="form_token" value="<?php echo esc_attr(self::sponsor_signup_form_token($started_at)); ?>">
                <input type="hidden" name="dog_id" value="<?php echo esc_attr((string)$dog_id); ?>">
                <p class="sod-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
                <div class="grid-2">
                    <p><label class="form-label"><?php echo esc_html(self::t('kontakt.form_vorname', 'Vorname')); ?><input class="form-input" name="first_name" maxlength="80" autocomplete="given-name" required></label></p>
                    <p><label class="form-label"><?php echo esc_html(self::t('kontakt.form_nachname', 'Nachname')); ?><input class="form-input" name="last_name" maxlength="80" autocomplete="family-name" required></label></p>
                </div>
                <p><label class="form-label"><?php echo esc_html(self::t('kontakt.form_email', 'E-Mail')); ?> <span class="req">*</span><input class="form-input" type="email" name="email" maxlength="160" required autocomplete="email"></label></p>
                <p><label class="form-label"><?php echo esc_html(self::t('kontakt.form_telefon', 'Telefon')); ?><input class="form-input" name="phone" maxlength="60" autocomplete="tel"></label></p>
                <div class="sod-sponsor-amount-card">
                    <label class="form-label" for="sod-sponsor-amount"><?php echo esc_html(self::t('dogcard.signup_amount_label', 'Dein monatlicher Patenschaftsbetrag')); ?></label>
                    <div class="sod-sponsor-amount-input">
                        <input class="form-input" id="sod-sponsor-amount" type="number" name="sponsor_amount" min="<?php echo esc_attr(number_format($minimum_amount, 2, '.', '')); ?>" max="<?php echo esc_attr(number_format($support['remaining'], 2, '.', '')); ?>" step="1" value="<?php echo esc_attr($default_amount_raw); ?>" required inputmode="decimal">
                        <span>€</span>
                    </div>
                    <p><?php echo esc_html(self::tpl('dogcard.signup_amount_hint_tpl', 'Noch {amount} € pro Monat werden benötigt. Eine Teilpatenschaft ist ab {minimum} € möglich; mit dem gesamten offenen Betrag übernimmst du die volle Patenschaft.', ['amount' => $remaining_raw, 'minimum' => $minimum_amount_raw])); ?></p>
                </div>
                <fieldset class="sod-sponsor-payment-method">
                    <legend class="form-label"><?php echo esc_html(self::t('dogcard.signup_payment_method_label', 'Zahlungsart')); ?></legend>
                    <label class="sod-sponsor-payment-option">
                        <input type="radio" name="payment_method" value="paypal">
                        <span><strong><?php echo esc_html(self::t('dogcard.signup_payment_paypal_title', 'PayPal-Abo')); ?></strong><br><?php echo esc_html(self::t('dogcard.signup_payment_paypal_text', 'Automatisch, sofort aktiv, jederzeit in PayPal kündbar.')); ?></span>
                    </label>
                    <label class="sod-sponsor-payment-option">
                        <input type="radio" name="payment_method" value="dauerauftrag" checked>
                        <span><strong><?php echo esc_html(self::t('dogcard.signup_payment_bank_title', 'Dauerauftrag (Banküberweisung)')); ?></strong><br><?php echo esc_html(self::t('dogcard.signup_payment_bank_text', 'Du richtest bei deiner Bank einen monatlichen Dauerauftrag ein — kein PayPal-Konto nötig.')); ?></span>
                    </label>
                </fieldset>
                <fieldset class="sod-sponsor-photo-optin">
                    <legend class="form-label"><?php echo esc_html(self::t('dogcard.signup_photo_legend', 'Als Pate auf der Hundeseite erscheinen (optional)')); ?></legend>
                    <label class="sod-sponsor-photo-optin-check" style="display:flex;gap:12px;align-items:flex-start;">
                        <input type="checkbox" name="show_publicly" value="1">
                        <span><?php echo esc_html(self::tpl('dogcard.signup_photo_optin_tpl', 'Ich willige ein, mit meinem Vornamen und Foto öffentlich als Pate/Patin von {name} zu erscheinen. Die Einwilligung ist freiwillig und jederzeit widerrufbar.', ['name' => $dog_name])); ?></span>
                    </label>
                    <p><label class="form-label"><?php echo esc_html(self::t('dogcard.signup_photo_label', 'Dein Foto (JPG, PNG oder WEBP, max. 6 MB)')); ?><input class="form-input" type="file" name="sponsor_photo" accept="image/jpeg,image/png,image/webp"></label></p>
                    <p class="description" style="font-size:.85rem;opacity:.75;"><?php echo esc_html(self::t('dogcard.signup_photo_note', 'Dein Foto erscheint erst nach kurzer Prüfung durch unser Team. Du kannst die Anzeige jederzeit widerrufen.')); ?></p>
                </fieldset>
                <p><label style="display:flex;gap:12px;align-items:flex-start;"><input type="checkbox" name="consent" value="1" required> <span><?php echo self::privacy_consent_html(self::t('kontakt.form_consent', 'Ich habe die Datenschutzerklärung zur Kenntnis genommen.')); ?></span></label></p>
                <button class="btn btn-primary btn-md" type="submit"><?php echo esc_html(self::t('dogcard.signup_submit_partial', 'Weiter zur monatlichen Patenschaft')); ?></button>
            </form>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    private static function privacy_consent_html(string $text): string
    {
        $escaped = esc_html($text);
        $url = esc_url(self::page_url('datenschutz'));
        foreach (['Datenschutzerklärung', 'privacy policy', 'Privacy Policy', 'pravila o zaštiti podataka', 'politiku privatnosti'] as $label) {
            $escaped_label = esc_html($label);
            if (str_contains($escaped, $escaped_label)) {
                return preg_replace(
                    '/' . preg_quote($escaped_label, '/') . '/',
                    '<a href="' . $url . '" target="_blank" rel="noopener">' . $escaped_label . '</a>',
                    $escaped,
                    1
                ) ?: $escaped;
            }
        }
        return $escaped . ' <a href="' . $url . '" target="_blank" rel="noopener">' . esc_html('Datenschutzerklärung') . '</a>';
    }

    /**
     * Alle organisationsspezifischen Angaben an EINER Stelle. Bewusst als
     * WordPress-Optionen statt fest im Code: so laesst sich das Projekt ohne
     * Code-Aenderung fuer eine andere Organisation verwenden. Leere Felder
     * fallen auf neutrale Platzhalter zurueck, damit nirgends fremde Daten
     * stehenbleiben.
     */
    private static function org(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $fallbacks = [
            'name' => 'Mein Tierschutzverein',
            'registration' => '',
            'street' => '',
            'zip' => '',
            'city' => '',
            'email' => (string)get_option('admin_email'),
            'phone' => '',
            'iban' => '',
            'bic' => '',
            'account_holder' => '',
            'donation_url' => '',
            'chairperson' => '',
            'support_email' => (string)get_option('admin_email'),
        ];
        $cache = [];
        foreach ($fallbacks as $key => $fallback) {
            $value = trim((string)get_option('sod_org_' . $key, ''));
            $cache[$key] = $value !== '' ? $value : $fallback;
        }
        return $cache;
    }

    /** Anschrift einzeilig, leere Bestandteile werden ausgelassen. */
    private static function org_address_line(): string
    {
        $org = self::org();
        $city = trim($org['zip'] . ' ' . $org['city']);
        return implode(', ', array_filter([$org['street'], $city]));
    }

    /** "Name  .  Registernummer 1234" - ohne Nummer nur der Name. */
    private static function org_footer_line(): string
    {
        $org = self::org();
        return $org['registration'] !== ''
            ? $org['name'] . '  ' . chr(183) . '  ' . $org['registration']
            : $org['name'];
    }

    private static function bank_details(): array
    {
        $org = self::org();
        return [
            'name' => $org['account_holder'] !== '' ? $org['account_holder'] : $org['name'],
            'iban' => $org['iban'],
            'bic' => $org['bic'],
        ];
    }

    private static function epc_qr_url(): string
    {
        return plugins_url('assets/images/girocode-spende.png', __FILE__);
    }

    private static function build_epc_qr_payload(float $amount, string $reference): string
    {
        $bank = self::bank_details();
        $lines = [
            'BCD',
            '002',
            '1',
            'SCT',
            $bank['bic'],
            mb_substr($bank['name'], 0, 70),
            str_replace(' ', '', $bank['iban']),
            'EUR' . number_format($amount, 2, '.', ''),
            '',
            '',
            mb_substr($reference, 0, 140),
        ];
        return implode("\n", $lines);
    }

    private static function amount_qr_data_uri(float $amount, string $reference): string
    {
        $payload = self::build_epc_qr_payload($amount, $reference);
        $response = wp_remote_get('https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => '220x220',
            'ecc' => 'M',
            'data' => $payload,
        ]), ['timeout' => 8]);

        if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = (string)wp_remote_retrieve_body($response);
        if ($body === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($body);
    }

    public static function donation_options_shortcode(): string
    {
        $bank = self::bank_details();
        $paypal_email = (string)get_option('sod_paypal_email', '');
        $ipn_url = admin_url('admin-post.php?action=sod_paypal_ipn');
        $t = static function (string $key, string $fallback): string {
            return function_exists('sod_t') ? sod_t($key) : $fallback;
        };
        ob_start();
        ?>
        <div class="sod-donation-options">
            <?php if ($paypal_email !== '') : ?>
                <div class="sod-donation-card">
                    <h3><?php echo esc_html($t('spenden.paypal_title', 'Online spenden (PayPal)')); ?></h3>
                    <p><?php echo esc_html($t('spenden.paypal_text', 'Schnell und direkt per Karte oder PayPal-Konto.')); ?></p>
                    <form id="sod-paypal-form" action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                        <input type="hidden" name="cmd" value="_xclick">
                        <input type="hidden" name="business" value="<?php echo esc_attr($paypal_email); ?>">
                        <input type="hidden" name="item_name" value="<?php echo esc_attr('Spende: ' . self::org()['name']); ?>">
                        <input type="hidden" name="currency_code" value="EUR">
                        <input type="hidden" name="no_shipping" value="1">
                        <input type="hidden" name="no_note" value="1">
                        <input type="hidden" name="charset" value="UTF-8">
                        <input type="hidden" name="notify_url" value="<?php echo esc_attr($ipn_url); ?>">
                        <input type="hidden" name="amount" id="sod-paypal-amount" value="">
                        <button class="btn btn-primary btn-md" type="submit"><?php echo esc_html($t('spenden.paypal_btn', 'Mit PayPal spenden')); ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <div class="sod-donation-card" id="sod-bank-qr">
                <h3><?php echo esc_html($t('spenden.qr_title', 'Per Banking-App scannen')); ?></h3>
                <p><?php echo esc_html($t('spenden.qr_text', 'QR-Code mit der Banking-App scannen — Kontodaten werden automatisch übernommen, Betrag frei wählbar.')); ?></p>
                <img class="sod-donation-qr" src="<?php echo esc_attr(self::epc_qr_url()); ?>" alt="QR-Code für Spende per Banküberweisung" width="180" height="180" loading="lazy">
                <p class="sod-donation-qr-note"><?php echo esc_html($t('spenden.qr_kontoinhaber', 'Kontoinhaber')); ?>: <?php echo esc_html($bank['name']); ?><br>IBAN: <?php echo esc_html($bank['iban']); ?> · BIC: <?php echo esc_html($bank['bic']); ?></p>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    public static function current_needs_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'limit' => 5,
        ], (array)$atts, 'sod_current_needs');
        $limit = max(1, min(8, absint($atts['limit'])));
        $rows = self::inventory_rows('', '', '');
        $needs = array_values(array_filter($rows, static function (array $row): bool {
            return $row['status'] === 'publish' && $row['min_number'] > 0 && $row['total_number'] <= $row['min_number'];
        }));

        usort($needs, static function (array $a, array $b): int {
            $a_gap = $a['min_number'] - $a['total_number'];
            $b_gap = $b['min_number'] - $b['total_number'];
            if ($a_gap === $b_gap) {
                return strcasecmp($a['title'], $b['title']);
            }
            return $b_gap <=> $a_gap;
        });

        $needs = array_slice($needs, 0, $limit);
        $t = static function (string $key, string $fallback): string {
            return function_exists('sod_t') ? sod_t($key) : $fallback;
        };
        ob_start();
        ?>
        <section class="sod-current-needs" aria-labelledby="sod-current-needs-title">
            <div class="sod-current-needs-head">
                <p><?php echo esc_html($t('spenden.needs_eyebrow', 'Aus der Verwaltung')); ?></p>
                <h3 id="sod-current-needs-title"><?php echo esc_html($t('spenden.needs_title', 'Aktuell gebraucht')); ?></h3>
                <span><?php echo esc_html($t('spenden.needs_sub', 'Nur gepflegte Lagerdaten werden hier angezeigt.')); ?></span>
            </div>
            <?php if ($needs) : ?>
                <div class="sod-current-needs-grid">
                    <?php foreach ($needs as $row) : ?>
                        <article class="sod-need-card">
                            <strong><?php echo esc_html($row['title']); ?></strong>
                            <span><?php echo esc_html($row['category'] !== '' ? $row['category'] : $t('spenden.needs_fallback_category', 'Bedarf')); ?></span>
                            <p>
                                <?php echo esc_html($t('spenden.needs_verfuegbar', 'Verfügbar')); ?>: <?php echo esc_html($row['available_label']); ?>
                                <?php if ($row['total_label'] !== $row['available_label']) : ?>
                                    <br><?php echo esc_html($t('spenden.needs_gesamt', 'Gesamt')); ?>: <?php echo esc_html($row['total_label']); ?>
                                <?php endif; ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sod-current-needs-empty"><?php echo esc_html($t('spenden.needs_empty', 'Konkrete Bedarfe werden angezeigt, sobald sie in der Verwaltung geprüft eingetragen sind.')); ?></p>
            <?php endif; ?>
            <p class="sod-current-needs-note"><?php echo esc_html($t('spenden.needs_note', 'Sachspenden können nach Absprache abgegeben werden - bitte vorher kurz Kontakt aufnehmen.')); ?></p>
        </section>
        <?php
        return (string)ob_get_clean();
    }

    public static function handle_paypal_ipn(): void
    {
        $raw = (string)file_get_contents('php://input');
        if ($raw === '') {
            status_header(400);
            exit;
        }

        $verify_body = 'cmd=_notify-validate&' . $raw;
        $response = wp_remote_post('https://ipnpb.paypal.com/cgi-bin/webscr', [
            'body' => $verify_body,
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_body($response) !== 'VERIFIED') {
            // Nur zählen statt einzeln protokollieren: An diese offene Adresse kann jeder
            // senden, einzelne Einträge würden das Protokoll fluten.
            $unverified = get_option('sod_paypal_ipn_unverified', []);
            update_option('sod_paypal_ipn_unverified', [
                'count' => (int)(is_array($unverified) ? ($unverified['count'] ?? 0) : 0) + 1,
                'last' => gmdate('c'),
            ], false);
            status_header(200);
            exit;
        }

        parse_str($raw, $data);
        $receiver = sanitize_email((string)($data['receiver_email'] ?? $data['business'] ?? ''));
        $configured_email = (string)get_option('sod_paypal_email', '');
        $name_sponsorship_email = (string)get_option('sod_name_sponsorship_paypal_email', '');
        $configured_emails = array_values(array_filter([$configured_email, $name_sponsorship_email]));
        $receiver_matches = false;
        foreach ($configured_emails as $configured) {
            if (strcasecmp($receiver, $configured) === 0) {
                $receiver_matches = true;
                break;
            }
        }
        if (!$receiver_matches) {
            self::log_paypal_ipn_issue(
                $configured_emails === []
                    ? 'Keine PayPal-Adresse in den SOD-Einstellungen hinterlegt'
                    : 'Empfängeradresse weicht von den SOD-Einstellungen ab',
                ['gemeldet' => $receiver, 'hinterlegt' => implode(', ', $configured_emails), 'txn_type' => (string)($data['txn_type'] ?? '')]
            );
            status_header(200);
            exit;
        }

        $txn_type = (string)($data['txn_type'] ?? '');
        if (in_array($txn_type, ['subscr_signup', 'subscr_payment', 'subscr_cancel', 'subscr_eot', 'subscr_failed'], true)) {
            self::handle_paypal_subscription_ipn($txn_type, $data);
            status_header(200);
            exit;
        }

        $status = (string)($data['payment_status'] ?? '');
        if ($status !== 'Completed') {
            status_header(200);
            exit;
        }

        $txn_id = sanitize_text_field((string)($data['txn_id'] ?? ''));
        if ($txn_id === '') {
            status_header(200);
            exit;
        }
        if (self::post_exists_by_meta('sod_finance', 'sod_finance_paypal_txn_id', $txn_id)) {
            status_header(200);
            exit;
        }

        $amount = sanitize_text_field((string)($data['mc_gross'] ?? ''));
        $currency = sanitize_text_field((string)($data['mc_currency'] ?? ''));
        if (strtoupper($currency) !== 'EUR' || self::numeric_quantity($amount) <= 0) {
            status_header(200);
            exit;
        }
        $donor_name = trim(sanitize_text_field((string)($data['first_name'] ?? '') . ' ' . (string)($data['last_name'] ?? '')));
        $donor_email = sanitize_email((string)($data['payer_email'] ?? ''));
        $payment_date = sanitize_text_field((string)($data['payment_date'] ?? ''));
        $item_name = sanitize_text_field((string)($data['item_name'] ?? ''));
        $custom = sanitize_text_field((string)($data['custom'] ?? ''));
        $name_suggestion = '';

        $notes = "Automatisch erfasst aus PayPal-Zahlung.\nTransaktions-ID: {$txn_id}\nWaehrung: {$currency}\nArtikel: {$item_name}";

        $name_sponsorship_dog_id = 0;
        if (strpos($custom, 'sod_name_sponsorship:') === 0) {
            $custom_parts = explode(':', substr($custom, strlen('sod_name_sponsorship:')), 2);
            $name_sponsorship_dog_id = absint($custom_parts[0] ?? '');
            $name_suggestion = sanitize_text_field(urldecode((string)($custom_parts[1] ?? '')));
            if ($name_sponsorship_dog_id > 0 && get_post_type($name_sponsorship_dog_id) !== 'sod_dog') {
                $name_sponsorship_dog_id = 0;
            }
        }
        if ($name_sponsorship_dog_id > 0) {
            $notes .= "\nNamensvorschlag: " . ($name_suggestion !== '' ? $name_suggestion : '(keiner angegeben)');
        }

        $finance_id = wp_insert_post([
            'post_type' => 'sod_finance',
            'post_status' => 'private',
            'post_title' => 'PayPal-Spende' . ($donor_name !== '' ? ' - ' . $donor_name : ''),
        ]);
        if ($finance_id && !is_wp_error($finance_id)) {
            update_post_meta($finance_id, 'sod_finance_type', 'spende');
            update_post_meta($finance_id, 'sod_finance_date', self::iso_date($payment_date) ?: gmdate('Y-m-d'));
            update_post_meta($finance_id, 'sod_finance_amount', $amount);
            update_post_meta($finance_id, 'sod_finance_category', 'tierheim');
            update_post_meta($finance_id, 'sod_finance_donor', $donor_name);
            update_post_meta($finance_id, 'sod_finance_donor_email', $donor_email);
            update_post_meta($finance_id, 'sod_finance_notes', trim($notes));
            update_post_meta($finance_id, 'sod_finance_paypal_txn_id', $txn_id);
            update_post_meta($finance_id, 'sod_finance_payment_method', 'paypal');
            if ($name_sponsorship_dog_id > 0) {
                update_post_meta($finance_id, 'sod_finance_dog', $name_sponsorship_dog_id);
            }
            self::update_finance_retention((int)$finance_id);
            self::send_finance_receipt_email((int)$finance_id);
        }

        if ($name_sponsorship_dog_id > 0) {
            $suggestion_note = 'Namenspatenschaft eingegangen (' . $amount . ' ' . $currency . ')'
                . ($donor_name !== '' ? ' von ' . $donor_name : '')
                . ($name_suggestion !== '' ? ', Namensvorschlag: "' . $name_suggestion . '"' : ', ohne Namensvorschlag')
                . ', ' . date_i18n('d.m.Y H:i') . ' Uhr.';
            update_post_meta($name_sponsorship_dog_id, 'sod_name_sponsor_suggestion', $name_suggestion);
            $internal_notes = trim((string)get_post_meta($name_sponsorship_dog_id, 'sod_internal_notes', true));
            update_post_meta($name_sponsorship_dog_id, 'sod_internal_notes', trim($internal_notes . "\n" . $suggestion_note));
        }

        status_header(200);
        exit;
    }

    private static function handle_paypal_subscription_ipn(string $txn_type, array $data): void
    {
        if ($txn_type === 'subscr_signup') {
            $token = sanitize_text_field((string)($data['custom'] ?? ''));
            if ($token === '') {
                self::log_paypal_ipn_issue('Abo-Anmeldung ohne Zuordnungs-Token', ['txn_type' => $txn_type]);
                return;
            }
            $sponsor_id = self::find_post_id_by_meta('sod_sponsor', 'sod_sponsor_paypal_token', $token);
            if (!$sponsor_id) {
                self::log_paypal_ipn_issue('Kein Paten-Datensatz zu diesem Zuordnungs-Token gefunden', ['txn_type' => $txn_type]);
                return;
            }
            $sponsor_status = (string)get_post_meta($sponsor_id, 'sod_sponsor_status', true);
            if (!in_array($sponsor_status, ['aktiv', 'inaktiv'], true)) {
                self::log_paypal_ipn_issue(
                    'Paten-Status passt nicht zur Abo-Anmeldung',
                    ['txn_type' => $txn_type, 'status' => $sponsor_status !== '' ? $sponsor_status : 'leer'],
                    $sponsor_id
                );
                return;
            }
            if (get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true) !== 'paypal') {
                self::log_paypal_ipn_issue('Im Paten-Datensatz ist nicht PayPal als Zahlungsart hinterlegt', ['txn_type' => $txn_type], $sponsor_id);
                return;
            }
            $mismatch = self::paypal_subscription_signup_mismatch($sponsor_id, $data);
            if ($mismatch !== '') {
                self::log_paypal_ipn_issue($mismatch, ['txn_type' => $txn_type], $sponsor_id);
                return;
            }
            $subscr_id = sanitize_text_field((string)($data['subscr_id'] ?? ''));
            if ($subscr_id === '') {
                self::log_paypal_ipn_issue('Abo-Nummer fehlt in der PayPal-Meldung', ['txn_type' => $txn_type], $sponsor_id);
                return;
            }
            $linked_sponsor_id = self::find_post_id_by_meta('sod_sponsor', 'sod_sponsor_paypal_subscr_id', $subscr_id);
            if ($linked_sponsor_id && $linked_sponsor_id !== $sponsor_id) {
                self::log_paypal_ipn_issue('Abo-Nummer ist bereits einem anderen Paten zugeordnet', ['txn_type' => $txn_type], $sponsor_id);
                return;
            }
            update_post_meta($sponsor_id, 'sod_sponsor_paypal_subscr_id', $subscr_id);
            self::append_sponsor_note($sponsor_id, 'PayPal-Abo angemeldet am ' . date_i18n('d.m.Y H:i') . ' Uhr; Zahlungseingang wartet auf die erste abgeschlossene Zahlung.');
            return;
        }

        if ($txn_type === 'subscr_payment') {
            $payment_status = (string)($data['payment_status'] ?? '');
            if ($payment_status !== 'Completed') {
                self::log_paypal_ipn_issue(
                    'Abo-Zahlung ist noch nicht abgeschlossen',
                    ['txn_type' => $txn_type, 'payment_status' => $payment_status !== '' ? $payment_status : 'leer']
                );
                return;
            }
            $subscr_id = sanitize_text_field((string)($data['subscr_id'] ?? ''));
            if ($subscr_id === '') {
                self::log_paypal_ipn_issue('Abo-Nummer fehlt in der Zahlungsmeldung', ['txn_type' => $txn_type]);
                return;
            }
            $sponsor_id = self::find_post_id_by_meta('sod_sponsor', 'sod_sponsor_paypal_subscr_id', $subscr_id);
            if (!$sponsor_id) {
                // Die Abo-Anmeldung wurde nie verarbeitet, deshalb fehlt die Verknuepfung
                // zur Abo-Nummer. PayPal schickt das Zuordnungs-Token aber auch bei jeder
                // Zahlung mit - damit laesst sich der Pate nachtraeglich finden.
                $token = sanitize_text_field((string)($data['custom'] ?? ''));
                $sponsor_id = $token !== ''
                    ? self::find_post_id_by_meta('sod_sponsor', 'sod_sponsor_paypal_token', $token)
                    : 0;
                if ($sponsor_id) {
                    update_post_meta($sponsor_id, 'sod_sponsor_paypal_subscr_id', $subscr_id);
                    self::append_sponsor_note(
                        $sponsor_id,
                        'Abo-Nummer nachträglich über das Zuordnungs-Token verknüpft am ' . date_i18n('d.m.Y H:i') . ' Uhr;'
                        . ' die ursprüngliche Abo-Anmeldung war nicht verarbeitet worden.'
                    );
                }
            }
            if (!$sponsor_id) {
                self::log_paypal_ipn_issue(
                    'Kein Paten-Datensatz zu dieser Abo-Nummer und keinem Zuordnungs-Token gefunden',
                    ['txn_type' => $txn_type]
                );
                return;
            }
            $txn_id = sanitize_text_field((string)($data['txn_id'] ?? ''));
            if ($txn_id === '') {
                self::log_paypal_ipn_issue('Transaktions-ID fehlt in der Zahlungsmeldung', ['txn_type' => $txn_type], $sponsor_id);
                return;
            }
            if (self::post_exists_by_meta('sod_finance', 'sod_finance_paypal_txn_id', $txn_id)) {
                // Bereits verbucht - PayPal wiederholt Meldungen, das ist kein Fehler.
                return;
            }
            $amount = sanitize_text_field((string)($data['mc_gross'] ?? ''));
            $currency = sanitize_text_field((string)($data['mc_currency'] ?? ''));
            if (!self::paypal_subscription_payment_matches($sponsor_id, $amount, $currency)) {
                self::log_paypal_ipn_issue(
                    'Betrag oder Währung der Abo-Zahlung weichen ab (gemeldet: ' . $amount . ' ' . $currency . ')',
                    ['txn_type' => $txn_type],
                    $sponsor_id
                );
                return;
            }
            $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
            $dog_name = $dog_id ? get_the_title($dog_id) : 'Hund';
            $pate_name = get_the_title($sponsor_id) ?: 'Pate/Patin';
            $payment_date = sanitize_text_field((string)($data['payment_date'] ?? ''));

            $finance_id = wp_insert_post([
                'post_type' => 'sod_finance',
                'post_status' => 'private',
                'post_title' => 'Patenschaft - ' . $pate_name,
            ]);
            if ($finance_id && !is_wp_error($finance_id)) {
                update_post_meta($finance_id, 'sod_finance_type', 'spende');
                update_post_meta($finance_id, 'sod_finance_date', self::iso_date($payment_date) ?: gmdate('Y-m-d'));
                update_post_meta($finance_id, 'sod_finance_amount', $amount);
                update_post_meta($finance_id, 'sod_finance_category', 'tierheim');
                update_post_meta($finance_id, 'sod_finance_donor', $pate_name);
                update_post_meta($finance_id, 'sod_finance_donor_email', sanitize_email((string)get_post_meta($sponsor_id, 'sod_sponsor_email', true)));
                update_post_meta($finance_id, 'sod_finance_notes', trim(
                    "Automatisch erfasst aus PayPal-Patenschafts-Abo.\nPate/Patin: {$pate_name}\nHund: {$dog_name}\nTransaktions-ID: {$txn_id}"
                ));
                update_post_meta($finance_id, 'sod_finance_paypal_txn_id', $txn_id);
                update_post_meta($finance_id, 'sod_finance_payment_method', 'paypal');
                if ($dog_id > 0) {
                    update_post_meta($finance_id, 'sod_finance_dog', $dog_id);
                }
                self::update_finance_retention((int)$finance_id);
                // Bei Patenschaften bewusst KEINE automatische Spendenbestaetigung - Paten
                // bekommen stattdessen das Patenschaftszertifikat (siehe confirm_sponsor_payment()).
            } else {
                return;
            }

            $was_confirmed = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1';
            self::confirm_sponsor_payment($sponsor_id, $payment_date, $txn_id);
            if (!$was_confirmed) {
                wp_mail(
                    self::notification_emails(),
                    'Neuer Pate/Patin aktiv',
                    "Die erste PayPal-Zahlung der Patenschaft wurde bestaetigt.\nPate/Patin: {$pate_name}\nHund: {$dog_name}",
                    array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers())
                );
            }
            return;
        }

        if ($txn_type === 'subscr_cancel' || $txn_type === 'subscr_eot') {
            $subscr_id = sanitize_text_field((string)($data['subscr_id'] ?? ''));
            if ($subscr_id === '') {
                return;
            }
            $sponsor_id = self::find_post_id_by_meta('sod_sponsor', 'sod_sponsor_paypal_subscr_id', $subscr_id);
            if (!$sponsor_id) {
                return;
            }
            update_post_meta($sponsor_id, 'sod_sponsor_status', 'beendet');
            self::update_sponsor_retention($sponsor_id, 'beendet');
            self::sync_dog_public_sponsors((int)get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
            $existing_notes = trim((string)get_post_meta($sponsor_id, 'sod_sponsor_notes', true));
            $note = 'PayPal-Abo beendet am ' . gmdate('d.m.Y') . ' (' . $txn_type . ').';
            update_post_meta($sponsor_id, 'sod_sponsor_notes', trim($existing_notes . "\n" . $note));
            $pate_name = get_the_title($sponsor_id) ?: 'Pate/Patin';
            wp_mail(
                self::notification_emails(),
                'Patenschafts-Abo beendet',
                "Das PayPal-Abo von {$pate_name} wurde beendet ({$txn_type}).",
                array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers())
            );
            return;
        }

        if ($txn_type === 'subscr_failed') {
            $subscr_id = sanitize_text_field((string)($data['subscr_id'] ?? ''));
            if ($subscr_id === '') {
                return;
            }
            $sponsor_id = self::find_post_id_by_meta('sod_sponsor', 'sod_sponsor_paypal_subscr_id', $subscr_id);
            if (!$sponsor_id) {
                return;
            }
            $existing_notes = trim((string)get_post_meta($sponsor_id, 'sod_sponsor_notes', true));
            $note = 'PayPal-Zahlung fehlgeschlagen am ' . gmdate('d.m.Y') . '.';
            update_post_meta($sponsor_id, 'sod_sponsor_notes', trim($existing_notes . "\n" . $note));
            $pate_name = get_the_title($sponsor_id) ?: 'Pate/Patin';
            wp_mail(
                self::notification_emails(),
                'PayPal-Zahlung fehlgeschlagen',
                "Eine Patenschafts-Zahlung von {$pate_name} ist fehlgeschlagen.",
                array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers())
            );
        }
    }

    private static function paypal_first_value(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim(sanitize_text_field((string)($data[$key] ?? '')));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private static function paypal_normalize_period(string $period): string
    {
        $period = strtoupper(trim((string)preg_replace('/\s+/', ' ', $period)));
        // PayPal schreibt denselben Zeitraum je nach Meldung als "1 M", "1M" oder "1 MONTH".
        if (preg_match('/^(\d+)\s*([DWMY])/', $period, $matches)) {
            return $matches[1] . ' ' . $matches[2];
        }
        return $period;
    }

    private static function paypal_subscription_signup_matches(int $sponsor_id, array $data): bool
    {
        return self::paypal_subscription_signup_mismatch($sponsor_id, $data) === '';
    }

    /**
     * Liefert den Ablehnungsgrund als lesbaren Text, oder einen leeren String, wenn die
     * Abo-Anmeldung zum hinterlegten Paten-Datensatz passt.
     */
    private static function paypal_subscription_signup_mismatch(int $sponsor_id, array $data): string
    {
        $amount = self::paypal_first_value($data, ['amount3', 'mc_amount3']);
        if ($amount === '') {
            return 'Betrag fehlt in der PayPal-Meldung';
        }
        $currency = strtoupper(self::paypal_first_value($data, ['mc_currency', 'currency_code']));
        if (!self::paypal_subscription_payment_matches($sponsor_id, $amount, $currency)) {
            return 'Betrag oder Währung weichen ab (gemeldet: ' . $amount . ' ' . ($currency !== '' ? $currency : 'ohne Währung') . ')';
        }
        $period_raw = self::paypal_first_value($data, ['period3', 'mc_period3']);
        if ($period_raw === '') {
            return 'Abrechnungszeitraum fehlt in der PayPal-Meldung';
        }
        if (self::paypal_normalize_period($period_raw) !== self::paypal_expected_period($sponsor_id)) {
            return 'Abrechnungszeitraum weicht ab (gemeldet: ' . $period_raw . ')';
        }
        return '';
    }

    private static function paypal_subscription_payment_matches(int $sponsor_id, string $amount, string $currency): bool
    {
        if (get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true) !== 'paypal'
            || get_post_meta($sponsor_id, 'sod_sponsor_interval', true) !== 'monatlich') {
            return false;
        }
        $expected_amount = (string)get_post_meta($sponsor_id, 'sod_sponsor_paypal_expected_amount', true);
        if ($expected_amount === '') {
            $expected_amount = number_format(self::money_number((string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true)), 2, '.', '');
        }
        $expected_currency = strtoupper((string)get_post_meta($sponsor_id, 'sod_sponsor_paypal_expected_currency', true));
        if ($expected_currency === '') {
            $expected_currency = 'EUR';
        }
        $actual_cents = self::paypal_amount_cents($amount);
        $expected_cents = self::paypal_amount_cents($expected_amount);
        return $actual_cents !== null
            && $expected_cents !== null
            && $actual_cents > 0
            && $actual_cents === $expected_cents
            && strtoupper(trim($currency)) === $expected_currency;
    }

    private static function paypal_expected_period(int $sponsor_id): string
    {
        $period = self::paypal_normalize_period((string)get_post_meta($sponsor_id, 'sod_sponsor_paypal_expected_period', true));
        return $period !== '' ? $period : '1 M';
    }

    private static function paypal_amount_cents(string $amount): ?int
    {
        $amount = trim($amount);
        // Je nach Kontosprache meldet PayPal "5.00" oder "5,00". Nur ein eindeutiges
        // Dezimalkomma umstellen; gemischte Trennzeichen bleiben bewusst ungültig.
        if (substr_count($amount, ',') === 1 && !str_contains($amount, '.')) {
            $amount = str_replace(',', '.', $amount);
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            return null;
        }
        return (int)round((float)$amount * 100);
    }

    /**
     * Hält fest, warum eine PayPal-Meldung nicht verarbeitet wurde. Bewusst nur technische
     * Felder - keine Namen, E-Mail-Adressen oder Anschriften aus der Meldung.
     */
    private static function log_paypal_ipn_issue(string $reason, array $context = [], int $sponsor_id = 0): void
    {
        $log = get_option('sod_paypal_ipn_log', []);
        if (!is_array($log)) {
            $log = [];
        }
        array_unshift($log, [
            'time' => gmdate('c'),
            'reason' => self::limited_text($reason, 200),
            'sponsor' => $sponsor_id,
            'context' => array_map(
                static fn ($value): string => self::limited_text(sanitize_text_field((string)$value), 80),
                $context
            ),
        ]);
        update_option('sod_paypal_ipn_log', array_slice($log, 0, 25), false);
        if ($sponsor_id > 0) {
            self::append_sponsor_note(
                $sponsor_id,
                'PayPal-Meldung nicht verarbeitet (' . $reason . ') am ' . date_i18n('d.m.Y H:i') . ' Uhr.'
            );
        }
    }

    private static function format_utc_datetime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false || $timestamp <= 0) {
            return $raw;
        }
        return get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), 'd.m.Y H:i');
    }

    private static function append_sponsor_note(int $sponsor_id, string $note): void
    {
        $existing = trim((string)get_post_meta($sponsor_id, 'sod_sponsor_notes', true));
        update_post_meta($sponsor_id, 'sod_sponsor_notes', trim($existing . "\n" . $note));
    }

    private static function schedule_sponsor_payment_deadline(int $sponsor_id): void
    {
        if ($sponsor_id <= 0 || (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1') {
            return;
        }
        $started_raw = trim((string)get_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', true));
        $started = $started_raw !== '' ? strtotime($started_raw) : false;
        if ($started === false || $started <= 0) {
            $started = time();
            update_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', gmdate('c', $started));
        }
        // Dauerauftrag/Banküberweisung braucht laenger, bis der Zahlungseingang sichtbar
        // wird und manuell bestaetigt werden kann, als eine PayPal-Zahlung - deshalb eine
        // deutlich laengere Wartefrist (40 Tage statt 24 Stunden), bevor automatisch auf
        // "Inaktiv" gesetzt wird.
        $payment_method = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true);
        $wait_period = $payment_method === 'dauerauftrag' ? 40 * DAY_IN_SECONDS : DAY_IN_SECONDS;
        $deadline = $started + $wait_period;
        update_post_meta($sponsor_id, 'sod_sponsor_payment_deadline_at', gmdate('c', $deadline));
        if (!wp_next_scheduled('sod_sponsor_payment_deadline', [$sponsor_id])) {
            wp_schedule_single_event(max(time() + 60, $deadline), 'sod_sponsor_payment_deadline', [$sponsor_id]);
        }
    }

    public static function check_sponsor_payment_deadline(int $sponsor_id): void
    {
        if ($sponsor_id <= 0 || get_post_type($sponsor_id) !== 'sod_sponsor'
            || (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1') {
            return;
        }
        $deadline_raw = trim((string)get_post_meta($sponsor_id, 'sod_sponsor_payment_deadline_at', true));
        $deadline = $deadline_raw !== '' ? strtotime($deadline_raw) : false;
        if ($deadline !== false && $deadline > time()) {
            self::schedule_sponsor_payment_deadline($sponsor_id);
            return;
        }
        if ((string)get_post_meta($sponsor_id, 'sod_sponsor_status', true) !== 'inaktiv') {
            update_post_meta($sponsor_id, 'sod_sponsor_status', 'inaktiv');
            update_post_meta($sponsor_id, 'sod_sponsor_inactive_at', gmdate('c'));
            self::append_sponsor_note($sponsor_id, 'Automatisch auf inaktiv gesetzt: innerhalb von 24 Stunden wurde kein Zahlungseingang bestätigt.');
        }
        self::update_sponsor_retention($sponsor_id, 'inaktiv');
        self::sync_dog_public_sponsors((int)get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
    }

    private static function confirm_sponsor_payment(int $sponsor_id, string $payment_date = '', string $txn_id = ''): void
    {
        if ($sponsor_id <= 0 || get_post_type($sponsor_id) !== 'sod_sponsor') {
            return;
        }
        $was_confirmed = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1';
        update_post_meta($sponsor_id, 'sod_sponsor_payment_received', '1');
        $date = self::iso_date($payment_date) ?: gmdate('Y-m-d');
        if (trim((string)get_post_meta($sponsor_id, 'sod_sponsor_payment_confirmed_at', true)) === '') {
            update_post_meta($sponsor_id, 'sod_sponsor_payment_confirmed_at', gmdate('c'));
        }
        if (trim((string)get_post_meta($sponsor_id, 'sod_sponsor_since', true)) === '') {
            update_post_meta($sponsor_id, 'sod_sponsor_since', $date);
        }
        if ($txn_id !== '') {
            update_post_meta($sponsor_id, 'sod_sponsor_paypal_first_txn_id', $txn_id);
        }
        update_post_meta($sponsor_id, 'sod_sponsor_status', 'aktiv');
        delete_post_meta($sponsor_id, 'sod_sponsor_inactive_at');
        wp_clear_scheduled_hook('sod_sponsor_payment_deadline', [$sponsor_id]);
        self::update_sponsor_retention($sponsor_id, 'aktiv');
        if (!$was_confirmed) {
            self::append_sponsor_note($sponsor_id, 'Zahlungseingang bestätigt am ' . date_i18n('d.m.Y H:i') . ' Uhr.');
            if ((string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true) === 'dauerauftrag') {
                self::create_sponsor_bank_finance_receipt($sponsor_id, $date);
            }
            self::send_sponsor_certificate_pdf_auto($sponsor_id);
        }
        self::sync_dog_public_sponsors((int)get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
    }

    private static function create_sponsor_bank_finance_receipt(int $sponsor_id, string $date): void
    {
        if (self::find_post_id_by_meta('sod_finance', 'sod_finance_sponsor_initial_payment_id', (string)$sponsor_id)) {
            return;
        }
        $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
        $dog_name = $dog_id > 0 ? get_the_title($dog_id) : 'Hund';
        $pate_name = get_the_title($sponsor_id) ?: 'Pate/Patin';
        $finance_id = wp_insert_post([
            'post_type' => 'sod_finance',
            'post_status' => 'private',
            'post_title' => 'Patenschaft - ' . $pate_name,
        ]);
        if (!$finance_id || is_wp_error($finance_id)) {
            return;
        }
        update_post_meta($finance_id, 'sod_finance_type', 'spende');
        update_post_meta($finance_id, 'sod_finance_date', $date);
        update_post_meta($finance_id, 'sod_finance_amount', (string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true));
        update_post_meta($finance_id, 'sod_finance_category', 'tierheim');
        update_post_meta($finance_id, 'sod_finance_donor', $pate_name);
        update_post_meta($finance_id, 'sod_finance_donor_email', sanitize_email((string)get_post_meta($sponsor_id, 'sod_sponsor_email', true)));
        update_post_meta($finance_id, 'sod_finance_notes', "Automatisch erfasst aus bestätigtem Dauerauftrag.\nPate/Patin: {$pate_name}\nHund: {$dog_name}");
        update_post_meta($finance_id, 'sod_finance_sponsor_initial_payment_id', (string)$sponsor_id);
        update_post_meta($finance_id, 'sod_finance_payment_method', 'dauerauftrag');
        if ($dog_id > 0) {
            update_post_meta($finance_id, 'sod_finance_dog', $dog_id);
        }
        self::update_finance_retention((int)$finance_id);
        // Bei Patenschaften bewusst KEINE automatische Spendenbestaetigung - Paten
        // bekommen stattdessen das Patenschaftszertifikat (siehe confirm_sponsor_payment()).
    }

    public static function handle_sponsor_signup(): void
    {
        $dog_id = absint($_POST['dog_id'] ?? 0);
        $dog_permalink = $dog_id > 0 ? get_permalink($dog_id) : false;
        $fallback_redirect = ($dog_permalink ?: home_url('/')) . '#pate-werden';

        $has_photo_upload = isset($_FILES['sponsor_photo']) && is_array($_FILES['sponsor_photo'])
            && (int)($_FILES['sponsor_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $max_body = $has_photo_upload ? (6 * 1024 * 1024 + 50000) : 20000;
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $max_body) {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }
        if (!self::sponsor_signup_token_ok()) {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }
        if (!empty($_POST['website']) || (time() - (int)($_POST['started_at'] ?? 0)) < 3 || empty($_POST['consent'])) {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }
        if (!self::sponsor_signup_rate_limit_ok()) {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }
        if ($dog_id <= 0 || get_post_type($dog_id) !== 'sod_dog' || get_post_meta($dog_id, 'sod_show_sponsorship', true) !== '1') {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }

        $support = self::dog_support_amounts($dog_id);
        $requested_amount = str_replace(',', '.', sanitize_text_field(wp_unslash((string)($_POST['sponsor_amount'] ?? ''))));
        $requested_cents = self::paypal_amount_cents($requested_amount);
        $remaining_cents = (int)round($support['remaining'] * 100);
        $minimum_cents = min(500, $remaining_cents);
        if ($support['target'] <= 0 || $requested_cents === null || $requested_cents < $minimum_cents || $requested_cents > $remaining_cents) {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }
        $amount_numeric = $requested_cents / 100;
        $amount_raw = self::money_plain($amount_numeric);

        $email = sanitize_email((string)($_POST['email'] ?? ''));
        if ($email === '') {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }

        $first_name = self::limited_text(sanitize_text_field((string)($_POST['first_name'] ?? '')), 80);
        $last_name = self::limited_text(sanitize_text_field((string)($_POST['last_name'] ?? '')), 80);
        $phone = self::limited_text(sanitize_text_field((string)($_POST['phone'] ?? '')), 60);
        $full_name = trim($first_name . ' ' . $last_name);
        if ($full_name === '') {
            $full_name = $email;
        }

        $dog_name = self::dog_public_name($dog_id);
        $token = bin2hex(random_bytes(16));
        $payment_method = (string)($_POST['payment_method'] ?? 'paypal') === 'dauerauftrag' ? 'dauerauftrag' : 'paypal';

        $sponsor_id = wp_insert_post([
            'post_type' => 'sod_sponsor',
            'post_status' => 'private',
            'post_title' => $full_name,
        ]);
        if (!$sponsor_id || is_wp_error($sponsor_id)) {
            wp_safe_redirect(add_query_arg('patenschaft', 'spam', $fallback_redirect));
            exit;
        }

        $waiting_note = $payment_method === 'dauerauftrag'
            ? 'Online-Patenschaft über ' . $amount_raw . ' € monatlich am ' . date_i18n('d.m.Y H:i') . ' Uhr vorläufig aktiviert; wartet auf Bestätigung, dass der Dauerauftrag eingerichtet wurde.'
            : 'Online-Patenschaft über ' . $amount_raw . ' € monatlich am ' . date_i18n('d.m.Y H:i') . ' Uhr vorläufig aktiviert; wartet auf PayPal-Bestätigung.';

        update_post_meta($sponsor_id, 'sod_sponsor_status', 'aktiv');
        update_post_meta($sponsor_id, 'sod_sponsor_dog', $dog_id);
        update_post_meta($sponsor_id, 'sod_sponsor_email', $email);
        update_post_meta($sponsor_id, 'sod_sponsor_phone', $phone);
        update_post_meta($sponsor_id, 'sod_sponsor_amount', $amount_raw);
        update_post_meta($sponsor_id, 'sod_sponsor_interval', 'monatlich');
        update_post_meta($sponsor_id, 'sod_sponsor_notes', $waiting_note);
        update_post_meta($sponsor_id, 'sod_sponsor_payment_method', $payment_method);
        update_post_meta($sponsor_id, 'sod_sponsor_payment_received', '0');
        self::update_sponsor_retention($sponsor_id, 'aktiv');
        self::sync_dog_public_sponsors($dog_id);

        if (!empty($_POST['show_publicly'])) {
            update_post_meta($sponsor_id, 'sod_sponsor_public_consent', '1');
            update_post_meta($sponsor_id, 'sod_sponsor_public_consent_at', gmdate('c'));
            update_post_meta($sponsor_id, 'sod_sponsor_public_consent_version', self::PRIVACY_NOTICE_VERSION);
            update_post_meta($sponsor_id, 'sod_sponsor_public_consent_source', 'online-formular');
            update_post_meta($sponsor_id, 'sod_sponsor_show_wish', '1');
            self::store_sponsor_photo($sponsor_id);
        }

        if ($payment_method === 'dauerauftrag') {
            // Die 24-Stunden-Zahlungsfrist startet bewusst erst mit dem Bestätigungsklick
            // auf der Dauerauftrag-Seite (handle_sponsor_bank_confirm), nicht schon hier.
            $bank_token = bin2hex(random_bytes(20));
            update_post_meta($sponsor_id, 'sod_sponsor_bank_token', $bank_token);
            self::render_dauerauftrag_instructions([
                'sponsor_id' => $sponsor_id,
                'dog_name' => $dog_name,
                'pate_name' => $full_name,
                'amount' => $amount_numeric,
                'token' => $bank_token,
            ]);
            exit;
        }

        update_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', gmdate('c'));
        self::schedule_sponsor_payment_deadline($sponsor_id);

        update_post_meta($sponsor_id, 'sod_sponsor_paypal_token', $token);
        update_post_meta($sponsor_id, 'sod_sponsor_paypal_expected_amount', number_format($amount_numeric, 2, '.', ''));
        update_post_meta($sponsor_id, 'sod_sponsor_paypal_expected_currency', 'EUR');
        update_post_meta($sponsor_id, 'sod_sponsor_paypal_expected_period', '1 M');

        self::render_paypal_subscribe_redirect([
            'sponsor_id' => $sponsor_id,
            'dog_id' => $dog_id,
            'dog_name' => $dog_name,
            'amount' => $amount_numeric,
            'token' => $token,
        ]);
        exit;
    }

    private static function store_sponsor_photo(int $sponsor_id): void
    {
        if (empty($_FILES['sponsor_photo']) || !is_array($_FILES['sponsor_photo'])) {
            return;
        }
        $file = $_FILES['sponsor_photo'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return;
        }

        $size = (int)($file['size'] ?? 0);
        $max_size = min((int)wp_max_upload_size(), 6 * 1024 * 1024);
        if ($size <= 0 || $size > $max_size) {
            return;
        }

        $original_name = sanitize_file_name((string)($file['name'] ?? 'pate'));
        $allowed = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ];
        $check = wp_check_filetype_and_ext((string)$file['tmp_name'], $original_name, $allowed);
        $ext = strtolower((string)($check['ext'] ?? ''));
        if ($ext === '' || !isset($allowed[$ext])) {
            return;
        }
        $dims = @getimagesize((string)$file['tmp_name']);
        if ($dims === false || (int)($dims[0] ?? 0) < 1 || (int)($dims[1] ?? 0) < 1) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $moved = wp_handle_upload($file, ['test_form' => false, 'mimes' => $allowed]);
        if (!is_array($moved) || !empty($moved['error']) || empty($moved['file'])) {
            return;
        }

        $editor = wp_get_image_editor((string)$moved['file']);
        if (!is_wp_error($editor)) {
            $editor->set_quality(88);
            $editor->save((string)$moved['file']);
        }

        $attach_id = wp_insert_attachment([
            'post_mime_type' => (string)($moved['type'] ?? $allowed[$ext]),
            'post_title'     => 'Patenfoto ' . $sponsor_id,
            'post_status'    => 'inherit',
            'post_parent'    => $sponsor_id,
        ], (string)$moved['file'], $sponsor_id);
        if (!$attach_id || is_wp_error($attach_id)) {
            return;
        }
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, (string)$moved['file']));

        update_post_meta($sponsor_id, 'sod_sponsor_photo_id', (int)$attach_id);
        update_post_meta($sponsor_id, 'sod_sponsor_show_wish', '1');
        update_post_meta($sponsor_id, 'sod_sponsor_photo_approved', '0');
    }

    private static function sponsor_public_first_name(WP_Post $sponsor): string
    {
        $title = trim((string)$sponsor->post_title);
        if ($title === '') {
            return self::t('dogcard.sponsor_wall_anon', 'Pate');
        }
        $parts = preg_split('/\s+/', $title) ?: [];
        $first = trim((string)($parts[0] ?? ''));
        return $first !== '' ? $first : self::t('dogcard.sponsor_wall_anon', 'Pate');
    }

    private static function sync_dog_public_sponsors(int $dog_id, int $exclude_id = 0): void
    {
        if ($dog_id <= 0) {
            return;
        }
        $exclude = $exclude_id > 0 ? ['post__not_in' => [$exclude_id]] : [];
        $sponsors = get_posts(array_merge([
            'post_type'      => 'sod_sponsor',
            'post_status'    => ['publish', 'private'],
            'posts_per_page' => 50,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'sod_sponsor_dog', 'value' => (string)$dog_id],
                ['key' => 'sod_sponsor_status', 'value' => 'aktiv'],
                ['key' => 'sod_sponsor_public_consent', 'value' => '1'],
                ['key' => 'sod_sponsor_photo_approved', 'value' => '1'],
            ],
        ], $exclude));
        $list = [];
        foreach ($sponsors as $sponsor) {
            $photo_id = (int)get_post_meta($sponsor->ID, 'sod_sponsor_photo_id', true);
            if ($photo_id <= 0) {
                continue;
            }
            $list[] = [
                'name'  => self::sponsor_public_first_name($sponsor),
                'photo' => $photo_id,
            ];
        }
        if ($list) {
            update_post_meta($dog_id, 'sod_dog_public_sponsors', $list);
        } else {
            delete_post_meta($dog_id, 'sod_dog_public_sponsors');
        }

        $support = self::dog_support_amounts($dog_id, $exclude_id);
        if ($support['target'] > 0 && $support['remaining'] < 1) {
            update_post_meta($dog_id, 'sod_dog_sponsorship_taken', '1');
        } else {
            delete_post_meta($dog_id, 'sod_dog_sponsorship_taken');
        }

        self::sync_active_sponsorships_index($exclude_id);
    }

    public static function on_sponsor_removed(int $post_id): void
    {
        if (get_post_type($post_id) !== 'sod_sponsor') {
            return;
        }
        $dog_id = (int)get_post_meta($post_id, 'sod_sponsor_dog', true);
        $current = current_action();
        // Beim Löschen/Papierkorb den betroffenen Paten aus der Neuberechnung ausschließen,
        // damit der Hund sofort wieder freigegeben wird. Beim Wiederherstellen normal zählen.
        $exclude = $current === 'untrashed_post' ? 0 : $post_id;
        if ($dog_id > 0) {
            self::sync_dog_public_sponsors($dog_id, $exclude);
        } else {
            self::sync_active_sponsorships_index($exclude);
        }
    }

    public static function maybe_rebuild_sponsor_index(): void
    {
        if (get_option('sod_sponsor_index_v') === self::VERSION) {
            return;
        }
        // Ueber ALLE Hunde gehen (nicht nur die mit aktuell existierendem Paten-Eintrag):
        // sync_dog_public_sponsors() setzt die "vergeben"-Markierung korrekt neu ODER
        // loescht sie, wenn kein aktiver Pate mehr existiert. Wuerden hier nur Hunde mit
        // noch vorhandenem Paten-Eintrag beruecksichtigt, bliebe die Markierung nach dem
        // Loeschen des letzten Paten eines Hundes faelschlich stehen.
        $dog_ids = get_posts([
            'post_type'      => 'sod_dog',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        foreach ($dog_ids as $dog_id) {
            self::sync_dog_public_sponsors((int)$dog_id);
        }
        self::sync_active_sponsorships_index();
        update_option('sod_sponsor_index_v', self::VERSION, false);
    }

    private static function sync_active_sponsorships_index(int $exclude_id = 0): void
    {
        $exclude = $exclude_id > 0 ? ['post__not_in' => [$exclude_id]] : [];
        $sponsors = get_posts(array_merge([
            'post_type'      => 'sod_sponsor',
            'post_status'    => ['publish', 'private'],
            'posts_per_page' => 200,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
            // Der Vorname erscheint mit dokumentierter Einwilligung. Das Foto zusaetzlich
            // erst nach ausdruecklicher Freigabe - deshalb hier bewusst keine Bedingung
            // auf sod_sponsor_photo_approved, sonst fehlen Paten ohne Foto ganz.
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'sod_sponsor_status', 'value' => 'aktiv'],
                ['key' => 'sod_sponsor_public_consent', 'value' => '1'],
            ],
        ], $exclude));
        $index = [];
        foreach ($sponsors as $sponsor) {
            $sponsor_photo = (int)get_post_meta($sponsor->ID, 'sod_sponsor_photo_id', true);
            if ((string)get_post_meta($sponsor->ID, 'sod_sponsor_photo_approved', true) !== '1') {
                $sponsor_photo = 0;
            }
            $dog_id = (int)get_post_meta($sponsor->ID, 'sod_sponsor_dog', true);
            $dog_photo = (int)get_post_meta($sponsor->ID, 'sod_sponsor_dog_photo_id', true);
            if ($dog_photo <= 0 && $dog_id > 0) {
                $dog_photo = (int)get_post_thumbnail_id($dog_id);
            }
            $index[] = [
                'sponsor_name'  => self::sponsor_public_first_name($sponsor),
                'sponsor_photo' => $sponsor_photo,
                'dog_id'        => $dog_id,
                'dog_name'      => $dog_id > 0 ? self::dog_public_name($dog_id) : '',
                'dog_photo'     => $dog_photo,
            ];
        }
        update_option('sod_active_sponsorships', $index, false);
    }

    public static function active_sponsorships_shortcode(): string
    {
        $index = get_option('sod_active_sponsorships', []);
        if (!is_array($index) || !$index) {
            return '';
        }
        $cards = [];
        foreach ($index as $entry) {
            $sponsor_photo = (int)($entry['sponsor_photo'] ?? 0);
            $dog_photo = (int)($entry['dog_photo'] ?? 0);
            $sponsor_img = $sponsor_photo > 0
                ? wp_get_attachment_image($sponsor_photo, 'medium', false, ['loading' => 'lazy', 'class' => 'sod-pairing-img'])
                : '';
            // Ohne freigegebenes Patenfoto zeigt die Karte nur Vorname und Hundebild.
            $is_solo = $sponsor_img === '';
            $dog_img = $dog_photo > 0
                ? wp_get_attachment_image($dog_photo, 'medium', false, ['loading' => 'lazy', 'class' => 'sod-pairing-img'])
                : '';
            $sponsor_name = trim((string)($entry['sponsor_name'] ?? ''));
            $dog_name = trim((string)($entry['dog_name'] ?? ''));
            $dog_id = (int)($entry['dog_id'] ?? 0);
            $dog_link = $dog_id > 0 ? (string)get_permalink($dog_id) : '';

            ob_start();
            ?>
            <figure class="sod-pairing-card<?php echo $is_solo ? ' is-solo' : ''; ?>">
                <div class="sod-pairing-photos">
                    <?php if (!$is_solo) : ?>
                        <div class="sod-pairing-photo sod-pairing-photo-sponsor">
                            <?php echo $sponsor_img; // escaped by WP ?>
                            <span class="sod-pairing-role"><?php echo esc_html(self::t('patenschaft.pairing_sponsor', 'Pate/Patin')); ?></span>
                        </div>
                        <span class="sod-pairing-heart" aria-hidden="true">&#10084;</span>
                    <?php endif; ?>
                    <div class="sod-pairing-photo sod-pairing-photo-dog">
                        <?php if ($dog_img !== '') : ?>
                            <?php echo $dog_img; // escaped by WP ?>
                        <?php else : ?>
                            <span class="sod-pairing-placeholder"><?php echo esc_html(self::t('dogcard.image_placeholder', 'Bild folgt')); ?></span>
                        <?php endif; ?>
                        <span class="sod-pairing-role"><?php echo esc_html(self::t('patenschaft.pairing_dog', 'Hund')); ?></span>
                    </div>
                </div>
                <figcaption class="sod-pairing-caption">
                    <strong><?php echo esc_html($sponsor_name !== '' ? $sponsor_name : self::t('dogcard.sponsor_wall_anon', 'Pate')); ?></strong>
                    <?php echo esc_html(self::t('patenschaft.pairing_for', 'ist Pate für')); ?>
                    <?php if ($dog_link !== '') : ?>
                        <a href="<?php echo esc_url($dog_link); ?>"><?php echo esc_html($dog_name); ?></a>
                    <?php else : ?>
                        <strong><?php echo esc_html($dog_name); ?></strong>
                    <?php endif; ?>
                </figcaption>
            </figure>
            <?php
            $cards[] = (string)ob_get_clean();
        }
        if (!$cards) {
            return '';
        }
        ob_start();
        ?>
        <section class="section sod-active-sponsorships">
            <div class="container">
                <div class="section-header center">
                    <h2 class="section-title"><?php echo esc_html(self::t('patenschaft.active_title', 'Aktive Patenschaften')); ?></h2>
                    <p class="section-subtitle"><?php echo esc_html(self::t('patenschaft.active_subtitle', 'Diese Menschen tragen gerade eine Patenschaft — danke für euren Rückhalt!')); ?></p>
                </div>
                <div class="sod-pairing-grid">
                    <?php echo implode('', $cards); // parts already escaped ?>
                </div>
            </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }

    private static function sponsor_image_picker(string $input_id, string $label, int $current_id, int $fallback_id): void
    {
        $shown_id = $current_id > 0 ? $current_id : $fallback_id;
        $is_default = $current_id <= 0;
        ?>
        <div class="sod-sponsor-imgpicker" data-sod-img-picker style="width:180px;">
            <label style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html($label); ?></label>
            <div class="sod-sponsor-imgpicker-thumb" style="width:100%;height:140px;border:1px solid #ccd0d4;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f6f7f7;">
                <?php if ($shown_id > 0) : ?>
                    <?php echo wp_get_attachment_image($shown_id, 'medium', false, ['style' => 'width:100%;height:100%;object-fit:cover;']); ?>
                <?php else : ?>
                    <span style="font-size:.6875rem;color:#888;">Kein Bild</span>
                <?php endif; ?>
            </div>
            <input type="hidden" name="<?php echo esc_attr($input_id); ?>" id="<?php echo esc_attr($input_id); ?>" value="<?php echo esc_attr((string)$current_id); ?>">
            <p style="margin:6px 0 0;">
                <button type="button" class="button button-small" data-sod-img-select="<?php echo esc_attr($input_id); ?>">Bild wählen</button>
                <button type="button" class="button button-small" data-sod-img-clear="<?php echo esc_attr($input_id); ?>"><?php echo $fallback_id > 0 ? 'Standard' : 'Entfernen'; ?></button>
            </p>
            <?php if ($fallback_id > 0 && $is_default) : ?>
                <p class="description" style="margin-top:4px;">Standard: aktuelles Hundefoto.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_sponsor_wall(int $dog_id): string
    {
        $list = get_post_meta($dog_id, 'sod_dog_public_sponsors', true);
        if (!is_array($list) || !$list) {
            return '';
        }
        $items = [];
        foreach ($list as $entry) {
            $photo_id = (int)($entry['photo'] ?? 0);
            if ($photo_id <= 0) {
                continue;
            }
            $img = wp_get_attachment_image($photo_id, 'medium', false, ['loading' => 'lazy', 'class' => 'sod-sponsor-wall-img']);
            if ($img === '') {
                continue;
            }
            $name = trim((string)($entry['name'] ?? ''));
            $items[] = '<figure class="sod-sponsor-wall-item">' . $img
                . '<figcaption>' . esc_html($name !== '' ? $name : self::t('dogcard.sponsor_wall_anon', 'Pate')) . '</figcaption></figure>';
        }
        if (!$items) {
            return '';
        }
        $dog_name = self::dog_public_name($dog_id);
        ob_start();
        ?>
        <div class="sod-dog-detail-section sod-sponsor-wall-section">
            <h2><?php echo esc_html(self::tpl('dogcard.sponsor_wall_title_tpl', 'Die Paten von {name}', ['name' => $dog_name])); ?></h2>
            <p><?php echo esc_html(self::t('dogcard.sponsor_wall_lead', 'Diese Menschen unterstützen bereits regelmäßig — danke!')); ?></p>
            <div class="sod-sponsor-wall">
                <?php echo implode('', $items); // phpcs:ignore — Teile sind bereits escaped ?>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    private static function render_dauerauftrag_instructions(array $params): void
    {
        $bank = self::bank_details();
        $amount = number_format((float)$params['amount'], 2, ',', '.');
        $reference = 'Patenschaft ' . $params['dog_name'] . ' - ' . $params['pate_name'];
        $qr_data_uri = self::amount_qr_data_uri((float)$params['amount'], $reference);

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html(self::t('dogcard.signup_bank_title', 'Dauerauftrag einrichten')); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
        </head>
        <body style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:60px 20px;line-height:1.6;">
            <h1 style="font-size:1.375rem;"><?php echo esc_html(self::t('dogcard.signup_bank_heading', 'Danke! Fast geschafft.')); ?></h1>
            <p><?php echo esc_html(self::t('dogcard.signup_bank_lead', 'Richte bei deiner Bank einmalig einen monatlichen Dauerauftrag mit folgenden Daten ein — dein Zertifikat kommt automatisch per E-Mail, sobald wir die erste Zahlung bestätigt haben.')); ?></p>
            <table style="width:100%;border-collapse:collapse;margin:24px 0;">
                <tr><td style="padding:8px 0;color:#666;"><?php echo esc_html(self::t('dogcard.signup_bank_holder', 'Kontoinhaber')); ?></td><td style="padding:8px 0;font-weight:700;"><?php echo esc_html($bank['name']); ?></td></tr>
                <tr><td style="padding:8px 0;color:#666;">IBAN</td><td style="padding:8px 0;font-weight:700;"><?php echo esc_html($bank['iban']); ?></td></tr>
                <tr><td style="padding:8px 0;color:#666;">BIC</td><td style="padding:8px 0;font-weight:700;"><?php echo esc_html($bank['bic']); ?></td></tr>
                <tr><td style="padding:8px 0;color:#666;"><?php echo esc_html(self::t('dogcard.signup_bank_amount', 'Monatlicher Betrag')); ?></td><td style="padding:8px 0;font-weight:700;"><?php echo esc_html($amount); ?> €</td></tr>
                <tr><td style="padding:8px 0;color:#666;"><?php echo esc_html(self::t('dogcard.signup_bank_reference', 'Verwendungszweck')); ?></td><td style="padding:8px 0;font-weight:700;"><?php echo esc_html($reference); ?></td></tr>
            </table>
            <p style="text-align:center;">
                <?php if ($qr_data_uri !== '') : ?>
                    <img src="<?php echo esc_attr($qr_data_uri); ?>" alt="QR-Code mit Betrag für Banking-App" width="200" height="200">
                <?php else : ?>
                    <img src="<?php echo esc_url(self::epc_qr_url()); ?>" alt="QR-Code für Banking-App" width="180" height="180">
                <?php endif; ?>
            </p>
            <p style="font-size:.9rem;color:#666;"><?php echo esc_html(self::t('dogcard.signup_bank_qr_note', 'Scanne den Code mit deiner Banking-App — falls sie „Dauerauftrag" oder „monatlich wiederholen" anbietet, wähle das dort. Sonst richte den Dauerauftrag einfach manuell mit obigen Daten ein.')); ?></p>
            <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="flex:1 1 220px;">
                    <input type="hidden" name="action" value="sod_sponsor_bank_confirm">
                    <input type="hidden" name="sponsor_id" value="<?php echo esc_attr((string)$params['sponsor_id']); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr((string)$params['token']); ?>">
                    <button type="submit" style="width:100%;padding:14px 16px;background:#204060;color:#fff;border:0;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;">
                        <?php echo esc_html(self::t('dogcard.signup_bank_confirm_button', 'Ich habe den Dauerauftrag eingerichtet')); ?>
                    </button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="flex:1 1 160px;">
                    <input type="hidden" name="action" value="sod_sponsor_bank_cancel">
                    <input type="hidden" name="sponsor_id" value="<?php echo esc_attr((string)$params['sponsor_id']); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr((string)$params['token']); ?>">
                    <button type="submit" style="width:100%;padding:14px 16px;background:transparent;color:#555;border:1px solid #ccc;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;">
                        <?php echo esc_html(self::t('dogcard.signup_bank_cancel_button', 'Doch nicht — abbrechen')); ?>
                    </button>
                </form>
            </div>
            <p style="font-size:.85rem;color:#666;margin-top:14px;"><?php echo esc_html(self::t('dogcard.signup_bank_confirm_note', 'Bitte bestätige erst, wenn du den Dauerauftrag bei deiner Bank tatsächlich eingerichtet hast. Erst danach beginnt die Frist, innerhalb der wir den Zahlungseingang prüfen.')); ?></p>
        </body>
        </html>
        <?php
    }

    public static function handle_sponsor_bank_confirm(): void
    {
        self::handle_sponsor_bank_action('confirm');
    }

    public static function handle_sponsor_bank_cancel(): void
    {
        self::handle_sponsor_bank_action('cancel');
    }

    private static function handle_sponsor_bank_action(string $action): void
    {
        $sponsor_id = absint($_POST['sponsor_id'] ?? 0);
        $token = sanitize_text_field(wp_unslash((string)($_POST['token'] ?? '')));
        $stored_token = $sponsor_id > 0 ? (string)get_post_meta($sponsor_id, 'sod_sponsor_bank_token', true) : '';
        $valid = $sponsor_id > 0
            && $token !== ''
            && $stored_token !== ''
            && get_post_type($sponsor_id) === 'sod_sponsor'
            && (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true) === 'dauerauftrag'
            && hash_equals($stored_token, $token);

        if (!$valid) {
            self::render_sponsor_bank_response(
                self::t('dogcard.signup_bank_invalid_title', 'Link ungültig'),
                self::t('dogcard.signup_bank_invalid_text', 'Dieser Link ist ungültig oder wurde bereits verwendet.')
            );
            exit;
        }

        $already_received = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1';

        if ($action === 'cancel') {
            if (!$already_received) {
                $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
                wp_clear_scheduled_hook('sod_sponsor_payment_deadline', [$sponsor_id]);
                update_post_meta($sponsor_id, 'sod_sponsor_status', 'beendet');
                self::update_sponsor_retention($sponsor_id, 'beendet');
                self::append_sponsor_note($sponsor_id, 'Patenschaft vom Besucher direkt nach der Einrichtung abgebrochen (Dauerauftrag nicht eingerichtet), ' . date_i18n('d.m.Y H:i') . ' Uhr.');
                if ($dog_id > 0) {
                    self::sync_dog_public_sponsors($dog_id);
                }
            }
            self::render_sponsor_bank_response(
                self::t('dogcard.signup_bank_cancelled_title', 'Alles klar'),
                self::t('dogcard.signup_bank_cancelled_text', 'Wir haben die Patenschaft storniert. Du kannst jederzeit einen neuen Versuch starten.')
            );
            exit;
        }

        if (!$already_received && trim((string)get_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', true)) === '') {
            update_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', gmdate('c'));
            self::schedule_sponsor_payment_deadline($sponsor_id);
            self::append_sponsor_note($sponsor_id, 'Besucher hat bestätigt, den Dauerauftrag eingerichtet zu haben, ' . date_i18n('d.m.Y H:i') . ' Uhr. 24-Stunden-Frist bis zur Zahlungsbestätigung gestartet.');
        }
        self::render_sponsor_bank_response(
            self::t('dogcard.signup_bank_confirmed_title', 'Danke für deine Bestätigung'),
            self::t('dogcard.signup_bank_confirmed_text', 'Wir haben deine Angabe vermerkt. Sobald der Zahlungseingang bestätigt ist, senden wir dir automatisch dein Zertifikat per E-Mail.')
        );
        exit;
    }

    private static function render_sponsor_bank_response(string $title, string $text): void
    {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html($title); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
        </head>
        <body style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:60px 20px;line-height:1.6;">
            <h1 style="font-size:1.375rem;"><?php echo esc_html($title); ?></h1>
            <p><?php echo esc_html($text); ?></p>
        </body>
        </html>
        <?php
    }

    public static function maybe_cancel_paypal_signup_return(): void
    {
        $return_state = sanitize_text_field((string)($_GET['patenschaft'] ?? ''));
        if (!in_array($return_state, ['abgebrochen', 'danke'], true)) {
            return;
        }

        $sponsor_id = absint($_GET['sod_sponsor_id'] ?? 0);
        $token_key = $return_state === 'abgebrochen' ? 'sod_cancel_token' : 'sod_return_token';
        $token = sanitize_text_field(wp_unslash((string)($_GET[$token_key] ?? '')));
        if ($sponsor_id <= 0 || $token === '') {
            return;
        }

        $stored_token = (string)get_post_meta($sponsor_id, 'sod_sponsor_paypal_token', true);
        $valid = $stored_token !== ''
            && get_post_type($sponsor_id) === 'sod_sponsor'
            && (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true) === 'paypal'
            && hash_equals($stored_token, $token);

        if (!$valid) {
            return;
        }

        // Die Rückkehr von PayPal bestätigt nur, dass der Besucher dort fertig ist.
        // Der Zahlungseingang darf ausschließlich über die verifizierte IPN-Meldung
        // von PayPal als bezahlt markiert werden.
        if ($return_state === 'danke') {
            if (trim((string)get_post_meta($sponsor_id, 'sod_sponsor_paypal_returned_at', true)) === '') {
                update_post_meta($sponsor_id, 'sod_sponsor_paypal_returned_at', gmdate('c'));
                update_post_meta($sponsor_id, 'sod_sponsor_paypal_checkout_closed', '1');
                self::append_sponsor_note($sponsor_id, 'PayPal-Vorgang vom Besucher abgeschlossen (zurueck zur Website geklickt), ' . date_i18n('d.m.Y H:i') . ' Uhr. Wartet auf PayPal-Zahlungsbestätigung.');
            }
            return;
        }

        $already_received = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1';
        $already_closed = (string)get_post_meta($sponsor_id, 'sod_sponsor_status', true) === 'beendet';

        if (!$already_received && !$already_closed) {
            $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
            wp_clear_scheduled_hook('sod_sponsor_payment_deadline', [$sponsor_id]);
            update_post_meta($sponsor_id, 'sod_sponsor_status', 'beendet');
            self::update_sponsor_retention($sponsor_id, 'beendet');
            self::append_sponsor_note($sponsor_id, 'Patenschaft vom Besucher bei PayPal abgebrochen (zurueck zur Website geklickt), ' . date_i18n('d.m.Y H:i') . ' Uhr.');
            if ($dog_id > 0) {
                self::sync_dog_public_sponsors($dog_id);
            }
        }
    }

    private static function render_paypal_subscribe_redirect(array $params): void
    {
        $paypal_email = (string)get_option('sod_paypal_email', '');
        $ipn_url = admin_url('admin-post.php?action=sod_paypal_ipn');
        $detail_url = get_permalink((int)$params['dog_id']) ?: home_url('/');
        $return_url = add_query_arg([
            'patenschaft' => 'danke',
            'sod_sponsor_id' => (int)$params['sponsor_id'],
            'sod_return_token' => (string)$params['token'],
        ], $detail_url) . '#pate-werden';
        $cancel_url = add_query_arg([
            'patenschaft' => 'abgebrochen',
            'sod_sponsor_id' => (int)$params['sponsor_id'],
            'sod_cancel_token' => (string)$params['token'],
        ], $detail_url) . '#pate-werden';
        $amount = number_format((float)$params['amount'], 2, '.', '');

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html(self::t('dogcard.signup_redirect_title', 'Weiterleitung zu PayPal ...')); ?></title>
            <meta name="robots" content="noindex">
        </head>
        <body style="font-family:sans-serif;text-align:center;padding:80px 20px;">
            <p><?php echo esc_html(self::t('dogcard.signup_redirect_text', 'Du wirst zu PayPal weitergeleitet, um dein monatliches Patenschafts-Abo abzuschliessen ...')); ?></p>
            <form id="sod-paypal-subscribe-form" action="https://www.paypal.com/cgi-bin/webscr" method="post">
                <input type="hidden" name="cmd" value="_xclick-subscriptions">
                <input type="hidden" name="business" value="<?php echo esc_attr($paypal_email); ?>">
                <input type="hidden" name="item_name" value="<?php echo esc_attr('Patenschaft fuer ' . $params['dog_name']); ?>">
                <input type="hidden" name="currency_code" value="EUR">
                <input type="hidden" name="a3" value="<?php echo esc_attr($amount); ?>">
                <input type="hidden" name="p3" value="1">
                <input type="hidden" name="t3" value="M">
                <input type="hidden" name="src" value="1">
                <input type="hidden" name="sra" value="1">
                <input type="hidden" name="no_shipping" value="1">
                <input type="hidden" name="no_note" value="1">
                <input type="hidden" name="custom" value="<?php echo esc_attr((string)$params['token']); ?>">
                <input type="hidden" name="notify_url" value="<?php echo esc_attr($ipn_url); ?>">
                <input type="hidden" name="return" value="<?php echo esc_attr($return_url); ?>">
                <input type="hidden" name="cancel_return" value="<?php echo esc_attr($cancel_url); ?>">
                <noscript><button type="submit"><?php echo esc_html(self::t('dogcard.signup_redirect_btn', 'Weiter zu PayPal')); ?></button></noscript>
            </form>
            <script>document.getElementById('sod-paypal-subscribe-form').submit();</script>
        </body>
        </html>
        <?php
    }

    private static function maybe_activate_sponsor(int $sponsor_id, string $new_status): void
    {
        if ($new_status !== 'aktiv') {
            return;
        }
        self::update_sponsor_retention($sponsor_id, 'aktiv');
        if ((string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) !== '1') {
            if (trim((string)get_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', true)) === '') {
                update_post_meta($sponsor_id, 'sod_sponsor_provisional_started_at', gmdate('c'));
            }
            self::schedule_sponsor_payment_deadline($sponsor_id);
        }
        self::sync_dog_public_sponsors((int)get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
    }

    private static function send_sponsor_certificate_pdf_auto(int $sponsor_id, bool $force = false): bool
    {
        if (!$force && trim((string)get_post_meta($sponsor_id, 'sod_sponsor_certificate_sent_at', true)) !== '') {
            return true;
        }
        $email = sanitize_email((string)get_post_meta($sponsor_id, 'sod_sponsor_email', true));
        if ($email === '') {
            return false;
        }
        $private_dir = self::private_upload_dir();
        if ($private_dir === '') {
            return false;
        }

        try {
            $pdf = self::sponsor_certificate_pdf($sponsor_id);
        } catch (\Throwable $e) {
            return false;
        }
        $filename = self::sponsor_certificate_filename($sponsor_id);
        $path = trailingslashit($private_dir) . $filename;
        file_put_contents($path, $pdf);

        $pate_name = get_the_title($sponsor_id) ?: 'liebe/r Pate/Patin';
        $payment_method = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true);
        $payment_confirmed = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_received', true) === '1';
        $confirmation_line = !$payment_confirmed && $force
            ? 'Deine Patenschaft wurde bei uns erfasst.'
            : ($payment_method === 'paypal'
                ? 'Deine erste PayPal-Zahlung wurde bestätigt.'
                : 'Deine Patenschaft wurde bestätigt.');
        $body = "Liebe/r {$pate_name},\n\n"
            . "herzlichen Dank, dass du Pate/Patin geworden bist! {$confirmation_line}\n"
            . "Im Anhang findest du dein Patenschaftszertifikat.\n\n"
            . "Danke, dass du uns unterstützt!\n\n"
            . self::org()['name'];
        update_post_meta($sponsor_id, 'sod_sponsor_certificate_last_attempt_at', gmdate('c'));
        $sent = wp_mail(
            $email,
            'Willkommen als Pate/Patin - dein Zertifikat',
            $body,
            array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers()),
            [$path]
        );

        @unlink($path);
        if ($sent) {
            $sent_at = gmdate('c');
            update_post_meta($sponsor_id, 'sod_sponsor_certificate_sent_at', $sent_at);
            delete_post_meta($sponsor_id, 'sod_sponsor_certificate_error');
            self::append_sponsor_note($sponsor_id, 'Patenschaftszertifikat ' . ($force ? 'manuell' : 'automatisch') . ' per E-Mail versendet am ' . date_i18n('d.m.Y H:i') . ' Uhr.');
        } else {
            update_post_meta($sponsor_id, 'sod_sponsor_certificate_error', 'wp_mail_failed');
        }
        return $sent;
    }

    public static function handle_application(): void
    {
        $redirect = wp_get_referer() ?: home_url('/');
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) {
            wp_safe_redirect(add_query_arg('sod_status', 'spam', $redirect));
            exit;
        }
        if (!self::application_token_ok()) {
            wp_safe_redirect(add_query_arg('sod_status', 'spam', $redirect));
            exit;
        }
        if (!empty($_POST['website']) || (time() - (int)($_POST['started_at'] ?? 0)) < 3 || empty($_POST['consent'])) {
            wp_safe_redirect(add_query_arg('sod_status', 'spam', $redirect));
            exit;
        }
        if (!self::application_rate_limit_ok()) {
            wp_safe_redirect(add_query_arg('sod_status', 'spam', $redirect));
            exit;
        }

        $email = sanitize_email((string)($_POST['email'] ?? ''));
        if ($email === '') {
            wp_safe_redirect(add_query_arg('sod_status', 'email', $redirect));
            exit;
        }

        $fields = [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'dog_name' => 'Hund',
            'interest' => 'Interesse',
            'address' => 'Wohnort',
            'experience' => 'Erfahrung',
            'message' => 'Nachricht',
        ];
        $values = [];
        $content = [];
        $limits = self::application_field_limits();
        foreach ($fields as $key => $label) {
            $raw_value = $key === 'email' ? $email : sanitize_textarea_field((string)($_POST[$key] ?? ''));
            $values[$key] = self::limited_text($raw_value, $limits[$key] ?? 500);
            $content[] = $label . ': ' . $values[$key];
        }
        if (self::application_is_spam($values)) {
            wp_safe_redirect(add_query_arg('sod_status', 'spam', $redirect));
            exit;
        }
        $dog_id = absint($_POST['dog_id'] ?? 0);
        if ($dog_id > 0 && get_post_type($dog_id) !== 'sod_dog') {
            $dog_id = 0;
        }
        if ($dog_id > 0) {
            $content[] = 'Hund-ID: ' . $dog_id;
        }

        $post_id = wp_insert_post([
            'post_type' => 'sod_application',
            'post_status' => 'private',
            'post_title' => 'Anfrage von ' . trim($values['first_name'] . ' ' . $values['last_name']),
            'post_content' => implode("\n", $content),
        ]);
        if ($post_id && !is_wp_error($post_id)) {
            foreach ($fields as $key => $label) {
                update_post_meta($post_id, $key, $values[$key]);
            }
            update_post_meta($post_id, 'dog_id', $dog_id);
            update_post_meta($post_id, 'delete_after', gmdate('Y-m-d', time() + 30 * DAY_IN_SECONDS));
            wp_mail(self::notification_emails(), 'Neue Shield-of-Dogs-Anfrage' . self::application_ticket_tag((int)$post_id), implode("\n", $content), array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers()));
            wp_mail(
                $email,
                'Ihre Anfrage bei ' . self::org()['name'] . ' ist eingegangen' . self::application_ticket_tag((int)$post_id),
                self::application_confirmation_email_html($values),
                array_merge(['Content-Type: text/html; charset=UTF-8'], self::application_mail_headers())
            );
        }

        wp_safe_redirect(add_query_arg('sod_status', 'gesendet', $redirect));
        exit;
    }

    private static function application_field_limits(): array
    {
        return [
            'first_name' => 80,
            'last_name' => 80,
            'email' => 160,
            'phone' => 60,
            'dog_name' => 120,
            'interest' => 80,
            'address' => 220,
            'experience' => 1500,
            'message' => 2500,
        ];
    }

    private static function limited_text(string $value, int $limit): string
    {
        $value = trim($value);
        if ($limit <= 0) {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
        }
        return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
    }

    private static function application_form_token(int $started_at): string
    {
        return hash_hmac('sha256', (string)$started_at, wp_salt('auth'));
    }

    private static function application_token_ok(): bool
    {
        $nonce = (string)($_POST['sod_application_nonce'] ?? '');
        if ($nonce !== '' && wp_verify_nonce($nonce, 'sod_public_application')) {
            return true;
        }

        $started_at = (int)($_POST['started_at'] ?? 0);
        $token = (string)($_POST['form_token'] ?? '');
        if ($started_at <= 0 || $token === '') {
            return false;
        }
        if (abs(time() - $started_at) > 3 * DAY_IN_SECONDS) {
            return false;
        }
        return hash_equals(self::application_form_token($started_at), $token);
    }

    private static function application_rate_limit_ok(): bool
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return true;
        }
        $key = 'sod_app_rate_' . md5($ip);
        $count = (int)get_transient($key);
        if ($count >= 3) {
            return false;
        }
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function sponsor_signup_form_token(int $started_at): string
    {
        return hash_hmac('sha256', 'sponsor_signup:' . $started_at, wp_salt('auth'));
    }

    private static function sponsor_signup_token_ok(): bool
    {
        $nonce = (string)($_POST['sod_sponsor_signup_nonce'] ?? '');
        if ($nonce !== '' && wp_verify_nonce($nonce, 'sod_public_sponsor_signup')) {
            return true;
        }

        $started_at = (int)($_POST['started_at'] ?? 0);
        $token = (string)($_POST['form_token'] ?? '');
        if ($started_at <= 0 || $token === '') {
            return false;
        }
        if (abs(time() - $started_at) > 3 * DAY_IN_SECONDS) {
            return false;
        }
        return hash_equals(self::sponsor_signup_form_token($started_at), $token);
    }

    private static function sponsor_signup_rate_limit_ok(): bool
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return true;
        }
        $key = 'sod_sponsor_signup_rate_' . md5($ip);
        $count = (int)get_transient($key);
        if ($count >= 3) {
            return false;
        }
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function application_is_spam(array $values): bool
    {
        $text = mb_strtolower(implode(' ', $values));
        if (preg_match_all('#https?://#i', $text) > 1) {
            return true;
        }
        $spam_words = ['viagra', 'casino', 'bitcoin', 'crypto', 'forex', 'seo service', 'backlink', 'porn', 'escort', 'payday loan'];
        foreach ($spam_words as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }
        return false;
    }

    public static function application_to_interest(): void
    {
        $application_id = absint($_GET['application_id'] ?? 0);
        if (!$application_id || !current_user_can('edit_post', $application_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_application_to_interest_' . $application_id);

        $application = get_post($application_id);
        if (!$application instanceof WP_Post || $application->post_type !== 'sod_application') {
            wp_die('Anfrage nicht gefunden.');
        }

        $existing_interest_id = absint(get_post_meta($application_id, 'sod_created_interest_id', true));
        if ($existing_interest_id > 0 && get_post_type($existing_interest_id) === 'sod_interest') {
            wp_safe_redirect(get_edit_post_link($existing_interest_id, 'raw') ?: admin_url('edit.php?post_type=sod_interest'));
            exit;
        }

        $first_name = trim((string)get_post_meta($application_id, 'first_name', true));
        $last_name = trim((string)get_post_meta($application_id, 'last_name', true));
        $name = trim($first_name . ' ' . $last_name);
        $dog_name = trim((string)get_post_meta($application_id, 'dog_name', true));
        $title = $name !== '' ? $name : 'Interessent aus Anfrage';
        if ($dog_name !== '') {
            $title .= ' - ' . $dog_name;
        }

        $interest_id = wp_insert_post([
            'post_type' => 'sod_interest',
            'post_status' => 'private',
            'post_title' => sanitize_text_field($title),
        ]);
        if (!$interest_id || is_wp_error($interest_id)) {
            wp_die('Interessent konnte nicht angelegt werden.');
        }

        $interest = self::map_application_interest((string)get_post_meta($application_id, 'interest', true));
        update_post_meta($interest_id, 'sod_interest_status', 'neu');
        update_post_meta($interest_id, 'sod_interest_type', $interest);
        update_post_meta($interest_id, 'sod_interest_dog', (string)absint(get_post_meta($application_id, 'dog_id', true)));
        update_post_meta($interest_id, 'sod_interest_email', sanitize_email((string)get_post_meta($application_id, 'email', true)));
        update_post_meta($interest_id, 'sod_interest_phone', sanitize_text_field((string)get_post_meta($application_id, 'phone', true)));
        update_post_meta($interest_id, 'sod_interest_location', sanitize_text_field((string)get_post_meta($application_id, 'address', true)));

        $notes = [
            'Übernommen aus Anfrage #' . $application_id . ' am ' . date_i18n('d.m.Y H:i'),
            '',
            'Name: ' . ($name !== '' ? $name : '-'),
            'Hund: ' . ($dog_name !== '' ? $dog_name : '-'),
            'Interesse: ' . (string)get_post_meta($application_id, 'interest', true),
            'Erfahrung: ' . (string)get_post_meta($application_id, 'experience', true),
            'Nachricht: ' . (string)get_post_meta($application_id, 'message', true),
        ];
        update_post_meta($interest_id, 'sod_interest_notes', sanitize_textarea_field(implode("\n", $notes)));
        update_post_meta($interest_id, 'sod_source_application_id', (string)$application_id);
        update_post_meta($application_id, 'sod_created_interest_id', (string)$interest_id);
        update_post_meta($application_id, 'sod_transfer_status', 'Interessent angelegt');

        wp_safe_redirect(get_edit_post_link($interest_id, 'raw') ?: admin_url('edit.php?post_type=sod_interest'));
        exit;
    }

    public static function handle_application_toggle_status(): void
    {
        $application_id = absint($_GET['application_id'] ?? 0);
        if (!$application_id || !current_user_can('edit_post', $application_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_application_toggle_status_' . $application_id);

        $application = get_post($application_id);
        if (!$application instanceof WP_Post || $application->post_type !== 'sod_application') {
            wp_die('Anfrage nicht gefunden.');
        }

        $status = (string)get_post_meta($application_id, 'sod_application_status', true) ?: 'offen';
        update_post_meta($application_id, 'sod_application_status', $status === 'beendet' ? 'offen' : 'beendet');

        wp_safe_redirect(get_edit_post_link($application_id, 'raw') ?: admin_url('edit.php?post_type=sod_application'));
        exit;
    }

    public static function application_to_case(): void
    {
        $application_id = absint($_GET['application_id'] ?? 0);
        if (!$application_id || !current_user_can('edit_post', $application_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_application_to_case_' . $application_id);

        $application = get_post($application_id);
        if (!$application instanceof WP_Post || $application->post_type !== 'sod_application') {
            wp_die('Anfrage nicht gefunden.');
        }

        $dog_id = absint(get_post_meta($application_id, 'dog_id', true));
        $name = trim((string)get_post_meta($application_id, 'first_name', true) . ' ' . (string)get_post_meta($application_id, 'last_name', true));
        $dog_name = $dog_id > 0 ? get_the_title($dog_id) : trim((string)get_post_meta($application_id, 'dog_name', true));
        $case_id = self::create_case([
            'title' => trim('Vermittlung - ' . ($dog_name ?: 'Hund') . ' - ' . ($name ?: 'Anfrage')),
            'dog_id' => $dog_id,
            'adopter' => self::contact_block_from_application($application_id),
            'notes' => 'Gestartet aus Anfrage #' . $application_id . ' am ' . date_i18n('d.m.Y H:i'),
        ]);

        if (!$case_id) {
            wp_die('Vermittlungsakte konnte nicht angelegt werden.');
        }
        wp_safe_redirect(add_query_arg('sod_notice', 'case_created', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
        exit;
    }

    public static function interest_to_case(): void
    {
        $interest_id = absint($_GET['interest_id'] ?? 0);
        if (!$interest_id || !current_user_can('edit_post', $interest_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_interest_to_case_' . $interest_id);

        $interest = get_post($interest_id);
        if (!$interest instanceof WP_Post || $interest->post_type !== 'sod_interest') {
            wp_die('Interessent nicht gefunden.');
        }

        $dog_id = absint(get_post_meta($interest_id, 'sod_interest_dog', true));
        $case_id = self::create_case([
            'title' => 'Vermittlung - ' . get_the_title($interest_id),
            'dog_id' => $dog_id,
            'adopter' => self::contact_block_from_interest($interest_id),
            'notes' => 'Gestartet aus Interessent #' . $interest_id . ' am ' . date_i18n('d.m.Y H:i'),
        ]);

        if (!$case_id) {
            wp_die('Vermittlungsakte konnte nicht angelegt werden.');
        }
        wp_safe_redirect(add_query_arg('sod_notice', 'case_created', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
        exit;
    }

    public static function admin_menu(): void
    {
        add_dashboard_page(
            'SOD Mitarbeiter-App',
            'SOD Mitarbeiter-App',
            'read',
            'sod-staff-app',
            [self::class, 'staff_app_admin_redirect']
        );

        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'Tierheim-Übersicht',
            'Tierheim-Übersicht',
            'edit_sod_dogs',
            'sod-shelter-overview',
            [self::class, 'shelter_overview_page']
        );

        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'Lagerübersicht',
            'Lagerübersicht',
            'edit_sod_items',
            'sod-inventory-overview',
            [self::class, 'inventory_overview_page']
        );

        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'Finanzbericht',
            'Finanzbericht',
            'edit_sod_finances',
            'sod-finance-report',
            [self::class, 'finance_report_page']
        );

        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'SOD Einstellungen',
            'Einstellungen',
            'manage_options',
            'sod-settings',
            [self::class, 'settings_page']
        );

        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'Nicht zugeordnete Antworten',
            'Nicht zugeordnete Antworten',
            'manage_options',
            'sod-unmatched-replies',
            [self::class, 'unmatched_replies_page']
        );

        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'Shield Import',
            'JSON Import',
            'manage_options',
            'sod-json-import',
            [self::class, 'import_page']
        );

        if (self::can_access_sod_items() && !self::can_access_sod_dogs()) {
            add_menu_page(
                'Lagerübersicht',
                'SOD Lager',
                'edit_sod_items',
                'sod-inventory-standalone',
                [self::class, 'inventory_overview_page'],
                'dashicons-archive',
                26
            );
        }
    }

    /**
     * Die Untermenue-Reihenfolge unter "Hunde" ergibt sich normalerweise aus einer Mischung
     * aus automatisch von WordPress fuer jeden Custom Post Type ergaenzten Eintraegen und den
     * add_submenu_page()-Aufrufen oben - beides in einer fuer den Betreiber nicht steuerbaren
     * Reihenfolge. Deshalb hier bewusst per Hand auf die gewuenschte Reihenfolge sortiert:
     * Hunde, Hund anlegen, Anfragen, Paten, Spenden & Ausgaben, Finanzbericht, Einstellungen zuerst, der Rest
     * bleibt danach in der bisherigen Reihenfolge. Muss auf einer sehr spaeten Prioritaet
     * laufen, damit alle Eintraege (auch die von anderen CPTs) bereits vorhanden sind.
     */
    public static function reorder_dog_submenu(): void
    {
        global $submenu;
        $key = 'edit.php?post_type=sod_dog';
        if (empty($submenu[$key])) {
            return;
        }
        $priority_slugs = [
            'edit.php?post_type=sod_dog',
            'post-new.php?post_type=sod_dog',
            'edit.php?post_type=sod_application',
            'edit.php?post_type=sod_sponsor',
            'edit.php?post_type=sod_finance',
            'sod-finance-report',
            'sod-settings',
        ];
        $items = $submenu[$key];
        $ordered = [];
        foreach ($priority_slugs as $slug) {
            foreach ($items as $i => $item) {
                if (($item[2] ?? '') === $slug) {
                    $ordered[] = $item;
                    unset($items[$i]);
                    break;
                }
            }
        }
        $submenu[$key] = array_merge($ordered, array_values($items));
    }

    public static function brand_admin_menu(): void
    {
        if (!self::is_restricted_staff_admin()) {
            return;
        }

        $allowed = ['index.php', 'edit.php?post_type=sod_dog', 'sod-inventory-standalone'];
        foreach ($GLOBALS['menu'] ?? [] as $item) {
            $slug = (string)($item[2] ?? '');
            if ($slug !== '' && !in_array($slug, $allowed, true)) {
                remove_menu_page($slug);
            }
        }
    }

    public static function restrict_staff_admin_pages(): void
    {
        if (!is_admin() || wp_doing_ajax() || !self::is_restricted_staff_admin()) {
            return;
        }

        $script = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (in_array($script, ['admin-post.php', 'async-upload.php', 'media-upload.php', 'profile.php', 'admin-ajax.php'], true)) {
            return;
        }

        if (!self::is_staff_allowed_admin_request($script)) {
            wp_safe_redirect(self::staff_landing_url());
            exit;
        }
    }

    public static function brand_admin_bar(WP_Admin_Bar $admin_bar): void
    {
        if (!self::is_restricted_staff_admin()) {
            return;
        }

        foreach (['wp-logo', 'comments', 'new-content', 'customize', 'updates', 'search'] as $node) {
            $admin_bar->remove_node($node);
        }
        $admin_bar->add_node([
            'id' => 'sod-home',
            'title' => 'SOD Verwaltung',
            'href' => self::staff_landing_url(),
            'meta' => ['class' => 'sod-adminbar-home'],
        ]);
        $admin_bar->add_node([
            'id' => 'sod-staff-app',
            'title' => 'Mitarbeiter-App',
            'href' => self::staff_app_url(),
            'meta' => ['class' => 'sod-adminbar-app'],
        ]);
    }

    public static function admin_brand_head(): void
    {
        $logo = esc_url(self::admin_logo_uri());
        echo '<style>:root{--sod-admin-logo:url("' . $logo . '");}.sod-admin-back-button{position:fixed;right:22px;bottom:22px;z-index:100000;display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:11px 16px;border:0;border-radius:999px;background:#f3c74f;color:#162531;font-weight:800;box-shadow:0 12px 28px rgba(32,64,96,.22);cursor:pointer}.sod-admin-back-button:hover,.sod-admin-back-button:focus{background:#204060;color:#fff}@media(max-width:782px){.sod-admin-back-button{right:14px;bottom:14px;min-height:48px;padding:12px 18px}}</style>';
    }

    public static function staff_admin_back_button(): void
    {
        if (!self::is_restricted_staff_admin()) {
            return;
        }
        ?>
        <button type="button" class="sod-admin-back-button" id="sod-admin-back-button" aria-label="Zurück">‹ Zurück</button>
        <script>
            document.getElementById('sod-admin-back-button')?.addEventListener('click', () => {
                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }
                window.location.href = '<?php echo esc_js(self::staff_app_url()); ?>';
            });
        </script>
        <?php
    }

    public static function login_branding(): void
    {
        ?>
        <style>
            body.login {
                background: linear-gradient(180deg,#eef4f6 0%,#ffffff 55%);
                font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
            }
            /*
             * Das Standard-Logo/H1 von WordPress wird ausgeblendet, weil sich die
             * interne Markup-/Selektor-Struktur je nach WP-Version aendert (zuletzt
             * unter WP 7.0 unbemerkt gebrochen: kein Logo mehr sichtbar). Stattdessen
             * rendert sod_login_branded_header() ueber den login_header-Hook ein
             * eigenes, von WP-Core unabhaengiges Logo direkt im Markup.
             */
            body.login #login h1 { display: none; }
            body.login #loginform,
            body.login #lostpasswordform,
            body.login #registerform {
                border: 1px solid #d7dde2;
                border-radius: 14px;
                box-shadow: 0 14px 34px rgba(32,64,96,.12);
                padding: 26px 24px;
            }
            body.login .button-primary {
                background: #204060;
                border-color: #204060;
                text-shadow: none;
                box-shadow: none;
            }
            body.login .button-primary:hover,
            body.login .button-primary:focus {
                background: #f3c74f;
                border-color: #d8bd53;
                color: #172b36;
            }
            body.login #nav a,
            body.login #backtoblog a {
                color: #204060;
            }
            body.login #login_error,
            body.login .message {
                border-left-color: #204060;
                border-radius: 6px;
            }
        </style>
        <?php
    }

    /**
     * Eigenes Logo/Titel oberhalb des Login-Formulars, unabhaengig vom internen
     * WP-Core-Markup (siehe Hinweis in login_branding()).
     */
    public static function login_custom_header(): void
    {
        ?>
        <div style="text-align:center;margin:0 0 18px;">
            <img src="<?php echo esc_url(self::staff_icon_url('192')); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>" width="88" height="88" style="width:88px;height:88px;object-fit:contain;">
            <div style="margin-top:10px;font-size:23px;font-weight:800;color:#204060;letter-spacing:.2px;"><?php echo esc_html(self::org()['name']); ?></div>
        </div>
        <?php
    }

    public static function login_header_url(): string
    {
        return home_url('/');
    }

    public static function login_header_text(): string
    {
        return self::org()['name'] . ' Verwaltung';
    }

    /**
     * Zeigt auf der normalen WP-Login-Seite einen Link zurueck zur Mitglieder-Registrierung -
     * aber nur, wenn der Login ueber den Mitgliederbereich-Redirect aufgerufen wurde (nicht bei
     * jedem normalen Mitarbeiter-/Admin-Login), damit die Seite fuer Staff nicht unnoetig
     * zusaetzliche, fuer sie irrelevante Links zeigt.
     */
    public static function member_login_footer_links(): void
    {
        $redirect = (string)($_GET['redirect_to'] ?? '');
        if (strpos($redirect, 'sod_member') === false) {
            return;
        }
        ?>
        <p style="text-align:center;margin-top:16px;">
            <a href="<?php echo esc_url(self::member_register_url()); ?>">Noch kein Konto? Jetzt als Pate/Patin registrieren</a>
        </p>
        <?php
    }

    public static function admin_body_class(string $classes): string
    {
        return trim($classes . ' sod-branded-admin' . (self::is_restricted_staff_admin() ? ' sod-staff-admin' : ''));
    }

    public static function admin_footer_text(): string
    {
        return self::org()['name'] . ' Verwaltung';
    }

    public static function admin_version_footer(string $text): string
    {
        return self::is_restricted_staff_admin() ? 'SOD Verwaltung ' . self::VERSION : $text;
    }

    private static function admin_logo_uri(): string
    {
        $logo_path = plugin_dir_path(__FILE__) . 'assets/images/logo.png';
        if (is_readable($logo_path)) {
            $logo_data = file_get_contents($logo_path);
            if ($logo_data !== false) {
                return 'data:image/png;base64,' . base64_encode($logo_data);
            }
        }

        return plugin_dir_url(__FILE__) . 'assets/images/logo.png';
    }

    public static function shelter_overview_page(): void
    {
        if (!self::can_access_sod_dogs()) {
            wp_die('Keine Berechtigung.');
        }
        $cards = [
            ['Hunde', self::post_count('sod_dog'), admin_url('edit.php?post_type=sod_dog')],
            ['Offene Aufgaben', self::post_count('sod_task'), admin_url('edit.php?post_type=sod_task')],
            ['Interessenten', self::post_count('sod_interest'), admin_url('edit.php?post_type=sod_interest')],
            ['Vermittlungsakten', self::post_count('sod_case'), admin_url('edit.php?post_type=sod_case')],
            ['Transporte', self::post_count('sod_transport'), admin_url('edit.php?post_type=sod_transport')],
            ['Pflegestellen', self::post_count('sod_foster'), admin_url('edit.php?post_type=sod_foster')],
            ['Paten', self::post_count('sod_sponsor'), admin_url('edit.php?post_type=sod_sponsor')],
            ['Spenden & Ausgaben', self::post_count('sod_finance'), admin_url('edit.php?post_type=sod_finance')],
        ];
        $workflow_cards = [
            ['Hund pflegen', 'Medizin, Dokumente, Fütterung, Medikamente, Quarantäne und Verträglichkeit direkt im Hund hinterlegen.', admin_url('edit.php?post_type=sod_dog')],
            ['Anfrage bearbeiten', 'Interessent anlegen, Status setzen, Wiedervorlage eintragen und bei passender Vermittlung eine Akte erstellen.', admin_url('edit.php?post_type=sod_interest')],
            ['Vermittlung abschließen', 'Vorkontrolle, Schutzvertrag, Zahlung, Transport, Übergabe und Nachkontrolle in der Vermittlungsakte sammeln.', admin_url('edit.php?post_type=sod_case')],
            ['Transparenz sichern', 'Spenden, Sachspenden und Ausgaben laufend erfassen. So lassen sich Monatsberichte leichter erstellen.', admin_url('edit.php?post_type=sod_finance')],
        ];
        ?>
        <div class="wrap sod-shelter-overview">
            <h1>Tierheim-Übersicht</h1>
            <p>Die wichtigsten Verwaltungsbereiche für Tierheim-Alltag, Vermittlung, Transporte und Transparenz.</p>
            <div class="sod-overview-cards">
                <?php foreach ($cards as [$label, $count, $url]) : ?>
                    <a class="sod-overview-card sod-overview-link" href="<?php echo esc_url($url); ?>">
                        <span><?php echo esc_html($label); ?></span>
                        <strong><?php echo esc_html((string)$count); ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
            <h2>Empfohlene Arbeitsweise</h2>
            <div class="sod-admin-help-grid">
                <?php foreach ($workflow_cards as [$title, $text, $url]) : ?>
                    <a class="sod-admin-help-card" href="<?php echo esc_url($url); ?>">
                        <strong><?php echo esc_html($title); ?></strong>
                        <p><?php echo esc_html($text); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function is_restricted_staff_admin(): bool
    {
        return is_user_logged_in()
            && !current_user_can('manage_options')
            && (
                self::can_access_sod_dogs()
                || self::can_access_sod_items()
                || self::can_access_sod_finances()
            );
    }

    private static function staff_landing_url(): string
    {
        return admin_url('index.php');
    }

    private static function staff_primary_area_url(): string
    {
        if (self::can_access_sod_dogs()) {
            return admin_url('edit.php?post_type=sod_dog&page=sod-shelter-overview');
        }
        if (self::can_access_sod_items()) {
            return admin_url('admin.php?page=sod-inventory-standalone');
        }
        if (self::can_access_sod_finances()) {
            return admin_url('edit.php?post_type=sod_dog&page=sod-finance-report');
        }
        return admin_url();
    }

    private static function is_staff_allowed_admin_request(string $script): bool
    {
        if ($script === 'post.php' || $script === 'post-new.php') {
            $post_type = isset($_GET['post_type']) ? sanitize_key((string)$_GET['post_type']) : '';
            if ($post_type === '' && isset($_GET['post'])) {
                $post_type = (string)get_post_type(absint($_GET['post']));
            }
            return in_array($post_type, self::staff_allowed_post_types(), true);
        }

        if ($script === 'index.php') {
            return true;
        }

        if ($script === 'edit.php') {
            $post_type = sanitize_key((string)($_GET['post_type'] ?? ''));
            return in_array($post_type, self::staff_allowed_post_types(), true);
        }

        if ($script === 'admin.php') {
            $page = sanitize_key((string)($_GET['page'] ?? ''));
            return in_array($page, self::staff_allowed_pages(), true);
        }

        return false;
    }

    private static function staff_allowed_post_types(): array
    {
        $types = [];
        if (self::can_access_sod_dogs()) {
            $types = array_merge($types, ['sod_dog', 'sod_application', 'sod_task', 'sod_interest', 'sod_case', 'sod_transport', 'sod_foster', 'sod_sponsor']);
        }
        if (self::can_access_sod_items()) {
            $types[] = 'sod_inventory';
        }
        if (self::can_access_sod_finances()) {
            $types[] = 'sod_finance';
        }
        return array_values(array_unique($types));
    }

    private static function staff_allowed_pages(): array
    {
        $pages = [];
        if (self::can_access_sod_dogs()) {
            $pages[] = 'sod-shelter-overview';
        }
        if (self::can_access_sod_items()) {
            $pages[] = 'sod-inventory-overview';
            $pages[] = 'sod-inventory-standalone';
        }
        if (self::can_access_sod_finances()) {
            $pages[] = 'sod-finance-report';
        }
        return $pages;
    }

    public static function dashboard_widgets(): void
    {
        if (
            self::can_access_sod_dogs()
            || self::can_access_sod_items()
            || self::can_access_sod_finances()
            || current_user_can('manage_options')
        ) {
            wp_add_dashboard_widget(
                'sod_dashboard_functions',
                'SOD Funktionen & Schnellzugriff',
                [self::class, 'dashboard_functions_widget']
            );
            wp_add_dashboard_widget(
                'sod_dashboard_workflow',
                'SOD Arbeitszentrale',
                [self::class, 'dashboard_workflow_widget']
            );
            wp_add_dashboard_widget(
                'sod_dashboard_today',
                'SOD Heute wichtig',
                [self::class, 'dashboard_today_widget']
            );
            wp_add_dashboard_widget(
                'sod_dashboard_compliance',
                'SOD Sicherheit & Datenschutz',
                [self::class, 'dashboard_compliance_widget']
            );
        }

        if (self::can_access_sod_dogs()) {
            wp_add_dashboard_widget(
                'sod_dashboard_due',
                'SOD Heute fällig',
                [self::class, 'dashboard_due_widget']
            );
            wp_add_dashboard_widget(
                'sod_dashboard_shelter',
                'SOD Tierheim-Übersicht',
                [self::class, 'dashboard_shelter_widget']
            );
            wp_add_dashboard_widget(
                'sod_dashboard_photo_needs',
                'SOD Bildbedarf',
                [self::class, 'dashboard_photo_needs_widget']
            );
            wp_add_dashboard_widget(
                'sod_dashboard_photo_reminders',
                'SOD Patenfotos fällig',
                [self::class, 'dashboard_photo_reminders_widget']
            );
        }
        if (self::can_access_sod_items()) {
            wp_add_dashboard_widget(
                'sod_dashboard_inventory',
                'SOD Lagerübersicht',
                [self::class, 'dashboard_inventory_widget']
            );
        }
        if (self::can_access_sod_finances()) {
            wp_add_dashboard_widget(
                'sod_dashboard_finance',
                'SOD Finanzen & Transparenz',
                [self::class, 'dashboard_finance_widget']
            );
        }
    }

    public static function remove_staff_dashboard_widgets(): void
    {
        if (!self::is_restricted_staff_admin()) {
            return;
        }

        foreach ([
            'dashboard_activity',
            'dashboard_browser_nag',
            'dashboard_incoming_links',
            'dashboard_php_nag',
            'dashboard_plugins',
            'dashboard_primary',
            'dashboard_quick_press',
            'dashboard_recent_comments',
            'dashboard_recent_drafts',
            'dashboard_right_now',
            'dashboard_secondary',
            'dashboard_site_health',
        ] as $widget_id) {
            remove_meta_box($widget_id, 'dashboard', 'normal');
            remove_meta_box($widget_id, 'dashboard', 'side');
        }
    }

    public static function dashboard_today_widget(): void
    {
        $cards = [];
        $sections = [];

        if (self::can_access_sod_dogs()) {
            $new_applications = self::recent_open_applications(5);
            $due = self::due_items();
            $prechecks = self::posts_by_meta('sod_case', 'sod_case_status', 'vorkontrolle', 5);
            $open_cases = self::posts_by_meta('sod_case', 'sod_case_status', 'nachkontrolle', 5);
            $data_quality = self::dog_data_quality_items(8);

            $cards[] = ['Neue Anfragen', count($new_applications), admin_url('edit.php?post_type=sod_application')];
            $cards[] = ['Fällige Punkte', count($due), admin_url('index.php#sod_dashboard_due')];
            $cards[] = ['Vorkontrollen', count($prechecks), admin_url('edit.php?post_type=sod_case')];
            $cards[] = ['Datenpflege', count($data_quality), admin_url('edit.php?post_type=sod_dog')];

            $sections[] = [
                'title' => 'Anfragen',
                'empty' => 'Keine neuen Anfragen im Blick.',
                'items' => array_map(
                    static fn (array $item): array => ['label' => $item['title'], 'info' => $item['date'], 'url' => $item['url']],
                    $new_applications
                ),
                'action' => ['Anfragen öffnen', admin_url('edit.php?post_type=sod_application')],
            ];
            $sections[] = [
                'title' => 'Fällig',
                'empty' => 'Keine fälligen Aufgaben, Wiedervorlagen oder Nachkontrollen.',
                'items' => array_slice($due, 0, 5),
                'action' => ['Aufgaben öffnen', admin_url('edit.php?post_type=sod_task')],
            ];
            $sections[] = [
                'title' => 'Vorkontrolle',
                'empty' => 'Keine Akte steht aktuell auf Vorkontrolle.',
                'items' => array_map(
                    static fn (array $item): array => ['label' => $item['title'], 'info' => $item['date'], 'url' => $item['url']],
                    $prechecks
                ),
                'action' => ['Vermittlungsakten öffnen', admin_url('edit.php?post_type=sod_case')],
            ];
            if ($open_cases) {
                $sections[] = [
                    'title' => 'Nachkontrolle',
                    'empty' => '',
                    'items' => array_map(
                        static fn (array $item): array => ['label' => $item['title'], 'info' => $item['date'], 'url' => $item['url']],
                        $open_cases
                    ),
                    'action' => ['Akte prüfen', admin_url('edit.php?post_type=sod_case')],
                ];
            }
            $sections[] = [
                'title' => 'Datenpflege Hunde',
                'empty' => 'Alle aktiven Hunde haben die wichtigsten Angaben.',
                'items' => $data_quality,
                'action' => ['Hunde öffnen', admin_url('edit.php?post_type=sod_dog')],
            ];
        }

        if (self::can_access_sod_items()) {
            $rows = self::inventory_rows('', '', '');
            $low_rows = array_slice(array_filter($rows, static fn (array $row): bool => $row['total_number'] > 0 && $row['total_number'] <= $row['min_number']), 0, 5);
            $cards[] = ['Knapp im Lager', count($low_rows), admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')];
            $sections[] = [
                'title' => 'Lager',
                'empty' => 'Keine knappen Artikel gefunden.',
                'items' => array_map(
                    static fn (array $row): array => ['label' => $row['title'], 'info' => $row['total_label'], 'url' => get_edit_post_link($row['id'], 'raw') ?: admin_url('edit.php?post_type=sod_inventory')],
                    $low_rows
                ),
                'action' => ['Lager öffnen', self::can_access_sod_dogs() ? admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview') : admin_url('admin.php?page=sod-inventory-standalone')],
            ];
        }

        if (self::can_access_sod_finances()) {
            $receipt_candidates = self::finance_receipt_candidates(5);
            $cards[] = ['Belege prüfen', count($receipt_candidates), admin_url('edit.php?post_type=sod_finance')];
            $sections[] = [
                'title' => 'Spenden & Belege',
                'empty' => 'Keine offenen Spendenbelege gefunden.',
                'items' => $receipt_candidates,
                'action' => ['Finanzen öffnen', admin_url('edit.php?post_type=sod_finance')],
            ];
        }

        if (!$cards && !$sections) {
            echo '<p>Für deinen Zugang liegen aktuell keine SOD-Tagespunkte vor.</p>';
            return;
        }

        echo '<div class="sod-today-widget">';
        echo '<div class="sod-dashboard-grid sod-dashboard-grid-3 sod-today-cards">';
        foreach ($cards as [$label, $value, $url]) {
            printf(
                '<a class="sod-dashboard-tile" href="%s"><span>%s</span><strong>%s</strong></a>',
                esc_url($url),
                esc_html($label),
                esc_html((string)$value)
            );
        }
        echo '</div>';

        echo '<div class="sod-today-sections">';
        foreach ($sections as $section) {
            echo '<section class="sod-today-section">';
            printf('<h4>%s</h4>', esc_html((string)$section['title']));
            if (empty($section['items'])) {
                printf('<p class="description">%s</p>', esc_html((string)$section['empty']));
            } else {
                echo '<ul class="sod-dashboard-list">';
                foreach (array_slice((array)$section['items'], 0, 5) as $item) {
                    printf(
                        '<li><a href="%s">%s</a><span>%s</span></li>',
                        esc_url((string)$item['url']),
                        esc_html((string)$item['label']),
                        esc_html((string)$item['info'])
                    );
                }
                echo '</ul>';
            }
            if (!empty($section['action'])) {
                [$label, $url] = $section['action'];
                printf('<p><a class="button button-small" href="%s">%s</a></p>', esc_url((string)$url), esc_html((string)$label));
            }
            echo '</section>';
        }
        echo '</div>';
        echo '</div>';
    }

    public static function dashboard_functions_widget(): void
    {
        $sections = [];

        if (self::can_access_sod_dashboard()) {
            $sections[] = [
                'title' => 'Mitarbeiter-App',
                'links' => [
                    ['SOD Mitarbeiter-App öffnen', self::staff_app_url(), 'Installierbare App für Handy und Desktop'],
                    ['Dashboard', admin_url('index.php'), 'Tageszentrale in WordPress'],
                ],
                'actions' => [
                    ['Mitarbeiter-App öffnen', self::staff_app_url(), 'button button-primary'],
                ],
            ];
        }

        if (self::can_access_sod_dogs()) {
            $sections[] = [
                'title' => 'Hunde & Vermittlung',
                'links' => [
                    ['Tierheim-Übersicht', admin_url('edit.php?post_type=sod_dog&page=sod-shelter-overview'), 'Überblick, Arbeitsweise und Kennzahlen'],
                    ['Hunde in Vermittlung', admin_url('edit.php?post_type=sod_dog'), 'Hunde pflegen, Bilder, Gesundheit und interne Infos'],
                    ['Hund anlegen', admin_url('post-new.php?post_type=sod_dog'), 'Neuen Hund mit Assistent erfassen'],
                    ['Anfragen', admin_url('edit.php?post_type=sod_application'), 'Kontaktanfragen bearbeiten und übernehmen'],
                    ['Interessenten', admin_url('edit.php?post_type=sod_interest'), 'Kontakte, Status und Wiedervorlagen'],
                    ['Vermittlungsakten', admin_url('edit.php?post_type=sod_case'), 'Vorkontrolle, Vertrag, Transport und Nachkontrolle'],
                    ['Aufgaben', admin_url('edit.php?post_type=sod_task'), 'Erinnerungen und To-dos'],
                    ['Transporte', admin_url('edit.php?post_type=sod_transport'), 'Ausreise und Fahrten organisieren'],
                    ['Pflegestellen', admin_url('edit.php?post_type=sod_foster'), 'Kapazitäten und Kontakte'],
                    ['Paten', admin_url('edit.php?post_type=sod_sponsor'), 'Patenschaften verwalten'],
                ],
                'actions' => [
                    ['Neue Anfrage erfassen', admin_url('post-new.php?post_type=sod_application'), 'button'],
                    ['Neue Akte starten', admin_url('post-new.php?post_type=sod_case'), 'button'],
                    ['Flyer über Hund öffnen', admin_url('edit.php?post_type=sod_dog'), 'button'],
                ],
            ];
        }

        if (self::can_access_sod_items()) {
            $inventory_base = self::can_access_sod_dogs()
                ? admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')
                : admin_url('admin.php?page=sod-inventory-standalone');
            $sections[] = [
                'title' => 'Futter, Zubehör & Lager',
                'links' => [
                    ['Lagerübersicht', $inventory_base, 'Bestand, Lagerorte, verfügbare Menge und Zuordnungen'],
                    ['Bestandsartikel', admin_url('edit.php?post_type=sod_inventory'), 'Artikel bearbeiten und Bewegungen prüfen'],
                    ['Artikel anlegen', admin_url('post-new.php?post_type=sod_inventory'), 'Futter oder Zubehör neu erfassen'],
                ],
                'actions' => [
                    ['Lager öffnen', $inventory_base, 'button button-primary'],
                    ['Artikel anlegen', admin_url('post-new.php?post_type=sod_inventory'), 'button'],
                ],
            ];
        }

        if (self::can_access_sod_finances()) {
            $sections[] = [
                'title' => 'Spenden, Belege & Transparenz',
                'links' => [
                    ['Spenden & Ausgaben', admin_url('edit.php?post_type=sod_finance'), 'Spenden, Sachspenden und Ausgaben erfassen'],
                    ['Eintrag anlegen', admin_url('post-new.php?post_type=sod_finance'), 'Neue Spende, Sachspende oder Ausgabe'],
                    ['Finanzbericht', admin_url('edit.php?post_type=sod_dog&page=sod-finance-report'), 'Monatsübersicht, Jahresfilter und CSV-Export'],
                ],
                'actions' => [
                    ['Spende erfassen', admin_url('post-new.php?post_type=sod_finance'), 'button button-primary'],
                    ['Finanzbericht öffnen', admin_url('edit.php?post_type=sod_dog&page=sod-finance-report'), 'button'],
                ],
            ];
        }

        if (current_user_can('manage_options')) {
            $sections[] = [
                'title' => 'System & Website',
                'links' => [
                    ['SOD Einstellungen', admin_url('edit.php?post_type=sod_dog&page=sod-settings'), 'E-Mail-Empfänger und Erinnerungen'],
                    ['JSON Import', admin_url('edit.php?post_type=sod_dog&page=sod-json-import'), 'Daten einmalig aus Dateien übernehmen'],
                    ['Öffentliche Website', home_url('/'), 'Startseite ansehen'],
                    ['Vermittlungsseite', home_url('/vermittlung/'), 'Öffentliche Hunde prüfen'],
                    ['Kontaktformular', home_url('/kontakt/'), 'Anfrageformular testen'],
                ],
                'actions' => [
                    ['Einstellungen öffnen', admin_url('edit.php?post_type=sod_dog&page=sod-settings'), 'button'],
                    ['Website ansehen', home_url('/'), 'button'],
                ],
            ];
        }

        echo '<div class="sod-function-dashboard">';
        foreach ($sections as $section) {
            echo '<section class="sod-function-section">';
            printf('<h3>%s</h3>', esc_html($section['title']));
            echo '<div class="sod-function-links">';
            foreach ($section['links'] as [$label, $url, $description]) {
                printf(
                    '<a class="sod-function-link" href="%s"><strong>%s</strong><span>%s</span></a>',
                    esc_url($url),
                    esc_html($label),
                    esc_html($description)
                );
            }
            echo '</div>';
            if (!empty($section['actions'])) {
                echo '<p class="sod-dashboard-actions">';
                foreach ($section['actions'] as [$label, $url, $class]) {
                    printf(
                        '<a class="%s" href="%s">%s</a> ',
                        esc_attr($class),
                        esc_url($url),
                        esc_html($label)
                    );
                }
                echo '</p>';
            }
            echo '</section>';
        }
        echo '</div>';
    }

    public static function dashboard_workflow_widget(): void
    {
        $cards = [];

        if (self::can_access_sod_dogs()) {
            $cards[] = [
                'step' => '1',
                'title' => 'Anfrage prüfen',
                'text' => 'Neue Kontaktanfragen ansehen, übernehmen und Wiedervorlage setzen.',
                'primary' => ['Anfragen öffnen', admin_url('edit.php?post_type=sod_application')],
                'secondary' => ['Interessent anlegen', admin_url('post-new.php?post_type=sod_interest')],
            ];
            $cards[] = [
                'step' => '2',
                'title' => 'Hund pflegen',
                'text' => 'Bilder, Gesundheit, Charakter, Standort und interne Hinweise aktuell halten.',
                'primary' => ['Hunde öffnen', admin_url('edit.php?post_type=sod_dog')],
                'secondary' => ['Hund anlegen', admin_url('post-new.php?post_type=sod_dog')],
            ];
        }

        if (self::can_access_sod_items()) {
            $inventory_base = self::can_access_sod_dogs()
                ? admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')
                : admin_url('admin.php?page=sod-inventory-standalone');
            $cards[] = [
                'step' => '3',
                'title' => 'Lager steuern',
                'text' => 'Bestand, Lagerorte, verfügbare Menge und Hund-Zuteilungen prüfen.',
                'primary' => ['Lager öffnen', $inventory_base],
                'secondary' => ['Artikel anlegen', admin_url('post-new.php?post_type=sod_inventory')],
            ];
        }

        if (self::can_access_sod_finances()) {
            $cards[] = [
                'step' => '4',
                'title' => 'Transparenz sichern',
                'text' => 'Spenden, Ausgaben, Bestätigungen und Jahresberichte sauber dokumentieren.',
                'primary' => ['Finanzbericht', admin_url('edit.php?post_type=sod_dog&page=sod-finance-report')],
                'secondary' => ['Spende erfassen', admin_url('post-new.php?post_type=sod_finance')],
            ];
        }

        if (!$cards) {
            echo '<p>Für diesen Zugang sind aktuell keine SOD-Arbeitsbereiche freigeschaltet.</p>';
            return;
        }

        echo '<div class="sod-workflow-grid">';
        foreach ($cards as $card) {
            echo '<section class="sod-workflow-card">';
            printf('<div class="sod-workflow-step">%s</div>', esc_html($card['step']));
            printf('<h3>%s</h3>', esc_html($card['title']));
            printf('<p>%s</p>', esc_html($card['text']));
            echo '<div class="sod-workflow-actions">';
            printf(
                '<a class="button button-primary" href="%s">%s</a>',
                esc_url($card['primary'][1]),
                esc_html($card['primary'][0])
            );
            printf(
                '<a class="button" href="%s">%s</a>',
                esc_url($card['secondary'][1]),
                esc_html($card['secondary'][0])
            );
            echo '</div>';
            echo '</section>';
        }
        echo '</div>';
    }

    public static function dashboard_due_widget(): void
    {
        $due = self::due_items();
        if (!$due) {
            echo '<p>Keine fälligen Erinnerungen. Alles erledigt!</p>';
            return;
        }
        echo '<ul class="sod-dashboard-list">';
        foreach (array_slice($due, 0, 12) as $item) {
            printf(
                '<li><a href="%s">%s</a><span>%s</span></li>',
                esc_url($item['url']),
                esc_html($item['label']),
                esc_html($item['info'])
            );
        }
        echo '</ul>';
        if (count($due) > 12) {
            printf('<p class="description">+ %d weitere fällige Einträge</p>', count($due) - 12);
        }
    }

    public static function dashboard_shelter_widget(): void
    {
        $cards = [
            ['Hunde', self::post_count('sod_dog'), admin_url('edit.php?post_type=sod_dog')],
            ['Aufgaben', self::post_count('sod_task'), admin_url('edit.php?post_type=sod_task')],
            ['Interessenten', self::post_count('sod_interest'), admin_url('edit.php?post_type=sod_interest')],
            ['Vermittlungsakten', self::post_count('sod_case'), admin_url('edit.php?post_type=sod_case')],
            ['Transporte', self::post_count('sod_transport'), admin_url('edit.php?post_type=sod_transport')],
            ['Pflegestellen', self::post_count('sod_foster'), admin_url('edit.php?post_type=sod_foster')],
            ['Paten', self::post_count('sod_sponsor'), admin_url('edit.php?post_type=sod_sponsor')],
        ];
        echo '<div class="sod-dashboard-grid">';
        foreach ($cards as [$label, $count, $url]) {
            printf(
                '<a class="sod-dashboard-tile" href="%s"><span>%s</span><strong>%s</strong></a>',
                esc_url($url),
                esc_html($label),
                esc_html((string)$count)
            );
        }
        echo '</div>';
        printf(
            '<p class="sod-dashboard-actions"><a class="button button-primary" href="%s">Tierheim-Übersicht öffnen</a></p>',
            esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-shelter-overview'))
        );
    }

    public static function maybe_redirect_staff_app_admin_page(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }
        if (sanitize_key((string)($_GET['page'] ?? '')) !== 'sod-staff-app') {
            return;
        }
        wp_safe_redirect(self::staff_app_url());
        exit;
    }

    public static function staff_app_admin_redirect(): void
    {
        ?>
        <div class="wrap">
            <h1>SOD Mitarbeiter-App</h1>
            <p>Die Mitarbeiter-App öffnet sich normalerweise automatisch.</p>
            <p><a class="button button-primary" href="<?php echo esc_url(self::staff_app_url()); ?>">Mitarbeiter-App öffnen</a></p>
        </div>
        <?php
    }

    public static function staff_app_router(): void
    {
        if (isset($_GET['sod_staff_manifest'])) {
            self::render_staff_manifest();
            exit;
        }
        if (isset($_GET['sod_staff_sw'])) {
            self::render_staff_service_worker();
            exit;
        }
        if (isset($_GET['sod_staff_app'])) {
            self::render_staff_app();
            exit;
        }
        if (isset($_GET['sod_staff_photo'])) {
            self::render_staff_photo_upload();
            exit;
        }
    }

    private static function staff_photo_upload_url(): string
    {
        return home_url('/?sod_staff_photo=1');
    }

    private static function staff_app_url(): string
    {
        return home_url('/?sod_staff_app=1');
    }

    public static function public_pwa_router(): void
    {
        if (isset($_GET['sod_pwa_manifest'])) {
            self::render_public_manifest();
            exit;
        }
    }

    private static function public_manifest_url(): string
    {
        return home_url('/?sod_pwa_manifest=1');
    }

    private static function render_public_manifest(): void
    {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=UTF-8');
        echo wp_json_encode([
            'name' => self::org()['name'],
            'short_name' => self::org()['name'],
            'description' => 'Tierschutz - Vermittlung, Patenschaften und Spenden.',
            // Eigener Startparameter, damit Browser diese App sauber von der
            // Mitarbeiter-App unterscheiden - beide teilen sich sonst denselben Scope.
            'id' => home_url('/?sod_app=1'),
            'start_url' => home_url('/?sod_app=1'),
            'scope' => home_url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f4f7f8',
            'theme_color' => '#204060',
            'icons' => [
                [
                    'src' => self::staff_icon_url('192'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => self::staff_icon_url('512'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function output_public_pwa_tags(): void
    {
        if (is_admin() || isset($_GET['sod_staff_app'])) {
            return;
        }
        ?>
<link rel="manifest" href="<?php echo esc_url(self::public_manifest_url()); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(self::org()['name']); ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
        <?php
    }

    public static function output_public_pwa_script(): void
    {
        if (is_admin() || isset($_GET['sod_staff_app'])) {
            return;
        }
        ?>
<div id="sod-install-banner" hidden>
    <img src="<?php echo esc_url(self::staff_icon_url('192')); ?>" alt="" width="44" height="44">
    <div class="sod-install-text">
        <strong><?php echo esc_html(self::org()['name']); ?> als App</strong>
        <span>Direkt am Startbildschirm, ohne Umweg über den Browser.</span>
    </div>
    <button type="button" id="sod-install-yes">Installieren</button>
    <button type="button" id="sod-install-no" aria-label="Hinweis schließen">&times;</button>
</div>
<style>
    #sod-install-banner { position:fixed; left:16px; right:16px; bottom:16px; z-index:9998; display:flex; align-items:center; gap:12px; max-width:520px; margin:0 auto; padding:14px 16px; border-radius:16px; background:#fff; color:#162531; box-shadow:0 18px 44px rgba(15,30,44,.28); font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }
    #sod-install-banner[hidden] { display:none !important; }
    #sod-install-banner img { flex:0 0 auto; border-radius:10px; }
    #sod-install-banner .sod-install-text { flex:1 1 auto; display:flex; flex-direction:column; gap:2px; min-width:0; }
    #sod-install-banner strong { font-size:15px; }
    #sod-install-banner span { font-size:12.5px; color:#5d6b76; line-height:1.35; }
    #sod-install-yes { flex:0 0 auto; border:0; border-radius:10px; padding:11px 15px; background:#204060; color:#fff; font-weight:700; font-size:14px; cursor:pointer; }
    #sod-install-no { flex:0 0 auto; border:0; background:transparent; color:#8b98a3; font-size:22px; line-height:1; padding:4px 6px; cursor:pointer; }
    @media (max-width:420px) { #sod-install-banner span { display:none; } }
</style>
<script>
(function () {
    var swUrl = '<?php echo esc_url(self::staff_service_worker_url()); ?>';
    var swScope = '<?php echo esc_url(home_url('/')); ?>';
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(swUrl, { scope: swScope }).catch(function () {});
        });
    }

    var banner = document.getElementById('sod-install-banner');
    var yes = document.getElementById('sod-install-yes');
    var no = document.getElementById('sod-install-no');
    if (!banner || !yes || !no) return;

    // Bereits installiert gestartet: dann ist der Hinweis sinnlos.
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent) ||
        (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    var dismissed = false;
    try { dismissed = window.localStorage.getItem('sodInstallDismissed') === '1'; } catch (e) {}
    if (standalone || dismissed) return;

    var prompt = null;
    // iOS unterstützt beforeinstallprompt nicht. Den Hinweis deshalb direkt
    // anzeigen und statt eines nicht funktionierenden Prompts eine Anleitung geben.
    if (isIos) {
        banner.hidden = false;
        yes.textContent = 'Anleitung';
        var description = banner.querySelector('.sod-install-text span');
        if (description) description.textContent = 'Über „Teilen“ und „Zum Home-Bildschirm“ installieren.';
    }
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        prompt = event;
        banner.hidden = false;
    });

    yes.addEventListener('click', function () {
        if (isIos && !prompt) {
            window.alert('So installierst du diese App auf dem iPhone:\n\n1. Tippe unten auf „Teilen“ (Quadrat mit Pfeil).\n2. Wähle „Zum Home-Bildschirm“.\n3. Tippe oben rechts auf „Hinzufügen“.');
            return;
        }
        if (!prompt) return;
        prompt.prompt();
        prompt.userChoice.then(function () {
            prompt = null;
            banner.hidden = true;
        });
    });

    no.addEventListener('click', function () {
        banner.hidden = true;
        try { window.localStorage.setItem('sodInstallDismissed', '1'); } catch (e) {}
    });

    window.addEventListener('appinstalled', function () {
        banner.hidden = true;
        try { window.localStorage.setItem('sodInstallDismissed', '1'); } catch (e) {}
    });
})();
</script>
        <?php
    }

    private static function staff_manifest_url(): string
    {
        return home_url('/?sod_staff_manifest=1');
    }

    private static function staff_service_worker_url(): string
    {
        return home_url('/?sod_staff_sw=1');
    }

    private static function staff_icon_url(string $size = '192'): string
    {
        $theme_dir = trailingslashit(get_stylesheet_directory());
        $theme_uri = trailingslashit(get_stylesheet_directory_uri());
        $file = 'assets/images/app-icons/shield-logo-' . $size . '.png';
        if (is_readable($theme_dir . $file)) {
            return $theme_uri . $file;
        }
        return plugin_dir_url(__FILE__) . 'assets/images/logo.png';
    }

    // =====================================================================
    // Mobiler Foto-Upload (prominent in der Mitarbeiter-App verlinkt)
    // =====================================================================

    private static function render_staff_photo_upload(): void
    {
        nocache_headers();
        $login_url = wp_login_url(self::staff_photo_upload_url());
        $is_logged_in = is_user_logged_in();
        $can_access = $is_logged_in && self::can_access_sod_dogs();
        $dogs = $can_access ? get_posts([
            'post_type' => 'sod_dog',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]) : [];
        $selected_dog = absint($_GET['dog_id'] ?? 0);
        $uploaded = isset($_GET['sod_staff_photo_uploaded']);
        $error = sanitize_text_field((string)($_GET['sod_staff_photo_error'] ?? ''));
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#204060">
    <title>Foto hochladen - SOD Team</title>
    <style>
        :root { color-scheme: light; --blue:#204060; --yellow:#f3c74f; --ink:#162531; --muted:#5d6b76; --line:#d8e0e6; --soft:#f4f7f8; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; background:linear-gradient(180deg,#eef4f6 0%,#ffffff 42%); color:var(--ink); }
        .app { width:min(560px,100%); margin:0 auto; padding: max(18px, env(safe-area-inset-top)) 16px 40px; }
        .topbar { display:flex; justify-content:flex-start; padding:4px 0 14px; }
        .back { display:inline-flex; align-items:center; gap:8px; min-height:44px; border:1px solid var(--line); border-radius:999px; padding:10px 15px; background:#fff; color:var(--blue); font-weight:900; font-size:15px; box-shadow:0 8px 22px rgba(32,64,96,.08); cursor:pointer; text-decoration:none; }
        h1 { margin:0 0 6px; font-size:clamp(24px,7vw,34px); color:var(--blue); }
        p.lead { margin:0 0 20px; color:var(--muted); }
        .notice { padding:16px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; }
        .notice-error { background:#fbeaea; color:#8a2a24; }
        .success-card { display:flex; flex-direction:column; align-items:center; text-align:center; gap:6px; padding:28px 20px; border-radius:20px; background:#eaf6ea; border:1px solid #b9e3ba; margin-bottom:20px; }
        .success-card .check { width:56px; height:56px; border-radius:50%; background:#2f9e44; color:#fff; display:flex; align-items:center; justify-content:center; font-size:30px; margin-bottom:4px; }
        .success-card strong { font-size:19px; color:#1f6b2c; }
        .success-card span { color:#2f7a3d; font-size:14px; }
        .card { padding:20px; border:1px solid var(--line); border-radius:18px; background:#fff; box-shadow:0 12px 30px rgba(32,64,96,.08); }
        label { display:block; font-weight:800; font-size:14px; margin-bottom:6px; }
        select, textarea { width:100%; padding:13px 12px; border:1px solid var(--line); border-radius:12px; font-size:16px; margin-bottom:18px; font-family:inherit; background:#fff; color:var(--ink); }
        .photo-buttons { display:flex; gap:10px; margin-bottom:18px; }
        .photo-input { flex:1 1 0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; min-height:110px; border:2px dashed var(--line); border-radius:16px; cursor:pointer; background:var(--soft); text-align:center; padding:14px 10px; }
        .photo-input .icon { font-size:26px; }
        .photo-input span { font-weight:800; color:var(--blue); font-size:14px; }
        .photo-input input[type=file] { display:none; }
        .photo-preview { max-width:100%; max-height:220px; border-radius:12px; display:none; margin-bottom:18px; }
        .cta { display:flex; width:100%; justify-content:center; align-items:center; min-height:54px; border:0; border-radius:14px; padding:14px 18px; background:var(--yellow); color:#172531; text-decoration:none; font-weight:900; cursor:pointer; font-size:17px; }
        .foot { margin-top:18px; color:var(--muted); font-size:13px; text-align:center; }
    </style>
</head>
<body>
    <main class="app">
        <div class="topbar">
            <a class="back" href="<?php echo esc_url(self::staff_app_url()); ?>">‹ Zurück</a>
        </div>
        <h1>📷 Foto hochladen</h1>
        <p class="lead">Direkt vom Handy ein neues Foto für einen Hund aufnehmen oder aus der Galerie wählen.</p>

        <?php if ($uploaded) : ?>
            <div class="success-card">
                <div class="check">✓</div>
                <strong>Foto erfolgreich hochgeladen!</strong>
                <span>Danke — es erscheint jetzt im Mitgliederbereich der Patin/des Paten.</span>
            </div>
        <?php elseif ($error === 'dog') : ?>
            <div class="notice notice-error">Bitte einen Hund auswählen.</div>
        <?php elseif ($error === 'upload') : ?>
            <div class="notice notice-error">Foto konnte nicht verarbeitet werden. Bitte ein anderes Bild versuchen (JPG, PNG oder WEBP).</div>
        <?php endif; ?>

        <?php if (!$is_logged_in) : ?>
            <div class="card">
                <p>Bitte zuerst einloggen.</p>
                <a class="cta" href="<?php echo esc_url($login_url); ?>">Einloggen</a>
            </div>
        <?php elseif (!$can_access) : ?>
            <div class="card">
                <p>Für diesen Benutzer sind keine SOD-Mitarbeiterrechte für Hunde freigeschaltet.</p>
            </div>
        <?php else : ?>
            <form class="card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="sod_staff_photo_upload">
                <?php wp_nonce_field('sod_staff_photo_upload', 'sod_staff_photo_nonce'); ?>

                <label for="sod_staff_photo_dog">Hund</label>
                <select name="dog_id" id="sod_staff_photo_dog" required>
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($dogs as $dog) : ?>
                        <option value="<?php echo esc_attr((string)$dog->ID); ?>" <?php selected($selected_dog, $dog->ID); ?>><?php echo esc_html(self::dog_public_name($dog->ID)); ?></option>
                    <?php endforeach; ?>
                </select>

                <img class="photo-preview" id="sod_staff_photo_preview" alt="">
                <div class="photo-buttons">
                    <label class="photo-input" for="sod_staff_photo_camera">
                        <span class="icon">📷</span>
                        <span>Foto aufnehmen</span>
                        <input type="file" name="photo_camera" id="sod_staff_photo_camera" accept="image/*" capture="environment">
                    </label>
                    <label class="photo-input" for="sod_staff_photo_gallery">
                        <span class="icon">🖼️</span>
                        <span>Aus Galerie wählen</span>
                        <input type="file" name="photo_gallery" id="sod_staff_photo_gallery" accept="image/*">
                    </label>
                </div>

                <label for="sod_staff_photo_caption">Kurzer Hinweis (optional)</label>
                <textarea name="caption" id="sod_staff_photo_caption" rows="2" placeholder="z.B. Gassirunde heute Vormittag"></textarea>

                <button type="submit" class="cta">Foto hochladen</button>
            </form>
        <?php endif; ?>

        <p class="foot">Das Foto erscheint automatisch im Mitgliederbereich der Patin/des Paten dieses Hundes.</p>
    </main>
    <script>
        var cameraInput = document.getElementById('sod_staff_photo_camera');
        var galleryInput = document.getElementById('sod_staff_photo_gallery');
        var preview = document.getElementById('sod_staff_photo_preview');
        var uploadForm = document.querySelector('form[action*="admin-post.php"]');

        function showPreview(input, other) {
            var file = input.files && input.files[0];
            if (!file) return;
            if (other) other.value = '';
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        }
        if (cameraInput) cameraInput.addEventListener('change', function () { showPreview(cameraInput, galleryInput); });
        if (galleryInput) galleryInput.addEventListener('change', function () { showPreview(galleryInput, cameraInput); });
        if (uploadForm) {
            uploadForm.addEventListener('submit', function (event) {
                var hasCamera = cameraInput && cameraInput.files && cameraInput.files.length > 0;
                var hasGallery = galleryInput && galleryInput.files && galleryInput.files.length > 0;
                if (!hasCamera && !hasGallery) {
                    event.preventDefault();
                    alert('Bitte zuerst ein Foto aufnehmen oder auswählen.');
                }
            });
        }
    </script>
</body>
</html>
        <?php
    }

    public static function handle_staff_photo_upload(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(self::staff_photo_upload_url()));
            exit;
        }
        if (!self::can_access_sod_dogs()) {
            wp_die('Keine Berechtigung.');
        }
        if (!isset($_POST['sod_staff_photo_nonce']) || !wp_verify_nonce((string)$_POST['sod_staff_photo_nonce'], 'sod_staff_photo_upload')) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }

        $dog_id = absint($_POST['dog_id'] ?? 0);
        if ($dog_id <= 0 || get_post_type($dog_id) !== 'sod_dog') {
            wp_safe_redirect(add_query_arg('sod_staff_photo_error', 'dog', self::staff_photo_upload_url()));
            exit;
        }

        $photo_id = self::store_staff_dog_photo($dog_id);
        if ($photo_id <= 0) {
            wp_safe_redirect(add_query_arg(['sod_staff_photo_error' => 'upload', 'dog_id' => $dog_id], self::staff_photo_upload_url()));
            exit;
        }

        $caption = sanitize_textarea_field((string)($_POST['caption'] ?? ''));
        $update_id = wp_insert_post([
            'post_type' => 'sod_dog_update',
            'post_status' => 'publish',
            'post_title' => self::dog_public_name($dog_id) . ' – Foto vom ' . date_i18n('d.m.Y'),
        ]);
        if ($update_id && !is_wp_error($update_id)) {
            update_post_meta($update_id, 'sod_dog_update_dog', $dog_id);
            update_post_meta($update_id, 'sod_dog_update_photo_id', $photo_id);
            update_post_meta($update_id, 'sod_dog_update_published_at', gmdate('c'));
            if ($caption !== '') {
                update_post_meta($update_id, 'sod_dog_update_body', wp_kses_post($caption));
            }
        }

        wp_safe_redirect(add_query_arg(['sod_staff_photo_uploaded' => '1', 'dog_id' => $dog_id], self::staff_photo_upload_url()));
        exit;
    }

    /**
     * Gleiche Validierungs-/Komprimierungs-Pipeline wie store_sponsor_photo() (Groessenlimit,
     * Dateityp-Pruefung, echte Bildpruefung per getimagesize, Kompression) - bewusst eigene
     * Funktion statt Wiederverwendung, damit sich beide Upload-Wege unabhaengig voneinander
     * anpassen lassen (unterschiedliche Grenzwerte, unterschiedlicher $_FILES-Feldname).
     */
    private static function store_staff_dog_photo(int $dog_id): int
    {
        // Zwei getrennte Datei-Felder (Kamera mit capture="environment" vs. Galerie ohne) -
        // manche mobilen Browser bieten bei EINEM Input mit capture-Attribut keine Galerie-
        // Auswahl mehr an. Es kann also genau eines der beiden Felder befuellt sein.
        $file = null;
        foreach (['photo_camera', 'photo_gallery'] as $field) {
            if (!empty($_FILES[$field]) && is_array($_FILES[$field]) && (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES[$field];
                break;
            }
        }
        if ($file === null) {
            return 0;
        }
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return 0;
        }

        $size = (int)($file['size'] ?? 0);
        $max_size = min((int)wp_max_upload_size(), 10 * 1024 * 1024);
        if ($size <= 0 || $size > $max_size) {
            return 0;
        }

        $original_name = sanitize_file_name((string)($file['name'] ?? 'hund'));
        $allowed = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ];
        $check = wp_check_filetype_and_ext((string)$file['tmp_name'], $original_name, $allowed);
        $ext = strtolower((string)($check['ext'] ?? ''));
        if ($ext === '' || !isset($allowed[$ext])) {
            return 0;
        }
        $dims = @getimagesize((string)$file['tmp_name']);
        if ($dims === false || (int)($dims[0] ?? 0) < 1 || (int)($dims[1] ?? 0) < 1) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $moved = wp_handle_upload($file, ['test_form' => false, 'mimes' => $allowed]);
        if (!is_array($moved) || !empty($moved['error']) || empty($moved['file'])) {
            return 0;
        }

        $editor = wp_get_image_editor((string)$moved['file']);
        if (!is_wp_error($editor)) {
            $editor->set_quality(85);
            $editor->save((string)$moved['file']);
        }

        $attach_id = wp_insert_attachment([
            'post_mime_type' => (string)($moved['type'] ?? $allowed[$ext]),
            'post_title'     => 'Hundefoto ' . $dog_id . ' ' . gmdate('Y-m-d-His'),
            'post_status'    => 'inherit',
            'post_parent'    => $dog_id,
        ], (string)$moved['file'], $dog_id);
        if (!$attach_id || is_wp_error($attach_id)) {
            return 0;
        }
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, (string)$moved['file']));

        return (int)$attach_id;
    }

    /**
     * Reihenfolge bewusst fest vorgegeben (Wunsch des Vereins): Foto hochladen, Hund anlegen,
     * Hunde, Paten, Finanzbericht zuerst - alles Weitere danach in beliebiger Reihenfolge.
     */
    private static function staff_app_links(): array
    {
        $links = [];

        if (self::can_access_sod_dogs()) {
            $links[] = ['📸 Update posten', 'Neuigkeit oder Foto für einen Hund posten – erscheint im Mitgliederbereich des Paten', admin_url('post-new.php?post_type=sod_dog_update'), 'primary'];
            $links[] = ['📷 Foto hochladen', 'Direkt vom Handy ein neues Hundefoto aufnehmen und hochladen', self::staff_photo_upload_url(), 'primary'];
            $links[] = ['Hund anlegen', 'Neuen Hund geführt erfassen', admin_url('post-new.php?post_type=sod_dog'), 'primary'];
            $links[] = ['Hunde', 'Profile, Bilder, Video, Gesundheit und interne Infos', admin_url('edit.php?post_type=sod_dog'), 'primary'];
            $links[] = ['Paten', 'Patenschaften, Zahlungen und Zertifikate verwalten', admin_url('edit.php?post_type=sod_sponsor'), 'primary'];
        }
        if (self::can_access_sod_finances()) {
            $links[] = ['Finanzbericht', 'Transparenz und Auswertung öffnen', admin_url('edit.php?post_type=sod_dog&page=sod-finance-report'), 'primary'];
        }

        $links[] = ['Dashboard', 'Tageszentrale öffnen', admin_url('index.php'), ''];

        if (self::can_access_sod_dogs()) {
            $links[] = ['Tierheim-Übersicht', 'Hunde, Aufgaben, Interessenten und Akten', admin_url('edit.php?post_type=sod_dog&page=sod-shelter-overview'), ''];
            $links[] = ['Anfragen', 'Neue Kontaktanfragen prüfen und übernehmen', admin_url('edit.php?post_type=sod_application'), ''];
            $links[] = ['Interessenten', 'Status, Wiedervorlage und Kontakt verwalten', admin_url('edit.php?post_type=sod_interest'), ''];
            $links[] = ['Vermittlungsakten', 'Vorkontrolle, Schutzvertrag und Übergabe', admin_url('edit.php?post_type=sod_case'), ''];
            $links[] = ['Aufgaben', 'Erinnerungen und tägliche To-dos', admin_url('edit.php?post_type=sod_task'), ''];
        }

        if (self::can_access_sod_items()) {
            $inventory_url = self::can_access_sod_dogs()
                ? admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')
                : admin_url('admin.php?page=sod-inventory-standalone');
            $links[] = ['Lagerübersicht', 'Futter, Zubehör, Lagerorte und verfügbare Menge', $inventory_url, ''];
            $links[] = ['Artikel anlegen', 'Futter oder Zubehör eintragen', admin_url('post-new.php?post_type=sod_inventory'), ''];
        }

        if (self::can_access_sod_finances()) {
            $links[] = ['Spenden & Ausgaben', 'Spenden, Sachspenden und Belege erfassen', admin_url('edit.php?post_type=sod_finance'), ''];
        }

        if (current_user_can('manage_options')) {
            $links[] = ['Einstellungen', 'Benachrichtigungen und System prüfen', admin_url('edit.php?post_type=sod_dog&page=sod-settings'), ''];
        }

        return $links;
    }

    private static function render_staff_manifest(): void
    {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=UTF-8');
        echo wp_json_encode([
            'name' => 'SOD Mitarbeiter-App',
            'short_name' => 'SOD Team',
            'description' => 'Mitarbeiter-App für ' . self::org()['name'] . '.',
            'id' => self::staff_app_url(),
            'start_url' => self::staff_app_url(),
            'scope' => home_url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f4f7f8',
            'theme_color' => '#204060',
            'icons' => [
                [
                    'src' => self::staff_icon_url('192'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => self::staff_icon_url('512'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function render_staff_service_worker(): void
    {
        nocache_headers();
        header('Content-Type: application/javascript; charset=UTF-8');
        $icon_192 = esc_js(self::staff_icon_url('192'));
        ?>
const SOD_STAFF_CACHE = 'sod-staff-app-v<?php echo esc_js(self::VERSION); ?>';
const SOD_SAFE_ASSETS = ['<?php echo $icon_192; ?>'];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(SOD_STAFF_CACHE).then(cache => cache.addAll(SOD_SAFE_ASSETS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== SOD_STAFF_CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.pathname.includes('/wp-admin/') || url.pathname.includes('admin-ajax.php') || event.request.method !== 'GET') {
    return;
  }
  event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
        <?php
    }

    private static function render_staff_app(): void
    {
        nocache_headers();
        $is_logged_in = is_user_logged_in();
        $can_access = self::can_access_sod_dashboard();
        $current_user = wp_get_current_user();
        $links = $is_logged_in && $can_access ? self::staff_app_links() : [];
        $login_url = wp_login_url(self::staff_app_url());
        $logo_url = self::staff_icon_url('512');
        $manifest_url = self::staff_manifest_url();
        $sw_url = self::staff_service_worker_url();
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#204060">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SOD Team">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(self::staff_icon_url('192')); ?>">
    <title>SOD Mitarbeiter-App</title>
    <style>
        :root { color-scheme: light; --blue:#204060; --yellow:#f3c74f; --ink:#162531; --muted:#5d6b76; --line:#d8e0e6; --soft:#f4f7f8; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; background:linear-gradient(180deg,#eef4f6 0%,#ffffff 42%); color:var(--ink); }
        a { color:inherit; }
        .app { width:min(1040px,100%); margin:0 auto; padding: max(18px, env(safe-area-inset-top)) 16px 28px; }
        .topbar { display:flex; justify-content:flex-start; padding:4px 0 2px; }
        .back { display:inline-flex; align-items:center; gap:8px; min-height:44px; border:1px solid var(--line); border-radius:999px; padding:10px 15px; background:#fff; color:var(--blue); font-weight:900; font-size:15px; box-shadow:0 8px 22px rgba(32,64,96,.08); cursor:pointer; }
        .back:hover, .back:focus { background:var(--yellow); color:#172531; border-color:#d8bd53; }
        .hero { display:flex; gap:16px; align-items:center; padding:18px 0 14px; }
        .logo { width:76px; height:76px; border-radius:20px; object-fit:contain; background:#fff; box-shadow:0 10px 30px rgba(32,64,96,.14); padding:8px; }
        h1 { margin:0; font-size:clamp(28px,8vw,54px); line-height:1; letter-spacing:0; text-transform:uppercase; color:var(--blue); }
        .subtitle { margin:8px 0 0; color:var(--muted); font-size:16px; line-height:1.45; }
        .status { display:flex; flex-wrap:wrap; gap:8px; margin:8px 0 20px; }
        .pill { border:1px solid var(--line); border-radius:999px; padding:8px 12px; background:#fff; color:var(--muted); font-weight:700; font-size:13px; }
        .install { display:none; width:100%; border:0; border-radius:16px; padding:17px 18px; margin:6px 0 18px; background:var(--yellow); color:#172531; font-weight:900; font-size:17px; box-shadow:0 12px 28px rgba(243,199,79,.28); }
        .install.show { display:block; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; }
        .card { display:flex; min-height:118px; flex-direction:column; justify-content:space-between; gap:14px; padding:18px; border:1px solid var(--line); border-radius:18px; background:#fff; text-decoration:none; box-shadow:0 12px 30px rgba(32,64,96,.08); }
        .card.primary { border-color:#d8bd53; background:#fff8dd; }
        .card strong { font-size:19px; color:var(--blue); }
        .card span { color:var(--muted); line-height:1.35; }
        .cta { display:inline-flex; justify-content:center; align-items:center; min-height:52px; border-radius:14px; padding:14px 16px; background:var(--blue); color:#fff; text-decoration:none; font-weight:900; }
        .notice { margin-top:20px; padding:18px; border:1px solid var(--line); border-radius:18px; background:#fff; box-shadow:0 12px 30px rgba(32,64,96,.08); }
        .notice h2 { margin:0 0 8px; font-size:22px; color:var(--blue); }
        .notice p { margin:0 0 14px; color:var(--muted); line-height:1.5; }
        .foot { margin-top:24px; color:var(--muted); font-size:13px; text-align:center; }
        @media (max-width: 560px) { .hero { align-items:flex-start; } .logo { width:64px; height:64px; border-radius:16px; } .grid { grid-template-columns:1fr; } .card { min-height:104px; } }
    </style>
</head>
<body>
    <main class="app">
        <div class="topbar">
            <button class="back" type="button" id="sod-back-button" aria-label="Zurück">‹ Zurück</button>
        </div>
        <header class="hero">
            <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
            <div>
                <h1>SOD Team</h1>
                <p class="subtitle">Mitarbeiter-App für Hunde, Anfragen, Lager und Transparenz.</p>
            </div>
        </header>

        <button class="install" type="button" id="sod-install-button">App installieren</button>

        <div class="status">
            <span class="pill"><?php echo $is_logged_in ? 'Angemeldet' : 'Nicht angemeldet'; ?></span>
            <?php if ($is_logged_in && $current_user->display_name) : ?>
                <span class="pill"><?php echo esc_html($current_user->display_name); ?></span>
            <?php endif; ?>
            <span class="pill">Auto-Aktualisierung: 10 Minuten</span>
        </div>

        <?php if (!$is_logged_in) : ?>
            <section class="notice">
                <h2>Bitte einloggen</h2>
                <p>Die Mitarbeiter-App verwendet den normalen WordPress-Zugang. Danach erscheinen automatisch deine freigegebenen Bereiche.</p>
                <a class="cta" href="<?php echo esc_url($login_url); ?>">Einloggen</a>
            </section>
        <?php elseif (!$can_access) : ?>
            <section class="notice">
                <h2>Keine SOD-Rechte</h2>
                <p>Für diesen Benutzer sind noch keine SOD-Mitarbeiterrechte freigeschaltet.</p>
                <a class="cta" href="<?php echo esc_url(admin_url()); ?>">WordPress öffnen</a>
            </section>
        <?php else : ?>
            <section class="grid" aria-label="SOD Bereiche">
                <?php foreach ($links as [$title, $description, $url, $class]) : ?>
                    <a class="card <?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                        <strong><?php echo esc_html($title); ?></strong>
                        <span><?php echo esc_html($description); ?></span>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <p class="foot"><?php echo esc_html(self::org()['name']); ?> - interne Mitarbeiter-App</p>
    </main>
    <script>
        document.getElementById('sod-back-button')?.addEventListener('click', () => {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.href = '<?php echo esc_js(admin_url('index.php')); ?>';
        });
        let sodInstallPrompt = null;
        const sodInstallButton = document.getElementById('sod-install-button');
        window.addEventListener('beforeinstallprompt', event => {
            event.preventDefault();
            sodInstallPrompt = event;
            sodInstallButton.classList.add('show');
        });
        sodInstallButton.addEventListener('click', async () => {
            if (!sodInstallPrompt) return;
            sodInstallPrompt.prompt();
            await sodInstallPrompt.userChoice;
            sodInstallPrompt = null;
            sodInstallButton.classList.remove('show');
        });
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?php echo esc_url($sw_url); ?>', { scope: '<?php echo esc_url(home_url('/')); ?>' }).catch(() => {});
            });
        }
        window.setInterval(() => {
            if (!document.hidden) window.location.reload();
        }, 10 * 60 * 1000);
    </script>
</body>
</html>
        <?php
    }

    public static function dashboard_photo_needs_widget(): void
    {
        $items = self::dog_photo_need_items(8);
        $export_url = wp_nonce_url(
            add_query_arg(['action' => 'sod_photo_needs_export'], admin_url('admin-post.php')),
            'sod_photo_needs_export'
        );
        echo '<p class="sod-photo-note">Nur echte Fotos des jeweiligen Hundes verwenden. Wenn kein sicheres Foto vorhanden ist, bleibt öffentlich bewusst der Platzhalter sichtbar.</p>';
        if (!$items) {
            echo '<p>Alle öffentlich gepflegten Hunde haben ein Bild und die Foto-Prüfung ist markiert.</p>';
            printf(
                '<p class="sod-dashboard-actions"><a class="button" href="%s">Bildbedarf exportieren</a></p>',
                esc_url($export_url)
            );
            return;
        }
        echo '<ul class="sod-dashboard-list">';
        foreach ($items as $item) {
            printf(
                '<li><a href="%s">%s</a><span>%s</span></li>',
                esc_url((string)$item['url']),
                esc_html((string)$item['label']),
                esc_html((string)$item['info'])
            );
        }
        echo '</ul>';
        printf(
            '<p class="sod-dashboard-actions"><a class="button button-primary" href="%s">Hunde öffnen</a> <a class="button" href="%s">Bildbedarf exportieren</a></p>',
            esc_url(admin_url('edit.php?post_type=sod_dog')),
            esc_url($export_url)
        );
    }

    public static function dashboard_inventory_widget(): void
    {
        $rows = self::inventory_rows('', '', '');
        $total_items = count($rows);
        $total_quantity = array_sum(array_map(static fn (array $row): float => $row['total_number'], $rows));
        $assigned = count(array_filter($rows, static fn (array $row): bool => $row['assigned_dog'] !== ''));
        $low_rows = array_slice(array_filter($rows, static fn (array $row): bool => $row['total_number'] > 0 && $row['total_number'] <= $row['min_number']), 0, 5);

        echo '<div class="sod-dashboard-grid sod-dashboard-grid-3">';
        foreach ([
            ['Artikel', $total_items],
            ['Gesamtmenge', self::format_quantity($total_quantity)],
            ['Zugeordnet', $assigned],
        ] as [$label, $value]) {
            printf('<div class="sod-dashboard-tile"><span>%s</span><strong>%s</strong></div>', esc_html($label), esc_html((string)$value));
        }
        echo '</div>';

        if ($low_rows) {
            echo '<h4>Knapp im Lager</h4><ul class="sod-dashboard-list">';
            foreach ($low_rows as $row) {
                printf(
                    '<li><a href="%s">%s</a><span>%s</span></li>',
                    esc_url(get_edit_post_link($row['id'])),
                    esc_html($row['title']),
                    esc_html($row['total_label'])
                );
            }
            echo '</ul>';
        }

        printf(
            '<p class="sod-dashboard-actions"><a class="button button-primary" href="%s">Lagerübersicht öffnen</a> <a class="button" href="%s">Artikel anlegen</a></p>',
            esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')),
            esc_url(admin_url('post-new.php?post_type=sod_inventory'))
        );
    }

    public static function dashboard_finance_widget(): void
    {
        $year = (int)date_i18n('Y');
        $summary = self::finance_summary($year);
        echo '<div class="sod-dashboard-grid sod-dashboard-grid-3">';
        foreach ([
            ['Einnahmen', self::money_label($summary['income'])],
            ['Ausgaben', self::money_label($summary['expense'])],
            ['Saldo', self::money_label($summary['balance'])],
        ] as [$label, $value]) {
            printf('<div class="sod-dashboard-tile"><span>%s</span><strong>%s</strong></div>', esc_html($label), esc_html($value));
        }
        echo '</div>';
        printf(
            '<p class="sod-dashboard-actions"><a class="button button-primary" href="%s">Finanzbericht öffnen</a> <a class="button" href="%s">Spende/Ausgabe anlegen</a></p>',
            esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-finance-report')),
            esc_url(admin_url('post-new.php?post_type=sod_finance'))
        );
    }

    public static function dashboard_compliance_widget(): void
    {
        $cleanup_scheduled = (bool)wp_next_scheduled('sod_cleanup_applications');
        $privacy_url = self::page_url('datenschutz');
        $settings_url = current_user_can('manage_options') ? admin_url('edit.php?post_type=sod_dog&page=sod-settings') : admin_url('index.php');
        $items = [
            [
                'title' => 'Anfragen',
                'status' => $cleanup_scheduled ? '30-Tage-Löschung geplant' : 'Löschung prüfen',
                'text' => 'Kontaktanfragen erhalten ein Löschdatum und werden automatisch bereinigt.',
            ],
            [
                'title' => 'Kontaktformular',
                'status' => 'Spam-Schutz aktiv',
                'text' => 'Bot-Feld, Mindestzeit, Inhaltsprüfung und Rate-Limit schützen vor Massenanfragen.',
            ],
            [
                'title' => 'Mitarbeiterrechte',
                'status' => 'Rollen getrennt',
                'text' => 'Hunde, Lager und Finanzen werden über eigene Berechtigungen serverseitig geprüft.',
            ],
            [
                'title' => 'Exporte',
                'status' => 'Intern geschützt',
                'text' => 'CSV-Exporte sind angemeldeten Nutzern mit passenden Rechten vorbehalten.',
            ],
        ];

        echo '<div class="sod-compliance-list">';
        foreach ($items as $item) {
            echo '<section class="sod-compliance-item">';
            printf('<strong>%s</strong>', esc_html($item['title']));
            printf('<span>%s</span>', esc_html($item['status']));
            printf('<p>%s</p>', esc_html($item['text']));
            echo '</section>';
        }
        echo '</div>';
        echo '<p class="description">Hinweis: Diese Übersicht ersetzt keine externe Rechtsprüfung, hilft aber bei der täglichen Datenpflege.</p>';
        printf(
            '<p class="sod-dashboard-actions"><a class="button button-primary" href="%s">Datenschutzseite prüfen</a> <a class="button" href="%s">SOD Einstellungen</a></p>',
            esc_url($privacy_url),
            esc_url($settings_url)
        );
    }

    public static function inventory_overview_page(): void
    {
        if (!self::can_access_sod_items()) {
            wp_die('Keine Berechtigung.');
        }

        $search = sanitize_text_field((string)($_GET['sod_search'] ?? ''));
        $category = sanitize_text_field((string)($_GET['sod_category'] ?? ''));
        $location = sanitize_text_field((string)($_GET['sod_location'] ?? ''));
        $assigned_filter = sanitize_text_field((string)($_GET['sod_assigned'] ?? ''));
        $assigned_dog_filter = absint($_GET['sod_assigned_dog'] ?? 0);
        $rows = self::inventory_rows($search, $category, $location, $assigned_filter, $assigned_dog_filter);
        $all_rows = self::inventory_rows('', '', '');
        $categories = self::unique_inventory_values($all_rows, 'category');
        $locations = self::unique_inventory_locations($all_rows);
        $total_items = count($rows);
        $total_quantity = array_sum(array_map(static fn (array $row): float => $row['total_number'], $rows));
        $available_quantity = array_sum(array_map(static fn (array $row): float => $row['available_number'], $rows));
        $assigned = count(array_filter($rows, static fn (array $row): bool => $row['assigned_dog_id'] > 0 || $row['assigned_dog_label'] !== ''));
        $assigned_url = add_query_arg([
            'post_type' => 'sod_dog',
            'page' => 'sod-inventory-overview',
            'sod_assigned' => '1',
        ], admin_url('edit.php'));
        $export_url = wp_nonce_url(
            add_query_arg([
                'action' => 'sod_inventory_export',
                'sod_search' => $search,
                'sod_category' => $category,
                'sod_location' => $location,
                'sod_assigned' => $assigned_filter,
                'sod_assigned_dog' => $assigned_dog_filter,
            ], admin_url('admin-post.php')),
            'sod_inventory_export'
        );
        ?>
        <div class="wrap sod-inventory-overview">
            <h1 class="wp-heading-inline">Lagerübersicht</h1>
            <a class="page-title-action" href="<?php echo esc_url(admin_url('post-new.php?post_type=sod_inventory')); ?>">Artikel anlegen</a>
            <a class="page-title-action" href="<?php echo esc_url($export_url); ?>">CSV exportieren</a>
            <button class="page-title-action sod-print-button" type="button" onclick="window.print()">Drucken / PDF</button>
            <hr class="wp-header-end">

            <div class="sod-overview-cards">
                <div class="sod-overview-card"><span>Artikel</span><strong><?php echo esc_html((string)$total_items); ?></strong></div>
                <div class="sod-overview-card"><span>Gesamtmenge</span><strong><?php echo esc_html(self::format_quantity($total_quantity)); ?></strong></div>
                <div class="sod-overview-card"><span>Verfügbar</span><strong><?php echo esc_html(self::format_quantity($available_quantity)); ?></strong></div>
                <a class="sod-overview-card sod-overview-link" href="<?php echo esc_url($assigned_url); ?>"><span>Zugeordnet</span><strong><?php echo esc_html((string)$assigned); ?></strong></a>
            </div>

            <form class="sod-overview-filters" method="get">
                <input type="hidden" name="post_type" value="sod_dog">
                <input type="hidden" name="page" value="sod-inventory-overview">
                <?php if ($assigned_filter !== '') : ?><input type="hidden" name="sod_assigned" value="<?php echo esc_attr($assigned_filter); ?>"><?php endif; ?>
                <?php if ($assigned_dog_filter > 0) : ?><input type="hidden" name="sod_assigned_dog" value="<?php echo esc_attr((string)$assigned_dog_filter); ?>"><?php endif; ?>
                <label>
                    <span class="screen-reader-text">Suchen</span>
                    <input type="search" name="sod_search" value="<?php echo esc_attr($search); ?>" placeholder="Name, Inventarnummer, Lagerort...">
                </label>
                <select name="sod_category">
                    <option value="">Alle Kategorien</option>
                    <?php foreach ($categories as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($category, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sod_location">
                    <option value="">Alle Lagerorte</option>
                    <?php foreach ($locations as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($location, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" type="submit">Filtern</button>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')); ?>">Zurücksetzen</a>
            </form>

            <div class="sod-admin-table-scroll">
            <table class="widefat striped sod-inventory-table">
                <thead>
                    <tr>
                        <th scope="col">Artikel</th>
                        <th scope="col">Inventarnummer</th>
                        <th scope="col">Kategorie</th>
                        <th scope="col">Gesamtbestand</th>
                        <th scope="col">Verfügbar</th>
                        <th scope="col">Lagerorte</th>
                        <th scope="col">Zugeordneter Hund</th>
                        <th scope="col">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows) : ?>
                        <tr><td colspan="8">Keine gelagerten Objekte gefunden.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['title']); ?></strong><?php if ($row['notes'] !== '') : ?><p class="description"><?php echo esc_html($row['notes']); ?></p><?php endif; ?></td>
                            <td><code><?php echo esc_html($row['number']); ?></code></td>
                            <td><?php echo esc_html($row['category'] ?: '-'); ?></td>
                            <td><?php echo esc_html($row['total_label']); ?></td>
                            <td><?php echo esc_html($row['available_label']); ?></td>
                            <td><?php echo wp_kses_post($row['locations_html']); ?></td>
                            <td><?php echo wp_kses_post($row['assigned_dog_html']); ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url(get_edit_post_link($row['id'])); ?>">Bearbeiten</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php
    }

    public static function export_inventory_csv(): void
    {
        if (!self::can_access_sod_items()) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_inventory_export');

        $search = sanitize_text_field((string)($_GET['sod_search'] ?? ''));
        $category = sanitize_text_field((string)($_GET['sod_category'] ?? ''));
        $location = sanitize_text_field((string)($_GET['sod_location'] ?? ''));
        $assigned_filter = sanitize_text_field((string)($_GET['sod_assigned'] ?? ''));
        $assigned_dog_filter = absint($_GET['sod_assigned_dog'] ?? 0);
        $rows = self::inventory_rows($search, $category, $location, $assigned_filter, $assigned_dog_filter);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shield-lageruebersicht-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fputcsv($out, ['Artikel', 'Inventarnummer', 'Kategorie', 'Einheit', 'Gesamtbestand', 'Verfügbar', 'Lagerorte', 'Zugeordneter Hund', 'Notizen'], ';');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                $row['title'],
                $row['number'],
                $row['category'],
                $row['unit'],
                $row['total'],
                $row['available'],
                $row['locations_text'],
                $row['assigned_dog_label'],
                $row['notes'],
            ]), ';');
        }
        fclose($out);
        exit;
    }

    public static function export_photo_needs_csv(): void
    {
        if (!self::can_access_sod_dogs()) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_photo_needs_export');

        $items = self::dog_photo_need_items(0);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shield-bildbedarf-hunde-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fputcsv($out, ['Hund', 'Problem', 'Status', 'Sichtbarkeit', 'Aufenthaltsort', 'Bearbeiten'], ';');
        foreach ($items as $item) {
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                (string)$item['label'],
                (string)$item['info'],
                (string)$item['status'],
                (string)$item['visibility'],
                (string)$item['location'],
                (string)$item['url'],
            ]), ';');
        }
        fclose($out);
        exit;
    }

    private static function csv_cell(string $value): string
    {
        $trimmed_start = ltrim($value, " \t\r\n");
        if ($trimmed_start !== '' && in_array($trimmed_start[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }
        return $value;
    }

    private static function finance_report_rows(int $year): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        $months = [];
        $categories = [];
        $years = [];
        $category_labels = self::finance_category_options();

        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $iso = self::iso_date((string)get_post_meta($post_id, 'sod_finance_date', true));
            if ($iso === '') {
                $iso = gmdate('Y-m-d', (int)get_post_time('U', true, $post_id));
            }
            $entry_year = (int)substr($iso, 0, 4);
            $years[$entry_year] = true;
            if ($entry_year !== $year) {
                continue;
            }
            $month = (int)substr($iso, 5, 2);
            $type = (string)get_post_meta($post_id, 'sod_finance_type', true);
            $amount = self::numeric_quantity((string)get_post_meta($post_id, 'sod_finance_amount', true));
            if (!isset($months[$month])) {
                $months[$month] = ['einnahmen' => 0.0, 'ausgaben' => 0.0, 'sachspenden' => 0, 'eintraege' => 0];
            }
            $months[$month]['eintraege']++;
            if ($type === 'spende' || $type === 'erstattung') {
                $months[$month]['einnahmen'] += $amount;
            } elseif ($type === 'ausgabe') {
                $months[$month]['ausgaben'] += $amount;
            } elseif ($type === 'sachspende') {
                $months[$month]['sachspenden']++;
            }
            $category = (string)get_post_meta($post_id, 'sod_finance_category', true);
            $category_label = $category_labels[$category] ?? ($category !== '' ? $category : 'Ohne Zweck');
            if (!isset($categories[$category_label])) {
                $categories[$category_label] = ['einnahmen' => 0.0, 'ausgaben' => 0.0];
            }
            if ($type === 'spende' || $type === 'erstattung') {
                $categories[$category_label]['einnahmen'] += $amount;
            } elseif ($type === 'ausgabe') {
                $categories[$category_label]['ausgaben'] += $amount;
            }
        }
        ksort($months);
        ksort($categories);
        $years = array_keys($years);
        rsort($years);
        return ['months' => $months, 'categories' => $categories, 'years' => $years];
    }

    /**
     * Einzelne Einnahmen-Eintraege (Geldspende + Erstattung) fuer die "Alle Einnahmen"-Tabelle
     * im Finanzbericht - im Gegensatz zu finance_report_rows() nicht aggregiert, sondern
     * Zeile pro Eintrag, damit nach Hund/Zahlungsart/Datum/Name/Betrag sortiert werden kann.
     */
    private static function finance_income_entries(int $year, string $sort, string $order): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        $payment_labels = self::finance_payment_method_options();
        $rows = [];
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $type = (string)get_post_meta($post_id, 'sod_finance_type', true);
            if ($type !== 'spende' && $type !== 'erstattung') {
                continue;
            }
            $iso = self::iso_date((string)get_post_meta($post_id, 'sod_finance_date', true));
            if ($iso === '') {
                $iso = gmdate('Y-m-d', (int)get_post_time('U', true, $post_id));
            }
            if ((int)substr($iso, 0, 4) !== $year) {
                continue;
            }
            $dog_id = absint(get_post_meta($post_id, 'sod_finance_dog', true));
            $payment_method = (string)get_post_meta($post_id, 'sod_finance_payment_method', true);
            $donor = (string)get_post_meta($post_id, 'sod_finance_donor', true);
            $rows[] = [
                'id' => $post_id,
                'date' => $iso,
                'dog' => $dog_id > 0 ? (get_the_title($dog_id) ?: '') : '',
                'donor' => $donor !== '' ? $donor : (get_the_title($post_id) ?: ('Eintrag #' . $post_id)),
                'payment_method' => $payment_labels[$payment_method] ?? ($payment_labels[''] ?? '– nicht angegeben –'),
                'amount' => self::numeric_quantity((string)get_post_meta($post_id, 'sod_finance_amount', true)),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url('edit.php?post_type=sod_finance'),
            ];
        }

        $key = in_array($sort, ['dog', 'payment_method', 'date', 'donor', 'amount'], true) ? $sort : 'date';
        usort($rows, static function (array $a, array $b) use ($key, $order): int {
            $value_a = $a[$key];
            $value_b = $b[$key];
            $cmp = is_float($value_a) ? ($value_a <=> $value_b) : strnatcasecmp((string)$value_a, (string)$value_b);
            return $order === 'asc' ? $cmp : -$cmp;
        });

        return $rows;
    }

    /**
     * Aktive Paten, die im angegebenen Monat noch keinen Finanzeintrag haben. Abgleich
     * ueber den Spendernamen (bei automatisch erfassten Eintraegen identisch mit dem Titel
     * des Paten-Datensatzes) - erkennt so vor allem Dauerauftrag-Paten, deren monatliche
     * Folgezahlung nicht manuell nacherfasst wurde, sowie ausgebliebene PayPal-Buchungen.
     * Rein heuristisch: manuell erfasste Eintraege mit abweichendem Spendernamen werden
     * nicht erkannt und der Pate faelschlich als "fehlend" gelistet.
     */
    private static function sponsors_missing_finance_entry(int $year, int $month): array
    {
        $sponsors = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'sod_sponsor_status', 'value' => 'aktiv'],
            ],
        ]);

        $month_prefix = sprintf('%04d-%02d', $year, $month);
        $covered_titles = [];
        $finance_query = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        foreach ($finance_query->posts as $finance_id) {
            $finance_id = (int)$finance_id;
            $type = (string)get_post_meta($finance_id, 'sod_finance_type', true);
            if ($type !== 'spende' && $type !== 'erstattung') {
                continue;
            }
            $iso = self::iso_date((string)get_post_meta($finance_id, 'sod_finance_date', true));
            if ($iso === '') {
                $iso = gmdate('Y-m-d', (int)get_post_time('U', true, $finance_id));
            }
            if (strpos($iso, $month_prefix) !== 0) {
                continue;
            }
            $donor = trim((string)get_post_meta($finance_id, 'sod_finance_donor', true));
            if ($donor !== '') {
                $covered_titles[$donor] = true;
            }
        }

        $payment_labels = self::sponsor_payment_method_options();
        $missing = [];
        foreach ($sponsors as $sponsor_id) {
            $sponsor_id = (int)$sponsor_id;
            $title = get_the_title($sponsor_id) ?: ('Pate #' . $sponsor_id);
            if (isset($covered_titles[$title])) {
                continue;
            }
            $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
            $payment_method = (string)get_post_meta($sponsor_id, 'sod_sponsor_payment_method', true);
            $missing[] = [
                'id' => $sponsor_id,
                'name' => $title,
                'dog' => $dog_id > 0 ? (get_the_title($dog_id) ?: '') : '',
                'payment_method' => $payment_labels[$payment_method] ?? $payment_method,
                'amount' => (string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true),
                'url' => get_edit_post_link($sponsor_id, 'raw') ?: admin_url('edit.php?post_type=sod_sponsor'),
            ];
        }
        usort($missing, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $missing;
    }

    private static function finance_summary(int $year): array
    {
        $report = self::finance_report_rows($year);
        $income = 0.0;
        $expense = 0.0;
        foreach ($report['months'] as $month) {
            $income += (float)($month['einnahmen'] ?? 0);
            $expense += (float)($month['ausgaben'] ?? 0);
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ];
    }

    private static function money_label(float $amount): string
    {
        return number_format_i18n($amount, 2) . ' €';
    }

    public static function finance_report_page(): void
    {
        if (!self::can_access_sod_finances()) {
            wp_die('Keine Berechtigung.');
        }
        $year = absint($_GET['sod_year'] ?? 0) ?: (int)date_i18n('Y');
        $report = self::finance_report_rows($year);
        $month_names = [1 => 'Jänner', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
        $total_in = array_sum(array_column($report['months'], 'einnahmen'));
        $total_out = array_sum(array_column($report['months'], 'ausgaben'));
        $sort = sanitize_text_field((string)($_GET['sod_sort'] ?? ''));
        $sort = in_array($sort, ['dog', 'payment_method', 'date', 'donor', 'amount'], true) ? $sort : 'date';
        $order = sanitize_text_field((string)($_GET['sod_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $income_entries = self::finance_income_entries($year, $sort, $order);
        $export_url = wp_nonce_url(
            add_query_arg(['action' => 'sod_finance_report_export', 'sod_year' => $year], admin_url('admin-post.php')),
            'sod_finance_report_export'
        );
        $entries_export_url = wp_nonce_url(
            add_query_arg(['action' => 'sod_finance_entries_export', 'sod_year' => $year], admin_url('admin-post.php')),
            'sod_finance_entries_export'
        );
        $income_export_url = wp_nonce_url(
            add_query_arg(['action' => 'sod_finance_income_export', 'sod_year' => $year, 'sod_sort' => $sort, 'sod_order' => $order], admin_url('admin-post.php')),
            'sod_finance_income_export'
        );
        $current_month = (int)date_i18n('n');
        $missing_sponsors = ($year === (int)date_i18n('Y')) ? self::sponsors_missing_finance_entry($year, $current_month) : [];
        $sort_link = static function (string $column, string $label) use ($year, $sort, $order): string {
            $next_order = ($sort === $column && $order === 'asc') ? 'desc' : 'asc';
            $url = add_query_arg([
                'post_type' => 'sod_dog',
                'page' => 'sod-finance-report',
                'sod_year' => $year,
                'sod_sort' => $column,
                'sod_order' => $next_order,
            ], admin_url('edit.php'));
            $indicator = $sort === $column ? ($order === 'asc' ? ' ▲' : ' ▼') : '';
            return sprintf('<a href="%s">%s%s</a>', esc_url($url), esc_html($label), esc_html($indicator));
        };
        ?>
        <div class="wrap sod-finance-report">
            <div class="sod-print-header">
                <img class="sod-print-logo" src="<?php echo esc_url(get_theme_file_uri('assets/images/logo.png')); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                <div class="sod-print-header-text">
                    <strong><?php echo esc_html(self::org()['name']); ?></strong>
                    <span>Finanzbericht <?php echo esc_html((string)$year); ?> · Erstellt am <?php echo esc_html(date_i18n('d.m.Y')); ?></span>
                </div>
                <div class="sod-print-confidential">Nur für internen Gebrauch</div>
            </div>
            <h1 class="wp-heading-inline">Finanzbericht <?php echo esc_html((string)$year); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url($export_url); ?>">Summen exportieren</a>
            <a class="page-title-action" href="<?php echo esc_url($entries_export_url); ?>">Einträge exportieren</a>
            <button class="page-title-action sod-print-button" type="button" onclick="window.print()">Drucken / PDF</button>
            <hr class="wp-header-end">

            <form method="get" class="sod-overview-filters">
                <input type="hidden" name="post_type" value="sod_dog">
                <input type="hidden" name="page" value="sod-finance-report">
                <label for="sod_year"><strong>Jahr</strong></label>
                <select id="sod_year" name="sod_year">
                    <?php foreach (($report['years'] ?: [$year]) as $option_year) : ?>
                        <option value="<?php echo esc_attr((string)$option_year); ?>" <?php selected($year, $option_year); ?>><?php echo esc_html((string)$option_year); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" type="submit">Anzeigen</button>
            </form>

            <div class="sod-overview-cards">
                <div class="sod-overview-card"><span>Einnahmen</span><strong><?php echo esc_html(self::format_quantity($total_in) . ' €'); ?></strong></div>
                <div class="sod-overview-card"><span>Ausgaben</span><strong><?php echo esc_html(self::format_quantity($total_out) . ' €'); ?></strong></div>
                <div class="sod-overview-card"><span>Saldo</span><strong><?php echo esc_html(self::format_quantity($total_in - $total_out) . ' €'); ?></strong></div>
                <div class="sod-overview-card"><span>Sachspenden</span><strong><?php echo esc_html((string)array_sum(array_column($report['months'], 'sachspenden'))); ?></strong></div>
            </div>

            <h2>Monatsübersicht</h2>
            <div class="sod-admin-table-scroll">
            <table class="widefat striped">
                <thead><tr><th scope="col">Monat</th><th scope="col">Einnahmen</th><th scope="col">Ausgaben</th><th scope="col">Saldo</th><th scope="col">Sachspenden</th><th scope="col">Einträge</th></tr></thead>
                <tbody>
                <?php if (!$report['months']) : ?>
                    <tr><td colspan="6">Keine Einträge für dieses Jahr.</td></tr>
                <?php endif; ?>
                <?php foreach ($report['months'] as $month => $sums) : ?>
                    <tr>
                        <td><?php echo esc_html($month_names[$month] ?? (string)$month); ?></td>
                        <td><?php echo esc_html(self::format_quantity($sums['einnahmen']) . ' €'); ?></td>
                        <td><?php echo esc_html(self::format_quantity($sums['ausgaben']) . ' €'); ?></td>
                        <td><?php echo esc_html(self::format_quantity($sums['einnahmen'] - $sums['ausgaben']) . ' €'); ?></td>
                        <td><?php echo esc_html((string)$sums['sachspenden']); ?></td>
                        <td><?php echo esc_html((string)$sums['eintraege']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <h2>Nach Zweck</h2>
            <div class="sod-admin-table-scroll">
            <table class="widefat striped">
                <thead><tr><th scope="col">Zweck</th><th scope="col">Einnahmen</th><th scope="col">Ausgaben</th></tr></thead>
                <tbody>
                <?php if (!$report['categories']) : ?>
                    <tr><td colspan="3">Keine Einträge für dieses Jahr.</td></tr>
                <?php endif; ?>
                <?php foreach ($report['categories'] as $label => $sums) : ?>
                    <tr>
                        <td><?php echo esc_html((string)$label); ?></td>
                        <td><?php echo esc_html(self::format_quantity($sums['einnahmen']) . ' €'); ?></td>
                        <td><?php echo esc_html(self::format_quantity($sums['ausgaben']) . ' €'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <h2 class="wp-heading-inline">Alle Einnahmen <?php echo esc_html((string)$year); ?></h2>
            <a class="page-title-action" href="<?php echo esc_url($income_export_url); ?>">Einnahmen exportieren</a>
            <div class="sod-admin-table-scroll">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo $sort_link('date', 'Datum'); ?></th>
                        <th scope="col"><?php echo $sort_link('dog', 'Hund'); ?></th>
                        <th scope="col"><?php echo $sort_link('donor', 'Name'); ?></th>
                        <th scope="col"><?php echo $sort_link('payment_method', 'Zahlungsart'); ?></th>
                        <th scope="col"><?php echo $sort_link('amount', 'Betrag'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$income_entries) : ?>
                    <tr><td colspan="5">Keine Einnahmen für dieses Jahr.</td></tr>
                <?php endif; ?>
                <?php foreach ($income_entries as $row) : ?>
                    <tr>
                        <td><?php echo esc_html(self::display_date($row['date'])); ?></td>
                        <td><?php echo $row['dog'] !== '' ? esc_html($row['dog']) : '–'; ?></td>
                        <td><a href="<?php echo esc_url($row['url']); ?>"><?php echo esc_html($row['donor']); ?></a></td>
                        <td><?php echo esc_html($row['payment_method']); ?></td>
                        <td><?php echo esc_html(self::format_quantity($row['amount']) . ' €'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="description">Beträge werden aus dem Feld „Betrag / Menge" gelesen; Einträge ohne Zahl zählen mit 0. Sachspenden werden gezählt, nicht summiert.</p>

            <?php if ($year === (int)date_i18n('Y')) : ?>
            <h2>Paten ohne Zahlungseintrag <?php echo esc_html($month_names[$current_month] ?? (string)$current_month); ?></h2>
            <div class="sod-admin-table-scroll">
            <table class="widefat striped">
                <thead><tr><th scope="col">Name</th><th scope="col">Hund</th><th scope="col">Zahlungsart</th><th scope="col">Betrag</th></tr></thead>
                <tbody>
                <?php if (!$missing_sponsors) : ?>
                    <tr><td colspan="4">Alle aktiven Paten haben diesen Monat einen Eintrag.</td></tr>
                <?php endif; ?>
                <?php foreach ($missing_sponsors as $row) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url($row['url']); ?>"><?php echo esc_html($row['name']); ?></a></td>
                        <td><?php echo $row['dog'] !== '' ? esc_html($row['dog']) : '–'; ?></td>
                        <td><?php echo esc_html($row['payment_method']); ?></td>
                        <td><?php echo esc_html($row['amount'] !== '' ? self::format_quantity((float)$row['amount']) . ' €' : '–'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="description">Basis: Abgleich über den Patennamen mit den Einnahmen dieses Monats. Manuell erfasste Einträge mit abweichendem Namen werden hier nicht erkannt.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function export_finance_report_csv(): void
    {
        if (!self::can_access_sod_finances()) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_finance_report_export');
        $year = absint($_GET['sod_year'] ?? 0) ?: (int)date_i18n('Y');
        $report = self::finance_report_rows($year);
        $month_names = [1 => 'Jänner', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shield-finanzbericht-' . $year . '.csv');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fputcsv($out, ['Monat', 'Einnahmen', 'Ausgaben', 'Saldo', 'Sachspenden', 'Einträge'], ';');
        foreach ($report['months'] as $month => $sums) {
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                $month_names[$month] ?? (string)$month,
                self::format_quantity($sums['einnahmen']),
                self::format_quantity($sums['ausgaben']),
                self::format_quantity($sums['einnahmen'] - $sums['ausgaben']),
                (string)$sums['sachspenden'],
                (string)$sums['eintraege'],
            ]), ';');
        }
        fputcsv($out, [], ';');
        fputcsv($out, ['Zweck', 'Einnahmen', 'Ausgaben'], ';');
        foreach ($report['categories'] as $label => $sums) {
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                (string)$label,
                self::format_quantity($sums['einnahmen']),
                self::format_quantity($sums['ausgaben']),
            ]), ';');
        }
        fclose($out);
        exit;
    }

    public static function export_finance_entries_csv(): void
    {
        if (!self::can_access_sod_finances()) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_finance_entries_export');

        $year = absint($_GET['sod_year'] ?? 0) ?: (int)date_i18n('Y');
        $type_labels = self::finance_type_options();
        $category_labels = self::finance_category_options();
        $query = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shield-finanzeintraege-' . $year . '.csv');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fputcsv($out, ['Datum', 'Art', 'Zweck', 'Betrag / Menge', 'Spender / Kontakt', 'Adresse', 'E-Mail', 'Bestätigung gesendet am', 'Titel', 'Notizen'], ';');
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $iso = self::iso_date((string)get_post_meta($post_id, 'sod_finance_date', true));
            if ($iso === '') {
                $iso = gmdate('Y-m-d', (int)get_post_time('U', true, $post_id));
            }
            if ((int)substr($iso, 0, 4) !== $year) {
                continue;
            }

            $type = (string)get_post_meta($post_id, 'sod_finance_type', true);
            $category = (string)get_post_meta($post_id, 'sod_finance_category', true);
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                $iso,
                $type_labels[$type] ?? $type,
                $category_labels[$category] ?? ($category !== '' ? $category : 'Ohne Zweck'),
                (string)get_post_meta($post_id, 'sod_finance_amount', true),
                (string)get_post_meta($post_id, 'sod_finance_donor', true),
                (string)get_post_meta($post_id, 'sod_finance_donor_address', true),
                (string)get_post_meta($post_id, 'sod_finance_donor_email', true),
                (string)get_post_meta($post_id, 'sod_finance_receipt_sent_at', true),
                get_the_title($post_id),
                wp_strip_all_tags((string)get_post_meta($post_id, 'sod_finance_notes', true)),
            ]), ';');
        }
        fclose($out);
        exit;
    }

    public static function export_finance_income_csv(): void
    {
        if (!self::can_access_sod_finances()) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_finance_income_export');

        $year = absint($_GET['sod_year'] ?? 0) ?: (int)date_i18n('Y');
        $sort = sanitize_text_field((string)($_GET['sod_sort'] ?? ''));
        $sort = in_array($sort, ['dog', 'payment_method', 'date', 'donor', 'amount'], true) ? $sort : 'date';
        $order = sanitize_text_field((string)($_GET['sod_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $rows = self::finance_income_entries($year, $sort, $order);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shield-einnahmen-' . $year . '.csv');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fputcsv($out, ['Datum', 'Hund', 'Name', 'Zahlungsart', 'Betrag'], ';');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                self::display_date($row['date']),
                $row['dog'],
                $row['donor'],
                $row['payment_method'],
                self::format_quantity($row['amount']),
            ]), ';');
        }
        fclose($out);
        exit;
    }

    public static function print_case_contract(): void
    {
        $case_id = absint($_GET['case_id'] ?? 0);
        if (!$case_id || !current_user_can('edit_post', $case_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_case_contract_' . $case_id);
        $case = get_post($case_id);
        if (!$case instanceof WP_Post || $case->post_type !== 'sod_case') {
            wp_die('Vermittlungsakte nicht gefunden.');
        }

        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : '';
        $adopter = (string)get_post_meta($case_id, 'sod_case_adopter', true);
        $fee = (string)get_post_meta($case_id, 'sod_case_fee', true);
        $documents = (string)get_post_meta($case_id, 'sod_case_documents', true);
        $notes = (string)get_post_meta($case_id, 'sod_case_notes', true);
        $logo_url = get_theme_file_uri('assets/images/logo.png');

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Schutzvertrag - <?php echo esc_html($dog_name ?: get_the_title($case_id)); ?></title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.55;margin:32px}
                .head{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #204060;padding-bottom:16px;margin-bottom:24px}
                .org{display:flex;align-items:center;gap:14px}
                .logo{width:64px;height:64px;object-fit:contain;flex:0 0 auto}
                h1{font-size:26px;margin:0 0 8px;color:#204060}
                h2{font-size:17px;margin:24px 0 8px;color:#204060}
                table{width:100%;border-collapse:collapse;margin:12px 0}
                th,td{text-align:left;vertical-align:top;border:1px solid #d7dde2;padding:8px}
                th{width:32%;background:#f5f7f8}
                .sign{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:52px}
                .line{border-top:1px solid #172b36;padding-top:8px}
                .print{position:fixed;right:20px;top:20px}
                @media print{.print{display:none}body{margin:18mm}}
            </style>
        </head>
        <body>
            <button class="print" onclick="window.print()">Drucken / als PDF speichern</button>
            <div class="head">
                <div class="org">
                    <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                    <div>
                        <h1>Schutzvertrag / Vermittlungsvereinbarung</h1>
                        <div><?php echo esc_html(self::org()['name']); ?></div>
                    </div>
                </div>
                <div>Datum: <?php echo esc_html(date_i18n('d.m.Y')); ?></div>
            </div>
            <h2>Hund</h2>
            <table>
                <tr><th>Name</th><td><?php echo esc_html($dog_name ?: '-'); ?></td></tr>
                <tr><th>Chipnummer</th><td><?php echo esc_html($dog_id ? (string)get_post_meta($dog_id, 'sod_microchip', true) : '-'); ?></td></tr>
                <tr><th>Gesundheit</th><td><?php echo esc_html($dog_id ? (string)get_post_meta($dog_id, 'sod_health', true) : '-'); ?></td></tr>
                <tr><th>Dokumente</th><td><?php echo nl2br(esc_html($documents ?: '-')); ?></td></tr>
            </table>
            <h2>Adoptant / Kontakt</h2>
            <p><?php echo nl2br(esc_html($adopter ?: 'Bitte Daten ergänzen.')); ?></p>
            <h2>Vereinbarung</h2>
            <p>Der Hund wird nach bestem Wissen beschrieben und mit den vorhandenen Informationen übergeben. Der Adoptant verpflichtet sich zu verantwortungsvoller Haltung, tiermedizinischer Versorgung, sicherer Unterbringung und Kontaktaufnahme mit dem Verein, falls die Haltung nicht mehr möglich ist.</p>
            <table>
                <tr><th>Schutzgebühr / Zahlung</th><td><?php echo esc_html($fee ?: '-'); ?></td></tr>
                <tr><th>Besondere Vereinbarungen</th><td><?php echo nl2br(esc_html($notes ?: '-')); ?></td></tr>
            </table>
            <div class="sign">
                <div class="line">Verein / Vertretung</div>
                <div class="line">Adoptant</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    public static function download_case_precheck_pdf(): void
    {
        $case_id = self::validated_case_id('case_id', 'sod_case_precheck_pdf_dl_');
        try {
            $pdf = self::case_precheck_pdf($case_id);
        } catch (\Throwable $e) {
            wp_die('Die PDF konnte nicht erstellt werden. Bitte erneut versuchen.');
        }
        $filename = self::case_precheck_pdf_filename($case_id);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public static function send_case_precheck_pdf(): void
    {
        $case_id = self::validated_case_id('case_id', 'sod_case_precheck_pdf_send_');
        $email = self::case_contact_email($case_id);
        if ($email === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'precheck_missing_email', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }

        $path = wp_tempnam(self::case_precheck_pdf_filename($case_id));
        if (!$path) {
            wp_safe_redirect(add_query_arg('sod_notice', 'precheck_send_failed', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }
        file_put_contents($path, self::case_precheck_pdf($case_id));

        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : 'Hund';
        $sent = wp_mail(
            $email,
            'Vorkontrolle Fragebogen - ' . $dog_name,
            'Anbei findest du den ausgefüllten Fragebogen zur Vorkontrolle.',
            array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers()),
            [$path]
        );

        @unlink($path);
        wp_safe_redirect(add_query_arg('sod_notice', $sent ? 'precheck_sent' : 'precheck_send_failed', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
        exit;
    }

    public static function save_case_precheck_pdf_to_dog(): void
    {
        $case_id = self::validated_case_id('case_id', 'sod_case_precheck_pdf_save_dog_');
        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        if (!$dog_id || get_post_type($dog_id) !== 'sod_dog') {
            wp_safe_redirect(add_query_arg('sod_notice', 'precheck_missing_dog', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }

        $path = self::private_document_path('precheck', $case_id);
        if ($path === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'precheck_save_failed', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }

        file_put_contents($path, self::case_precheck_pdf($case_id));
        @chmod($path, 0640);
        $url = self::private_document_base_url('sod_precheck_document', ['case_id' => $case_id]);

        $current = trim((string)get_post_meta($dog_id, 'sod_document_links', true));
        $line = 'Vorkontrolle PDF (Akte #' . $case_id . '): ' . $url;
        if (!str_contains($current, $url)) {
            update_post_meta($dog_id, 'sod_document_links', trim($current . "\n" . $line));
        }

        wp_safe_redirect(add_query_arg('sod_notice', 'precheck_saved_dog', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
        exit;
    }

    public static function download_case_contract_pdf(): void
    {
        $case_id = self::validated_case_id('case_id', 'sod_case_contract_pdf_dl_');
        try {
            $pdf = self::case_contract_pdf($case_id);
        } catch (\Throwable $e) {
            wp_die('Die PDF konnte nicht erstellt werden. Bitte erneut versuchen.');
        }
        $filename = self::contract_document_filename($case_id);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public static function save_case_contract_pdf(): void
    {
        $case_id = self::validated_case_id('case_id', 'sod_case_contract_pdf_save_');
        $path = self::private_document_path('contract', $case_id);
        if ($path === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'contract_save_failed', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }

        file_put_contents($path, self::case_contract_pdf($case_id));
        @chmod($path, 0640);
        $url = self::private_document_base_url('sod_contract_document', ['case_id' => $case_id]);

        $documents = trim((string)get_post_meta($case_id, 'sod_case_documents', true));
        $line = 'Schutzvertrag PDF: ' . $url;
        if (!str_contains($documents, $url)) {
            update_post_meta($case_id, 'sod_case_documents', trim($documents . "\n" . $line));
        }
        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        if ($dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
            $dog_documents = trim((string)get_post_meta($dog_id, 'sod_document_links', true));
            $dog_line = 'Schutzvertrag PDF (Akte #' . $case_id . '): ' . $url;
            if (!str_contains($dog_documents, $url)) {
                update_post_meta($dog_id, 'sod_document_links', trim($dog_documents . "\n" . $dog_line));
            }
        }
        if ((string)get_post_meta($case_id, 'sod_case_contract', true) === 'offen') {
            update_post_meta($case_id, 'sod_case_contract', 'erstellt');
        }

        wp_safe_redirect(add_query_arg('sod_notice', 'contract_saved', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
        exit;
    }

    public static function send_case_contract_pdf(): void
    {
        $case_id = self::validated_case_id('case_id', 'sod_case_contract_pdf_send_');
        $email = self::case_contact_email($case_id);
        if ($email === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'contract_missing_email', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }

        $path = wp_tempnam(self::contract_document_filename($case_id));
        if (!$path) {
            wp_safe_redirect(add_query_arg('sod_notice', 'contract_send_failed', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
            exit;
        }

        file_put_contents($path, self::case_contract_pdf($case_id));

        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : 'Hund';
        $sent = wp_mail(
            $email,
            'Schutzvertrag ' . self::org()['name'] . ' - ' . $dog_name,
            "Anbei findest du den Schutzvertrag als PDF.\n\nBitte lies den Vertrag in Ruhe durch und melde dich bei Fragen direkt bei uns.",
            array_merge(['Content-Type: text/plain; charset=UTF-8'], self::mail_headers()),
            [$path]
        );

        @unlink($path);
        if ($sent) {
            update_post_meta($case_id, 'sod_case_contract', 'gesendet');
            update_post_meta($case_id, 'sod_case_contract_sent_at', current_time('mysql'));
        }

        wp_safe_redirect(add_query_arg('sod_notice', $sent ? 'contract_sent' : 'contract_send_failed', get_edit_post_link($case_id, 'raw') ?: admin_url('edit.php?post_type=sod_case')));
        exit;
    }

    public static function view_contract_document(): void
    {
        $case_id = absint($_GET['case_id'] ?? 0);
        if (!$case_id || !current_user_can('edit_post', $case_id) || get_post_type($case_id) !== 'sod_case') {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_contract_document_' . $case_id);
        $path = self::private_document_path('contract', $case_id);
        if ($path === '' || !is_readable($path)) {
            wp_die('Dokument nicht gefunden. Bitte in der Vermittlungsakte erneut „Schutzvertrag-PDF ablegen“ ausführen.');
        }
        self::serve_private_document($path, 'application/pdf', self::contract_document_filename($case_id), true);
    }

    public static function view_dog_health_document(): void
    {
        $dog_id = absint($_GET['dog_id'] ?? 0);
        $file = sanitize_file_name((string)($_GET['file'] ?? ''));
        if (!$dog_id || $file === '' || !current_user_can('edit_post', $dog_id) || get_post_type($dog_id) !== 'sod_dog') {
            wp_die('Keine Berechtigung.');
        }
        if (!str_starts_with($file, 'gesundheit-hund-' . $dog_id . '-')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer(self::health_document_nonce_action($dog_id, $file));

        $private_dir = self::private_upload_dir();
        $path = $private_dir !== '' ? trailingslashit($private_dir) . $file : '';
        if (!self::is_private_document_path($path, $private_dir)) {
            wp_die('Dokument nicht gefunden.');
        }

        $mime = self::health_document_mime_type($path);
        self::serve_private_document($path, $mime, basename($path), str_starts_with($mime, 'image/') || $mime === 'application/pdf');
    }

    private static function contract_document_filename(int $case_id): string
    {
        return 'schutzvertrag-akte-' . $case_id . '.pdf';
    }

    private static function case_contract_pdf(int $case_id): string
    {
        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : '-';
        $adopter = trim((string)get_post_meta($case_id, 'sod_case_adopter', true));
        $notes = trim((string)get_post_meta($case_id, 'sod_case_notes', true));
        $documents = trim((string)get_post_meta($case_id, 'sod_case_documents', true));

        $blocks = [
            ['type' => 'heading', 'text' => 'HUND'],
            ['type' => 'fact', 'label' => 'Name', 'value' => $dog_name],
            ['type' => 'fact', 'label' => 'Chipnummer', 'value' => (($dog_id ? (string)get_post_meta($dog_id, 'sod_microchip', true) : '') ?: '-')],
            ['type' => 'fact', 'label' => 'Gesundheit', 'value' => (($dog_id ? (string)get_post_meta($dog_id, 'sod_health', true) : '') ?: '-')],
            ['type' => 'space', 'h' => 6],
            ['type' => 'heading', 'text' => 'ADOPTANT / KONTAKT'],
            ['type' => 'lines', 'lines' => preg_split('/\r\n|\r|\n/', $adopter !== '' ? $adopter : '-') ?: ['-']],
            ['type' => 'space', 'h' => 6],
            ['type' => 'heading', 'text' => 'VEREINBARUNG'],
            ['type' => 'paragraph', 'text' => 'Der Hund wird nach bestem Wissen beschrieben und mit den vorhandenen Informationen übergeben. Der Adoptant verpflichtet sich zu verantwortungsvoller Haltung, tiermedizinischer Versorgung, sicherer Unterbringung und Kontaktaufnahme mit dem Verein, falls die Haltung nicht mehr möglich ist.'],
            ['type' => 'space', 'h' => 8],
            ['type' => 'fact', 'label' => 'Schutzgebühr / Zahlung', 'value' => ((string)get_post_meta($case_id, 'sod_case_fee', true) ?: '-')],
            ['type' => 'space', 'h' => 6],
            ['type' => 'heading', 'text' => 'BESONDERE VEREINBARUNGEN'],
            ['type' => 'lines', 'lines' => preg_split('/\r\n|\r|\n/', $notes !== '' ? $notes : '-') ?: ['-']],
            ['type' => 'space', 'h' => 6],
            ['type' => 'heading', 'text' => 'DOKUMENTE'],
            ['type' => 'lines', 'lines' => preg_split('/\r\n|\r|\n/', $documents !== '' ? $documents : '-') ?: ['-']],
            ['type' => 'space', 'h' => 16],
            ['type' => 'signature', 'left' => 'Verein / Vertretung', 'right' => 'Adoptant', 'field_prefix' => 'sod_contract_' . $case_id],
        ];

        $meta = 'Vertragsnummer: SOD-V-' . gmdate('Y') . '-' . str_pad((string)$case_id, 5, '0', STR_PAD_LEFT) . '   ·   Datum: ' . date_i18n('d.m.Y');
        $footer = self::org_footer_line();

        return self::build_case_pdf('SCHUTZVERTRAG', 'Vermittlungsvereinbarung', $meta, $blocks, $footer);
    }

    public static function view_precheck_document(): void
    {
        $case_id = absint($_GET['case_id'] ?? 0);
        if (!$case_id || !current_user_can('edit_post', $case_id) || get_post_type($case_id) !== 'sod_case') {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_precheck_document_' . $case_id);
        $path = self::private_document_path('precheck', $case_id);
        if ($path === '' || !is_readable($path)) {
            wp_die('Dokument nicht gefunden. Bitte in der Vermittlungsakte erneut „PDF beim Hund speichern“ ausführen.');
        }
        self::serve_private_document($path, 'application/pdf', self::precheck_document_filename($case_id), true);
    }

    private static function precheck_document_filename(int $case_id): string
    {
        return 'vorkontrolle-akte-' . $case_id . '.pdf';
    }

    private static function handle_dog_health_document_upload(int $dog_id): void
    {
        if (empty($_FILES['sod_health_document_upload']) || !is_array($_FILES['sod_health_document_upload'])) {
            return;
        }
        $file = $_FILES['sod_health_document_upload'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return;
        }

        $tmp_name = (string)$file['tmp_name'];
        $original_name = sanitize_file_name((string)($file['name'] ?? 'gesundheitsdokument'));
        $size = (int)($file['size'] ?? 0);
        $max_size = min((int)wp_max_upload_size(), 10 * 1024 * 1024);
        if ($size <= 0 || $size > $max_size) {
            return;
        }

        $allowed = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $check = wp_check_filetype_and_ext($tmp_name, $original_name, $allowed);
        $ext = strtolower((string)($check['ext'] ?? ''));
        if ($ext === '' || !isset($allowed[$ext])) {
            return;
        }

        $private_dir = self::private_upload_dir();
        if ($private_dir === '') {
            return;
        }

        $label = preg_replace('/\.[^.]+$/', '', $original_name) ?: 'Gesundheitsdokument';
        $filename = sanitize_file_name(sprintf(
            'gesundheit-hund-%d-%s-%s.%s',
            $dog_id,
            gmdate('Ymd-His'),
            wp_generate_password(8, false, false),
            $ext
        ));
        $path = trailingslashit($private_dir) . $filename;
        if (!move_uploaded_file($tmp_name, $path)) {
            return;
        }
        @chmod($path, 0640);

        $url = self::private_document_base_url('sod_dog_health_document', [
            'dog_id' => $dog_id,
            'file' => $filename,
        ]);
        $line = trim($label) . ': ' . $url;
        $documents = trim((string)get_post_meta($dog_id, 'sod_document_links', true));
        if (!str_contains($documents, $url)) {
            update_post_meta($dog_id, 'sod_document_links', trim($documents . "\n" . $line));
        }
    }

    private static function health_document_mime_type(string $path): string
    {
        $type = wp_check_filetype(basename($path));
        if (!empty($type['type'])) {
            return (string)$type['type'];
        }
        return 'application/octet-stream';
    }

    private static function private_document_path(string $kind, int $record_id): string
    {
        if ($record_id <= 0 || !in_array($kind, ['contract', 'precheck'], true)) {
            return '';
        }
        $private_dir = self::private_upload_dir();
        if ($private_dir === '') {
            return '';
        }
        $prefix = $kind === 'contract' ? 'schutzvertrag' : 'vorkontrolle';
        $token = substr(hash_hmac('sha256', $kind . ':' . $record_id, wp_salt('auth')), 0, 24);
        $path = trailingslashit($private_dir) . $prefix . '-' . $record_id . '-' . $token . '.pdf';

        $legacy_filename = $kind === 'contract'
            ? self::contract_document_filename($record_id)
            : self::precheck_document_filename($record_id);
        $legacy_path = trailingslashit($private_dir) . $legacy_filename;
        if (!file_exists($path) && is_readable($legacy_path)) {
            @rename($legacy_path, $path);
        }
        return $path;
    }

    private static function private_document_base_url(string $action, array $args): string
    {
        return add_query_arg(array_merge(['action' => $action], $args), admin_url('admin-post.php'));
    }

    private static function private_document_nonce_action(string $url): string
    {
        $url = html_entity_decode($url);
        $expected_url = admin_url('admin-post.php');
        if (strtolower((string)wp_parse_url($url, PHP_URL_HOST)) !== strtolower((string)wp_parse_url($expected_url, PHP_URL_HOST))
            || (string)wp_parse_url($url, PHP_URL_PATH) !== (string)wp_parse_url($expected_url, PHP_URL_PATH)) {
            return '';
        }
        $query = (string)wp_parse_url($url, PHP_URL_QUERY);
        parse_str($query, $args);
        $action = sanitize_key((string)($args['action'] ?? ''));
        if ($action === 'sod_contract_document') {
            $case_id = absint($args['case_id'] ?? 0);
            return $case_id > 0 ? 'sod_contract_document_' . $case_id : '';
        }
        if ($action === 'sod_precheck_document') {
            $case_id = absint($args['case_id'] ?? 0);
            return $case_id > 0 ? 'sod_precheck_document_' . $case_id : '';
        }
        if ($action === 'sod_dog_health_document') {
            $dog_id = absint($args['dog_id'] ?? 0);
            $file = sanitize_file_name((string)($args['file'] ?? ''));
            return $dog_id > 0 && $file !== '' ? self::health_document_nonce_action($dog_id, $file) : '';
        }
        return '';
    }

    private static function health_document_nonce_action(int $dog_id, string $file): string
    {
        return 'sod_dog_health_document_' . $dog_id . '_' . hash('sha256', $file);
    }

    private static function canonicalize_private_document_urls(string $value): string
    {
        return preg_replace_callback('#https?://[^\s]+#', static function (array $match): string {
            $url = html_entity_decode((string)$match[0]);
            return self::private_document_nonce_action($url) !== ''
                ? remove_query_arg(['_wpnonce', '_wp_http_referer'], $url)
                : (string)$match[0];
        }, $value) ?: $value;
    }

    private static function refresh_private_document_urls(string $value): string
    {
        return preg_replace_callback('#https?://[^\s]+#', static function (array $match): string {
            $url = html_entity_decode((string)$match[0]);
            $nonce_action = self::private_document_nonce_action($url);
            if ($nonce_action === '') {
                return (string)$match[0];
            }
            $url = remove_query_arg(['_wpnonce', '_wp_http_referer'], $url);
            return add_query_arg('_wpnonce', wp_create_nonce($nonce_action), $url);
        }, $value) ?: $value;
    }

    private static function is_private_document_path(string $path, string $private_dir): bool
    {
        if ($path === '' || $private_dir === '' || !is_readable($path)) {
            return false;
        }
        $real_path = realpath($path);
        $real_dir = realpath($private_dir);
        return $real_path !== false
            && $real_dir !== false
            && str_starts_with($real_path, trailingslashit($real_dir));
    }

    private static function serve_private_document(string $path, string $mime, string $filename, bool $inline): void
    {
        $private_dir = self::private_upload_dir();
        if (!self::is_private_document_path($path, $private_dir)) {
            wp_die('Dokument nicht gefunden.');
        }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . (string)filesize($path));
        readfile($path);
        exit;
    }

    private static function private_upload_dir(): string
    {
        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return '';
        }
        $path = trailingslashit($upload['basedir']) . 'sod-private';
        if (!is_dir($path) && !wp_mkdir_p($path)) {
            return '';
        }
        @chmod($path, 0750);
        if (!file_exists($path . '/.htaccess')) {
            file_put_contents($path . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!file_exists($path . '/web.config')) {
            file_put_contents($path . '/web.config', '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>');
        }
        if (!file_exists($path . '/index.html')) {
            file_put_contents($path . '/index.html', '');
        }
        if (!file_exists($path . '/index.php')) {
            file_put_contents($path . '/index.php', "<?php\nhttp_response_code(404);\nexit;\n");
        }
        return $path;
    }

    private static function mail_headers(): array
    {
        $host = (string)wp_parse_url(home_url(), PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host) ?: 'localhost';
        return [
            'From: ' . self::org()['name'] . ' <wordpress@' . $host . '>',
            'Reply-To: ' . self::org()['name'] . ' <' . self::org()['email'] . '>',
        ];
    }

    /**
     * Fuer Anfrage-Mails (Bestaetigung + Mitarbeiter-Antwort): Reply-To zeigt auf das
     * Ticket-Postfach, das per IMAP abgerufen wird (siehe check_inbound_application_replies()),
     * damit Antworten der anfragenden Person automatisch der Anfrage zugeordnet werden koennen.
     * Solange das Postfach noch nicht in den Einstellungen hinterlegt ist, Fallback auf die
     * bisherige allgemeine Reply-To-Adresse.
     */
    private static function application_mail_headers(): array
    {
        $headers = self::mail_headers();
        $ticket_mailbox = sanitize_email((string)get_option('sod_ticket_imap_user', ''));
        if ($ticket_mailbox === '') {
            return $headers;
        }
        foreach ($headers as $i => $header) {
            if (str_starts_with($header, 'Reply-To:')) {
                $headers[$i] = 'Reply-To: ' . self::org()['name'] . ' <' . $ticket_mailbox . '>';
            }
        }
        return $headers;
    }

    private static function application_ticket_tag(int $application_id): string
    {
        return ' [Anfrage #' . $application_id . ']';
    }

    private static function flyer_strings(string $lang): array
    {
        $strings = [
            'de' => [
                'tagline' => 'Vermittlung mit Verantwortung',
                'age' => 'Alter',
                'type' => 'Typ',
                'gender' => 'Geschlecht',
                'health' => 'Gesundheit',
                'character' => 'Charakter',
                'needs' => 'Braucht / Zuhause',
                'location_fallback' => 'Aufenthaltsort auf Anfrage',
                'contact' => 'Kontakt:',
                'inquiry' => 'Anfrage:',
                'print' => 'Drucken / als PDF speichern',
            ],
            'en' => [
                'tagline' => 'Rehoming with responsibility',
                'age' => 'Age',
                'type' => 'Type',
                'gender' => 'Gender',
                'health' => 'Health',
                'character' => 'Character',
                'needs' => 'Needs / Home',
                'location_fallback' => 'Location on request',
                'contact' => 'Contact:',
                'inquiry' => 'Inquiry:',
                'print' => 'Print / save as PDF',
            ],
            'bs' => [
                'tagline' => 'Udomljavanje s odgovornošću',
                'age' => 'Starost',
                'type' => 'Tip',
                'gender' => 'Spol',
                'health' => 'Zdravlje',
                'character' => 'Karakter',
                'needs' => 'Potrebe / Dom',
                'location_fallback' => 'Lokacija na upit',
                'contact' => 'Kontakt:',
                'inquiry' => 'Upit:',
                'print' => 'Štampaj / sačuvaj kao PDF',
            ],
        ];
        return $strings[$lang] ?? $strings['de'];
    }

    public static function print_dog_flyer(): void
    {
        $dog_id = absint($_GET['dog_id'] ?? 0);
        if (!$dog_id || !current_user_can('edit_post', $dog_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_dog_flyer_' . $dog_id);
        $dog = get_post($dog_id);
        if (!$dog instanceof WP_Post || $dog->post_type !== 'sod_dog') {
            wp_die('Hund nicht gefunden.');
        }
        $lang = sanitize_key((string)($_GET['lang'] ?? 'de'));
        $lang = in_array($lang, ['de', 'en', 'bs'], true) ? $lang : 'de';
        $text = self::flyer_strings($lang);
        $image = has_post_thumbnail($dog_id) ? get_the_post_thumbnail_url($dog_id, 'large') : '';
        if ($image === '' && self::dog_image_ids($dog_id)) {
            $image = wp_get_attachment_image_url(self::dog_image_ids($dog_id)[0], 'large') ?: '';
        }
        $contact = add_query_arg([
            'sod_dog_id' => $dog_id,
            'sod_dog_name' => self::dog_public_name($dog_id),
            'sod_interest' => 'Vermittlung',
        ], self::page_url('kontakt'));
        $logo_url = get_theme_file_uri('assets/images/logo.png');

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="<?php echo esc_attr($lang); ?>">
        <head>
            <meta charset="utf-8">
            <title>Flyer - <?php echo esc_html(get_the_title($dog_id)); ?></title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;margin:0;background:#f3f5f6}
                .page{width:210mm;min-height:297mm;margin:0 auto;background:#fff;padding:18mm;box-sizing:border-box}
                .head{display:flex;justify-content:space-between;gap:20px;align-items:center;border-bottom:3px solid #f3c74f;padding-bottom:12px}
                .org{display:flex;align-items:center;gap:12px}
                .logo{width:48px;height:48px;object-fit:contain;flex:0 0 auto}
                .brand{font-size:15px;color:#204060;font-weight:700}
                h1{font-size:42px;margin:18px 0 8px;color:#204060}
                .sub{font-size:18px;color:#52606a;margin-bottom:16px}
                .photo{width:100%;height:118mm;object-fit:cover;object-position:center 38%;border-radius:8px;background:#eef1f3}
                .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0}
                .box{border:1px solid #d7dde2;border-radius:8px;padding:10px}
                .box span{display:block;color:#68737d;font-size:12px;text-transform:uppercase;font-weight:700}
                .box strong{display:block;font-size:18px;margin-top:4px}
                h2{font-size:19px;color:#204060;margin:18px 0 6px}
                p{line-height:1.5}
                .cta{margin-top:18px;padding:14px;border-radius:8px;background:#204060;color:#fff}
                .cta a{color:#fff}
                .print{position:fixed;right:20px;top:20px}
                @media print{body{background:#fff}.page{margin:0}.print{display:none}}
            </style>
        </head>
        <body>
            <button class="print" onclick="window.print()"><?php echo esc_html($text['print']); ?></button>
            <main class="page">
                <div class="head">
                    <div class="org">
                        <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                        <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                    </div>
                    <div><?php echo esc_html($text['tagline']); ?></div>
                </div>
                <h1><?php echo esc_html(get_the_title($dog_id)); ?></h1>
                <div class="sub"><?php echo esc_html((string)get_post_meta($dog_id, 'sod_location', true) ?: $text['location_fallback']); ?></div>
                <?php if ($image !== '') : ?><img class="photo" src="<?php echo esc_url($image); ?>" alt=""><?php endif; ?>
                <div class="grid">
                    <?php foreach (['sod_age' => $text['age'], 'sod_breed' => $text['type'], 'sod_gender' => $text['gender'], 'sod_health' => $text['health']] as $key => $label) : ?>
                        <div class="box"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html((string)get_post_meta($dog_id, $key, true) ?: '-'); ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <?php foreach (['sod_character' => $text['character'], 'sod_needs' => $text['needs']] as $key => $label) : ?>
                    <?php $value = trim((string)get_post_meta($dog_id, $key, true)); ?>
                    <?php if ($value !== '') : ?><h2><?php echo esc_html($label); ?></h2><p><?php echo nl2br(esc_html($value)); ?></p><?php endif; ?>
                <?php endforeach; ?>
                <div class="cta">
                    <strong><?php echo esc_html($text['contact']); ?></strong> <?php echo esc_html(self::org()['email']); ?> · <?php echo esc_html(self::org()['phone']); ?><br>
                    <?php echo esc_html(rtrim($text['inquiry'], ':')); ?>: <a href="<?php echo esc_url($contact); ?>"><?php echo esc_html($contact); ?></a>
                </div>
            </main>
        </body>
        </html>
        <?php
        exit;
    }

    public static function print_finance_receipt(): void
    {
        $finance_id = absint($_REQUEST['finance_id'] ?? 0);
        if (!$finance_id || !current_user_can('edit_post', $finance_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_finance_receipt_' . $finance_id);

        $finance = get_post($finance_id);
        if (!$finance instanceof WP_Post || $finance->post_type !== 'sod_finance') {
            wp_die('Spenden-Eintrag nicht gefunden.');
        }

        header('Content-Type: text/html; charset=utf-8');
        echo self::finance_receipt_html($finance_id, true);
        exit;
    }

    public static function send_finance_receipt(): void
    {
        $finance_id = absint($_REQUEST['finance_id'] ?? 0);
        if (!$finance_id || !current_user_can('edit_post', $finance_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_finance_receipt_send_' . $finance_id);

        $finance = get_post($finance_id);
        if (!$finance instanceof WP_Post || $finance->post_type !== 'sod_finance') {
            wp_die('Spenden-Eintrag nicht gefunden.');
        }

        if (sanitize_email((string)get_post_meta($finance_id, 'sod_finance_donor_email', true)) === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'receipt_missing_email', get_edit_post_link($finance_id, 'raw') ?: admin_url('edit.php?post_type=sod_finance')));
            exit;
        }

        if (self::send_finance_receipt_email($finance_id, true)) {
            wp_safe_redirect(add_query_arg('sod_notice', 'receipt_sent', get_edit_post_link($finance_id, 'raw') ?: admin_url('edit.php?post_type=sod_finance')));
            exit;
        }

        wp_safe_redirect(add_query_arg('sod_notice', 'receipt_send_failed', get_edit_post_link($finance_id, 'raw') ?: admin_url('edit.php?post_type=sod_finance')));
        exit;
    }

    private static function send_finance_receipt_email(int $finance_id, bool $force = false): bool
    {
        $finance = get_post($finance_id);
        if (!$finance instanceof WP_Post || $finance->post_type !== 'sod_finance') {
            return false;
        }
        if (!$force && trim((string)get_post_meta($finance_id, 'sod_finance_receipt_sent_at', true)) !== '') {
            return true;
        }
        $email = sanitize_email((string)get_post_meta($finance_id, 'sod_finance_donor_email', true));
        update_post_meta($finance_id, 'sod_finance_receipt_last_attempt_at', current_time('mysql'));
        if ($email === '') {
            update_post_meta($finance_id, 'sod_finance_receipt_error', 'missing_email');
            return false;
        }
        $sent = wp_mail(
            $email,
            'Spendenbestätigung ' . self::org()['name'],
            self::finance_receipt_email_html($finance_id),
            array_merge(['Content-Type: text/html; charset=UTF-8'], self::mail_headers())
        );
        if ($sent) {
            update_post_meta($finance_id, 'sod_finance_receipt_sent_at', current_time('mysql'));
            delete_post_meta($finance_id, 'sod_finance_receipt_error');
            return true;
        }
        update_post_meta($finance_id, 'sod_finance_receipt_error', 'wp_mail_failed');
        return false;
    }

    public static function dog_list_filters(string $post_type): void
    {
        if ($post_type !== 'sod_dog') {
            return;
        }
        $selected = sanitize_text_field((string)($_GET['sod_status_filter'] ?? ''));
        echo '<select name="sod_status_filter"><option value="">Alle Status</option>';
        foreach (self::dog_statuses() as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($selected, $value, false), esc_html($label));
        }
        echo '</select>';
        $visibility = sanitize_text_field((string)($_GET['sod_visibility_filter'] ?? ''));
        echo '<select name="sod_visibility_filter"><option value="">Alle Sichtbarkeiten</option>';
        foreach (['adoption' => 'In Vermittlung sichtbar', 'sponsorship' => 'Für Patenschaft sichtbar', 'intern' => 'Nur intern'] as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($visibility, $value, false), esc_html($label));
        }
        echo '</select>';
        $export_url = wp_nonce_url(add_query_arg([
            'action' => 'sod_dogs_export',
            'sod_status_filter' => $selected,
            'sod_visibility_filter' => $visibility,
        ], admin_url('admin-post.php')), 'sod_dogs_export');
        printf(' <a class="button" href="%s">Hunde exportieren</a>', esc_url($export_url));
    }

    public static function apply_dog_list_filters(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'sod_dog') {
            return;
        }
        $meta_query = (array)$query->get('meta_query');
        $status = sanitize_text_field((string)($_GET['sod_status_filter'] ?? ''));
        if ($status !== '' && array_key_exists($status, self::dog_statuses())) {
            $meta_query[] = ['key' => 'sod_status', 'value' => $status];
        }
        $visibility = sanitize_text_field((string)($_GET['sod_visibility_filter'] ?? ''));
        if ($visibility === 'adoption') {
            $meta_query[] = ['key' => 'sod_show_adoption', 'value' => '1'];
        } elseif ($visibility === 'sponsorship') {
            $meta_query[] = ['key' => 'sod_show_sponsorship', 'value' => '1'];
        } elseif ($visibility === 'intern') {
            $meta_query[] = [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    ['key' => 'sod_show_adoption', 'value' => '1', 'compare' => '!='],
                    ['key' => 'sod_show_adoption', 'compare' => 'NOT EXISTS'],
                ],
                [
                    'relation' => 'OR',
                    ['key' => 'sod_show_sponsorship', 'value' => '1', 'compare' => '!='],
                    ['key' => 'sod_show_sponsorship', 'compare' => 'NOT EXISTS'],
                ],
            ];
        }
        if ($meta_query) {
            $query->set('meta_query', $meta_query);
        }
    }

    public static function sponsor_list_filters(string $post_type): void
    {
        if ($post_type !== 'sod_sponsor') {
            return;
        }
        $selected = sanitize_text_field((string)($_GET['sod_sponsor_status_filter'] ?? ''));
        echo '<select name="sod_sponsor_status_filter"><option value="">Alle Status</option>';
        foreach (self::sponsor_status_options() as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($selected, $value, false), esc_html($label));
        }
        echo '</select>';
    }

    public static function apply_sponsor_list_filters(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'sod_sponsor') {
            return;
        }
        $status = sanitize_text_field((string)($_GET['sod_sponsor_status_filter'] ?? ''));
        if ($status === '' || !array_key_exists($status, self::sponsor_status_options())) {
            return;
        }
        $meta_query = (array)$query->get('meta_query');
        $meta_query[] = ['key' => 'sod_sponsor_status', 'value' => $status];
        $query->set('meta_query', $meta_query);
    }

    public static function application_list_filters(string $post_type): void
    {
        if ($post_type !== 'sod_application') {
            return;
        }
        $selected = sanitize_text_field((string)($_GET['sod_application_status_filter'] ?? ''));
        echo '<select name="sod_application_status_filter"><option value="">Alle Status</option>';
        foreach (self::application_status_options() as $value => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($selected, $value, false), esc_html($label));
        }
        echo '</select>';
    }

    public static function apply_application_list_filters(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'sod_application') {
            return;
        }
        $status = sanitize_text_field((string)($_GET['sod_application_status_filter'] ?? ''));
        if ($status === '' || !array_key_exists($status, self::application_status_options())) {
            return;
        }
        $meta_query = (array)$query->get('meta_query');
        if ($status === 'offen') {
            // Anfragen ohne gesetzten Status gelten als "offen" (Altbestand vor dieser Funktion).
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => 'sod_application_status', 'value' => 'offen'],
                ['key' => 'sod_application_status', 'compare' => 'NOT EXISTS'],
            ];
        } else {
            $meta_query[] = ['key' => 'sod_application_status', 'value' => $status];
        }
        $query->set('meta_query', $meta_query);
    }

    public static function application_admin_columns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'title') {
                $new_columns['sod_application_status_column'] = 'Status';
            }
        }
        return $new_columns;
    }

    public static function application_admin_column_content(string $column, int $post_id): void
    {
        if ($column === 'sod_application_status_column') {
            $status = (string)get_post_meta($post_id, 'sod_application_status', true) ?: 'offen';
            $options = self::application_status_options();
            echo esc_html($options[$status] ?? $status);
        }
    }

    private static function is_sponsor_dog_sort_query(WP_Query $query): bool
    {
        return is_admin()
            && $query->is_main_query()
            && $query->get('post_type') === 'sod_sponsor'
            && $query->get('orderby') === 'sod_sponsor_dog_column';
    }

    public static function sponsor_dog_sort_join(string $join, WP_Query $query): string
    {
        if (!self::is_sponsor_dog_sort_query($query)) {
            return $join;
        }
        global $wpdb;
        $join .= " LEFT JOIN {$wpdb->postmeta} AS sod_sponsor_dog_meta ON sod_sponsor_dog_meta.post_id = {$wpdb->posts}.ID AND sod_sponsor_dog_meta.meta_key = 'sod_sponsor_dog'";
        $join .= " LEFT JOIN {$wpdb->posts} AS sod_sponsor_dog_post ON sod_sponsor_dog_post.ID = CAST(sod_sponsor_dog_meta.meta_value AS UNSIGNED)";
        return $join;
    }

    public static function sponsor_dog_sort_orderby(string $orderby, WP_Query $query): string
    {
        if (!self::is_sponsor_dog_sort_query($query)) {
            return $orderby;
        }
        $direction = strtoupper((string)$query->get('order')) === 'DESC' ? 'DESC' : 'ASC';
        return "sod_sponsor_dog_post.post_title {$direction}";
    }

    public static function export_dogs_csv(): void
    {
        if (!self::can_access_sod_dogs()) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_dogs_export');

        $status_filter = sanitize_text_field((string)($_GET['sod_status_filter'] ?? ''));
        $visibility_filter = sanitize_text_field((string)($_GET['sod_visibility_filter'] ?? ''));
        $meta_query = [];
        if ($status_filter !== '' && array_key_exists($status_filter, self::dog_statuses())) {
            $meta_query[] = ['key' => 'sod_status', 'value' => $status_filter];
        }
        if ($visibility_filter === 'adoption') {
            $meta_query[] = ['key' => 'sod_show_adoption', 'value' => '1'];
        } elseif ($visibility_filter === 'sponsorship') {
            $meta_query[] = ['key' => 'sod_show_sponsorship', 'value' => '1'];
        } elseif ($visibility_filter === 'intern') {
            $meta_query[] = [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    ['key' => 'sod_show_adoption', 'value' => '1', 'compare' => '!='],
                    ['key' => 'sod_show_adoption', 'compare' => 'NOT EXISTS'],
                ],
                [
                    'relation' => 'OR',
                    ['key' => 'sod_show_sponsorship', 'value' => '1', 'compare' => '!='],
                    ['key' => 'sod_show_sponsorship', 'compare' => 'NOT EXISTS'],
                ],
            ];
        }

        $query_args = [
            'post_type' => 'sod_dog',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ];
        if ($meta_query) {
            $query_args['meta_query'] = $meta_query;
        }
        $query = new WP_Query($query_args);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shield-hunde-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Name', 'Status', 'Sichtbarkeit', 'Fotos geprüft', 'Alter', 'Typ', 'Geschlecht', 'Gewicht', 'Aufenthaltsort', 'Bereich intern', 'Gesundheit', 'Charakter', 'Braucht / Zuhause', 'Monatlicher Futterbedarf', 'Zusätzlich manuell gesichert', 'Patenschaftsbetrag', 'Beschreibung', 'Interne Infos', 'Zuletzt geändert'], ';');
        $statuses = self::dog_statuses();
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $status = (string)get_post_meta($post_id, 'sod_status', true);
            $visibility = [];
            if (get_post_meta($post_id, 'sod_show_adoption', true) === '1') {
                $visibility[] = 'Vermittlung';
            }
            if (get_post_meta($post_id, 'sod_show_sponsorship', true) === '1') {
                $visibility[] = 'Patenschaft';
            }
            fputcsv($out, array_map([self::class, 'csv_cell'], [
                (string)$post_id,
                get_the_title($post_id),
                $statuses[$status] ?? $status,
                $visibility ? implode(', ', $visibility) : 'intern',
                get_post_meta($post_id, 'sod_photos_verified', true) === '1' ? 'ja' : 'nein',
                (string)get_post_meta($post_id, 'sod_age', true),
                (string)get_post_meta($post_id, 'sod_breed', true),
                (string)get_post_meta($post_id, 'sod_gender', true),
                (string)get_post_meta($post_id, 'sod_weight', true),
                (string)get_post_meta($post_id, 'sod_location', true),
                (string)get_post_meta($post_id, 'sod_internal_area', true),
                (string)get_post_meta($post_id, 'sod_health', true),
                (string)get_post_meta($post_id, 'sod_character', true),
                (string)get_post_meta($post_id, 'sod_needs', true),
                (string)get_post_meta($post_id, 'sod_monthly_food_need', true),
                (string)get_post_meta($post_id, 'sod_monthly_food_secured', true),
                (string)get_post_meta($post_id, 'sod_sponsorship_amount', true),
                wp_strip_all_tags((string)get_post_field('post_content', $post_id)),
                (string)get_post_meta($post_id, 'sod_internal_notes', true),
                get_post_modified_time('Y-m-d H:i:s', false, $post_id) ?: '',
            ]), ';');
        }
        fclose($out);
        exit;
    }

    public static function dog_admin_columns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            if ($key === 'cb') {
                $new_columns[$key] = $label;
                $new_columns['sod_dog_image'] = 'Bild';
                continue;
            }
            $new_columns[$key] = $label;
            if ($key === 'title') {
                $new_columns['sod_dog_status'] = 'Status';
                $new_columns['sod_dog_location'] = 'Standort / Bereich';
                $new_columns['sod_dog_visibility'] = 'Sichtbar';
                $new_columns['sod_dog_actions'] = 'Nächste Aktion';
            }
        }
        return $new_columns;
    }

    public static function dog_admin_column_content(string $column, int $post_id): void
    {
        if ($column === 'sod_dog_image') {
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, 'thumbnail', ['class' => 'sod-list-thumb']);
                return;
            }
            $image_ids = self::dog_image_ids($post_id);
            if ($image_ids) {
                echo wp_get_attachment_image($image_ids[0], 'thumbnail', false, ['class' => 'sod-list-thumb']);
                return;
            }
            echo '<span class="sod-muted">kein Bild</span>';
            return;
        }

        if ($column === 'sod_dog_status') {
            $status = (string)get_post_meta($post_id, 'sod_status', true);
            $statuses = self::dog_statuses();
            echo esc_html($statuses[$status] ?? $status ?: '-');
            return;
        }

        if ($column === 'sod_dog_location') {
            $location = trim((string)get_post_meta($post_id, 'sod_location', true));
            $area = trim((string)get_post_meta($post_id, 'sod_internal_area', true));
            echo esc_html($location !== '' ? $location : '-');
            if ($area !== '') {
                echo '<br><span class="sod-muted">' . esc_html($area) . '</span>';
            }
            return;
        }

        if ($column === 'sod_dog_visibility') {
            $labels = [];
            if (get_post_meta($post_id, 'sod_show_adoption', true) === '1') {
                $labels[] = 'Vermittlung';
            }
            if (get_post_meta($post_id, 'sod_show_sponsorship', true) === '1') {
                $labels[] = 'Patenschaft';
            }
            echo $labels ? esc_html(implode(', ', $labels)) : '<span class="sod-muted">intern</span>';
            return;
        }

        if ($column === 'sod_dog_actions') {
            $flyer_url = wp_nonce_url(add_query_arg(['action' => 'sod_dog_flyer', 'dog_id' => $post_id], admin_url('admin-post.php')), 'sod_dog_flyer_' . $post_id);
            printf('<a class="button button-small" target="_blank" href="%s">Flyer</a>', esc_url($flyer_url));
            return;
        }

        if ($column === 'sod_dog_inventory') {
            $items = self::inventory_for_dog($post_id);
            if (!$items) {
                echo '<span class="sod-muted">keine Zuordnung</span>';
                return;
            }
            echo '<ul class="sod-admin-inventory-list">';
            foreach ($items as $item) {
                printf(
                    '<li><a href="%s">%s</a><span>%s</span></li>',
                    esc_url(get_edit_post_link($item['id'])),
                    esc_html($item['title']),
                    esc_html($item['meta'])
                );
            }
            echo '</ul>';
        }
    }

    public static function interest_admin_columns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'title') {
                $new_columns['sod_interest_dog_column'] = 'Hund';
                $new_columns['sod_interest_status_column'] = 'Status';
                $new_columns['sod_interest_contact_column'] = 'Kontakt';
                $new_columns['sod_interest_followup_column'] = 'Wiedervorlage';
                $new_columns['sod_interest_action_column'] = 'Nächste Aktion';
            }
        }
        return $new_columns;
    }

    public static function interest_admin_column_content(string $column, int $post_id): void
    {
        if ($column === 'sod_interest_dog_column') {
            $dog_id = absint(get_post_meta($post_id, 'sod_interest_dog', true));
            if ($dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
                $edit_link = get_edit_post_link($dog_id);
                if ($edit_link) {
                    printf('<a href="%s">%s</a>', esc_url($edit_link), esc_html(get_the_title($dog_id)));
                    return;
                }
                echo esc_html(get_the_title($dog_id));
                return;
            }
            echo '<span class="sod-muted">kein Hund</span>';
            return;
        }

        if ($column === 'sod_interest_status_column') {
            $status = (string)get_post_meta($post_id, 'sod_interest_status', true);
            $statuses = self::interest_status_options();
            echo esc_html($statuses[$status] ?? $status ?: '-');
            return;
        }

        if ($column === 'sod_interest_contact_column') {
            $email = trim((string)get_post_meta($post_id, 'sod_interest_email', true));
            $phone = trim((string)get_post_meta($post_id, 'sod_interest_phone', true));
            echo esc_html($email !== '' ? $email : '-');
            if ($phone !== '') {
                echo '<br><span class="sod-muted">' . esc_html($phone) . '</span>';
            }
            return;
        }

        if ($column === 'sod_interest_followup_column') {
            echo esc_html(self::display_date((string)get_post_meta($post_id, 'sod_interest_followup', true)) ?: '-');
            return;
        }

        if ($column === 'sod_interest_action_column') {
            $case_url = wp_nonce_url(add_query_arg(['action' => 'sod_interest_to_case', 'interest_id' => $post_id], admin_url('admin-post.php')), 'sod_interest_to_case_' . $post_id);
            printf('<a class="button button-small" href="%s">Akte starten</a>', esc_url($case_url));
        }
    }

    public static function sponsor_admin_columns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'title') {
                $new_columns['sod_sponsor_dog_column'] = 'Hund';
                $new_columns['sod_sponsor_status_column'] = 'Status';
                $new_columns['sod_sponsor_amount_column'] = 'Betrag';
                $new_columns['sod_sponsor_payment_column'] = 'Zahlungsart';
                $new_columns['sod_sponsor_certificate_column'] = 'Zertifikat';
            }
        }
        return $new_columns;
    }

    public static function sponsor_sortable_columns(array $columns): array
    {
        $columns['sod_sponsor_dog_column'] = 'sod_sponsor_dog_column';
        return $columns;
    }

    public static function sponsor_admin_column_content(string $column, int $post_id): void
    {
        if ($column === 'sod_sponsor_dog_column') {
            $dog_id = absint(get_post_meta($post_id, 'sod_sponsor_dog', true));
            if ($dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
                $edit_link = get_edit_post_link($dog_id);
                if ($edit_link) {
                    printf('<a href="%s">%s</a>', esc_url($edit_link), esc_html(get_the_title($dog_id)));
                    return;
                }
                echo esc_html(get_the_title($dog_id));
                return;
            }
            echo '<span class="sod-muted">kein Hund</span>';
            return;
        }
        if ($column === 'sod_sponsor_status_column') {
            $status = (string)get_post_meta($post_id, 'sod_sponsor_status', true);
            $statuses = self::sponsor_status_options();
            echo esc_html($statuses[$status] ?? ($status ?: '-'));
            return;
        }
        if ($column === 'sod_sponsor_amount_column') {
            $amount = trim((string)get_post_meta($post_id, 'sod_sponsor_amount', true));
            $interval = (string)get_post_meta($post_id, 'sod_sponsor_interval', true);
            echo esc_html($amount !== '' ? $amount . ' €' : '-');
            if ($amount !== '' && $interval !== '') {
                echo '<br><span class="sod-muted">' . esc_html($interval) . '</span>';
            }
            return;
        }
        if ($column === 'sod_sponsor_payment_column') {
            $method = (string)get_post_meta($post_id, 'sod_sponsor_payment_method', true);
            $methods = self::sponsor_payment_method_options();
            echo esc_html($methods[$method] ?? ($method !== '' ? $method : '-'));
            $received = (string)get_post_meta($post_id, 'sod_sponsor_payment_received', true) === '1';
            echo '<br><span class="sod-muted">' . esc_html($received ? 'Zahlung bestätigt' : 'Zahlung offen') . '</span>';
            return;
        }
        if ($column === 'sod_sponsor_certificate_column') {
            $sent_at = self::format_utc_datetime((string)get_post_meta($post_id, 'sod_sponsor_certificate_sent_at', true));
            if ($sent_at !== '') {
                echo esc_html('versendet') . '<br><span class="sod-muted">' . esc_html($sent_at . ' Uhr') . '</span>';
                return;
            }
            if ((string)get_post_meta($post_id, 'sod_sponsor_certificate_error', true) !== '') {
                echo '<span style="color:#b32d2e;">Versand fehlgeschlagen</span>';
                return;
            }
            echo '<span class="sod-muted">noch nicht versendet</span>';
        }
    }

    public static function finance_admin_columns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'title') {
                $new_columns['sod_finance_receipt_column'] = 'Spendenbestätigung';
            }
        }
        return $new_columns;
    }

    public static function finance_admin_column_content(string $column, int $post_id): void
    {
        if ($column !== 'sod_finance_receipt_column') {
            return;
        }
        $sent_at = trim((string)get_post_meta($post_id, 'sod_finance_receipt_sent_at', true));
        if ($sent_at !== '') {
            echo esc_html('versendet') . '<br><span class="sod-muted">' . esc_html(mysql2date('d.m.Y H:i', $sent_at) . ' Uhr') . '</span>';
            return;
        }
        $error = (string)get_post_meta($post_id, 'sod_finance_receipt_error', true);
        if ($error === 'missing_email') {
            echo '<span style="color:#b32d2e;">keine E-Mail-Adresse</span>';
            return;
        }
        if ($error !== '') {
            echo '<span style="color:#b32d2e;">Versand fehlgeschlagen</span>';
            return;
        }
        echo '<span class="sod-muted">noch nicht versendet</span>';
    }

    public static function inventory_admin_columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox">',
            'title' => $columns['title'] ?? 'Artikel',
            'sod_inventory_category_column' => 'Kategorie',
            'sod_inventory_total_column' => 'Gesamtbestand',
            'sod_inventory_available_column' => 'Verfügbar',
            'sod_inventory_locations_column' => 'Lagerorte',
            'sod_inventory_dog_column' => 'Zugeordnet',
            'date' => $columns['date'] ?? 'Datum',
        ];
    }

    public static function inventory_admin_column_content(string $column, int $post_id): void
    {
        if ($column === 'sod_inventory_category_column') {
            echo esc_html((string)get_post_meta($post_id, 'sod_inventory_category', true) ?: '-');
            return;
        }
        if ($column === 'sod_inventory_total_column') {
            $total = trim((string)get_post_meta($post_id, 'sod_inventory_total', true));
            $unit = trim((string)get_post_meta($post_id, 'sod_inventory_unit', true));
            $number = self::numeric_quantity($total);
            $class = $number > 0 && $number <= self::low_stock_threshold($post_id) ? 'sod-stock-low' : '';
            printf('<span class="%s">%s</span>', esc_attr($class), esc_html(trim($total . ' ' . $unit) ?: '-'));
            return;
        }
        if ($column === 'sod_inventory_available_column') {
            $category = (string)get_post_meta($post_id, 'sod_inventory_category', true);
            $unit = (string)get_post_meta($post_id, 'sod_inventory_unit', true);
            $total = self::numeric_quantity((string)get_post_meta($post_id, 'sod_inventory_total', true));
            $assigned = self::assigned_dog_details(trim((string)get_post_meta($post_id, 'sod_inventory_assigned_dog', true)));
            echo esc_html(self::available_quantity_label($total, $unit, $category, $assigned));
            return;
        }
        if ($column === 'sod_inventory_locations_column') {
            echo wp_kses_post(self::locations_html(self::parse_locations((string)get_post_meta($post_id, 'sod_inventory_locations', true)), ''));
            return;
        }
        if ($column === 'sod_inventory_dog_column') {
            $dog_id = absint(get_post_meta($post_id, 'sod_inventory_assigned_dog', true));
            echo $dog_id > 0 && get_post_type($dog_id) === 'sod_dog' ? esc_html(get_the_title($dog_id)) : '<span class="sod-muted">nicht zugeteilt</span>';
        }
    }

    public static function import_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(self::org()['name']); ?> - JSON-Import</h1>
            <p>Importiert vorhandene Daten aus der bisherigen Website-Struktur. Bestehende Einträge werden anhand der alten ID nicht doppelt importiert.</p>
            <?php if (isset($_GET['imported'])) : ?><div class="notice notice-success"><p>Import abgeschlossen.</p></div><?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('sod_import_json', 'sod_import_nonce'); ?>
                <p><label>Pfad zu dogs.json<br><input class="regular-text" name="dogs_path" value="<?php echo esc_attr(ABSPATH . '../private-data/dogs.json'); ?>"></label></p>
                <p><label>Pfad zu food.json<br><input class="regular-text" name="food_path" value="<?php echo esc_attr(ABSPATH . '../private-data/food.json'); ?>"></label></p>
                <p><button class="button button-primary" name="sod_import_json" value="1">JSON importieren</button></p>
            </form>
        </div>
        <?php
    }

    public static function maybe_import_json(): void
    {
        if (empty($_POST['sod_import_json']) || !current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('sod_import_json', 'sod_import_nonce');
        self::import_dogs((string)($_POST['dogs_path'] ?? ''));
        self::import_inventory((string)($_POST['food_path'] ?? ''));
        wp_safe_redirect(add_query_arg('imported', '1', admin_url('edit.php?post_type=sod_dog&page=sod-json-import')));
        exit;
    }

    private static function import_dogs(string $path): void
    {
        $items = self::read_json_array($path);
        foreach ($items as $item) {
            $old_id = sanitize_text_field((string)($item['id'] ?? ''));
            if ($old_id !== '' && self::post_exists_by_meta('sod_dog', 'sod_old_id', $old_id)) {
                continue;
            }
            $post_id = wp_insert_post([
                'post_type' => 'sod_dog',
                'post_status' => 'publish',
                'post_title' => sanitize_text_field((string)($item['name'] ?? 'Hund')),
                'post_content' => sanitize_textarea_field((string)($item['description'] ?? '')),
            ]);
            if (!$post_id || is_wp_error($post_id)) {
                continue;
            }
            update_post_meta($post_id, 'sod_old_id', $old_id);
            $map = [
                'status' => 'sod_status',
                'age' => 'sod_age',
                'breed' => 'sod_breed',
                'gender' => 'sod_gender',
                'weight' => 'sod_weight',
                'location' => 'sod_location',
                'health' => 'sod_health',
                'character' => 'sod_character',
                'needs' => 'sod_needs',
                'showAdoption' => 'sod_show_adoption',
                'showSponsorship' => 'sod_show_sponsorship',
                'sponsorshipAmount' => 'sod_sponsorship_amount',
                'sponsorshipText' => 'sod_sponsorship_text',
            ];
            foreach ($map as $source => $target) {
                update_post_meta($post_id, $target, sanitize_textarea_field((string)($item[$source] ?? '')));
            }
        }
    }

    private static function import_inventory(string $path): void
    {
        $items = self::read_json_array($path);
        foreach ($items as $item) {
            $old_id = sanitize_text_field((string)($item['id'] ?? ''));
            if ($old_id !== '' && self::post_exists_by_meta('sod_inventory', 'sod_old_id', $old_id)) {
                continue;
            }
            $post_id = wp_insert_post([
                'post_type' => 'sod_inventory',
                'post_status' => 'private',
                'post_title' => sanitize_text_field((string)($item['name'] ?? 'Artikel')),
                'post_content' => sanitize_textarea_field((string)($item['notes'] ?? '')),
            ]);
            if (!$post_id || is_wp_error($post_id)) {
                continue;
            }
            update_post_meta($post_id, 'sod_old_id', $old_id);
            update_post_meta($post_id, 'sod_inventory_number', sanitize_text_field((string)($item['inventoryNumber'] ?? ('SOD-' . $post_id))));
            update_post_meta($post_id, 'sod_inventory_category', sanitize_text_field((string)($item['category'] ?? '')));
            update_post_meta($post_id, 'sod_inventory_unit', sanitize_text_field((string)($item['unit'] ?? '')));
            update_post_meta($post_id, 'sod_inventory_total', sanitize_text_field((string)($item['total'] ?? '')));
            update_post_meta($post_id, 'sod_inventory_locations', wp_json_encode($item['locations'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private static function read_json_array(string $path): array
    {
        $path = trim($path);
        if ($path === '' || !str_ends_with(mb_strtolower($path), '.json') || !is_readable($path)) {
            return [];
        }
        $real = realpath($path);
        $base = realpath(dirname(untrailingslashit(ABSPATH)));
        if ($real === false || $base === false || !str_starts_with($real, trailingslashit($base))) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($real), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function post_exists_by_meta(string $post_type, string $key, string $value): bool
    {
        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
        return $query->have_posts();
    }

    private static function find_post_id_by_meta(string $post_type, string $key, string $value): int
    {
        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
        return $query->have_posts() ? (int)$query->posts[0] : 0;
    }

    private static function inventory_rows(string $search, string $category, string $location, string $assigned_filter = '', int $assigned_dog_filter = 0): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_inventory',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $rows = [];

        foreach ($query->posts as $post) {
            $post_id = (int)$post->ID;
            $locations_raw = (string)get_post_meta($post_id, 'sod_inventory_locations', true);
            $locations = self::parse_locations($locations_raw);
            $assigned_raw = trim((string)get_post_meta($post_id, 'sod_inventory_assigned_dog', true));
            $assigned = self::assigned_dog_details($assigned_raw);
            $category_value = (string)get_post_meta($post_id, 'sod_inventory_category', true);
            $unit_value = (string)get_post_meta($post_id, 'sod_inventory_unit', true);
            $total_value = (string)get_post_meta($post_id, 'sod_inventory_total', true);
            $total_number = self::numeric_quantity($total_value);
            $available_number = self::available_quantity_number($total_number, $category_value, $assigned);
            $row = [
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'status' => (string)get_post_status($post_id),
                'min_number' => self::low_stock_threshold($post_id),
                'number' => (string)get_post_meta($post_id, 'sod_inventory_number', true),
                'category' => $category_value,
                'unit' => $unit_value,
                'total' => $total_value,
                'total_number' => $total_number,
                'available' => self::format_quantity($available_number),
                'available_number' => $available_number,
                'assigned_dog' => $assigned_raw,
                'assigned_dog_id' => $assigned['id'],
                'assigned_dog_label' => $assigned['label'],
                'assigned_dog_html' => self::assigned_dog_html($assigned),
                'notes' => trim(wp_strip_all_tags((string)$post->post_content)),
                'locations' => $locations,
                'locations_text' => self::locations_text($locations, $locations_raw),
                'locations_html' => self::locations_html($locations, $locations_raw),
            ];
            $row['total_label'] = trim($row['total'] . ' ' . $row['unit']) ?: '-';
            $row['available_label'] = trim($row['available'] . ' ' . $row['unit']) ?: '-';

            if ($category !== '' && strcasecmp($row['category'], $category) !== 0) {
                continue;
            }
            if ($location !== '' && !self::row_has_location($row, $location)) {
                continue;
            }
            if ($assigned_filter === '1' && $row['assigned_dog_id'] <= 0 && $row['assigned_dog_label'] === '') {
                continue;
            }
            if ($assigned_dog_filter > 0 && $row['assigned_dog_id'] !== $assigned_dog_filter) {
                continue;
            }
            if ($search !== '' && !self::row_matches_search($row, $search)) {
                continue;
            }

            $rows[] = $row;
        }

        wp_reset_postdata();
        return $rows;
    }

    private static function inventory_for_dog(int $dog_id): array
    {
        $dog_title = get_the_title($dog_id);
        $query = new WP_Query([
            'post_type' => 'sod_inventory',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $items = [];
        foreach ($query->posts as $post) {
            $assigned = trim((string)get_post_meta((int)$post->ID, 'sod_inventory_assigned_dog', true));
            if ($assigned !== (string)$dog_id && strcasecmp($assigned, $dog_title) !== 0) {
                continue;
            }
            $category = (string)get_post_meta((int)$post->ID, 'sod_inventory_category', true);
            $total = trim((string)get_post_meta((int)$post->ID, 'sod_inventory_total', true));
            $unit = trim((string)get_post_meta((int)$post->ID, 'sod_inventory_unit', true));
            $meta = trim($category . ($total !== '' ? ' - ' . $total . ' ' . $unit : ''));
            $items[] = [
                'id' => (int)$post->ID,
                'title' => get_the_title((int)$post->ID),
                'meta' => $meta !== '' ? $meta : 'zugeordnet',
            ];
        }
        wp_reset_postdata();
        return $items;
    }

    private static function assigned_dog_details(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['id' => 0, 'label' => '', 'url' => ''];
        }

        $dog_id = absint($value);
        if ($dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
            return [
                'id' => $dog_id,
                'label' => get_the_title($dog_id),
                'url' => add_query_arg([
                    'post_type' => 'sod_dog',
                    'page' => 'sod-inventory-overview',
                    'sod_assigned_dog' => $dog_id,
                ], admin_url('edit.php')),
            ];
        }

        return ['id' => 0, 'label' => $value, 'url' => ''];
    }

    private static function assigned_dog_html(array $assigned): string
    {
        $label = trim((string)($assigned['label'] ?? ''));
        if ($label === '') {
            return '<span class="sod-muted">-</span>';
        }
        $url = trim((string)($assigned['url'] ?? ''));
        if ($url === '') {
            return esc_html($label);
        }
        return sprintf(
            '<a class="button button-small sod-dog-assignment-button" href="%s">%s</a>',
            esc_url($url),
            esc_html($label)
        );
    }

    private static function available_quantity_number(float $total, string $category, array $assigned): float
    {
        $label = trim((string)($assigned['label'] ?? ''));
        if ($label === '') {
            return max(0, $total);
        }

        $category_normalized = mb_strtolower(trim($category));
        if (in_array($category_normalized, ['futter', 'leckerli'], true)) {
            return max(0, $total);
        }

        return max(0, $total - 1);
    }

    private static function available_quantity_label(float $total, string $unit, string $category, array $assigned): string
    {
        $available = self::available_quantity_number($total, $category, $assigned);
        return trim(self::format_quantity($available) . ' ' . trim($unit)) ?: '-';
    }

    private static function parse_locations(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $locations = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string)($item['name'] ?? $item['location'] ?? $item['place'] ?? ''));
                $amount = trim((string)($item['amount'] ?? $item['quantity'] ?? $item['count'] ?? ''));
                if ($name !== '' || $amount !== '') {
                    $locations[] = ['name' => $name, 'amount' => $amount];
                }
            }
            return $locations;
        }

        $locations = [];
        foreach (preg_split('/\r\n|\r|\n|;/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $amount] = array_pad(preg_split('/\s*[:=,-]\s*/', $line, 2) ?: [], 2, '');
            $locations[] = ['name' => trim((string)$name), 'amount' => trim((string)$amount)];
        }
        return $locations;
    }

    private static function locations_text(array $locations, string $raw): string
    {
        if (!$locations) {
            return trim($raw);
        }
        return implode(' | ', array_map(static function (array $location): string {
            $name = trim((string)($location['name'] ?? ''));
            $amount = trim((string)($location['amount'] ?? ''));
            return trim($name . ($amount !== '' ? ': ' . $amount : ''));
        }, $locations));
    }

    private static function locations_html(array $locations, string $raw): string
    {
        if (!$locations) {
            $text = trim($raw);
            return $text !== '' ? nl2br(esc_html($text)) : '-';
        }
        $html = '<ul class="sod-location-list">';
        foreach ($locations as $location) {
            $name = trim((string)($location['name'] ?? ''));
            $amount = trim((string)($location['amount'] ?? ''));
            $label = trim($name . ($amount !== '' ? ': ' . $amount : ''));
            if ($label !== '') {
                $html .= '<li>' . esc_html($label) . '</li>';
            }
        }
        return $html . '</ul>';
    }

    private static function unique_inventory_values(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                $values[$value] = true;
            }
        }
        $values = array_keys($values);
        natcasesort($values);
        return array_values($values);
    }

    private static function unique_inventory_locations(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ($row['locations'] as $location) {
                $name = trim((string)($location['name'] ?? ''));
                if ($name !== '') {
                    $values[$name] = true;
                }
            }
        }
        $values = array_keys($values);
        natcasesort($values);
        return array_values($values);
    }

    private static function row_has_location(array $row, string $location): bool
    {
        foreach ($row['locations'] as $item) {
            if (strcasecmp(trim((string)($item['name'] ?? '')), $location) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function row_matches_search(array $row, string $search): bool
    {
        $haystack = implode(' ', [
            $row['title'],
            $row['number'],
            $row['category'],
            $row['unit'],
            $row['total'],
            $row['assigned_dog_label'],
            $row['assigned_dog'],
            $row['notes'],
            $row['locations_text'],
        ]);
        return stripos($haystack, $search) !== false;
    }

    private static function numeric_quantity(string $value): float
    {
        $value = str_replace(',', '.', $value);
        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $match)) {
            return (float)$match[0];
        }
        return 0.0;
    }

    private static function format_quantity(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string)(int)round($value);
        }
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private static function post_count(string $post_type): int
    {
        $counts = wp_count_posts($post_type);
        if (!$counts) {
            return 0;
        }
        return (int)($counts->publish ?? 0)
            + (int)($counts->private ?? 0)
            + (int)($counts->draft ?? 0)
            + (int)($counts->pending ?? 0);
    }

    private static function recent_posts(string $post_type, int $limit = 5): array
    {
        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        return array_map(static function ($post_id): array {
            $post_id = (int)$post_id;
            return [
                'id' => $post_id,
                'title' => get_the_title($post_id) ?: ('Eintrag #' . $post_id),
                'date' => get_the_date('d.m.Y', $post_id),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url(),
            ];
        }, $query->posts);
    }

    /**
     * Wie recent_posts(), aber ohne bereits beendete Anfragen - sobald eine Anfrage
     * "beendet" gesetzt wird, soll sie im Dashboard nicht mehr auftauchen.
     */
    private static function recent_open_applications(int $limit = 5): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_application',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'sod_application_status', 'value' => 'beendet', 'compare' => '!='],
            ],
        ]);
        return array_map(static function ($post_id): array {
            $post_id = (int)$post_id;
            return [
                'id' => $post_id,
                'title' => get_the_title($post_id) ?: ('Eintrag #' . $post_id),
                'date' => get_the_date('d.m.Y', $post_id),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url(),
            ];
        }, $query->posts);
    }

    private static function posts_by_meta(string $post_type, string $key, string $value, int $limit = 5): array
    {
        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_query' => [
                ['key' => $key, 'value' => $value],
            ],
        ]);
        return array_map(static function ($post_id): array {
            $post_id = (int)$post_id;
            return [
                'id' => $post_id,
                'title' => get_the_title($post_id) ?: ('Eintrag #' . $post_id),
                'date' => get_the_modified_date('d.m.Y', $post_id),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url(),
            ];
        }, $query->posts);
    }

    private static function finance_receipt_candidates(int $limit = 5): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_finance',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => 30,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        $items = [];
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $type = (string)get_post_meta($post_id, 'sod_finance_type', true);
            if (!in_array($type, ['spende', 'sachspende'], true)) {
                continue;
            }
            if ((string)get_post_meta($post_id, 'sod_finance_receipt_sent_at', true) !== '') {
                continue;
            }
            $donor = trim((string)get_post_meta($post_id, 'sod_finance_donor', true));
            $email = sanitize_email((string)get_post_meta($post_id, 'sod_finance_donor_email', true));
            $amount = trim((string)get_post_meta($post_id, 'sod_finance_amount', true));
            $items[] = [
                'label' => get_the_title($post_id) ?: ($donor !== '' ? $donor : 'Spende #' . $post_id),
                'info' => trim(($amount !== '' ? $amount : 'Betrag offen') . ($email !== '' ? ' · E-Mail vorhanden' : ' · E-Mail fehlt')),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url('edit.php?post_type=sod_finance'),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    private static function dog_photo_need_items(int $limit = 8): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_dog',
            'post_status' => ['publish', 'private', 'pending'],
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        $items = [];
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $visible = (string)get_post_meta($post_id, 'sod_show_adoption', true) === '1'
                || (string)get_post_meta($post_id, 'sod_show_sponsorship', true) === '1';
            if (!$visible) {
                continue;
            }
            $image_ids = self::dog_image_ids($post_id);
            if (!has_post_thumbnail($post_id) && !$image_ids) {
                $info = 'Echtes Foto fehlt';
            } elseif (get_post_meta($post_id, 'sod_photos_verified', true) !== '1') {
                $info = 'Foto-Prüfung offen';
            } else {
                continue;
            }
            $visibility = [];
            if ((string)get_post_meta($post_id, 'sod_show_adoption', true) === '1') {
                $visibility[] = 'Vermittlung';
            }
            if ((string)get_post_meta($post_id, 'sod_show_sponsorship', true) === '1') {
                $visibility[] = 'Patenschaft';
            }
            $status = (string)get_post_meta($post_id, 'sod_status', true);
            $status_label = self::dog_statuses()[$status] ?? ($status !== '' ? $status : 'Unbekannt');
            $items[] = [
                'label' => get_the_title($post_id) ?: ('Hund #' . $post_id),
                'info' => $info,
                'status' => $status_label,
                'visibility' => implode(', ', $visibility),
                'location' => (string)get_post_meta($post_id, 'sod_location', true),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url('edit.php?post_type=sod_dog'),
            ];
            if ($limit > 0 && count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    private static function dog_data_quality_items(int $limit = 8): array
    {
        $query = new WP_Query([
            'post_type' => 'sod_dog',
            'post_status' => ['publish', 'private', 'pending'],
            'posts_per_page' => 80,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        $items = [];
        foreach ($query->posts as $post_id) {
            $post_id = (int)$post_id;
            $missing = [];
            if (trim((string)get_the_title($post_id)) === '') {
                $missing[] = 'Name';
            }
            $image_ids = self::dog_image_ids($post_id);
            if (!has_post_thumbnail($post_id) && !$image_ids) {
                $missing[] = 'Bild';
            } elseif (get_post_meta($post_id, 'sod_photos_verified', true) !== '1') {
                $missing[] = 'Foto-Prüfung';
            }
            if (trim((string)get_post_meta($post_id, 'sod_character', true)) === '') {
                $missing[] = 'Charakter';
            }
            if (trim((string)get_post_meta($post_id, 'sod_needs', true)) === '') {
                $missing[] = 'Bedürfnisse';
            }
            $visible = (string)get_post_meta($post_id, 'sod_show_adoption', true) === '1'
                || (string)get_post_meta($post_id, 'sod_show_sponsorship', true) === '1';
            if (!$visible) {
                $missing[] = 'Sichtbarkeit';
            }
            $sponsorship_visible = (string)get_post_meta($post_id, 'sod_show_sponsorship', true) === '1';
            if ($sponsorship_visible && self::money_number((string)get_post_meta($post_id, 'sod_monthly_food_need', true)) <= 0) {
                $missing[] = 'Monatsversorgung';
            }
            if (!$missing) {
                continue;
            }
            $items[] = [
                'label' => get_the_title($post_id) ?: ('Hund #' . $post_id),
                'info' => 'Fehlt: ' . implode(', ', array_slice($missing, 0, 4)),
                'url' => get_edit_post_link($post_id, 'raw') ?: admin_url('edit.php?post_type=sod_dog'),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    public static function admin_notices(): void
    {
        self::dashboard_fixed_panel();

        $notice = sanitize_text_field((string)($_GET['sod_notice'] ?? ''));
        if ($notice === 'case_created') {
            echo '<div class="notice notice-success is-dismissible"><p>Vermittlungsakte wurde angelegt. Als nächstes Vertrag, Zahlung, Transport und Nachkontrolle ergänzen.</p></div>';
        }
        if ($notice === 'dog_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Hund gespeichert. Nächster Schritt: Bilder prüfen, Vermittlung sichtbar schalten oder Flyer erstellen.</p></div>';
        }
        if ($notice === 'application_reply_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Antwort wurde per E-Mail an die anfragende Person gesendet.</p></div>';
        }
        if ($notice === 'application_reply_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Antwort konnte nicht gesendet werden: Text fehlt oder es ist keine gültige E-Mail-Adresse hinterlegt.</p></div>';
        }
        if ($notice === 'support_reply_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Antwort wurde per E-Mail an die meldende Person gesendet.</p></div>';
        }
        if ($notice === 'support_reply_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Antwort konnte nicht gesendet werden: Text fehlt oder es ist keine gültige E-Mail-Adresse hinterlegt.</p></div>';
        }
        if ($notice === 'support_reply_mail_failed') {
            $failed_ticket_id = absint($_GET['post'] ?? 0);
            $support_mail_error = $failed_ticket_id > 0 ? trim((string)get_post_meta($failed_ticket_id, 'sod_support_reply_mail_error', true)) : '';
            echo '<div class="notice notice-error is-dismissible"><p>Die Antwort wurde im Verlauf gespeichert, aber der Mailserver hat den Versand abgelehnt. Bei der meldenden Person ist nichts angekommen.'
                . ($support_mail_error !== '' ? '<br><em>Fehlermeldung: ' . esc_html($support_mail_error) . '</em>' : '')
                . '</p></div>';
        }
        if ($notice === 'application_reply_mail_failed') {
            $failed_post_id = absint($_GET['post'] ?? 0);
            $mail_error_detail = $failed_post_id > 0 ? trim((string)get_post_meta($failed_post_id, 'sod_application_reply_mail_error', true)) : '';
            echo '<div class="notice notice-error is-dismissible"><p>Die Antwort wurde im Verlauf gespeichert, aber der Mailserver hat den Versand abgelehnt. Bei der anfragenden Person ist nichts angekommen. Bitte E-Mail-Adresse prüfen und erneut senden.'
                . ($mail_error_detail !== '' ? '<br><em>Fehlermeldung: ' . esc_html($mail_error_detail) . '</em>' : '')
                . '</p></div>';
        }
        if ($notice === 'application_note_added') {
            echo '<div class="notice notice-success is-dismissible"><p>Interne Notiz gespeichert. Sie ist ausschließlich für Mitarbeiter sichtbar.</p></div>';
        }
        if ($notice === 'application_note_empty') {
            echo '<div class="notice notice-error is-dismissible"><p>Notiz konnte nicht gespeichert werden: Text fehlt.</p></div>';
        }
        if ($notice === 'dog_visibility_required') {
            echo '<div class="notice notice-error is-dismissible"><p>Bitte beim Hund mindestens „In Vermittlung anzeigen“ oder „Für Patenschaft anzeigen“ auswählen. Der Hund wurde als Entwurf gespeichert und ist nicht öffentlich sichtbar.</p></div>';
        }
        if ($notice === 'inventory_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Bestand gespeichert. Gesamtbestand und Lagerorte wurden übernommen.</p></div>';
        }
        if ($notice === 'interest_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Interessent gespeichert. Nächster Schritt: Wiedervorlage setzen oder Vermittlungsakte starten.</p></div>';
        }
        if ($notice === 'case_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Vermittlungsakte gespeichert. Status, Vertrag, Transport und Nachkontrolle wurden übernommen.</p></div>';
        }
        if ($notice === 'receipt_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Spendenbestätigung wurde per E-Mail gesendet.</p></div>';
        }
        if ($notice === 'receipt_missing_email') {
            echo '<div class="notice notice-error is-dismissible"><p>Spendenbestätigung konnte nicht gesendet werden: Beim Spender fehlt eine gültige E-Mail-Adresse.</p></div>';
        }
        if ($notice === 'receipt_send_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Spendenbestätigung konnte nicht gesendet werden. Bitte E-Mail-Einstellungen von WordPress prüfen oder die Druckansicht als PDF senden.</p></div>';
        }
        if ($notice === 'sponsor_certificate_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Patenschaftszertifikat wurde manuell per E-Mail gesendet.</p></div>';
        }
        if ($notice === 'sponsor_certificate_missing_email') {
            echo '<div class="notice notice-error is-dismissible"><p>Patenschaftszertifikat konnte nicht gesendet werden: Beim Paten fehlt eine gültige E-Mail-Adresse.</p></div>';
        }
        if ($notice === 'sponsor_certificate_send_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Patenschaftszertifikat konnte nicht gesendet werden. Bitte E-Mail-Einstellungen von WordPress prüfen oder das PDF herunterladen.</p></div>';
        }
        if ($notice === 'precheck_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Vorkontrolle-PDF wurde per E-Mail gesendet.</p></div>';
        }
        if ($notice === 'precheck_saved_dog') {
            echo '<div class="notice notice-success is-dismissible"><p>Vorkontrolle-PDF wurde beim Hund als Dokument gespeichert.</p></div>';
        }
        if ($notice === 'precheck_missing_email') {
            echo '<div class="notice notice-error is-dismissible"><p>Vorkontrolle-PDF konnte nicht gesendet werden: Im Kontaktfeld fehlt eine E-Mail-Adresse.</p></div>';
        }
        if ($notice === 'precheck_missing_dog') {
            echo '<div class="notice notice-error is-dismissible"><p>Vorkontrolle-PDF konnte nicht beim Hund gespeichert werden: In der Vermittlungsakte ist kein Hund zugeordnet.</p></div>';
        }
        if (in_array($notice, ['precheck_send_failed', 'precheck_save_failed'], true)) {
            echo '<div class="notice notice-error is-dismissible"><p>Vorkontrolle-PDF konnte nicht verarbeitet werden. Bitte erneut versuchen oder WordPress-Uploads prüfen.</p></div>';
        }
        if ($notice === 'contract_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Schutzvertrag-PDF wurde in der Akte und beim Hund abgelegt.</p></div>';
        }
        if ($notice === 'contract_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Schutzvertrag-PDF wurde per E-Mail gesendet und der Status auf „Gesendet“ gesetzt.</p></div>';
        }
        if ($notice === 'contract_missing_email') {
            echo '<div class="notice notice-error is-dismissible"><p>Schutzvertrag-PDF konnte nicht gesendet werden: Im Feld „Adoptant / Kontakt“ fehlt eine gültige E-Mail-Adresse.</p></div>';
        }
        if ($notice === 'contract_save_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Schutzvertrag-PDF konnte nicht abgelegt werden. Bitte WordPress-Uploads prüfen.</p></div>';
        }
        if ($notice === 'contract_send_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Schutzvertrag-PDF konnte nicht gesendet werden. Bitte E-Mail-Einstellungen von WordPress prüfen oder die PDF manuell herunterladen.</p></div>';
        }
        if ($notice === 'member_linked') {
            echo '<div class="notice notice-success is-dismissible"><p>Konto wurde einer Patenschaft zugeordnet.</p></div>';
        }
        if ($notice === 'member_unlinked') {
            echo '<div class="notice notice-success is-dismissible"><p>Verknüpfung wurde getrennt. Das Konto erscheint jetzt als nicht zugeordnet.</p></div>';
        }
        self::maybe_interest_duplicate_notice();
    }

    private static function dashboard_fixed_panel(): void
    {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'dashboard') {
            return;
        }
        if (!self::can_access_sod_dashboard()) {
            return;
        }

        echo '<div class="sod-dashboard-panel">';
        echo '<div class="sod-dashboard-panel-head">';
        printf('<h2>%s</h2>', esc_html(self::org()['name'] . ' Verwaltung'));
        echo '<p>Tageszentrale, Lager, Finanzen, Bildbedarf und Schnellzugriffe an einem Ort.</p>';
        echo '</div>';
        self::dashboard_panel_section('Nächste Schritte', 'Die wichtigsten Arbeitswege für heute.', [self::class, 'dashboard_workflow_widget']);
        self::dashboard_panel_section('Heute wichtig', 'Anfragen, Wiedervorlagen, Datenpflege und offene Belege.', [self::class, 'dashboard_today_widget']);
        if (self::can_access_sod_dogs()) {
            self::dashboard_panel_section('Tierheim-Übersicht', 'Hunde, Aufgaben, Interessenten, Akten, Transporte und Pflegestellen.', [self::class, 'dashboard_shelter_widget']);
        }
        if (self::can_access_sod_items()) {
            self::dashboard_panel_section('Lager-Übersicht', 'Gesamtbestand, knappe Artikel, Lagerorte und neue Bestandsartikel.', [self::class, 'dashboard_inventory_widget']);
        }
        if (self::can_access_sod_finances()) {
            self::dashboard_panel_section('Finanzen & Transparenz', 'Spenden, Ausgaben, Saldo und Jahresbericht.', [self::class, 'dashboard_finance_widget']);
        }
        if (self::can_access_sod_dogs()) {
            self::dashboard_panel_section('Bildbedarf', 'Fehlende oder noch nicht geprüfte echte Hundefotos.', [self::class, 'dashboard_photo_needs_widget']);
        }
        self::dashboard_panel_section('Sicherheit & Datenschutz', 'Löschfristen, Datenschutzseite und sichere Einstellungen im Blick behalten.', [self::class, 'dashboard_compliance_widget']);
        self::dashboard_panel_section('Alle Funktionen', 'Direkte Sprungmarken zu allen freigeschalteten SOD-Bereichen.', [self::class, 'dashboard_functions_widget']);
        echo '</div>';
    }

    private static function dashboard_panel_section(string $title, string $description, callable $callback): void
    {
        echo '<section class="sod-dashboard-panel-section">';
        printf(
            '<div class="sod-dashboard-panel-section-head"><h3>%s</h3><p>%s</p></div>',
            esc_html($title),
            esc_html($description)
        );
        call_user_func($callback);
        echo '</section>';
    }

    private static function can_access_sod_dashboard(): bool
    {
        return current_user_can('manage_options')
            || self::can_access_sod_dogs()
            || self::can_access_sod_items()
            || self::can_access_sod_finances();
    }

    private static function can_access_sod_dogs(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_sod_dogs');
    }

    private static function can_access_sod_items(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_sod_items');
    }

    private static function can_access_sod_finances(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_sod_finances');
    }

    private static function maybe_interest_duplicate_notice(): void
    {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'sod_interest') {
            return;
        }
        $post_id = absint($_GET['post'] ?? 0);
        if (!$post_id) {
            return;
        }
        $email = sanitize_email((string)get_post_meta($post_id, 'sod_interest_email', true));
        if ($email === '') {
            return;
        }
        $duplicates = new WP_Query([
            'post_type' => 'sod_interest',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => 5,
            'fields' => 'ids',
            'post__not_in' => [$post_id],
            'meta_query' => [
                ['key' => 'sod_interest_email', 'value' => $email],
            ],
        ]);
        if (!$duplicates->posts) {
            return;
        }
        $links = array_map(static function ($duplicate_id): string {
            $url = get_edit_post_link((int)$duplicate_id, 'raw');
            return $url ? '<a href="' . esc_url($url) . '">' . esc_html(get_the_title((int)$duplicate_id)) . '</a>' : esc_html(get_the_title((int)$duplicate_id));
        }, $duplicates->posts);
        printf(
            '<div class="notice notice-warning"><p><strong>Mögliches Duplikat:</strong> Die E-Mail-Adresse %s ist bereits bei folgenden Interessenten eingetragen: %s</p></div>',
            esc_html($email),
            implode(', ', $links)
        );
    }

    public static function redirect_post_location(string $location, int $post_id): string
    {
        $post_type = get_post_type($post_id);
        $map = [
            'sod_dog' => 'dog_saved',
            'sod_inventory' => 'inventory_saved',
            'sod_interest' => 'interest_saved',
            'sod_case' => 'case_saved',
        ];
        if (isset($map[$post_type]) && !isset($_GET['bulk_edit'])) {
            if ($post_type === 'sod_dog' && get_post_meta($post_id, '_sod_visibility_missing', true) === '1') {
                return add_query_arg('sod_notice', 'dog_visibility_required', $location);
            }
            return add_query_arg('sod_notice', $map[$post_type], $location);
        }
        return $location;
    }

    private static function create_case(array $data): int
    {
        $case_id = wp_insert_post([
            'post_type' => 'sod_case',
            'post_status' => 'private',
            'post_title' => sanitize_text_field((string)($data['title'] ?? 'Neue Vermittlungsakte')),
        ]);
        if (!$case_id || is_wp_error($case_id)) {
            return 0;
        }
        update_post_meta($case_id, 'sod_case_status', 'neu');
        update_post_meta($case_id, 'sod_case_dog', (string)absint($data['dog_id'] ?? 0));
        update_post_meta($case_id, 'sod_case_adopter', sanitize_textarea_field((string)($data['adopter'] ?? '')));
        update_post_meta($case_id, 'sod_case_contract', 'offen');
        update_post_meta($case_id, 'sod_case_notes', sanitize_textarea_field((string)($data['notes'] ?? '')));
        return (int)$case_id;
    }

    private static function finance_receipt_data(int $finance_id): array
    {
        $type = (string)get_post_meta($finance_id, 'sod_finance_type', true);
        $types = self::finance_type_options();
        $category = (string)get_post_meta($finance_id, 'sod_finance_category', true);
        $categories = self::finance_category_options();
        $sent_at = (string)get_post_meta($finance_id, 'sod_finance_receipt_sent_at', true);

        $donor = trim((string)get_post_meta($finance_id, 'sod_finance_donor', true));
        if ($donor === '') {
            $title = trim(get_the_title($finance_id));
            $donor = $title !== '' && !str_starts_with($title, 'Automatischer Entwurf') ? $title : '';
        }
        $donor_address = trim((string)get_post_meta($finance_id, 'sod_finance_donor_address', true));

        return [
            'receipt_number' => 'SOD-' . gmdate('Y') . '-' . str_pad((string)$finance_id, 5, '0', STR_PAD_LEFT),
            'date' => self::display_date((string)get_post_meta($finance_id, 'sod_finance_date', true)) ?: date_i18n('d.m.Y'),
            'amount' => (string)get_post_meta($finance_id, 'sod_finance_amount', true),
            'type' => $types[$type] ?? $type ?: 'Spende',
            'category' => $categories[$category] ?? $category ?: 'Allgemeine Unterstützung',
            'donor' => $donor,
            'donor_email' => (string)get_post_meta($finance_id, 'sod_finance_donor_email', true),
            'donor_address' => $donor_address,
            'notes' => (string)get_post_meta($finance_id, 'sod_finance_notes', true),
            'sent_at' => $sent_at !== '' ? date_i18n('d.m.Y H:i', strtotime($sent_at)) : '',
        ];
    }

    private static function finance_receipt_html(int $finance_id, bool $print_page): string
    {
        $data = self::finance_receipt_data($finance_id);
        $donor = trim($data['donor']) !== '' ? $data['donor'] : 'Nicht angegeben';
        $address = trim($data['donor_address']) !== '' ? nl2br(esc_html($data['donor_address'])) : 'Nicht angegeben';
        $logo_url = get_theme_file_uri('assets/images/logo.png');
        ob_start();
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Spendenbestätigung <?php echo esc_html($data['receipt_number']); ?></title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.55;margin:0;background:#f3f5f6}
                .page{max-width:820px;margin:0 auto;background:#fff;min-height:100vh;padding:34px;box-sizing:border-box}
                .head{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #f3c74f;padding-bottom:16px;margin-bottom:24px}
                .org{display:flex;align-items:center;gap:14px}
                .logo{width:74px;height:74px;object-fit:contain;flex:0 0 auto}
                .brand{font-weight:700;color:#204060}
                h1{font-size:28px;margin:0 0 6px;color:#204060}
                h2{font-size:17px;margin:24px 0 8px;color:#204060}
                table{width:100%;border-collapse:collapse;margin:12px 0}
                th,td{text-align:left;vertical-align:top;border:1px solid #d7dde2;padding:9px}
                th{width:34%;background:#f5f7f8}
                .print{position:fixed;right:20px;top:20px}
                @media print{body{background:#fff}.page{max-width:none;min-height:auto;padding:18mm}.print{display:none}}
            </style>
        </head>
        <body>
            <?php if ($print_page) : ?><button class="print" onclick="window.print()">Drucken / als PDF speichern</button><?php endif; ?>
            <main class="page">
                <div class="head">
                    <div class="org">
                        <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                        <div>
                            <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                            <div><?php echo esc_html(self::org()['registration']); ?></div>
                            <div><?php echo esc_html(self::org_address_line()); ?></div>
                        </div>
                    </div>
                    <div>
                        <strong><?php echo esc_html($data['receipt_number']); ?></strong><br>
                        Ausgestellt am <?php echo esc_html(date_i18n('d.m.Y')); ?>
                    </div>
                </div>
                <h1>Spendenbestätigung</h1>
                <p>Wir bestätigen den Eingang der folgenden Unterstützung für <?php echo esc_html(self::org()['name']); ?>.</p>
                <h2>Spenderdaten</h2>
                <table>
                    <tr><th>Name</th><td><?php echo esc_html($donor); ?></td></tr>
                    <tr><th>Adresse</th><td><?php echo $address; ?></td></tr>
                    <tr><th>E-Mail</th><td><?php echo esc_html($data['donor_email'] ?: '-'); ?></td></tr>
                </table>
                <h2>Spende</h2>
                <table>
                    <tr><th>Datum</th><td><?php echo esc_html($data['date']); ?></td></tr>
                    <tr><th>Art</th><td><?php echo esc_html($data['type']); ?></td></tr>
                    <tr><th>Betrag / Menge</th><td><?php echo esc_html($data['amount'] ?: '-'); ?></td></tr>
                    <tr><th>Zweck</th><td><?php echo esc_html($data['category']); ?></td></tr>
                    <tr><th>Notiz</th><td><?php echo nl2br(esc_html($data['notes'] ?: '-')); ?></td></tr>
                </table>
                <p>Die Spende wird ausschließlich für die satzungsgemäßen Zwecke der Organisation verwendet.</p>
                <?php if ($data['sent_at'] !== '') : ?><p><small>Zuletzt per E-Mail gesendet: <?php echo esc_html($data['sent_at']); ?></small></p><?php endif; ?>
            </main>
        </body>
        </html>
        <?php
        return (string)ob_get_clean();
    }

    private static function finance_receipt_email_html(int $finance_id): string
    {
        return self::finance_receipt_html($finance_id, false);
    }

    private static function contact_block_from_application(int $application_id): string
    {
        return trim(implode("\n", array_filter([
            trim((string)get_post_meta($application_id, 'first_name', true) . ' ' . (string)get_post_meta($application_id, 'last_name', true)),
            (string)get_post_meta($application_id, 'email', true),
            (string)get_post_meta($application_id, 'phone', true),
            (string)get_post_meta($application_id, 'address', true),
        ])));
    }

    private static function contact_block_from_interest(int $interest_id): string
    {
        return trim(implode("\n", array_filter([
            get_the_title($interest_id),
            (string)get_post_meta($interest_id, 'sod_interest_email', true),
            (string)get_post_meta($interest_id, 'sod_interest_phone', true),
            (string)get_post_meta($interest_id, 'sod_interest_location', true),
        ])));
    }

    private static function case_precheck_questionnaire(WP_Post $post): void
    {
        $status = (string)get_post_meta($post->ID, 'sod_case_status', true);
        $adopter = (string)get_post_meta($post->ID, 'sod_case_adopter', true);
        $contact = self::case_contact_parts($adopter);
        $stored_name = trim((string)get_post_meta($post->ID, 'sod_precheck_name', true));
        $stored_address = trim((string)get_post_meta($post->ID, 'sod_precheck_address', true));
        $name = $stored_name !== '' ? $stored_name : $contact['name'];
        $address = $stored_address !== '' ? $stored_address : $contact['address'];
        $visible_class = $status === 'vorkontrolle' ? ' is-visible' : '';

        echo '<div class="sod-precheck-panel' . esc_attr($visible_class) . '" data-sod-precheck-panel>';
        echo '<h3>Fragebogen Vorkontrolle</h3>';
        echo '<p class="description">Dieser Bereich wird automatisch sichtbar, wenn der Status der Vermittlungsakte auf „Vorkontrolle“ steht. Name und Adresse werden aus dem Kontaktfeld übernommen und können hier korrigiert werden.</p>';
        if ($post->ID > 0) {
            $download_url = wp_nonce_url(add_query_arg(['action' => 'sod_case_precheck_pdf_dl', 'case_id' => $post->ID], admin_url('admin-post.php')), 'sod_case_precheck_pdf_dl_' . $post->ID);
            echo '<div class="sod-quick-actions">';
            printf('<a class="button button-secondary" href="%s">PDF herunterladen</a>', esc_url($download_url));
            self::post_action_button('sod_case_precheck_pdf_send', 'case_id', $post->ID, 'sod_case_precheck_pdf_send_' . $post->ID, 'PDF senden', 'button', 'Vorkontrolle-PDF jetzt per E-Mail senden?');
            self::post_action_button('sod_case_precheck_pdf_save_dog', 'case_id', $post->ID, 'sod_case_precheck_pdf_save_dog_' . $post->ID, 'PDF beim Hund speichern', 'button button-primary', 'Vorkontrolle-PDF beim Hund speichern?');
            echo '</div>';
            echo '<p class="description">Bitte die Vermittlungsakte nach dem Ausfüllen zuerst speichern, damit die PDF die aktuellen Antworten enthält.</p>';
        }
        echo '<div class="sod-admin-grid">';
        self::raw_text_field('sod_precheck_name', 'Name', $name, 'wird automatisch übernommen', 'recommended');
        self::raw_textarea_field('sod_precheck_address', 'Adresse', $address, 3, 'wird automatisch übernommen', 'recommended');
        foreach (self::case_precheck_fields() as $key => $field) {
            if (in_array($key, ['sod_precheck_name', 'sod_precheck_address'], true)) {
                continue;
            }
            self::raw_textarea_field($key, $field['label'], (string)get_post_meta($post->ID, $key, true), (int)($field['rows'] ?? 3), (string)($field['placeholder'] ?? ''));
        }
        echo '</div></div>';
    }

    private static function case_precheck_fields(): array
    {
        return [
            'sod_precheck_name' => ['label' => 'Name'],
            'sod_precheck_address' => ['label' => 'Adresse'],
            'sod_precheck_household' => ['label' => 'Wer lebt im Haushalt?', 'placeholder' => 'Erwachsene, Kinder, weitere Tiere', 'rows' => 3],
            'sod_precheck_home' => ['label' => 'Wohnsituation', 'placeholder' => 'Wohnung/Haus, Stockwerk, Eigentum/Miete, Erlaubnis zur Hundehaltung', 'rows' => 4],
            'sod_precheck_garden' => ['label' => 'Garten / Sicherung', 'placeholder' => 'Garten vorhanden, Zaunhöhe, Türen/Tore, Balkon/Terrasse', 'rows' => 4],
            'sod_precheck_experience' => ['label' => 'Hundeerfahrung', 'placeholder' => 'Erfahrung mit Angsthunden, Auslandshunden, Training, bisherigen Hunden', 'rows' => 4],
            'sod_precheck_daily_routine' => ['label' => 'Tagesablauf', 'placeholder' => 'Arbeitszeiten, Betreuung, Spaziergänge, Ruheplatz', 'rows' => 4],
            'sod_precheck_absence' => ['label' => 'Alleinbleiben / Betreuung', 'placeholder' => 'Wie lange wäre der Hund alleine? Wer hilft im Notfall?', 'rows' => 3],
            'sod_precheck_costs' => ['label' => 'Kosten & Tierarzt', 'placeholder' => 'Bewusstsein für Futter, Versicherung, Tierarzt, Notfälle', 'rows' => 3],
            'sod_precheck_safety' => ['label' => 'Sicherheit in der Anfangszeit', 'placeholder' => 'Doppelsicherung, Sicherheitsgeschirr, Schleppleine, Transport nach Ankunft', 'rows' => 4],
            'sod_precheck_impression' => ['label' => 'Eindruck / Empfehlung', 'placeholder' => 'Geeignet, offen, Bedenken, nächste Schritte', 'rows' => 5],
        ];
    }

    private static function case_contact_parts(string $contact): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $contact) ?: [])));
        $name = $lines[0] ?? '';
        $address_lines = [];
        foreach ($lines as $line) {
            if (str_contains($line, '@') || preg_match('/^\+?\d[\d\s\/-]+$/', $line)) {
                continue;
            }
            if ($line !== $name) {
                $address_lines[] = $line;
            }
        }
        return ['name' => $name, 'address' => implode("\n", $address_lines)];
    }

    private static function validated_case_id(string $param, string $nonce_prefix): int
    {
        $case_id = absint($_REQUEST[$param] ?? 0);
        if (!$case_id || !current_user_can('edit_post', $case_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer($nonce_prefix . $case_id);
        $case = get_post($case_id);
        if (!$case instanceof WP_Post || $case->post_type !== 'sod_case') {
            wp_die('Vermittlungsakte nicht gefunden.');
        }
        return $case_id;
    }

    private static function post_action_button(string $action, string $id_name, int $id, string $nonce_action, string $label, string $class = 'button', string $confirm = ''): void
    {
        $url = wp_nonce_url(
            add_query_arg(['action' => $action, $id_name => $id], admin_url('admin-post.php')),
            $nonce_action
        );
        printf(
            '<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
            esc_attr($class),
            esc_url($url),
            $confirm !== '' ? ' data-sod-confirm="' . esc_attr($confirm) . '"' : '',
            esc_html($label)
        );
    }

    private static function case_contact_email(int $case_id): string
    {
        $contact = (string)get_post_meta($case_id, 'sod_case_adopter', true);
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $contact, $match)) {
            return sanitize_email($match[0]);
        }
        return '';
    }

    private static function case_precheck_pdf_filename(int $case_id): string
    {
        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : 'Hund';
        return sanitize_file_name('vorkontrolle-' . $dog_name . '-' . $case_id . '.pdf');
    }

    private static function case_precheck_pdf(int $case_id): string
    {
        $dog_id = absint(get_post_meta($case_id, 'sod_case_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : '-';

        $blocks = [
            ['type' => 'heading', 'text' => 'ANGABEN ZUR PERSON'],
            ['type' => 'fact', 'label' => 'Name', 'value' => ((string)get_post_meta($case_id, 'sod_precheck_name', true) ?: '-')],
            ['type' => 'fact', 'label' => 'Adresse', 'value' => ((string)get_post_meta($case_id, 'sod_precheck_address', true) ?: '-')],
            ['type' => 'space', 'h' => 8],
        ];
        foreach (self::case_precheck_fields() as $key => $field) {
            if (in_array($key, ['sod_precheck_name', 'sod_precheck_address'], true)) {
                continue;
            }
            $value = trim((string)get_post_meta($case_id, $key, true));
            $blocks[] = ['type' => 'label', 'text' => (string)$field['label']];
            $blocks[] = ['type' => 'lines', 'lines' => preg_split('/\r\n|\r|\n/', $value !== '' ? $value : '-') ?: ['-']];
            $blocks[] = ['type' => 'space', 'h' => 8];
        }

        $meta = 'Vermittlungsakte #' . $case_id . '   ·   Hund: ' . $dog_name . '   ·   Erstellt am ' . date_i18n('d.m.Y H:i');
        $footer = self::org_footer_line();

        return self::build_case_pdf('VORKONTROLLE', 'Fragebogen zur Wohnsituation', $meta, $blocks, $footer);
    }

    private static function pdf_logo_image(): ?array
    {
        static $computed = false;
        static $cached = null;
        if ($computed) {
            return $cached;
        }
        $computed = true;

        if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
            return null;
        }
        $path = plugin_dir_path(__FILE__) . 'assets/images/logo.png';
        if (!is_readable($path)) {
            return null;
        }
        $source = @imagecreatefrompng($path);
        if (!$source) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return null;
        }

        $flattened = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefill($flattened, 0, 0, $white);
        imagealphablending($flattened, true);
        imagesavealpha($flattened, false);
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($flattened, null, 88);
        $data = (string)ob_get_clean();
        imagedestroy($flattened);

        if ($data === '') {
            return null;
        }
        $cached = ['data' => $data, 'width' => $width, 'height' => $height];
        return $cached;
    }

    private static function pdf_brand_colors(): array
    {
        return [
            'primary' => [0.1255, 0.2510, 0.3765],
            'accent' => [0.9529, 0.7804, 0.3098],
            'text' => [0.11, 0.11, 0.11],
            'muted' => [0.42, 0.42, 0.42],
            'line' => [0.80, 0.80, 0.80],
        ];
    }

    private static function pdf_num(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function pdf_rgb(array $rgb): string
    {
        return self::pdf_num($rgb[0]) . ' ' . self::pdf_num($rgb[1]) . ' ' . self::pdf_num($rgb[2]);
    }

    private static function pdf_wrap_to_width(string $text, float $width_pt, float $font_size): array
    {
        $chars = max(10, (int)floor($width_pt / ($font_size * 0.52)));
        return self::wrap_pdf_line($text, $chars);
    }

    private static function pdf_text_op(string $text, float $x, float $y, string $font, float $size, array $color, float $letter_spacing = 0.0): string
    {
        $tc = $letter_spacing != 0.0 ? self::pdf_num($letter_spacing) . " Tc\n" : '';
        return "BT\n/" . $font . ' ' . self::pdf_num($size) . " Tf\n" . $tc . self::pdf_rgb($color) . " rg\n"
            . self::pdf_num($x) . ' ' . self::pdf_num($y) . " Td\n(" . self::pdf_text($text) . ") Tj\nET\n";
    }

    /**
     * Standard Times-Roman / Times-Bold AFM character widths (per 1000 em),
     * keyed by Windows-1252 byte value. Covers ASCII + the German/typographic
     * characters used in generated documents, so text can be centered exactly
     * instead of guessed.
     */
    private static function pdf_times_widths(bool $bold): array
    {
        if ($bold) {
            return [
                32 => 250, 33 => 333, 34 => 555, 35 => 500, 36 => 500, 37 => 1000, 38 => 833, 39 => 278,
                40 => 333, 41 => 333, 42 => 500, 43 => 570, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
                48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500, 56 => 500, 57 => 500,
                58 => 333, 59 => 333, 60 => 570, 61 => 570, 62 => 570, 63 => 500, 64 => 930,
                65 => 722, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 778, 73 => 389, 74 => 500,
                75 => 778, 76 => 667, 77 => 944, 78 => 722, 79 => 778, 80 => 611, 81 => 778, 82 => 722, 83 => 556, 84 => 667,
                85 => 722, 86 => 722, 87 => 1000, 88 => 722, 89 => 722, 90 => 667,
                91 => 333, 92 => 278, 93 => 333, 94 => 581, 95 => 500, 96 => 333,
                97 => 500, 98 => 556, 99 => 444, 100 => 556, 101 => 444, 102 => 333, 103 => 500, 104 => 556, 105 => 278,
                106 => 333, 107 => 556, 108 => 278, 109 => 833, 110 => 556, 111 => 500, 112 => 556, 113 => 556, 114 => 444,
                115 => 389, 116 => 333, 117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 444,
                123 => 394, 124 => 220, 125 => 394, 126 => 520,
                149 => 350, 150 => 500, 151 => 1000, 183 => 250,
                196 => 722, 214 => 778, 220 => 722, 223 => 556, 228 => 500, 233 => 444, 246 => 500, 252 => 556,
            ];
        }
        return [
            32 => 250, 33 => 333, 34 => 408, 35 => 500, 36 => 500, 37 => 833, 38 => 778, 39 => 180,
            40 => 333, 41 => 333, 42 => 500, 43 => 564, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
            48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500, 56 => 500, 57 => 500,
            58 => 278, 59 => 278, 60 => 564, 61 => 564, 62 => 564, 63 => 444, 64 => 921,
            65 => 722, 66 => 667, 67 => 667, 68 => 722, 69 => 611, 70 => 556, 71 => 722, 72 => 722, 73 => 333, 74 => 389,
            75 => 722, 76 => 611, 77 => 889, 78 => 722, 79 => 722, 80 => 556, 81 => 722, 82 => 667, 83 => 556, 84 => 611,
            85 => 722, 86 => 722, 87 => 944, 88 => 722, 89 => 722, 90 => 611,
            91 => 333, 92 => 278, 93 => 333, 94 => 469, 95 => 500, 96 => 333,
            97 => 444, 98 => 500, 99 => 444, 100 => 500, 101 => 444, 102 => 333, 103 => 500, 104 => 500, 105 => 278,
            106 => 278, 107 => 500, 108 => 278, 109 => 778, 110 => 500, 111 => 500, 112 => 500, 113 => 500, 114 => 333,
            115 => 389, 116 => 278, 117 => 500, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 444,
            123 => 480, 124 => 200, 125 => 480, 126 => 541,
            149 => 350, 150 => 500, 151 => 1000, 183 => 250,
            196 => 722, 214 => 722, 220 => 722, 223 => 500, 228 => 444, 233 => 444, 246 => 500, 252 => 500,
        ];
    }

    /**
     * Standard Helvetica / Helvetica-Bold AFM character widths (per 1000 em),
     * keyed by Windows-1252 byte value. Used to emit an explicit /Widths array
     * so PDF renderers don't have to guess glyph advances for accented
     * characters (some viewers otherwise render the next character on top of
     * an umlaut/ß instead of advancing the cursor).
     */
    private static function pdf_helvetica_widths(bool $bold): array
    {
        if ($bold) {
            return [
                32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
                40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
                48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
                58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611, 64 => 975,
                65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 556,
                75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
                85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611,
                91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556, 96 => 333,
                97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611, 104 => 611, 105 => 278,
                106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611, 112 => 611, 113 => 611, 114 => 389,
                115 => 556, 116 => 333, 117 => 611, 118 => 556, 119 => 778, 120 => 556, 121 => 556, 122 => 500,
                123 => 389, 124 => 280, 125 => 389, 126 => 584,
                149 => 350, 150 => 556, 151 => 1000, 183 => 278,
                196 => 722, 214 => 778, 220 => 722, 223 => 611, 228 => 556, 233 => 556, 246 => 611, 252 => 611,
            ];
        }
        return [
            32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
            40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
            58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015,
            65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 500,
            75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
            85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611,
            91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556, 96 => 333,
            97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556, 104 => 556, 105 => 222,
            106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556, 112 => 556, 113 => 556, 114 => 333,
            115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500,
            123 => 334, 124 => 260, 125 => 334, 126 => 584,
            149 => 350, 150 => 556, 151 => 1000, 183 => 278,
            196 => 667, 214 => 778, 220 => 722, 223 => 611, 228 => 556, 233 => 556, 246 => 556, 252 => 556,
        ];
    }

    private static function pdf_widths_array(array $widths, int $first, int $last): string
    {
        $parts = [];
        for ($i = $first; $i <= $last; $i++) {
            $parts[] = (string)($widths[$i] ?? 500);
        }
        return '[' . implode(' ', $parts) . ']';
    }

    private static function pdf_text_width_exact(string $text, float $size, bool $bold = false): float
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = $text;
        }
        $widths = self::pdf_times_widths($bold);
        $total = 0;
        $len = strlen($encoded);
        for ($i = 0; $i < $len; $i++) {
            $total += $widths[ord($encoded[$i])] ?? 500;
        }
        return $total * $size / 1000.0;
    }

    private static function pdf_center_text_op(string $text, float $center_x, float $y, string $font, float $size, array $color, float $letter_spacing = 0.0): string
    {
        $width = self::pdf_text_width_exact($text, $size, $font === 'F2') + strlen($text) * $letter_spacing;
        return self::pdf_text_op($text, $center_x - $width / 2, $y, $font, $size, $color, $letter_spacing);
    }

    private static function pdf_rect_op(float $x, float $y, float $w, float $h, array $color, bool $stroke = true): string
    {
        $op = $stroke ? "RG\n0.75 w\n" : "rg\n";
        return self::pdf_rgb($color) . ' ' . $op . self::pdf_num($x) . ' ' . self::pdf_num($y) . ' ' . self::pdf_num($w) . ' ' . self::pdf_num($h)
            . " re\n" . ($stroke ? "S\n" : "f\n");
    }

    private static function pdf_line_op(float $x1, float $y1, float $x2, float $y2, array $color, float $width = 0.75): string
    {
        return self::pdf_rgb($color) . " RG\n" . self::pdf_num($width) . " w\n"
            . self::pdf_num($x1) . ' ' . self::pdf_num($y1) . ' m ' . self::pdf_num($x2) . ' ' . self::pdf_num($y2) . " l\nS\n";
    }

    private static function pdf_page_header(string $title, string $subtitle, string $meta, bool $is_first, ?array $logo, float $page_w, float $page_h, float $margin): array
    {
        $colors = self::pdf_brand_colors();
        $ops = self::pdf_rect_op(0, $page_h - 4, $page_w, 4, $colors['accent'], false);

        if ($is_first) {
            if ($logo !== null) {
                $max_w = 52.0;
                $max_h = 52.0;
                $scale = min($max_w / $logo['width'], $max_h / $logo['height']);
                $draw_w = $logo['width'] * $scale;
                $draw_h = $logo['height'] * $scale;
                $lx = $page_w - $margin - $draw_w;
                $ly = $page_h - 24 - $draw_h;
                $ops .= "q\n" . self::pdf_num($draw_w) . ' 0 0 ' . self::pdf_num($draw_h) . ' ' . self::pdf_num($lx) . ' ' . self::pdf_num($ly) . " cm\n/Im1 Do\nQ\n";
            }
            $ops .= self::pdf_text_op(self::org()['name'], $margin, $page_h - 38, 'F2', 16, $colors['primary']);
            $ops .= self::pdf_text_op(self::org()['name'], $margin, $page_h - 51, 'F1', 8.5, $colors['muted']);
            $ops .= self::pdf_text_op($title, $margin, $page_h - 80, 'F2', 19, $colors['primary']);
            $ops .= self::pdf_text_op($subtitle, $margin, $page_h - 97, 'F1', 10.5, $colors['muted']);
            if ($meta !== '') {
                $ops .= self::pdf_text_op($meta, $margin, $page_h - 111, 'F1', 8.5, $colors['muted']);
            }
            $header_h = 128.0;
        } else {
            $ops .= self::pdf_text_op($title, $margin, $page_h - 28, 'F2', 11, $colors['primary']);
            $header_h = 46.0;
        }

        $rule_y = $page_h - $header_h;
        $ops .= self::pdf_line_op($margin, $rule_y, $page_w - $margin, $rule_y, $colors['line']);

        return [$ops, $rule_y - 22.0];
    }

    private static function pdf_page_footer(string $left, int $page_num, int $total_pages, float $page_w, float $margin): string
    {
        $colors = self::pdf_brand_colors();
        $y = 34.0;
        $ops = self::pdf_line_op($margin, $y + 14, $page_w - $margin, $y + 14, $colors['line'], 0.5);
        $ops .= self::pdf_text_op($left, $margin, $y, 'F1', 8, $colors['muted']);
        $right_text = 'Seite ' . $page_num . ' von ' . $total_pages;
        $right_x = $page_w - $margin - (strlen($right_text) * 8 * 0.5);
        $ops .= self::pdf_text_op($right_text, $right_x, $y, 'F1', 8, $colors['muted']);
        return $ops;
    }

    /**
     * Renders a branded, multi-page PDF from a list of layout blocks.
     * Supported block types: space, heading, label, fact, paragraph, lines, signature.
     * The "signature" block also registers fillable AcroForm text fields (Ort, Datum),
     * so the resulting PDF can be filled in directly in a PDF reader.
     */
    private static function build_case_pdf(string $title, string $subtitle, string $meta, array $blocks, string $footer_left): string
    {
        $colors = self::pdf_brand_colors();
        $page_w = 595.0;
        $page_h = 842.0;
        $margin = 54.0;
        $content_w = $page_w - 2 * $margin;
        $footer_limit = 60.0;
        $label_col_w = 150.0;
        $logo = self::pdf_logo_image();

        $pages = [];
        $page_index = -1;
        $ops = '';
        $fields = [];
        $y = 0.0;

        $start_page = function () use (&$ops, &$y, &$page_index, &$fields, $title, $subtitle, $meta, $logo, $page_w, $page_h, $margin) {
            $page_index++;
            [$header_ops, $start_y] = self::pdf_page_header($title, $subtitle, $meta, $page_index === 0, $logo, $page_w, $page_h, $margin);
            $ops = $header_ops;
            $y = $start_y;
            $fields = [];
        };
        $finish_page = function () use (&$ops, &$fields, &$pages) {
            $pages[] = ['ops' => $ops, 'fields' => $fields];
        };

        $start_page();

        foreach ($blocks as $block) {
            $type = (string)($block['type'] ?? '');

            if ($type === 'space') {
                $y -= (float)($block['h'] ?? 8);
                continue;
            }

            if ($type === 'heading') {
                if ($y - 34 < $footer_limit) {
                    $finish_page();
                    $start_page();
                }
                $y -= 12;
                $ops .= self::pdf_text_op((string)$block['text'], $margin, $y, 'F2', 11, $colors['primary']);
                $y -= 5;
                $ops .= self::pdf_line_op($margin, $y, $margin + 36, $y, $colors['accent'], 1.5);
                $y -= 14;
                continue;
            }

            if ($type === 'label') {
                if ($y - 24 < $footer_limit) {
                    $finish_page();
                    $start_page();
                }
                $y -= 12;
                $ops .= self::pdf_text_op((string)$block['text'], $margin, $y, 'F2', 9.5, $colors['primary']);
                $y -= 14;
                continue;
            }

            if ($type === 'fact') {
                $label = (string)$block['label'];
                $value = (string)$block['value'];
                $value_lines = self::pdf_wrap_to_width($value, $content_w - $label_col_w, 9.5);
                foreach ($value_lines as $index => $line) {
                    if ($y - 14 < $footer_limit) {
                        $finish_page();
                        $start_page();
                    }
                    if ($index === 0) {
                        $ops .= self::pdf_text_op($label . ':', $margin, $y, 'F2', 9.5, $colors['muted']);
                    }
                    $ops .= self::pdf_text_op($line, $margin + $label_col_w, $y, 'F1', 9.5, $colors['text']);
                    $y -= 14;
                }
                continue;
            }

            if ($type === 'paragraph') {
                $para_lines = self::pdf_wrap_to_width((string)$block['text'], $content_w, 9.75);
                foreach ($para_lines as $line) {
                    if ($y - 13.5 < $footer_limit) {
                        $finish_page();
                        $start_page();
                    }
                    $ops .= self::pdf_text_op($line, $margin, $y, 'F1', 9.75, $colors['text']);
                    $y -= 13.5;
                }
                continue;
            }

            if ($type === 'lines') {
                foreach ((array)$block['lines'] as $raw_line) {
                    $wrapped = self::pdf_wrap_to_width((string)$raw_line, $content_w, 9.75);
                    foreach ($wrapped as $line) {
                        if ($y - 13.5 < $footer_limit) {
                            $finish_page();
                            $start_page();
                        }
                        $ops .= self::pdf_text_op($line, $margin, $y, 'F1', 9.75, $colors['text']);
                        $y -= 13.5;
                    }
                }
                continue;
            }

            if ($type === 'signature') {
                $needed = 72.0;
                if ($y - $needed < $footer_limit) {
                    $finish_page();
                    $start_page();
                }
                $col_gap = 24.0;
                $col_w = ($content_w - $col_gap) / 2;
                $cols = [
                    ['x' => $margin, 'label' => (string)$block['left'], 'field' => (string)$block['field_prefix'] . '_verein'],
                    ['x' => $margin + $col_w + $col_gap, 'label' => (string)$block['right'], 'field' => (string)$block['field_prefix'] . '_adoptant'],
                ];
                foreach ($cols as $col) {
                    $box_top = $y;
                    $box_bottom = $y - 18;
                    $ops .= self::pdf_text_op('Ort, Datum', $col['x'], $box_top + 2, 'F1', 7.5, $colors['muted']);
                    $ops .= self::pdf_rect_op($col['x'], $box_bottom, $col_w, 18, $colors['line'], true);
                    $fields[] = [
                        'name' => $col['field'],
                        'rect' => [$col['x'] + 2, $box_bottom + 2, $col['x'] + $col_w - 2, $box_bottom + 16],
                    ];
                    $line_y = $box_bottom - 24;
                    $ops .= self::pdf_line_op($col['x'], $line_y, $col['x'] + $col_w, $line_y, $colors['text'], 0.75);
                    $ops .= self::pdf_text_op($col['label'], $col['x'], $line_y - 12, 'F1', 9, $colors['muted']);
                }
                $y -= $needed;
                continue;
            }
        }

        $finish_page();

        $total_pages = count($pages);
        foreach ($pages as $index => $page) {
            $pages[$index]['ops'] .= self::pdf_page_footer($footer_left, $index + 1, $total_pages, $page_w, $margin);
        }

        return self::assemble_pdf($pages, $logo);
    }

    private static function assemble_pdf(array $pages, ?array $logo): string
    {
        $objects = [];
        $objects[] = ''; // 1: Catalog (patched below)
        $objects[] = ''; // 2: Pages (patched below)
        $font_regular_id = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths '
            . self::pdf_widths_array(self::pdf_helvetica_widths(false), 32, 255) . ' >>';
        $font_bold_id = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths '
            . self::pdf_widths_array(self::pdf_helvetica_widths(true), 32, 255) . ' >>';

        $image_id = 0;
        if ($logo !== null) {
            $image_id = count($objects) + 1;
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . (int)$logo['width'] . ' /Height ' . (int)$logo['height']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo['data'])
                . " >>\nstream\n" . $logo['data'] . "\nendstream";
        }

        $page_ids = [];
        $all_field_ids = [];

        foreach ($pages as $page_index => $page) {
            $page_id = count($objects) + 1;
            $objects[] = ''; // placeholder, patched below
            $page_ids[] = $page_id;

            $field_ids = [];
            foreach ($page['fields'] as $field) {
                $field_id = count($objects) + 1;
                $rect = $field['rect'];
                $objects[] = '<< /Type /Annot /Subtype /Widget /FT /Tx /Rect [' . self::pdf_num($rect[0]) . ' ' . self::pdf_num($rect[1]) . ' '
                    . self::pdf_num($rect[2]) . ' ' . self::pdf_num($rect[3]) . '] /T (' . self::pdf_text($field['name']) . ') /DA (/F1 9 Tf 0.11 0.11 0.11 rg) /F 4 /P '
                    . $page_id . ' 0 R /Border [0 0 0] /BS << /W 0 >> >>';
                $field_ids[] = $field_id;
                $all_field_ids[] = $field_id;
            }

            $content_id = count($objects) + 1;
            $objects[] = '<< /Length ' . strlen($page['ops']) . " >>\nstream\n" . $page['ops'] . "endstream";

            $resources = '/Font << /F1 ' . $font_regular_id . ' 0 R /F2 ' . $font_bold_id . ' 0 R >>';
            if ($image_id > 0 && $page_index === 0) {
                $resources .= ' /XObject << /Im1 ' . $image_id . ' 0 R >>';
            }
            $page_object = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << ' . $resources . ' >> /Contents ' . $content_id . ' 0 R';
            if ($field_ids) {
                $page_object .= ' /Annots [' . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $field_ids)) . ']';
            }
            $page_object .= ' >>';
            $objects[$page_id - 1] = $page_object;
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $page_ids));
        $objects[1] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($page_ids) . ' >>';

        if ($all_field_ids) {
            $acroform_id = count($objects) + 1;
            $objects[] = '<< /Fields [' . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $all_field_ids)) . '] /DA (/F1 9 Tf 0.11 0.11 0.11 rg) /DR << /Font << /F1 '
                . $font_regular_id . ' 0 R /F2 ' . $font_bold_id . ' 0 R >> >> /NeedAppearances true >>';
            $objects[0] = '<< /Type /Catalog /Pages 2 0 R /AcroForm ' . $acroform_id . ' 0 R >>';
        } else {
            $objects[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private static function assemble_single_page_pdf(string $ops, ?array $logo, float $page_w, float $page_h): string
    {
        $objects = [];
        $objects[] = ''; // 1: Catalog (patched below)
        $objects[] = ''; // 2: Pages (patched below)
        $font_regular_id = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths '
            . self::pdf_widths_array(self::pdf_times_widths(false), 32, 255) . ' >>';
        $font_bold_id = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths '
            . self::pdf_widths_array(self::pdf_times_widths(true), 32, 255) . ' >>';

        $image_id = 0;
        if ($logo !== null) {
            $image_id = count($objects) + 1;
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . (int)$logo['width'] . ' /Height ' . (int)$logo['height']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo['data'])
                . " >>\nstream\n" . $logo['data'] . "\nendstream";
        }

        $watermark_gs_id = count($objects) + 1;
        $objects[] = '<< /Type /ExtGState /ca 0.07 /CA 0.07 >>';

        $resources = '/Font << /F1 ' . $font_regular_id . ' 0 R /F2 ' . $font_bold_id . ' 0 R >> /ExtGState << /GSWatermark ' . $watermark_gs_id . ' 0 R >>';
        if ($image_id > 0) {
            $resources .= ' /XObject << /Im1 ' . $image_id . ' 0 R >>';
        }

        $page_id = count($objects) + 1;
        $objects[] = '';
        $content_id = count($objects) + 1;
        $objects[] = '<< /Length ' . strlen($ops) . " >>\nstream\n" . $ops . "endstream";
        $objects[$page_id - 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::pdf_num($page_w) . ' ' . self::pdf_num($page_h)
            . '] /Resources << ' . $resources . ' >> /Contents ' . $content_id . ' 0 R >>';

        $objects[1] = '<< /Type /Pages /Kids [' . $page_id . ' 0 R] /Count 1 >>';
        $objects[0] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private static function sponsor_certificate_filename(int $sponsor_id): string
    {
        $name = get_the_title($sponsor_id) ?: 'Pate';
        return sanitize_file_name('patenschaftszertifikat-' . $name . '-' . $sponsor_id . '.pdf');
    }

    private static function sponsor_certificate_pdf(int $sponsor_id): string
    {
        $colors = self::pdf_brand_colors();
        $gold = [0.7255, 0.5490, 0.1216];
        $page_w = 842.0;
        $page_h = 595.0;
        $center_x = $page_w / 2;
        $logo = self::pdf_logo_image();

        $pate_name = get_the_title($sponsor_id) ?: 'Pate';
        $dog_id = absint(get_post_meta($sponsor_id, 'sod_sponsor_dog', true));
        $dog_name = $dog_id ? get_the_title($dog_id) : 'einen Hund von ' . self::org()['name'];
        $amount = self::money_number((string)get_post_meta($sponsor_id, 'sod_sponsor_amount', true));
        $amount_line = $amount > 0
            ? 'Monatlicher Patenschaftsbetrag: ' . self::money_plain($amount) . ' €'
            : '';
        $since_raw = trim((string)get_post_meta($sponsor_id, 'sod_sponsor_since', true));
        $since_ts = $since_raw !== '' ? strtotime($since_raw) : false;
        $since_line = $since_ts !== false
            ? 'seit dem ' . date_i18n('d.m.Y', $since_ts) . ' als Pate/Patin liebevoll unterstützt:'
            : 'als Pate/Patin liebevoll unterstützt:';

        $ops = '';

        // Logo watermark, large and centered behind all other content.
        if ($logo !== null) {
            $wm_size = 380.0;
            $wm_scale = min($wm_size / $logo['width'], $wm_size / $logo['height']);
            $wm_w = $logo['width'] * $wm_scale;
            $wm_h = $logo['height'] * $wm_scale;
            $wm_x = $center_x - $wm_w / 2;
            $wm_y = ($page_h - $wm_h) / 2;
            $ops .= "q\n/GSWatermark gs\n" . self::pdf_num($wm_w) . ' 0 0 ' . self::pdf_num($wm_h) . ' ' . self::pdf_num($wm_x) . ' ' . self::pdf_num($wm_y) . " cm\n/Im1 Do\nQ\n";
        }

        // Outer + inner decorative border frame.
        $ops .= self::pdf_rect_op(22, 22, $page_w - 44, $page_h - 44, $gold, true);
        $ops .= self::pdf_rgb($gold) . " RG\n0.75 w\n" . self::pdf_num(28) . ' ' . self::pdf_num(28) . ' m ' . self::pdf_num($page_w - 28) . ' ' . self::pdf_num(28) . " l\n"
            . self::pdf_num($page_w - 28) . ' ' . self::pdf_num($page_h - 28) . " l\n" . self::pdf_num(28) . ' ' . self::pdf_num($page_h - 28) . " l\n"
            . self::pdf_num(28) . ' ' . self::pdf_num(28) . " l\nS\n";

        // Logo, centered top.
        if ($logo !== null) {
            $logo_size = 62.0;
            $scale = min($logo_size / $logo['width'], $logo_size / $logo['height']);
            $draw_w = $logo['width'] * $scale;
            $draw_h = $logo['height'] * $scale;
            $lx = $center_x - $draw_w / 2;
            $ly = $page_h - 60 - $draw_h;
            $ops .= "q\n" . self::pdf_num($draw_w) . ' 0 0 ' . self::pdf_num($draw_h) . ' ' . self::pdf_num($lx) . ' ' . self::pdf_num($ly) . " cm\n/Im1 Do\nQ\n";
        }

        $y = $page_h - 150;
        $ops .= self::pdf_center_text_op('PATENSCHAFTSURKUNDE', $center_x, $y, 'F2', 28, $colors['primary'], 4.5);
        $y -= 22;
        $ops .= self::pdf_center_text_op(self::org()['name'], $center_x, $y, 'F1', 12, $colors['muted'], 0.6);

        $y -= 20;
        $ops .= self::pdf_line_op($center_x - 130, $y, $center_x + 130, $y, $gold, 1.25);
        $diamond_r = 5.0;
        $ops .= self::pdf_rgb($gold) . " rg\n"
            . self::pdf_num($center_x) . ' ' . self::pdf_num($y + $diamond_r) . " m\n"
            . self::pdf_num($center_x + $diamond_r) . ' ' . self::pdf_num($y) . " l\n"
            . self::pdf_num($center_x) . ' ' . self::pdf_num($y - $diamond_r) . " l\n"
            . self::pdf_num($center_x - $diamond_r) . ' ' . self::pdf_num($y) . " l\n"
            . "h\nf\n";

        $y -= 40;
        $ops .= self::pdf_center_text_op('Hiermit bestätigen wir mit großer Dankbarkeit, dass', $center_x, $y, 'F1', 13, $colors['text']);

        $y -= 34;
        $ops .= self::pdf_center_text_op($pate_name, $center_x, $y, 'F2', 24, $gold, 1.0);

        $y -= 30;
        $ops .= self::pdf_center_text_op($since_line, $center_x, $y, 'F1', 13, $colors['text']);

        $y -= 36;
        $ops .= self::pdf_center_text_op($dog_name, $center_x, $y, 'F2', 27, $colors['primary'], 1.0);

        if ($amount_line !== '') {
            $y -= 27;
            $ops .= self::pdf_center_text_op($amount_line, $center_x, $y, 'F2', 13, $gold, 0.25);
        }

        $y -= 32;
        $thanks_lines = self::pdf_wrap_to_width(
            'Deine Unterstützung schenkt ' . $dog_name . ' Futter, Sicherheit und die Chance auf ein besseres Leben. '
            . 'Du bist Teil einer Rettungskette, die wirklich etwas verändert — von Herzen: Danke, dass du dabei bist!',
            560,
            12.5
        );
        foreach ($thanks_lines as $line) {
            $ops .= self::pdf_center_text_op($line, $center_x, $y, 'F1', 12.5, $colors['muted']);
            $y -= 18;
        }

        // Signature area.
        $sig_y = 92.0;
        $left_x1 = 120.0;
        $left_x2 = 360.0;
        $right_x1 = $page_w - 360.0;
        $right_x2 = $page_w - 120.0;
        $issued_line = trim(self::org()['city'] . ', ') . date_i18n('d.m.Y');
        $ops .= self::pdf_center_text_op($issued_line, ($left_x1 + $left_x2) / 2, $sig_y + 6, 'F1', 12, $colors['text']);
        $ops .= self::pdf_line_op($left_x1, $sig_y, $left_x2, $sig_y, $colors['text'], 0.75);
        $ops .= self::pdf_center_text_op('Ort, Datum', ($left_x1 + $left_x2) / 2, $sig_y - 16, 'F1', 9.5, $colors['muted']);
        $ops .= self::pdf_line_op($right_x1, $sig_y, $right_x2, $sig_y, $colors['text'], 0.75);
        $ops .= self::pdf_center_text_op(self::org()['chairperson'], ($right_x1 + $right_x2) / 2, $sig_y - 16, 'F1', 9.5, $colors['muted']);

        $ops .= self::pdf_center_text_op(self::org_footer_line(), $center_x, 46, 'F1', 9, $colors['muted']);

        return self::assemble_single_page_pdf($ops, $logo, $page_w, $page_h);
    }

    public static function download_sponsor_certificate_pdf(): void
    {
        $sponsor_id = absint($_REQUEST['sponsor_id'] ?? 0);
        if (!$sponsor_id || !current_user_can('edit_post', $sponsor_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_sponsor_certificate_pdf_' . $sponsor_id);
        $sponsor = get_post($sponsor_id);
        if (!$sponsor instanceof WP_Post || $sponsor->post_type !== 'sod_sponsor') {
            wp_die('Pate nicht gefunden.');
        }

        try {
            $pdf = self::sponsor_certificate_pdf($sponsor_id);
        } catch (\Throwable $e) {
            wp_die('Das Zertifikat konnte nicht erstellt werden. Bitte erneut versuchen.');
        }
        $filename = self::sponsor_certificate_filename($sponsor_id);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public static function send_sponsor_certificate_pdf_manual(): void
    {
        $sponsor_id = absint($_REQUEST['sponsor_id'] ?? 0);
        if (!$sponsor_id || !current_user_can('edit_post', $sponsor_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_sponsor_certificate_send_' . $sponsor_id);
        $sponsor = get_post($sponsor_id);
        if (!$sponsor instanceof WP_Post || $sponsor->post_type !== 'sod_sponsor') {
            wp_die('Pate nicht gefunden.');
        }
        $email = sanitize_email((string)get_post_meta($sponsor_id, 'sod_sponsor_email', true));
        $notice = $email === ''
            ? 'sponsor_certificate_missing_email'
            : (self::send_sponsor_certificate_pdf_auto($sponsor_id, true)
                ? 'sponsor_certificate_sent'
                : 'sponsor_certificate_send_failed');
        wp_safe_redirect(add_query_arg(
            'sod_notice',
            $notice,
            get_edit_post_link($sponsor_id, 'raw') ?: admin_url('edit.php?post_type=sod_sponsor')
        ));
        exit;
    }

    private static function wrap_pdf_line(string $line, int $max): array
    {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
        if ($line === '') {
            return [''];
        }
        return explode("\n", wordwrap($line, $max, "\n", true));
    }

    private static function pdf_text(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private static function wizard_nav(array $steps): void
    {
        echo '<div class="sod-wizard-nav" role="tablist">';
        foreach ($steps as $index => $label) {
            printf(
                '<button type="button" class="%s" data-sod-step-button="%d">%d. %s</button>',
                $index === 0 ? 'is-active' : '',
                $index,
                $index + 1,
                esc_html((string)$label)
            );
        }
        echo '</div>';
    }

    private static function wizard_controls(): void
    {
        echo '<div class="sod-wizard-controls">';
        echo '<button type="button" class="button" data-sod-prev>Zurück</button>';
        echo '<button type="button" class="button button-secondary" data-sod-next>Weiter</button>';
        echo '<span class="description">Zum Speichern bitte rechts oben „Aktualisieren“ oder „Veröffentlichen“ verwenden.</span>';
        echo '</div>';
    }

    private static function render_admin_record_fields(WP_Post $post, array $config, array $keys): void
    {
        foreach ($keys as $key) {
            $field = $config['fields'][$key] ?? null;
            if (!$field) {
                continue;
            }
            $label = $field['label'];
            $type = $field['type'] ?? 'text';
            $importance = in_array($key, ['sod_interest_status', 'sod_interest_dog', 'sod_interest_email', 'sod_task_dog'], true) ? 'recommended' : '';
            if ($type === 'select') {
                $selected = (string)get_post_meta($post->ID, $key, true);
                if ($selected === '' && $key === 'sod_task_dog') {
                    $selected = self::prefill_dog_id_param();
                }
                self::select_field($key, $label, $field['options'] ?? [], $selected, true, $importance);
                continue;
            }
            if ($type === 'textarea') {
                self::textarea_field($post->ID, $key, $label, (int)($field['rows'] ?? 3), $importance);
                continue;
            }
            if ($type === 'date') {
                self::date_field($post->ID, $key, $label, $importance);
                continue;
            }
            self::text_field($post->ID, $key, $label, (string)($field['placeholder'] ?? ''), $importance);
        }
    }

    private static function field_badge(string $importance): string
    {
        if ($importance === 'required') {
            return ' <span class="sod-field-badge sod-field-required">Pflicht</span>';
        }
        if ($importance === 'recommended') {
            return ' <span class="sod-field-badge">Empfohlen</span>';
        }
        return '';
    }

    private static function field_class(string $importance): string
    {
        return trim('sod-admin-field ' . ($importance !== '' ? 'sod-field-' . $importance : ''));
    }

    private static function text_field(int $post_id, string $key, string $label, string $placeholder = '', string $importance = ''): void
    {
        printf(
            '<p class="%5$s"><label for="%1$s"><strong>%2$s</strong>%6$s</label><br><input type="text" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s"></p>',
            esc_attr($key),
            esc_html($label),
            esc_attr((string)get_post_meta($post_id, $key, true)),
            esc_attr($placeholder),
            esc_attr(self::field_class($importance)),
            self::field_badge($importance)
        );
    }

    private static function textarea_field(int $post_id, string $key, string $label, int $rows = 2, string $importance = ''): void
    {
        $value = (string)get_post_meta($post_id, $key, true);
        if (in_array($key, ['sod_document_links', 'sod_case_documents'], true)) {
            $value = self::refresh_private_document_urls($value);
        }
        printf(
            '<p class="%5$s"><label for="%1$s"><strong>%2$s</strong>%6$s</label><br><textarea id="%1$s" name="%1$s" rows="%3$d">%4$s</textarea></p>',
            esc_attr($key),
            esc_html($label),
            $rows,
            esc_textarea($value),
            esc_attr(self::field_class($importance)),
            self::field_badge($importance)
        );
    }

    private static function file_field(string $key, string $label, string $accept = '', string $description = ''): void
    {
        printf(
            '<p class="%4$s"><label for="%1$s"><strong>%2$s</strong></label><br><input type="file" id="%1$s" name="%1$s"%3$s>%5$s</p>',
            esc_attr($key),
            esc_html($label),
            $accept !== '' ? ' accept="' . esc_attr($accept) . '"' : '',
            esc_attr(self::field_class('')),
            $description !== '' ? '<span class="description">' . esc_html($description) . '</span>' : ''
        );
    }

    private static function post_content_field(WP_Post $post, string $key, string $label, int $rows = 4, string $importance = ''): void
    {
        printf(
            '<p class="%5$s"><label for="%1$s"><strong>%2$s</strong>%6$s</label><br><textarea id="%1$s" name="%1$s" rows="%3$d">%4$s</textarea></p>',
            esc_attr($key),
            esc_html($label),
            $rows,
            esc_textarea((string)$post->post_content),
            esc_attr(self::field_class($importance)),
            self::field_badge($importance)
        );
    }

    private static function raw_text_field(string $key, string $label, string $value, string $placeholder = '', string $importance = ''): void
    {
        printf(
            '<p class="%5$s"><label for="%1$s"><strong>%2$s</strong>%6$s</label><br><input type="text" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s"></p>',
            esc_attr($key),
            esc_html($label),
            esc_attr($value),
            esc_attr($placeholder),
            esc_attr(self::field_class($importance)),
            self::field_badge($importance)
        );
    }

    private static function raw_textarea_field(string $key, string $label, string $value, int $rows = 3, string $placeholder = '', string $importance = ''): void
    {
        printf(
            '<p class="%6$s"><label for="%1$s"><strong>%2$s</strong>%7$s</label><br><textarea id="%1$s" name="%1$s" rows="%3$d" placeholder="%4$s">%5$s</textarea></p>',
            esc_attr($key),
            esc_html($label),
            $rows,
            esc_attr($placeholder),
            esc_textarea($value),
            esc_attr(self::field_class($importance)),
            self::field_badge($importance)
        );
    }

    private static function dog_image_ids(int $post_id): array
    {
        return self::sanitize_id_list((string)get_post_meta($post_id, 'sod_dog_image_ids', true));
    }

    private static function sanitize_id_list(string $value): array
    {
        $ids = array_map('absint', preg_split('/\s*,\s*/', trim($value)) ?: []);
        return array_values(array_unique(array_filter($ids)));
    }

    private static function update_post_content(int $post_id, string $content, string $callback): void
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || (string)$post->post_content === $content) {
            return;
        }
        $hook = $post->post_type === 'sod_dog' ? 'save_post_sod_dog' : 'save_post_sod_inventory';
        remove_action($hook, [self::class, $callback], 10);
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
        ]);
        add_action($hook, [self::class, $callback], 10, 2);
    }

    private static function select_field(string $key, string $label, array $options, string $selected, bool $wrap = true, string $importance = ''): void
    {
        if ($selected !== '' && !array_key_exists($selected, $options)) {
            $options[$selected] = $selected;
        }
        if ($wrap) {
            printf('<p class="%3$s"><label for="%1$s"><strong>%2$s</strong>%4$s</label><br>', esc_attr($key), esc_html($label), esc_attr(self::field_class($importance)), self::field_badge($importance));
        } else {
            printf('<p><label><span>%s</span>', esc_html($label));
        }
        printf('<select id="%1$s" name="%1$s">', esc_attr($key));
        foreach ($options as $value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr((string)$value),
                selected($selected, (string)$value, false),
                esc_html((string)$option_label)
            );
        }
        echo '</select></label></p>';
    }

    private static function admin_record_configs(): array
    {
        return [
            'sod_task' => [
                'name' => 'Aufgaben',
                'singular' => 'Aufgabe',
                'add_new' => 'Aufgabe anlegen',
                'edit' => 'Aufgabe bearbeiten',
                'box_title' => 'Aufgabendaten',
                'icon' => 'dashicons-yes-alt',
                'hint' => 'Geeignet für Impf-Erinnerungen, Futter nachkaufen, Rückrufe, Foto-Updates und Nachkontrollen.',
                'fields' => [
                    'sod_task_status' => ['label' => 'Status', 'type' => 'select', 'options' => self::task_status_options()],
                    'sod_task_due' => ['label' => 'Fällig am', 'type' => 'date'],
                    'sod_task_priority' => ['label' => 'Priorität', 'type' => 'select', 'options' => self::priority_options()],
                    'sod_task_dog' => ['label' => 'Hund', 'type' => 'select', 'options' => self::dog_assignment_options()],
                    'sod_task_owner' => ['label' => 'Zuständig', 'placeholder' => 'Name'],
                    'sod_task_notes' => ['label' => 'Notizen', 'type' => 'textarea', 'rows' => 4],
                ],
            ],
            'sod_interest' => [
                'name' => 'Interessenten',
                'singular' => 'Interessent',
                'add_new' => 'Interessent anlegen',
                'edit' => 'Interessent bearbeiten',
                'box_title' => 'Interessentendaten',
                'icon' => 'dashicons-groups',
                'hint' => 'Hier lassen sich Anfragen, Gespräche, Wiedervorlagen, Absagegründe und passende Hunde sauber nachverfolgen.',
                'fields' => [
                    'sod_interest_status' => ['label' => 'Status', 'type' => 'select', 'options' => self::interest_status_options()],
                    'sod_interest_type' => ['label' => 'Interesse', 'type' => 'select', 'options' => self::interest_type_options()],
                    'sod_interest_dog' => ['label' => 'Hund', 'type' => 'select', 'options' => self::dog_assignment_options()],
                    'sod_interest_email' => ['label' => 'E-Mail'],
                    'sod_interest_phone' => ['label' => 'Telefon'],
                    'sod_interest_location' => ['label' => 'Wohnort / Umgebung'],
                    'sod_interest_followup' => ['label' => 'Wiedervorlage', 'type' => 'date'],
                    'sod_interest_reject_reason' => ['label' => 'Absagegrund', 'type' => 'textarea', 'rows' => 3],
                    'sod_interest_notes' => ['label' => 'Gesprächsnotizen', 'type' => 'textarea', 'rows' => 5],
                    'sod_privacy_legal_hold' => ['label' => 'Löschung aussetzen (nur mit dokumentiertem Rechts-/Nachweisgrund)', 'type' => 'select', 'options' => self::yes_no_options()],
                ],
            ],
            'sod_case' => [
                'name' => 'Vermittlungsakten',
                'singular' => 'Vermittlungsakte',
                'add_new' => 'Vermittlungsakte anlegen',
                'edit' => 'Vermittlungsakte bearbeiten',
                'box_title' => 'Vermittlungsakte',
                'icon' => 'dashicons-portfolio',
                'hint' => 'Sammelt Anfrage, Vertrag, Zahlung, Transport, Übergabe sowie Vor- und Nachkontrolle an einem Ort.',
                'fields' => [
                    'sod_case_status' => ['label' => 'Status', 'type' => 'select', 'options' => self::case_status_options()],
                    'sod_case_dog' => ['label' => 'Hund', 'type' => 'select', 'options' => self::dog_assignment_options()],
                    'sod_case_adopter' => ['label' => 'Adoptant / Kontakt'],
                    'sod_case_contract' => ['label' => 'Schutzvertrag', 'type' => 'select', 'options' => self::contract_status_options()],
                    'sod_case_fee' => ['label' => 'Schutzgebühr / Zahlung'],
                    'sod_case_precheck' => ['label' => 'Vorkontrolle', 'type' => 'textarea', 'rows' => 4],
                    'sod_case_aftercheck' => ['label' => 'Nachkontrolle', 'type' => 'textarea', 'rows' => 4],
                    'sod_case_documents' => ['label' => 'Dokumente / Links', 'type' => 'textarea', 'rows' => 4],
                    'sod_case_notes' => ['label' => 'Notizen', 'type' => 'textarea', 'rows' => 5],
                    'sod_privacy_legal_hold' => ['label' => 'Löschung aussetzen (nur mit dokumentiertem Rechts-/Nachweisgrund)', 'type' => 'select', 'options' => self::yes_no_options()],
                ],
            ],
            'sod_transport' => [
                'name' => 'Transporte',
                'singular' => 'Transport',
                'add_new' => 'Transport anlegen',
                'edit' => 'Transport bearbeiten',
                'box_title' => 'Transportplanung',
                'icon' => 'dashicons-location-alt',
                'hint' => 'Für Reisedatum, Fahrer, Route, Übergabeort und Dokumentencheck.',
                'fields' => [
                    'sod_transport_status' => ['label' => 'Status', 'type' => 'select', 'options' => self::transport_status_options()],
                    'sod_transport_date' => ['label' => 'Reisedatum', 'type' => 'date'],
                    'sod_transport_dogs' => ['label' => 'Hunde', 'type' => 'textarea', 'rows' => 3],
                    'sod_transport_driver' => ['label' => 'Fahrer / Begleitung'],
                    'sod_transport_route' => ['label' => 'Route', 'type' => 'textarea', 'rows' => 3],
                    'sod_transport_handover' => ['label' => 'Übergabeort'],
                    'sod_transport_documents' => ['label' => 'Dokumente vollständig?', 'type' => 'select', 'options' => self::yes_no_options()],
                    'sod_transport_notes' => ['label' => 'Notizen', 'type' => 'textarea', 'rows' => 4],
                ],
            ],
            'sod_foster' => [
                'name' => 'Pflegestellen',
                'singular' => 'Pflegestelle',
                'add_new' => 'Pflegestelle anlegen',
                'edit' => 'Pflegestelle bearbeiten',
                'box_title' => 'Pflegestellendaten',
                'icon' => 'dashicons-admin-home',
                'hint' => 'Hilft bei Kapazität, Kontakt, aktuellem Hund und besonderen Bedingungen.',
                'fields' => [
                    'sod_foster_status' => ['label' => 'Status', 'type' => 'select', 'options' => self::foster_status_options()],
                    'sod_foster_contact' => ['label' => 'Kontaktperson'],
                    'sod_foster_phone' => ['label' => 'Telefon'],
                    'sod_foster_email' => ['label' => 'E-Mail'],
                    'sod_foster_location' => ['label' => 'Ort'],
                    'sod_foster_capacity' => ['label' => 'Kapazität'],
                    'sod_foster_current_dogs' => ['label' => 'Aktuelle Hunde', 'type' => 'textarea', 'rows' => 3],
                    'sod_foster_conditions' => ['label' => 'Besonderheiten / Bedingungen', 'type' => 'textarea', 'rows' => 4],
                ],
            ],
            'sod_sponsor' => [
                'name' => 'Paten',
                'singular' => 'Pate',
                'add_new' => 'Pate anlegen',
                'edit' => 'Pate bearbeiten',
                'box_title' => 'Patendaten',
                'icon' => 'dashicons-heart',
                'hint' => 'Für Patenschaften: Pate, Hund, Betrag und Intervall erfassen. Der Titel ist der Name des Paten.',
                'fields' => [
                    'sod_sponsor_status' => ['label' => 'Status', 'type' => 'select', 'options' => self::sponsor_status_options()],
                    'sod_sponsor_dog' => ['label' => 'Hund', 'type' => 'select', 'options' => self::dog_assignment_options()],
                    'sod_sponsor_email' => ['label' => 'E-Mail'],
                    'sod_sponsor_phone' => ['label' => 'Telefon'],
                    'sod_sponsor_amount' => ['label' => 'Betrag', 'placeholder' => 'z.B. 25 €'],
                    'sod_sponsor_interval' => ['label' => 'Intervall', 'type' => 'select', 'options' => self::sponsor_interval_options()],
                    'sod_sponsor_payment_method' => ['label' => 'Zahlungsart', 'type' => 'select', 'options' => self::sponsor_payment_method_options()],
                    'sod_sponsor_payment_received' => ['label' => 'Zahlungseingang bestätigt', 'type' => 'select', 'options' => ['0' => 'Noch nicht', '1' => 'Ja, Zahlung eingegangen']],
                    'sod_sponsor_since' => ['label' => 'Pate seit', 'type' => 'date'],
                    'sod_sponsor_public_consent' => ['label' => 'Einwilligung zur öffentlichen Anzeige dokumentiert', 'type' => 'select', 'options' => ['0' => 'Nein', '1' => 'Ja']],
                    'sod_sponsor_photo_approved' => ['label' => 'Foto öffentlich zeigen', 'type' => 'select', 'options' => ['0' => 'Nein', '1' => 'Ja (auf Hundeseite anzeigen)']],
                    'sod_sponsor_notes' => ['label' => 'Notizen', 'type' => 'textarea', 'rows' => 4],
                    'sod_privacy_legal_hold' => ['label' => 'Löschung aussetzen (nur mit dokumentiertem Rechts-/Nachweisgrund)', 'type' => 'select', 'options' => self::yes_no_options()],
                ],
            ],
            'sod_finance' => [
                'name' => 'Spenden & Ausgaben',
                'singular' => 'Eintrag',
                'add_new' => 'Eintrag anlegen',
                'edit' => 'Eintrag bearbeiten',
                'box_title' => 'Finanz- und Sachspendendaten',
                'icon' => 'dashicons-money-alt',
                'hint' => 'Für Transparenz: Spenden, Sachspenden, Tierarzt, Futter, Transport und Tierheim-Bau erfassen.',
                'fields' => [
                    'sod_finance_type' => ['label' => 'Art', 'type' => 'select', 'options' => self::finance_type_options()],
                    'sod_finance_date' => ['label' => 'Datum', 'type' => 'date'],
                    'sod_finance_amount' => ['label' => 'Betrag / Menge'],
                    'sod_finance_category' => ['label' => 'Zweck', 'type' => 'select', 'options' => self::finance_category_options()],
                    'sod_finance_payment_method' => ['label' => 'Zahlungsart', 'type' => 'select', 'options' => self::finance_payment_method_options()],
                    'sod_finance_dog' => ['label' => 'Hund', 'type' => 'select', 'options' => self::dog_assignment_options()],
                    'sod_finance_donor' => ['label' => 'Spender Name', 'placeholder' => 'Vor- und Nachname oder Firma'],
                    'sod_finance_donor_email' => ['label' => 'Spender E-Mail', 'placeholder' => 'name@example.at'],
                    'sod_finance_donor_address' => ['label' => 'Spender Adresse', 'type' => 'textarea', 'rows' => 3],
                    'sod_finance_receipt' => ['label' => 'Beleg / Link'],
                    'sod_finance_notes' => ['label' => 'Notizen', 'type' => 'textarea', 'rows' => 4],
                    'sod_privacy_legal_hold' => ['label' => 'Löschung aussetzen (nur mit dokumentiertem Rechts-/Nachweisgrund)', 'type' => 'select', 'options' => self::yes_no_options()],
                ],
            ],
        ];
    }

    private static function dog_gender_options(): array
    {
        return [
            'Weiblich' => 'Weiblich',
            'Männlich' => 'Männlich',
            'Unbekannt' => 'Unbekannt',
        ];
    }

    private static function dog_health_options(): array
    {
        return [
            '' => 'Bitte auswählen',
            'Unbekannt / wird geprüft' => 'Unbekannt / wird geprüft',
            'Gesund' => 'Gesund',
            'Geimpft' => 'Geimpft',
            'Geimpft und gechippt' => 'Geimpft und gechippt',
            'Geimpft, gechippt und kastriert' => 'Geimpft, gechippt und kastriert',
            'In Behandlung' => 'In Behandlung',
            'Braucht medizinische Abklärung' => 'Braucht medizinische Abklärung',
            'Chronische Erkrankung' => 'Chronische Erkrankung',
            'Handicap' => 'Handicap',
            'Erholt sich von Verletzung/OP' => 'Erholt sich von Verletzung/OP',
        ];
    }

    private static function castration_options(): array
    {
        return [
            '' => 'Bitte auswählen',
            'Unbekannt' => 'Unbekannt',
            'Nicht kastriert' => 'Nicht kastriert',
            'Kastriert' => 'Kastriert',
            'Geplant' => 'Geplant',
            'Aus medizinischen Gründen nicht möglich' => 'Aus medizinischen Gründen nicht möglich',
        ];
    }

    private static function quarantine_options(): array
    {
        return [
            '' => 'Bitte auswählen',
            'Keine Quarantäne' => 'Keine Quarantäne',
            'In Quarantäne' => 'In Quarantäne',
            'Beobachtung' => 'Beobachtung',
            'Freigegeben' => 'Freigegeben',
        ];
    }

    private static function task_status_options(): array
    {
        return [
            'offen' => 'Offen',
            'in_arbeit' => 'In Arbeit',
            'erledigt' => 'Erledigt',
            'verschoben' => 'Verschoben',
        ];
    }

    private static function priority_options(): array
    {
        return [
            'normal' => 'Normal',
            'hoch' => 'Hoch',
            'dringend' => 'Dringend',
            'niedrig' => 'Niedrig',
        ];
    }

    private static function interest_status_options(): array
    {
        return [
            'neu' => 'Neu',
            'kontakt' => 'In Kontakt',
            'pruefung' => 'In Prüfung',
            'passend' => 'Passend',
            'abgesagt' => 'Abgesagt',
            'archiv' => 'Archiv',
        ];
    }

    private static function interest_type_options(): array
    {
        return [
            'vermittlung' => 'Vermittlung / Adoption',
            'patenschaft' => 'Patenschaft',
            'pflegestelle' => 'Pflegestelle',
            'sachspende' => 'Sachspende',
            'allgemein' => 'Allgemein',
        ];
    }

    private static function map_application_interest(string $interest): string
    {
        $interest = mb_strtolower(trim($interest));
        if (str_contains($interest, 'patenschaft')) {
            return 'patenschaft';
        }
        if (str_contains($interest, 'sachspende') || str_contains($interest, 'spende')) {
            return 'sachspende';
        }
        if (str_contains($interest, 'pflegestelle')) {
            return 'pflegestelle';
        }
        if (str_contains($interest, 'vermittlung') || str_contains($interest, 'adoption')) {
            return 'vermittlung';
        }
        return 'allgemein';
    }

    private static function case_status_options(): array
    {
        return [
            'neu' => 'Neu',
            'vorkontrolle' => 'Vorkontrolle',
            'reserviert' => 'Reserviert',
            'vertrag' => 'Vertrag offen',
            'transport' => 'Transport geplant',
            'vermittelt' => 'Vermittelt',
            'nachkontrolle' => 'Nachkontrolle offen',
            'abgeschlossen' => 'Abgeschlossen',
            'abgebrochen' => 'Abgebrochen',
        ];
    }

    private static function contract_status_options(): array
    {
        return [
            'offen' => 'Offen',
            'erstellt' => 'Erstellt',
            'gesendet' => 'Gesendet',
            'unterschrieben' => 'Unterschrieben',
            'abgelegt' => 'Abgelegt',
        ];
    }

    private static function transport_status_options(): array
    {
        return [
            'planung' => 'In Planung',
            'bereit' => 'Bereit',
            'unterwegs' => 'Unterwegs',
            'angekommen' => 'Angekommen',
            'verschoben' => 'Verschoben',
        ];
    }

    private static function yes_no_options(): array
    {
        return [
            '' => 'Bitte auswählen',
            'ja' => 'Ja',
            'nein' => 'Nein',
            'teilweise' => 'Teilweise',
        ];
    }

    private static function foster_status_options(): array
    {
        return [
            'aktiv' => 'Aktiv',
            'frei' => 'Freier Platz',
            'voll' => 'Voll',
            'pause' => 'Pause',
            'pruefung' => 'In Prüfung',
        ];
    }

    private static function sponsor_status_options(): array
    {
        return [
            'ausstehend' => 'Ausstehend (Altbestand)',
            'aktiv' => 'Aktiv',
            'inaktiv' => 'Inaktiv (kein Zahlungseingang: 24 Std. bei PayPal / 40 Tage bei Dauerauftrag)',
            'pausiert' => 'Pausiert',
            'beendet' => 'Beendet',
        ];
    }

    private static function sponsor_payment_method_options(): array
    {
        return [
            'paypal' => 'PayPal-Abo',
            'dauerauftrag' => 'Dauerauftrag (Banküberweisung)',
        ];
    }

    private static function sponsor_interval_options(): array
    {
        return [
            'monatlich' => 'Monatlich',
            'vierteljaehrlich' => 'Vierteljährlich',
            'jaehrlich' => 'Jährlich',
            'einmalig' => 'Einmalig',
        ];
    }

    private static function finance_type_options(): array
    {
        return [
            'spende' => 'Geldspende',
            'sachspende' => 'Sachspende',
            'ausgabe' => 'Ausgabe',
            'erstattung' => 'Erstattung',
        ];
    }

    private static function finance_category_options(): array
    {
        return [
            'tierheim' => 'Bau / Erhaltung Tierheim',
            'futter' => 'Futter',
            'tierarzt' => 'Tierarzt / Medizin',
            'transport' => 'Transport',
            'zubehoer' => 'Zubehör',
            'verwaltung' => 'Verwaltung',
            'sonstiges' => 'Sonstiges',
        ];
    }

    private static function finance_payment_method_options(): array
    {
        return [
            '' => '– nicht angegeben –',
            'paypal' => 'PayPal',
            'dauerauftrag' => 'Dauerauftrag (Banküberweisung)',
            'ueberweisung' => 'Überweisung (einmalig)',
            'bar' => 'Bar',
            'sonstige' => 'Sonstige',
        ];
    }

    private static function inventory_category_options(): array
    {
        return [
            'Futter' => 'Futter',
            'Leckerli' => 'Leckerli',
            'Halsband' => 'Halsband',
            'Geschirr' => 'Geschirr',
            'Leine' => 'Leine',
            'Napf' => 'Napf',
            'Decke/Bett' => 'Decke/Bett',
            'Transport' => 'Transport',
            'Pflege' => 'Pflege',
            'Medizin' => 'Medizin',
            'Reinigung' => 'Reinigung',
            'Sonstiges' => 'Sonstiges',
        ];
    }

    private static function inventory_unit_options(): array
    {
        return [
            'kg' => 'kg',
            'Säcke' => 'Säcke',
            'Dosen' => 'Dosen',
            'Stück' => 'Stück',
            'Liter' => 'Liter',
            'Packungen' => 'Packungen',
            'Rollen' => 'Rollen',
            'Sets' => 'Sets',
        ];
    }

    private static function storage_location_options(): array
    {
        return [
            '' => 'Bitte auswählen',
            'Hauptlager' => 'Hauptlager',
            'Nebenlager' => 'Nebenlager',
            'Tierheim' => 'Tierheim',
            'Pflegestelle' => 'Pflegestelle',
            'Transport / Auto' => 'Transport / Auto',
            'Tierarzt' => 'Tierarzt',
            'Sonstiges' => 'Sonstiges',
        ];
    }

    private static function dog_assignment_options(): array
    {
        $options = ['' => 'Keinem Hund zugeteilt'];
        $dogs = get_posts([
            'post_type' => 'sod_dog',
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        foreach ($dogs as $dog) {
            $options[(string)$dog->ID] = get_the_title($dog);
        }
        return $options;
    }

    public static function admin_assets(string $hook = ''): void
    {
        wp_enqueue_style('sod-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], '1.4.0');
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array((string)$screen->post_type, ['sod_dog', 'sod_inventory', 'sod_interest', 'sod_application', 'sod_case', 'sod_finance', 'sod_task', 'sod_transport', 'sod_foster', 'sod_sponsor', 'sod_dog_update', 'sod_support'], true)) {
            wp_enqueue_media();
            wp_enqueue_script('sod-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], '1.3.7', true);
        }
    }

    public static function public_assets(): void
    {
        wp_enqueue_style('sod-public', plugin_dir_url(__FILE__) . 'assets/css/public.css', [], '1.8.0');
        wp_enqueue_script('sod-public', plugin_dir_url(__FILE__) . 'assets/js/public.js', [], '1.3.0', true);
    }

    private static function can_save(int $post_id, string $nonce_field, string $action): bool
    {
        return !defined('DOING_AUTOSAVE')
            && isset($_POST[$nonce_field])
            && wp_verify_nonce((string)$_POST[$nonce_field], $action)
            && current_user_can('edit_post', $post_id);
    }

    private static function dog_statuses(): array
    {
        if (is_admin()) {
            return [
                'verfuegbar' => 'Verfügbar',
                'vermittlung' => 'In Vermittlung',
                'vermittelt' => 'Vermittelt',
                'notfall' => 'Notfall',
                'pause' => 'Pause',
            ];
        }
        return [
            'verfuegbar' => self::t('dogcard.status_verfuegbar', 'Verfügbar'),
            'vermittlung' => self::t('dogcard.status_vermittlung', 'In Vermittlung'),
            'vermittelt' => self::t('dogcard.status_vermittelt', 'Vermittelt'),
            'notfall' => self::t('dogcard.status_notfall', 'Notfall'),
            'pause' => self::t('dogcard.status_pause', 'Pause'),
        ];
    }

    private static function page_url(string $slug): string
    {
        $page = get_page_by_path($slug);
        return $page ? get_permalink($page) : home_url('/' . $slug . '/');
    }

    private static function prefill_dog_id_param(): string
    {
        $dog_id = absint($_GET['sod_prefill_dog'] ?? 0);
        if ($dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
            return (string)$dog_id;
        }
        return '';
    }

    public static function register_abilities(): void
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability('shield-of-dogs/list-dogs', [
            'label' => 'Hunde auflisten',
            'description' => 'Listet Hunde, die aktuell oeffentlich zur Vermittlung oder fuer Patenschaften freigegeben sind, mit den auf der Website sichtbaren Daten.',
            'category' => 'shield-of-dogs',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'adoption fuer Vermittlung, sponsorship fuer Patenschaft, all fuer beides',
                        'enum' => ['adoption', 'sponsorship', 'all'],
                        'default' => 'adoption',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximale Anzahl Ergebnisse',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                ],
            ],
            'output_schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'age' => ['type' => 'string'],
                        'breed' => ['type' => 'string'],
                        'gender' => ['type' => 'string'],
                        'weight' => ['type' => 'string'],
                        'location' => ['type' => 'string'],
                        'health' => ['type' => 'string'],
                        'character' => ['type' => 'string'],
                        'needs' => ['type' => 'string'],
                        'sponsorship_amount' => ['type' => 'string'],
                        'monthly_food_need' => ['type' => 'string'],
                        'monthly_food_secured' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'image_url' => ['type' => 'string'],
                    ],
                ],
            ],
            'execute_callback' => [self::class, 'ability_list_dogs'],
            'permission_callback' => static fn (): bool => current_user_can('edit_sod_dogs'),
            'meta' => [
                'mcp' => ['public' => false],
            ],
        ]);

        wp_register_ability('shield-of-dogs/get-dog', [
            'label' => 'Hund abrufen',
            'description' => 'Ruft die oeffentlich sichtbaren Details zu einem einzelnen, freigegebenen Hund ab.',
            'category' => 'shield-of-dogs',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'dog_id' => [
                        'type' => 'integer',
                        'description' => 'WordPress-ID des Hundes',
                    ],
                ],
                'required' => ['dog_id'],
            ],
            'output_schema' => ['type' => 'object'],
            'execute_callback' => [self::class, 'ability_get_dog'],
            'permission_callback' => static fn (): bool => current_user_can('edit_sod_dogs'),
            'meta' => [
                'mcp' => ['public' => false],
            ],
        ]);

        wp_register_ability('shield-of-dogs/shelter-overview', [
            'label' => 'Tierheim-Uebersicht',
            'description' => 'Interne Kennzahlen: Anzahl Hunde, offene Aufgaben, Interessenten, Vermittlungsakten, Transporte, Pflegestellen und Paten. Erfordert Mitarbeiterrechte fuer Hunde.',
            'category' => 'shield-of-dogs',
            'input_schema' => ['type' => 'object'],
            'output_schema' => ['type' => 'object'],
            'execute_callback' => [self::class, 'ability_shelter_overview'],
            'permission_callback' => static fn (): bool => current_user_can('edit_sod_dogs'),
            'meta' => [
                'mcp' => ['public' => false],
            ],
        ]);

        wp_register_ability('shield-of-dogs/inventory-status', [
            'label' => 'Lagerbestand',
            'description' => 'Listet Futter- und Zubehoerartikel mit Gesamtbestand, verfuegbarer Menge, Lagerorten und Hund-Zuordnung. Erfordert Mitarbeiterrechte fuer Lager.',
            'category' => 'shield-of-dogs',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'only_low_stock' => [
                        'type' => 'boolean',
                        'description' => 'Nur Artikel mit knappem Bestand (unter Mindestbestand) anzeigen',
                        'default' => false,
                    ],
                ],
            ],
            'output_schema' => ['type' => 'array'],
            'execute_callback' => [self::class, 'ability_inventory_status'],
            'permission_callback' => static fn (): bool => current_user_can('edit_sod_items'),
            'meta' => [
                'mcp' => ['public' => false],
            ],
        ]);

        wp_register_ability('shield-of-dogs/due-reminders', [
            'label' => 'Faellige Erinnerungen',
            'description' => 'Listet faellige Impfungen, offene Aufgaben, Wiedervorlagen bei Interessenten und offene Nachkontrollen. Erfordert Mitarbeiterrechte fuer Hunde.',
            'category' => 'shield-of-dogs',
            'input_schema' => ['type' => 'object'],
            'output_schema' => ['type' => 'array'],
            'execute_callback' => [self::class, 'ability_due_reminders'],
            'permission_callback' => static fn (): bool => current_user_can('edit_sod_dogs'),
            'meta' => [
                'mcp' => ['public' => false],
            ],
        ]);
    }

    public static function ability_list_dogs(array $input): array
    {
        $mode = (string)($input['mode'] ?? 'adoption');
        $limit = max(1, min(100, (int)($input['limit'] ?? 20)));

        $meta_query = [];
        if ($mode === 'adoption') {
            $meta_query[] = ['key' => 'sod_show_adoption', 'value' => '1'];
        } elseif ($mode === 'sponsorship') {
            $meta_query[] = ['key' => 'sod_show_sponsorship', 'value' => '1'];
        } else {
            $meta_query = [
                'relation' => 'OR',
                ['key' => 'sod_show_adoption', 'value' => '1'],
                ['key' => 'sod_show_sponsorship', 'value' => '1'],
            ];
        }

        $query = new WP_Query([
            'post_type' => 'sod_dog',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => $meta_query,
            'orderby' => 'menu_order date',
            'order' => 'DESC',
        ]);

        $dogs = [];
        foreach ($query->posts as $post) {
            $dogs[] = self::dog_ability_payload((int)$post->ID);
        }
        wp_reset_postdata();
        return $dogs;
    }

    public static function ability_get_dog(array $input)
    {
        $dog_id = absint($input['dog_id'] ?? 0);
        if (!$dog_id || get_post_type($dog_id) !== 'sod_dog' || get_post_status($dog_id) !== 'publish') {
            return new WP_Error('sod_dog_not_found', 'Hund nicht gefunden.', ['status' => 404]);
        }
        $visible = get_post_meta($dog_id, 'sod_show_adoption', true) === '1'
            || get_post_meta($dog_id, 'sod_show_sponsorship', true) === '1';
        if (!$visible) {
            return new WP_Error('sod_dog_not_public', 'Dieser Hund ist aktuell nicht oeffentlich sichtbar.', ['status' => 404]);
        }
        return self::dog_ability_payload($dog_id);
    }

    private static function dog_ability_payload(int $dog_id): array
    {
        $image = has_post_thumbnail($dog_id) ? get_the_post_thumbnail_url($dog_id, 'large') : '';
        if ($image === '' && self::dog_image_ids($dog_id)) {
            $image = wp_get_attachment_image_url(self::dog_image_ids($dog_id)[0], 'large') ?: '';
        }
        return [
            'id' => $dog_id,
            'name' => get_the_title($dog_id),
            'status' => (string)get_post_meta($dog_id, 'sod_status', true) ?: 'verfuegbar',
            'age' => (string)get_post_meta($dog_id, 'sod_age', true),
            'breed' => (string)get_post_meta($dog_id, 'sod_breed', true),
            'gender' => (string)get_post_meta($dog_id, 'sod_gender', true),
            'weight' => (string)get_post_meta($dog_id, 'sod_weight', true),
            'location' => (string)get_post_meta($dog_id, 'sod_location', true),
            'health' => (string)get_post_meta($dog_id, 'sod_health', true),
            'character' => (string)get_post_meta($dog_id, 'sod_character', true),
            'needs' => (string)get_post_meta($dog_id, 'sod_needs', true),
            'sponsorship_amount' => (string)get_post_meta($dog_id, 'sod_sponsorship_amount', true),
            'monthly_food_need' => (string)get_post_meta($dog_id, 'sod_monthly_food_need', true),
            'monthly_food_secured' => (string)get_post_meta($dog_id, 'sod_monthly_food_secured', true),
            'url' => (string)get_permalink($dog_id),
            'image_url' => $image,
        ];
    }

    public static function ability_shelter_overview(array $input): array
    {
        return [
            'dogs' => self::post_count('sod_dog'),
            'open_tasks' => self::post_count('sod_task'),
            'interests' => self::post_count('sod_interest'),
            'cases' => self::post_count('sod_case'),
            'transports' => self::post_count('sod_transport'),
            'fosters' => self::post_count('sod_foster'),
            'sponsors' => self::post_count('sod_sponsor'),
        ];
    }

    public static function ability_inventory_status(array $input): array
    {
        $rows = self::inventory_rows('', '', '');
        $only_low_stock = !empty($input['only_low_stock']);
        $result = [];
        foreach ($rows as $row) {
            if ($only_low_stock && !($row['total_number'] > 0 && $row['total_number'] <= $row['min_number'])) {
                continue;
            }
            $result[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'number' => $row['number'],
                'category' => $row['category'],
                'unit' => $row['unit'],
                'total' => $row['total'],
                'available' => $row['available'],
                'locations' => $row['locations_text'],
                'assigned_dog' => $row['assigned_dog_label'],
            ];
        }
        return $result;
    }

    public static function ability_due_reminders(array $input): array
    {
        return array_map(
            static fn (array $item): array => [
                'label' => $item['label'],
                'info' => $item['info'],
                'url' => $item['url'],
            ],
            self::due_items()
        );
    }

    // =====================================================================
    // Mitgliederkonten-Verwaltung (Admin-Backend fuer den Mitgliederbereich)
    // =====================================================================

    public static function register_member_admin_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=sod_dog',
            'Mitgliederkonten',
            'Mitgliederkonten',
            'manage_options',
            'sod-member-accounts',
            [self::class, 'render_member_accounts_page']
        );
    }

    public static function render_member_accounts_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $members = get_users(['role' => 'sod_member', 'orderby' => 'display_name']);
        $unassigned_sponsors = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'sod_sponsor_user_id', 'compare' => 'NOT EXISTS'],
                ['key' => 'sod_sponsor_user_id', 'value' => '', 'compare' => '='],
                ['key' => 'sod_sponsor_user_id', 'value' => '0', 'compare' => '='],
            ],
        ]);
        ?>
        <div class="wrap">
            <h1>Mitgliederkonten</h1>
            <p>Konten, die sich über den Mitgliederbereich registriert haben, samt Verknüpfung zur jeweiligen Patenschaft.</p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Registriert am</th>
                        <th>Verifiziert</th>
                        <th>Verknüpfte Patenschaft</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$members) : ?>
                        <tr><td colspan="6">Noch keine Mitgliederkonten vorhanden.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($members as $member) :
                        $sponsor = self::member_linked_sponsor($member->ID);
                        $verified = trim((string)get_user_meta($member->ID, 'sod_member_verified_at', true)) !== '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($member->display_name); ?></td>
                            <td><?php echo esc_html($member->user_email); ?></td>
                            <td><?php echo esc_html(mysql2date('d.m.Y', $member->user_registered)); ?></td>
                            <td><?php echo $verified ? 'Ja' : 'Nein'; ?></td>
                            <td>
                                <?php if ($sponsor instanceof WP_Post) :
                                    $dog_id = (int)get_post_meta($sponsor->ID, 'sod_sponsor_dog', true);
                                    ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($sponsor->ID) ?: ''); ?>"><?php echo esc_html($sponsor->post_title); ?></a>
                                    <?php if ($dog_id > 0) : ?>
                                        — <?php echo esc_html(self::dog_public_name($dog_id)); ?>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <em>nicht zugeordnet</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sponsor instanceof WP_Post) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="sod_member_unlink">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string)$member->ID); ?>">
                                        <?php wp_nonce_field('sod_member_unlink_' . $member->ID, 'sod_member_unlink_nonce'); ?>
                                        <button type="submit" class="button button-small">Trennen</button>
                                    </form>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex;gap:6px;">
                                        <input type="hidden" name="action" value="sod_member_manual_link">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string)$member->ID); ?>">
                                        <?php wp_nonce_field('sod_member_manual_link_' . $member->ID, 'sod_member_manual_link_nonce'); ?>
                                        <select name="sponsor_id">
                                            <option value="">– Patenschaft wählen –</option>
                                            <?php foreach ($unassigned_sponsors as $unassigned) : ?>
                                                <option value="<?php echo esc_attr((string)$unassigned->ID); ?>"><?php echo esc_html($unassigned->post_title . ' (' . get_post_meta($unassigned->ID, 'sod_sponsor_email', true) . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="button button-small">Zuordnen</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_member_manual_link(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $user_id = absint($_POST['user_id'] ?? 0);
        if (!isset($_POST['sod_member_manual_link_nonce']) || !wp_verify_nonce((string)$_POST['sod_member_manual_link_nonce'], 'sod_member_manual_link_' . $user_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }
        $sponsor_id = absint($_POST['sponsor_id'] ?? 0);
        if ($sponsor_id > 0 && get_post_type($sponsor_id) === 'sod_sponsor') {
            update_post_meta($sponsor_id, 'sod_sponsor_user_id', $user_id);
        }
        wp_safe_redirect(add_query_arg('sod_notice', 'member_linked', admin_url('edit.php?post_type=sod_dog&page=sod-member-accounts')));
        exit;
    }

    public static function handle_member_unlink(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $user_id = absint($_POST['user_id'] ?? 0);
        if (!isset($_POST['sod_member_unlink_nonce']) || !wp_verify_nonce((string)$_POST['sod_member_unlink_nonce'], 'sod_member_unlink_' . $user_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }
        $sponsor_id = self::member_linked_sponsor_id($user_id);
        if ($sponsor_id > 0) {
            delete_post_meta($sponsor_id, 'sod_sponsor_user_id');
        }
        wp_safe_redirect(add_query_arg('sod_notice', 'member_unlinked', admin_url('edit.php?post_type=sod_dog&page=sod-member-accounts')));
        exit;
    }

    // =====================================================================
    // 30-Tage-Foto-Erinnerung fuer aktiv gesponserte Hunde
    // =====================================================================

    private static function dogs_with_active_sponsorship(): array
    {
        $sponsor_ids = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => 'sod_sponsor_status', 'value' => 'aktiv'],
            ],
        ]);
        $dog_ids = [];
        foreach ($sponsor_ids as $sponsor_id) {
            $dog_id = (int)get_post_meta($sponsor_id, 'sod_sponsor_dog', true);
            if ($dog_id > 0) {
                $dog_ids[$dog_id] = true;
            }
        }
        return array_keys($dog_ids);
    }

    private static function dog_latest_photo_date(int $dog_id): string
    {
        $query = self::dog_updates_query($dog_id, 1, true);
        if (!$query->have_posts()) {
            return '';
        }
        return get_the_date('Y-m-d', $query->posts[0]);
    }

    /**
     * Liefert je ueberfaelligem Hund auch einen "gap_key" (letztes Fotodatum oder 'none') -
     * das ist der Schluessel, mit dem check_dog_photo_reminders() erkennt, ob fuer genau
     * diese Foto-Luecke schon einmal erinnert wurde (verhindert taegliches Nag-Mailing).
     */
    private static function overdue_photo_dogs(): array
    {
        $overdue = [];
        $threshold = time() - 30 * DAY_IN_SECONDS;
        foreach (self::dogs_with_active_sponsorship() as $dog_id) {
            $last_photo = self::dog_latest_photo_date($dog_id);
            $last_photo_ts = $last_photo !== '' ? (int)strtotime($last_photo) : 0;
            if ($last_photo_ts > $threshold) {
                continue;
            }
            $overdue[] = [
                'dog_id' => $dog_id,
                'dog_name' => self::dog_public_name($dog_id),
                'last_photo' => $last_photo,
                'gap_key' => $last_photo !== '' ? $last_photo : 'none',
            ];
        }
        return $overdue;
    }

    public static function check_dog_photo_reminders(): void
    {
        $to_notify = [];
        foreach (self::overdue_photo_dogs() as $entry) {
            $sent_for = (string)get_post_meta($entry['dog_id'], 'sod_dog_photo_reminder_sent_for', true);
            if ($sent_for === $entry['gap_key']) {
                continue;
            }
            $to_notify[] = $entry;
        }
        if (!$to_notify) {
            return;
        }

        $lines = [];
        foreach ($to_notify as $entry) {
            $lines[] = '- ' . $entry['dog_name'] . ': ' . ($entry['last_photo'] !== ''
                ? 'letztes Foto-Update ' . date_i18n('d.m.Y', (int)strtotime($entry['last_photo']))
                : 'noch nie ein Foto-Update');
        }
        $body = "Für folgende aktiv gesponserte Hunde gibt es seit mindestens 30 Tagen kein neues Foto-Update mehr:\n\n"
            . implode("\n", $lines)
            . "\n\nBitte im SOD-Adminbereich unter \"Hunde-Updates\" ein neues Foto ergänzen, damit Paten aktuelle Bilder im Mitgliederbereich sehen.";

        wp_mail(
            self::notification_emails(),
            'SOD Patenfotos fällig: ' . count($to_notify) . ' Hund(e)',
            $body,
            self::mail_headers()
        );

        foreach ($to_notify as $entry) {
            update_post_meta($entry['dog_id'], 'sod_dog_photo_reminder_sent_for', $entry['gap_key']);
        }
    }

    public static function dashboard_photo_reminders_widget(): void
    {
        $overdue = self::overdue_photo_dogs();
        if (!$overdue) {
            echo '<p>Für alle aktiv gesponserten Hunde gibt es ein Foto-Update aus den letzten 30 Tagen.</p>';
            return;
        }
        echo '<ul class="sod-dashboard-list">';
        foreach ($overdue as $entry) {
            $info = $entry['last_photo'] !== ''
                ? 'letztes Foto ' . date_i18n('d.m.Y', (int)strtotime($entry['last_photo']))
                : 'noch nie ein Foto';
            printf(
                '<li><a href="%s">%s</a><span>%s</span></li>',
                esc_url(admin_url('post-new.php?post_type=sod_dog_update&sod_prefill_dog=' . $entry['dog_id'])),
                esc_html($entry['dog_name']),
                esc_html($info)
            );
        }
        echo '</ul>';
        printf(
            '<p class="sod-dashboard-actions"><a class="button button-primary" href="%s">Neues Update anlegen</a></p>',
            esc_url(admin_url('post-new.php?post_type=sod_dog_update'))
        );
    }

    private static function support_status_options(): array
    {
        return ['offen' => 'Offen', 'in_arbeit' => 'In Arbeit', 'erledigt' => 'Erledigt'];
    }

    private static function support_ticket_tag(int $ticket_id): string
    {
        return ' [Support #' . $ticket_id . ']';
    }

    /**
     * Screenshots liegen bewusst NICHT in der Mediathek: sie zeigen oft ganze
     * Bildschirme samt personenbezogener Daten und waeren dort unter einer rate-
     * baren URL oeffentlich abrufbar. Stattdessen im geschuetzten sod-private-
     * Ordner (.htaccess/web.config/index.php) und nur ueber eine nonce-gebundene
     * Admin-Route ausgeliefert - dasselbe Muster wie bei Vertrags-/Vorkontroll-PDFs.
     */
    private static function support_screenshot_path(int $ticket_id, string $file): string
    {
        $private_dir = self::private_upload_dir();
        $file = sanitize_file_name($file);
        if ($private_dir === '' || $ticket_id <= 0 || $file === '') {
            return '';
        }
        $path = trailingslashit($private_dir) . $file;
        return self::is_private_document_path($path, $private_dir) ? $path : '';
    }

    private static function support_screenshot_url(int $ticket_id, string $file): string
    {
        $url = add_query_arg(
            ['action' => 'sod_support_screenshot', 'ticket_id' => $ticket_id, 'file' => rawurlencode($file)],
            admin_url('admin-post.php')
        );
        return wp_nonce_url($url, 'sod_support_screenshot_' . $ticket_id);
    }

    /**
     * Erst pruefen, dann erst den Ticket-Datensatz anlegen: sonst blieben bei einem
     * fehlerhaften Upload leere Support-Tickets zurueck.
     *
     * @return array{0: array<int, array{tmp: string, ext: string}>, 1: bool}
     */
    private static function support_validate_screenshots(): array
    {
        $files = $_FILES['screenshots'] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return [[], false];
        }

        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $valid = [];
        $failed = false;
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (count($valid) >= self::SUPPORT_MAX_SCREENSHOTS) {
                $failed = true;
                break;
            }
            $tmp_name = (string)($files['tmp_name'][$i] ?? '');
            $size = (int)($files['size'][$i] ?? 0);
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp_name) || $size <= 0 || $size > self::SUPPORT_MAX_SCREENSHOT_BYTES) {
                $failed = true;
                continue;
            }
            $check = wp_check_filetype_and_ext($tmp_name, sanitize_file_name((string)($files['name'][$i] ?? 'screenshot')), $allowed);
            $ext = strtolower((string)($check['ext'] ?? ''));
            if ($ext === '' || !isset($allowed[$ext])) {
                $failed = true;
                continue;
            }
            $dims = @getimagesize($tmp_name);
            if ($dims === false || (int)($dims[0] ?? 0) < 1 || (int)($dims[1] ?? 0) < 1) {
                $failed = true;
                continue;
            }
            $valid[] = ['tmp' => $tmp_name, 'ext' => $ext];
        }

        return [$valid, $failed];
    }

    /**
     * @param array<int, array{tmp: string, ext: string}> $validated
     * @return array<int, string> gespeicherte Dateinamen
     */
    private static function support_persist_screenshots(int $ticket_id, array $validated): array
    {
        $private_dir = self::private_upload_dir();
        if ($private_dir === '' || $ticket_id <= 0) {
            return [];
        }
        $stored = [];
        foreach ($validated as $index => $item) {
            $token = substr(hash_hmac('sha256', 'support:' . $ticket_id . ':' . $index, wp_salt('auth')), 0, 24);
            $file = 'support-' . $ticket_id . '-' . ($index + 1) . '-' . $token . '.' . $item['ext'];
            $target = trailingslashit($private_dir) . $file;
            if (@move_uploaded_file($item['tmp'], $target)) {
                @chmod($target, 0640);
                $stored[] = $file;
            }
        }
        return $stored;
    }

    public static function serve_support_screenshot(): void
    {
        $ticket_id = absint($_GET['ticket_id'] ?? 0);
        $file = sanitize_file_name(rawurldecode((string)($_GET['file'] ?? '')));
        if ($ticket_id <= 0 || !current_user_can('edit_post', $ticket_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_support_screenshot_' . $ticket_id);

        $stored = get_post_meta($ticket_id, 'sod_support_screenshots', true);
        $stored = is_array($stored) ? $stored : [];
        if (!in_array($file, $stored, true)) {
            wp_die('Datei nicht gefunden.');
        }
        $path = self::support_screenshot_path($ticket_id, $file);
        if ($path === '' || !is_readable($path)) {
            wp_die('Datei nicht gefunden.');
        }
        $type = (string)(wp_check_filetype($file)['type'] ?? 'application/octet-stream');
        self::serve_private_document($path, $type, $file, true);
    }

    public static function support_meta_box(WP_Post $post): void
    {
        $name = (string)get_post_meta($post->ID, 'sod_support_name', true);
        $email = (string)get_post_meta($post->ID, 'sod_support_email', true);
        $page = (string)get_post_meta($post->ID, 'sod_support_page', true);
        $status = (string)get_post_meta($post->ID, 'sod_support_status', true) ?: 'offen';
        $options = self::support_status_options();
        $screenshots = get_post_meta($post->ID, 'sod_support_screenshots', true);
        $screenshots = is_array($screenshots) ? $screenshots : [];

        echo '<div class="sod-record-summary">';
        printf('<div><span>Name</span><strong>%s</strong></div>', esc_html($name !== '' ? $name : '-'));
        printf('<div><span>E-Mail</span><strong>%s</strong></div>', esc_html($email !== '' ? $email : '-'));
        printf('<div><span>Status</span><strong>%s</strong></div>', esc_html($options[$status] ?? $status));
        printf('<div><span>Eingegangen</span><strong>%s</strong></div>', esc_html(get_the_date('d.m.Y H:i', $post)));
        echo '</div>';

        if ($page !== '') {
            printf(
                '<p class="description">Gemeldet auf: <a href="%s" target="_blank" rel="noopener">%s</a></p>',
                esc_url($page),
                esc_html($page)
            );
        }

        echo '<div class="sod-quick-actions">';
        foreach ($options as $key => $label) {
            if ($key === $status) {
                continue;
            }
            $url = wp_nonce_url(
                add_query_arg(['action' => 'sod_support_set_status', 'ticket_id' => $post->ID, 'status' => $key], admin_url('admin-post.php')),
                'sod_support_set_status_' . $post->ID
            );
            printf('<a class="button" href="%s">Auf „%s" setzen</a>', esc_url($url), esc_html($label));
        }
        echo '</div>';

        if ($screenshots) {
            echo '<div class="sod-admin-field-wide" style="margin:18px 0;max-width:640px;">';
            echo '<h3 style="margin:0 0 8px;">Screenshots</h3>';
            echo '<p class="description" style="margin:0 0 10px;">Nur für angemeldete Mitarbeiter abrufbar – die Bilder liegen in einem geschützten Ordner, nicht in der Mediathek.</p>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
            foreach ($screenshots as $file) {
                $url = self::support_screenshot_url($post->ID, (string)$file);
                printf(
                    '<a href="%s" target="_blank" rel="noopener" style="display:block;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;"><img src="%s" alt="Screenshot" style="display:block;width:150px;height:110px;object-fit:cover;"></a>',
                    esc_url($url),
                    esc_url($url)
                );
            }
            echo '</div></div>';
        }

        self::render_support_message_thread($post);
    }

    private static function render_support_message_thread(WP_Post $post): void
    {
        $name = trim((string)get_post_meta($post->ID, 'sod_support_name', true));
        $message = trim((string)get_post_meta($post->ID, 'sod_support_message', true));
        $reply_email = trim((string)get_post_meta($post->ID, 'sod_support_email', true));

        self::print_sod_thread_styles();

        echo '<div class="sod-message-thread">';
        echo '<h3>Nachrichtenverlauf</h3>';
        echo '<div class="sod-message-list">';
        echo '<div class="sod-message sod-message-in"><div class="sod-message-bubble">';
        printf(
            '<div class="sod-message-meta"><strong>%s</strong><span class="sod-message-time">%s</span></div>',
            esc_html($name !== '' ? $name : 'Meldende Person'),
            esc_html(get_the_date('d.m.Y H:i', $post))
        );
        printf('<div class="sod-message-body">%s</div>', nl2br(esc_html($message !== '' ? $message : '(keine Beschreibung angegeben)')));
        echo '</div></div>';

        foreach (get_comments(['post_id' => $post->ID, 'status' => 'approve', 'order' => 'ASC', 'type__in' => ['comment', 'sod_inbound_reply']]) as $comment) {
            $is_inbound = $comment->comment_type === 'sod_inbound_reply';
            echo '<div class="sod-message ' . ($is_inbound ? 'sod-message-in' : 'sod-message-out') . '"><div class="sod-message-bubble">';
            printf(
                '<div class="sod-message-meta"><strong>%s</strong><span class="sod-message-time">%s</span></div>',
                esc_html($comment->comment_author !== '' ? $comment->comment_author : self::org()['name']),
                esc_html(mysql2date('d.m.Y H:i', $comment->comment_date))
            );
            printf('<div class="sod-message-body">%s</div>', nl2br(esc_html($comment->comment_content)));
            echo '</div></div>';
        }
        echo '</div>';

        self::print_sod_ajax_box_script();

        if ($reply_email === '') {
            echo '<p class="description">Ohne hinterlegte E-Mail-Adresse kann keine Antwort gesendet werden.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="sod-admin-field-wide sod-ajax-box" data-sod-action="sod_support_reply" data-sod-post-id="' . esc_attr((string)$post->ID) . '">';
        wp_nonce_field('sod_support_reply_' . $post->ID, 'sod_support_reply_nonce');
        echo '<label for="sod_support_reply_message"><strong>Antwort per E-Mail an ' . esc_html($reply_email) . '</strong></label><br>';
        echo '<textarea name="reply_message" id="sod_support_reply_message" rows="4" style="width:100%;max-width:640px;" placeholder="Antwort an die meldende Person…"></textarea><br>';
        echo '<button type="button" class="button button-primary sod-ajax-submit" style="margin-top:8px;">Antwort senden</button>';
        echo '</div>';
        echo '</div>';
    }

    public static function handle_support_reply(): void
    {
        $ticket_id = absint($_POST['application_id'] ?? 0);
        if (!$ticket_id || !current_user_can('edit_post', $ticket_id)) {
            wp_die('Keine Berechtigung.');
        }
        if (!isset($_POST['sod_support_reply_nonce']) || !wp_verify_nonce((string)$_POST['sod_support_reply_nonce'], 'sod_support_reply_' . $ticket_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }
        $ticket = get_post($ticket_id);
        if (!$ticket instanceof WP_Post || $ticket->post_type !== 'sod_support') {
            wp_die('Support-Anfrage nicht gefunden.');
        }

        $edit_link = get_edit_post_link($ticket_id, 'raw') ?: admin_url('edit.php?post_type=sod_support');
        $reply = sanitize_textarea_field((string)($_POST['reply_message'] ?? ''));
        $to_email = sanitize_email((string)get_post_meta($ticket_id, 'sod_support_email', true));
        if ($reply === '' || $to_email === '') {
            wp_safe_redirect(add_query_arg('sod_notice', 'support_reply_failed', $edit_link));
            exit;
        }

        $user = wp_get_current_user();
        $mail_error = '';
        $capture_mail_error = static function (WP_Error $error) use (&$mail_error): void {
            $mail_error = $error->get_error_message();
        };
        add_action('wp_mail_failed', $capture_mail_error);
        $sent = wp_mail(
            $to_email,
            'Antwort auf deine Support-Meldung' . self::support_ticket_tag($ticket_id),
            self::support_reply_email_html($reply),
            array_merge(['Content-Type: text/html; charset=UTF-8'], self::application_mail_headers())
        );
        remove_action('wp_mail_failed', $capture_mail_error);

        wp_insert_comment([
            'comment_post_ID' => $ticket_id,
            'comment_content' => $reply,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'user_id' => $user->ID,
            'comment_approved' => 1,
            'comment_type' => 'comment',
        ]);

        if (!$sent) {
            update_post_meta($ticket_id, 'sod_support_reply_mail_error', $mail_error !== '' ? $mail_error : 'Unbekannter Fehler beim Mailversand.');
            wp_safe_redirect(add_query_arg('sod_notice', 'support_reply_mail_failed', $edit_link));
            exit;
        }
        delete_post_meta($ticket_id, 'sod_support_reply_mail_error');

        if ((string)get_post_meta($ticket_id, 'sod_support_status', true) === 'offen') {
            update_post_meta($ticket_id, 'sod_support_status', 'in_arbeit');
        }

        wp_safe_redirect(add_query_arg('sod_notice', 'support_reply_sent', $edit_link));
        exit;
    }

    public static function handle_support_set_status(): void
    {
        $ticket_id = absint($_GET['ticket_id'] ?? 0);
        if (!$ticket_id || !current_user_can('edit_post', $ticket_id)) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('sod_support_set_status_' . $ticket_id);
        $status = sanitize_key((string)($_GET['status'] ?? ''));
        if (isset(self::support_status_options()[$status])) {
            update_post_meta($ticket_id, 'sod_support_status', $status);
        }
        wp_safe_redirect(get_edit_post_link($ticket_id, 'raw') ?: admin_url('edit.php?post_type=sod_support'));
        exit;
    }

    private static function support_reply_email_html(string $reply_text): string
    {
        $logo_url = get_theme_file_uri('assets/images/logo.png');
        ob_start();
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Antwort auf deine Support-Meldung</title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.6;margin:0;background:#f3f5f6}
                .page{max-width:560px;margin:0 auto;background:#fff;padding:28px 30px;box-sizing:border-box}
                .head{display:flex;align-items:center;gap:14px;border-bottom:3px solid #f3c74f;padding-bottom:16px;margin-bottom:22px}
                .logo{width:56px;height:56px;object-fit:contain;flex:0 0 auto}
                .brand{font-weight:700;color:#204060;font-size:15px}
                .brand small{display:block;font-weight:400;color:#68808c;font-size:12px}
                h1{font-size:20px;margin:0 0 16px;color:#204060}
                p{margin:0 0 14px}
                .reply-text{white-space:pre-line}
                .foot{margin-top:26px;padding-top:14px;border-top:1px solid #e4e9eb;font-size:12px;color:#8a9aa3}
            </style>
        </head>
        <body>
            <div class="page">
                <div class="head">
                    <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                    <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                </div>
                <h1>Antwort auf deine Meldung</h1>
                <p class="reply-text"><?php echo nl2br(esc_html($reply_text)); ?></p>
                <p>Herzliche Grüße<br><?php echo esc_html(self::org()['name']); ?></p>
                <div class="foot">Du kannst auf diese E-Mail ganz normal antworten – deine Antwort landet automatisch bei der passenden Meldung.</div>
            </div>
        </body>
        </html>
        <?php
        return (string)ob_get_clean();
    }

    public static function support_admin_columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $label) {
            if ($key === 'date') {
                $new['sod_support_status_column'] = 'Status';
                $new['sod_support_email_column'] = 'E-Mail';
            }
            $new[$key] = $label;
        }
        return $new;
    }

    public static function support_admin_column_content(string $column, int $post_id): void
    {
        if ($column === 'sod_support_status_column') {
            $status = (string)get_post_meta($post_id, 'sod_support_status', true) ?: 'offen';
            echo esc_html(self::support_status_options()[$status] ?? $status);
        }
        if ($column === 'sod_support_email_column') {
            echo esc_html((string)get_post_meta($post_id, 'sod_support_email', true) ?: '-');
        }
    }

    /**
     * Die Nachrichtenverlauf-Optik steckt in der ausgelieferten admin.css nicht in
     * jeder Version drin (Live liefert noch 1.3.5 ohne diese Regeln aus). Damit der
     * Verlauf auf JEDEM Bildschirm gleich aussieht, werden die Regeln hier einmal
     * pro Seitenaufruf mitgegeben - doppelt vorhandene, identische Regeln schaden
     * nicht, ein fehlendes Stylesheet dagegen schon.
     */
    private static function print_sod_thread_styles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        echo '<style>
            .sod-message-thread{margin:18px 0;max-width:640px}
            .sod-message-thread h3{margin:0 0 10px}
            .sod-message-list{display:flex;flex-direction:column;gap:10px;margin-bottom:14px;max-height:420px;overflow-y:auto;padding:4px 6px;border:1px solid #dcdcde;border-radius:8px}
            .sod-message{display:flex}
            .sod-message-in{justify-content:flex-start}
            .sod-message-out{justify-content:flex-end}
            .sod-message-bubble{max-width:82%;border-radius:12px;padding:10px 14px;border:1px solid #dcdcde}
            .sod-message-in .sod-message-bubble{background:#f6f7f7;border-bottom-left-radius:3px}
            .sod-message-out .sod-message-bubble{background:#eef6ff;border-color:#c3dcf5;border-bottom-right-radius:3px}
            .sod-message-meta{display:flex;align-items:baseline;gap:10px;font-size:12px;color:#646970;margin-bottom:4px}
            .sod-message-time{white-space:nowrap;color:#8a929a}
            .sod-message-body{white-space:pre-line;line-height:1.5}
        </style>';
    }

    // =====================================================================
    // Hilfe & Support (Button unten rechts auf jeder oeffentlichen Seite)
    // =====================================================================

    /**
     * FAQ-Inhalte zum richtigen Bezahlen einer Patenschaft. Bewusst hier im Plugin
     * gepflegt (nicht als WordPress-Seite), damit der Hilfe-Button auf JEDER Seite
     * denselben, immer aktuellen Stand zeigt - inkl. der echten Bankdaten aus
     * bank_details(), die sonst an zwei Stellen gepflegt werden muessten.
     */
    private static function support_faq_items(): array
    {
        $bank = self::bank_details();
        return [
            [
                'q' => self::t('support.faq_ways_q', 'Wie kann ich eine Patenschaft bezahlen?'),
                'a' => self::t('support.faq_ways_a', 'Es gibt zwei Wege: ein monatliches PayPal-Abo oder einen Dauerauftrag bei deiner eigenen Bank. Beides läuft monatlich und ist jederzeit kündbar. Du wählst den Weg direkt im Patenschafts-Formular aus.'),
            ],
            [
                'q' => self::t('support.faq_paypal_q', 'PayPal: Wie läuft das ab?'),
                'a' => self::t('support.faq_paypal_a', 'Nach dem Absenden des Formulars wirst du zu PayPal weitergeleitet und schließt dort ein monatliches Abo ab. Wichtig: Den Vorgang bei PayPal wirklich bis zum Schluss bestätigen. Brichst du bei PayPal ab, kommt keine Patenschaft zustande. Danach läuft die Zahlung automatisch – du musst nichts weiter tun.'),
            ],
            [
                'q' => self::t('support.faq_bank_q', 'Dauerauftrag: Was muss ich tun?'),
                'a' => self::tpl(
                    'support.faq_bank_a_tpl',
                    'Nach dem Formular bekommst du unsere Bankdaten und einen QR-Code angezeigt. Richte damit bei deiner Bank einen monatlichen Dauerauftrag ein (unbefristet). Klicke danach auf der Seite auf „Ich habe den Dauerauftrag eingerichtet". Empfänger: {name}, IBAN: {iban}, BIC: {bic}.',
                    ['name' => $bank['name'], 'iban' => $bank['iban'], 'bic' => $bank['bic']]
                ),
            ],
            [
                'q' => self::t('support.faq_reference_q', 'Was muss in den Verwendungszweck?'),
                'a' => self::t('support.faq_reference_a', 'Bitte immer „Patenschaft" plus den Namen des Hundes und deinen eigenen Namen angeben, zum Beispiel: „Patenschaft Luna – Maria Muster". Ohne diese Angabe können wir deine Zahlung nicht der richtigen Patenschaft zuordnen. Wenn du den QR-Code nutzt, ist der Verwendungszweck bereits richtig hinterlegt.'),
            ],
            [
                'q' => self::t('support.faq_certificate_q', 'Wann bekomme ich mein Patenschafts-Zertifikat?'),
                'a' => self::t('support.faq_certificate_a', 'Bei PayPal automatisch, sobald die erste Zahlung bestätigt ist. Beim Dauerauftrag, sobald die erste Überweisung bei uns eingegangen ist und wir sie geprüft haben – das kann ein paar Bankarbeitstage dauern. Das Zertifikat kommt per E-Mail.'),
            ],
            [
                'q' => self::t('support.faq_amount_q', 'Kann ich auch einen kleineren Betrag übernehmen?'),
                'a' => self::t('support.faq_amount_a', 'Ja. Neben der vollen Patenschaft gibt es die Teilpatenschaft ab 5 € im Monat. Wenn für einen Hund nur noch ein kleinerer Restbetrag offen ist, gilt genau dieser Restbetrag. Den Betrag wählst du im Formular aus.'),
            ],
            [
                'q' => self::t('support.faq_cancel_q', 'Wie kann ich die Patenschaft beenden?'),
                'a' => self::t('support.faq_cancel_a', 'Bei PayPal kündigst du das Abo direkt in deinem PayPal-Konto unter „Einstellungen → Zahlungen → Automatische Zahlungen". Beim Dauerauftrag löschst du ihn bei deiner Bank. Bitte gib uns in beiden Fällen kurz Bescheid, damit wir den Hund wieder für eine neue Patenschaft freigeben können.'),
            ],
            [
                'q' => self::t('support.faq_problem_q', 'Die Zahlung hat nicht funktioniert – was nun?'),
                'a' => self::t('support.faq_problem_a', 'Kein Problem, es geht nichts verloren. Melde dich einfach über das Formular unten oder über unser Kontaktformular. Schreib dazu, welchen Hund du unterstützen wolltest und welchen Zahlungsweg du gewählt hast – wir klären das gemeinsam.'),
            ],
        ];
    }

    private static function support_form_token(int $started_at): string
    {
        return hash_hmac('sha256', 'support:' . $started_at, wp_salt('auth'));
    }

    private static function support_token_ok(): bool
    {
        $nonce = (string)($_POST['sod_support_nonce'] ?? '');
        if ($nonce !== '' && wp_verify_nonce($nonce, 'sod_public_support')) {
            return true;
        }
        $started_at = (int)($_POST['started_at'] ?? 0);
        $token = (string)($_POST['form_token'] ?? '');
        if ($started_at <= 0 || $token === '' || abs(time() - $started_at) > 3 * DAY_IN_SECONDS) {
            return false;
        }
        return hash_equals(self::support_form_token($started_at), $token);
    }

    private static function support_rate_limit_ok(): bool
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return true;
        }
        $key = 'sod_support_rate_' . md5($ip);
        $count = (int)get_transient($key);
        if ($count >= 3) {
            return false;
        }
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    public static function output_support_widget(): void
    {
        if (is_admin() || isset($_GET['sod_staff_app']) || isset($_GET['sod_member']) || isset($_GET['sod_member_register'])) {
            return;
        }

        $started_at = time();
        $notice = sanitize_text_field((string)($_GET['sod_support'] ?? ''));
        $faq = self::support_faq_items();
        ?>
<div id="sod-support" data-sod-support-notice="<?php echo esc_attr($notice); ?>">
    <button type="button" id="sod-support-toggle" aria-expanded="false" aria-controls="sod-support-panel">
        <span aria-hidden="true">?</span>
        <span class="sod-support-toggle-text"><?php echo esc_html(self::t('support.button', 'Hilfe')); ?></span>
    </button>
    <div id="sod-support-panel" role="dialog" aria-modal="false" aria-labelledby="sod-support-title" hidden>
        <div class="sod-support-head">
            <strong id="sod-support-title"><?php echo esc_html(self::t('support.title', 'Hilfe & Support')); ?></strong>
            <button type="button" id="sod-support-close" aria-label="<?php echo esc_attr(self::t('support.close', 'Schließen')); ?>">&times;</button>
        </div>
        <div class="sod-support-body">
            <?php if ($notice === 'ok') : ?>
                <p class="sod-support-msg sod-support-msg-ok"><?php echo esc_html(self::t('support.sent_ok', 'Danke! Deine Meldung ist bei uns angekommen. Du bekommst gleich eine Bestätigung per E-Mail – wir melden uns so schnell wie möglich bei dir.')); ?></p>
            <?php elseif ($notice === 'error') : ?>
                <p class="sod-support-msg sod-support-msg-error"><?php echo esc_html(self::t('support.sent_error', 'Das hat leider nicht geklappt. Bitte prüfe deine E-Mail-Adresse und die Fehlerbeschreibung und versuche es noch einmal.')); ?></p>
            <?php elseif ($notice === 'file') : ?>
                <p class="sod-support-msg sod-support-msg-error"><?php echo esc_html(self::t('support.sent_file_error', 'Ein Bild konnte nicht verarbeitet werden. Erlaubt sind JPG, PNG oder WEBP bis 6 MB, maximal 3 Bilder.')); ?></p>
            <?php endif; ?>

            <h3 class="sod-support-h"><?php echo esc_html(self::t('support.faq_title', 'Patenschaft bezahlen – häufige Fragen')); ?></h3>
            <div class="sod-support-faq">
                <?php foreach ($faq as $i => $item) : ?>
                    <details<?php echo $i === 0 ? ' open' : ''; ?>>
                        <summary><?php echo esc_html($item['q']); ?></summary>
                        <p><?php echo esc_html($item['a']); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>

            <h3 class="sod-support-h"><?php echo esc_html(self::t('support.form_title', 'Technisches Problem melden')); ?></h3>
            <p class="sod-support-lead"><?php echo esc_html(self::t('support.form_lead', 'Etwas funktioniert nicht wie erwartet? Beschreib kurz, was passiert ist – gerne mit Screenshot. Die Meldung geht direkt an unsere technische Betreuung.')); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="sod-support-form">
                <input type="hidden" name="action" value="sod_support_request">
                <?php wp_nonce_field('sod_public_support', 'sod_support_nonce'); ?>
                <input type="hidden" name="started_at" value="<?php echo esc_attr((string)$started_at); ?>">
                <input type="hidden" name="form_token" value="<?php echo esc_attr(self::support_form_token($started_at)); ?>">
                <input type="hidden" name="page_url" value="">
                <p class="sod-support-hp" aria-hidden="true"><label><?php echo esc_html(self::t('support.hp', 'Bitte freilassen')); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>

                <label class="sod-support-label" for="sod-support-name"><?php echo esc_html(self::t('support.field_name', 'Name')); ?></label>
                <input class="sod-support-input" type="text" id="sod-support-name" name="name" maxlength="120" required>

                <label class="sod-support-label" for="sod-support-email"><?php echo esc_html(self::t('support.field_email', 'E-Mail-Adresse')); ?></label>
                <input class="sod-support-input" type="email" id="sod-support-email" name="email" maxlength="150" required>

                <label class="sod-support-label" for="sod-support-message"><?php echo esc_html(self::t('support.field_message', 'Was funktioniert nicht?')); ?></label>
                <textarea class="sod-support-input" id="sod-support-message" name="message" rows="4" maxlength="3000" required placeholder="<?php echo esc_attr(self::t('support.field_message_ph', 'Zum Beispiel: Auf welcher Seite tritt der Fehler auf, was hast du geklickt, was ist dann passiert?')); ?>"></textarea>

                <label class="sod-support-label" for="sod-support-files"><?php echo esc_html(self::t('support.field_files', 'Screenshots (optional, max. 3 Bilder)')); ?></label>
                <input class="sod-support-input sod-support-file" type="file" id="sod-support-files" name="screenshots[]" accept="image/jpeg,image/png,image/webp" multiple>
                <span class="sod-support-hint"><?php echo esc_html(self::t('support.field_files_hint', 'JPG, PNG oder WEBP, je bis 6 MB.')); ?></span>

                <label class="sod-support-consent"><input type="checkbox" name="consent" value="1" required> <span><?php echo self::privacy_consent_html(self::t('support.consent', 'Ich habe die Datenschutzerklärung gelesen und bin einverstanden, dass meine Angaben zur Bearbeitung dieser Meldung verarbeitet werden.')); ?></span></label>

                <button type="submit" class="sod-support-submit"><?php echo esc_html(self::t('support.submit', 'Meldung senden')); ?></button>
            </form>
        </div>
    </div>
</div>
<style>
    #sod-support { position:fixed; right:18px; bottom:18px; z-index:9990; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }
    #sod-support-toggle { display:flex; align-items:center; gap:8px; border:0; border-radius:999px; padding:12px 18px; background:#f3c74f; color:#0b1b29; font-weight:800; font-size:15px; cursor:pointer; box-shadow:0 10px 28px rgba(0,0,0,.35); }
    #sod-support-toggle:hover { background:#e4a91f; }
    #sod-support-toggle > span[aria-hidden] { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#204060; color:#fff; font-size:14px; font-weight:800; }
    #sod-support-panel { position:absolute; right:0; bottom:60px; width:min(400px,calc(100vw - 36px)); max-height:min(78vh,660px); overflow-y:auto; background:#07131b; color:#f8fafc; border:1px solid rgba(255,255,255,.1); border-radius:18px; box-shadow:0 24px 60px rgba(0,0,0,.5); }
    #sod-support-panel[hidden] { display:none; }
    .sod-support-head { position:sticky; top:0; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:16px 18px; background:#07131b; border-bottom:1px solid rgba(255,255,255,.1); }
    .sod-support-head strong { font-size:17px; }
    #sod-support-close { border:0; background:transparent; color:#94a3b8; font-size:24px; line-height:1; cursor:pointer; padding:0 4px; }
    .sod-support-body { padding:16px 18px 20px; }
    .sod-support-h { margin:18px 0 8px; font-size:14px; text-transform:uppercase; letter-spacing:.08em; color:#f3c74f; }
    .sod-support-h:first-of-type { margin-top:4px; }
    .sod-support-lead { margin:0 0 12px; font-size:13.5px; line-height:1.55; color:#94a3b8; }
    .sod-support-faq details { border-bottom:1px solid rgba(255,255,255,.08); padding:8px 0; }
    .sod-support-faq summary { cursor:pointer; font-size:14px; font-weight:600; line-height:1.45; list-style:none; padding-right:18px; position:relative; }
    .sod-support-faq summary::-webkit-details-marker { display:none; }
    .sod-support-faq summary::after { content:'+'; position:absolute; right:0; top:0; color:#f3c74f; font-weight:800; }
    .sod-support-faq details[open] summary::after { content:'–'; }
    .sod-support-faq p { margin:8px 0 4px; font-size:13.5px; line-height:1.6; color:#b6c4cf; }
    .sod-support-label { display:block; margin:12px 0 4px; font-size:13.5px; font-weight:600; }
    .sod-support-input { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid rgba(255,255,255,.12); border-radius:10px; background:#0c1d2b; color:#f8fafc; font-size:14px; font-family:inherit; }
    .sod-support-file { padding:8px 10px; font-size:13px; }
    .sod-support-hint { display:block; margin-top:4px; font-size:12px; color:#8194a3; }
    .sod-support-consent { display:flex; gap:9px; align-items:flex-start; margin:14px 0 0; font-size:12.5px; line-height:1.5; color:#94a3b8; }
    .sod-support-consent a { color:#86adc3; }
    .sod-support-submit { width:100%; margin-top:14px; border:0; border-radius:12px; padding:13px 16px; background:#f3c74f; color:#0b1b29; font-weight:800; font-size:15px; cursor:pointer; }
    .sod-support-submit:hover { background:#e4a91f; }
    .sod-support-msg { margin:0 0 12px; padding:10px 13px; border-radius:10px; font-size:13.5px; line-height:1.5; }
    .sod-support-msg-ok { background:rgba(34,197,94,.15); color:#86efac; }
    .sod-support-msg-error { background:rgba(220,38,38,.15); color:#fca5a5; }
    .sod-support-hp { position:absolute; left:-9999px; }
    @media (max-width:520px) {
        #sod-support { right:12px; bottom:12px; }
        .sod-support-toggle-text { display:none; }
        #sod-support-toggle { padding:14px; }
    }
    @media print { #sod-support { display:none; } }
</style>
<script>
(function () {
    var root = document.getElementById('sod-support');
    if (!root) { return; }
    var toggle = document.getElementById('sod-support-toggle');
    var panel = document.getElementById('sod-support-panel');
    var closeBtn = document.getElementById('sod-support-close');
    var pageField = root.querySelector('input[name="page_url"]');
    if (pageField) { pageField.value = window.location.href; }

    function open() { panel.hidden = false; toggle.setAttribute('aria-expanded', 'true'); }
    function close() { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }

    toggle.addEventListener('click', function () { panel.hidden ? open() : close(); });
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { close(); } });

    // Nach dem Absenden kommt der Besucher mit ?sod_support=... zurueck - das Panel
    // muss dann von selbst offen sein, sonst bliebe die Rueckmeldung unsichtbar.
    if (root.getAttribute('data-sod-support-notice')) { open(); }
})();
</script>
        <?php
    }

    public static function handle_support_request(): void
    {
        $redirect = wp_get_referer() ?: home_url('/');
        $fail = static function (string $reason) use ($redirect): void {
            wp_safe_redirect(add_query_arg('sod_support', $reason, $redirect) . '#sod-support');
            exit;
        };

        if (!self::support_token_ok()) {
            $fail('error');
        }
        if (!empty($_POST['website']) || (time() - (int)($_POST['started_at'] ?? 0)) < 3 || empty($_POST['consent'])) {
            $fail('error');
        }
        if (!self::support_rate_limit_ok()) {
            $fail('error');
        }

        $name = self::limited_text(sanitize_text_field((string)($_POST['name'] ?? '')), 120);
        $email = sanitize_email((string)($_POST['email'] ?? ''));
        $message = self::limited_text(sanitize_textarea_field((string)($_POST['message'] ?? '')), 3000);
        $page_url = esc_url_raw((string)($_POST['page_url'] ?? ''));
        if ($name === '' || $email === '' || $message === '') {
            $fail('error');
        }

        // Erst pruefen, dann anlegen - sonst blieben bei fehlerhaften Uploads
        // leere Support-Tickets im Backend zurueck.
        [$validated, $upload_failed] = self::support_validate_screenshots();
        if ($upload_failed) {
            $fail('file');
        }

        $ticket_id = wp_insert_post([
            'post_type' => 'sod_support',
            'post_status' => 'private',
            'post_title' => 'Support-Meldung von ' . $name,
            'post_content' => $message,
        ]);
        if (!$ticket_id || is_wp_error($ticket_id)) {
            $fail('error');
        }
        $ticket_id = (int)$ticket_id;

        update_post_meta($ticket_id, 'sod_support_name', $name);
        update_post_meta($ticket_id, 'sod_support_email', $email);
        update_post_meta($ticket_id, 'sod_support_message', $message);
        update_post_meta($ticket_id, 'sod_support_page', $page_url);
        update_post_meta($ticket_id, 'sod_support_status', 'offen');

        $stored = self::support_persist_screenshots($ticket_id, $validated);
        if ($stored) {
            update_post_meta($ticket_id, 'sod_support_screenshots', $stored);
        }
        $attachments = [];
        foreach ($stored as $file) {
            $path = self::support_screenshot_path($ticket_id, (string)$file);
            if ($path !== '' && is_readable($path)) {
                $attachments[] = $path;
            }
        }

        $edit_link = get_edit_post_link($ticket_id, 'raw') ?: admin_url('edit.php?post_type=sod_support');
        $lines = [
            'Neue Support-Meldung von der Shield-of-Dogs-Website',
            '',
            'Name:    ' . $name,
            'E-Mail:  ' . $email,
            'Seite:   ' . ($page_url !== '' ? $page_url : '(nicht übermittelt)'),
            'Zeit:    ' . date_i18n('d.m.Y H:i'),
            'Bilder:  ' . count($attachments),
            '',
            'Beschreibung:',
            $message,
            '',
            'Im Backend ansehen und antworten:',
            $edit_link,
        ];

        wp_mail(
            self::org()['support_email'],
            'SOD Support: Technisches Problem von ' . $name . self::support_ticket_tag($ticket_id),
            implode("
", $lines),
            array_merge(
                ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>'],
                self::mail_headers()
            ),
            $attachments
        );

        wp_mail(
            $email,
            'Wir haben deine Meldung erhalten – ' . self::org()['name'] . self::support_ticket_tag($ticket_id),
            self::support_confirmation_email_html($name, $message),
            array_merge(['Content-Type: text/html; charset=UTF-8'], self::application_mail_headers())
        );

        wp_safe_redirect(add_query_arg('sod_support', 'ok', $redirect) . '#sod-support');
        exit;
    }

    private static function support_confirmation_email_html(string $name, string $message): string
    {
        $logo_url = get_theme_file_uri('assets/images/logo.png');
        ob_start();
        ?>
        <!doctype html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <title>Wir haben deine Meldung erhalten</title>
            <style>
                body{font-family:Arial,sans-serif;color:#172b36;line-height:1.6;margin:0;background:#f3f5f6}
                .page{max-width:560px;margin:0 auto;background:#fff;padding:28px 30px;box-sizing:border-box}
                .head{display:flex;align-items:center;gap:14px;border-bottom:3px solid #f3c74f;padding-bottom:16px;margin-bottom:22px}
                .logo{width:56px;height:56px;object-fit:contain;flex:0 0 auto}
                .brand{font-weight:700;color:#204060;font-size:15px}
                .brand small{display:block;font-weight:400;color:#68808c;font-size:12px}
                h1{font-size:20px;margin:0 0 16px;color:#204060}
                p{margin:0 0 14px}
                .quote{white-space:pre-line;background:#f7f9fa;border-left:3px solid #f3c74f;padding:12px 14px;margin:0 0 18px;font-size:14px;color:#445a68}
                .foot{margin-top:26px;padding-top:14px;border-top:1px solid #e4e9eb;font-size:12px;color:#8a9aa3}
            </style>
        </head>
        <body>
            <div class="page">
                <div class="head">
                    <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(self::org()['name']); ?>">
                    <div class="brand"><?php echo esc_html(self::org()['name']); ?></div>
                </div>
                <h1>Danke für deine Meldung</h1>
                <p>Hallo <?php echo esc_html($name); ?>,</p>
                <p>wir haben deine Meldung zu einem technischen Problem erhalten. Wir schauen uns das an und melden uns so schnell wie möglich bei dir.</p>
                <p><strong>Das hast du uns geschrieben:</strong></p>
                <div class="quote"><?php echo nl2br(esc_html($message)); ?></div>
                <p>Du musst nichts weiter tun – wir kommen auf dich zu.</p>
                <p>Herzliche Grüße<br><?php echo esc_html(self::org()['name']); ?></p>
                <div class="foot">Diese E-Mail bestätigt nur den Eingang deiner Meldung. Du kannst direkt darauf antworten, wenn dir noch etwas einfällt.</div>
            </div>
        </body>
        </html>
        <?php
        return (string)ob_get_clean();
    }

    // =====================================================================
    // Hunde-Updates (News/Foto je Hund - Grundlage für Paten-News-Feed/Galerie)
    // =====================================================================

    public static function dog_update_meta_box(WP_Post $post): void
    {
        wp_nonce_field('sod_save_dog_update', 'sod_dog_update_nonce');
        $dog_id = (int)get_post_meta($post->ID, 'sod_dog_update_dog', true);
        if ($dog_id <= 0) {
            $dog_id = (int)self::prefill_dog_id_param();
        }
        $body = (string)get_post_meta($post->ID, 'sod_dog_update_body', true);
        $photo_id = (int)get_post_meta($post->ID, 'sod_dog_update_photo_id', true);
        $video_url = (string)get_post_meta($post->ID, 'sod_dog_update_video_url', true);
        $dogs = get_posts([
            'post_type' => 'sod_dog',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);
        ?>
        <div class="sod-admin-grid">
            <p class="sod-admin-field">
                <label for="sod_dog_update_dog"><strong>Hund</strong></label><br>
                <select name="sod_dog_update_dog" id="sod_dog_update_dog" required>
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($dogs as $dog) : ?>
                        <option value="<?php echo esc_attr((string)$dog->ID); ?>" <?php selected($dog_id, $dog->ID); ?>><?php echo esc_html(self::dog_public_name($dog->ID)); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="sod-admin-field">
                <label for="sod_dog_update_body"><strong>Text</strong></label><br>
                <textarea name="sod_dog_update_body" id="sod_dog_update_body" rows="4" style="width:100%;max-width:640px;"><?php echo esc_textarea($body); ?></textarea><br>
                <span class="description">Erscheint im News-Feed des zugehörigen Paten. Kann leer bleiben, wenn nur ein Foto ergänzt wird.</span>
            </p>
            <?php self::sponsor_image_picker('sod_dog_update_photo_id', 'Foto (optional)', $photo_id, 0); ?>
            <p class="sod-admin-field">
                <label for="sod_dog_update_video_url"><strong>Video (optional)</strong></label><br>
                <input type="url" name="sod_dog_update_video_url" id="sod_dog_update_video_url" value="<?php echo esc_attr($video_url); ?>" placeholder="https://... (MP4-Link oder YouTube/Vimeo)" style="width:100%;max-width:640px;"><br>
                <span class="description">Direkter Videolink (MP4/WebM) oder YouTube-/Vimeo-Link. Erscheint im News-Feed des Paten, bei externen Anbietern erst nach dessen Einwilligung.</span>
            </p>
        </div>
        <?php
    }

    public static function save_dog_update(int $post_id, WP_Post $post): void
    {
        if (!isset($_POST['sod_dog_update_nonce']) || !wp_verify_nonce((string)$_POST['sod_dog_update_nonce'], 'sod_save_dog_update')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_sod_dog_update', $post_id)) {
            return;
        }

        $dog_id = absint($_POST['sod_dog_update_dog'] ?? 0);
        if ($dog_id > 0 && get_post_type($dog_id) === 'sod_dog') {
            update_post_meta($post_id, 'sod_dog_update_dog', $dog_id);
        } else {
            delete_post_meta($post_id, 'sod_dog_update_dog');
        }

        update_post_meta($post_id, 'sod_dog_update_body', wp_kses_post((string)($_POST['sod_dog_update_body'] ?? '')));

        $photo_id = absint($_POST['sod_dog_update_photo_id'] ?? 0);
        if ($photo_id > 0) {
            update_post_meta($post_id, 'sod_dog_update_photo_id', $photo_id);
        } else {
            delete_post_meta($post_id, 'sod_dog_update_photo_id');
        }

        $video_url = esc_url_raw(trim((string)($_POST['sod_dog_update_video_url'] ?? '')));
        if ($video_url !== '') {
            update_post_meta($post_id, 'sod_dog_update_video_url', $video_url);
        } else {
            delete_post_meta($post_id, 'sod_dog_update_video_url');
        }

        if (trim((string)get_post_meta($post_id, 'sod_dog_update_published_at', true)) === '') {
            $created = (int)get_post_time('U', true, $post_id);
            update_post_meta($post_id, 'sod_dog_update_published_at', gmdate('c', $created > 0 ? $created : time()));
        }
    }

    // =====================================================================
    // Mitgliederbereich (Login-Konten für Paten)
    // =====================================================================

    public static function member_area_router(): void
    {
        if (isset($_GET['sod_member_verify'])) {
            self::render_member_verify();
            exit;
        }
        if (isset($_GET['sod_member_delete'])) {
            self::render_member_delete_confirm();
            exit;
        }
        if (isset($_GET['sod_member_register'])) {
            self::render_member_register();
            exit;
        }
        if (isset($_GET['sod_member'])) {
            self::render_member_dashboard();
            exit;
        }
    }

    private static function member_area_url(): string
    {
        return home_url('/?sod_member=1');
    }

    private static function member_register_url(): string
    {
        return home_url('/?sod_member_register=1');
    }

    private static function member_verify_url(int $user_id, string $token): string
    {
        return add_query_arg(
            ['sod_member_verify' => '1', 'uid' => $user_id, 'token' => $token],
            home_url('/')
        );
    }

    private static function member_delete_url(): string
    {
        return home_url('/?sod_member_delete=1');
    }

    /**
     * Selbstständige, minimalistische HTML-Seite für den Mitgliederbereich - analog zur
     * bestehenden Mitarbeiter-App (render_staff_app()) bewusst plugin-eigen statt als
     * Theme-Template, damit für Registrierung/Login/Dashboard/Konto-Löschung ausschließlich
     * diese eine Plugin-Datei gepflegt werden muss.
     */
    private static function render_member_shell(string $title, callable $content): void
    {
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#204060">
    <title><?php echo esc_html($title); ?></title>
    <style>
        :root {
            color-scheme: dark;
            --blue:#204060; --yellow:#f3c74f; --yellow-dark:#e4a91f;
            --ink:#f8fafc; --muted:#94a3b8; --line:rgba(255,255,255,.08);
            --soft:#07131b; --page-bg:#03090d; --input-bg:#0c1d2b; --link:#86adc3;
        }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; background:var(--page-bg); color:var(--ink); }
        a { color:var(--link); }
        .app { width:min(640px,100%); margin:0 auto; padding: 28px 16px 40px; }
        h1 { margin:0; font-size:clamp(22px,6vw,32px); color:var(--ink); }
        .logo-row { display:flex; align-items:center; gap:12px; margin-bottom:6px; }
        .home-link { display:inline-flex; align-items:center; gap:6px; margin-bottom:18px; color:var(--muted); font-size:13.5px; text-decoration:none; }
        .home-link:hover { color:var(--link); }
        .notice, .card { padding:22px; border:1px solid var(--line); border-radius:18px; background:var(--soft); box-shadow:0 12px 30px rgba(0,0,0,.35); margin-bottom:16px; }
        .notice h2, .card h2 { margin:0 0 10px; font-size:21px; color:var(--ink); }
        .notice p, .card p { margin:0 0 14px; color:var(--muted); line-height:1.5; }
        .thanks { border-left:3px solid var(--yellow); }
        .thanks p { color:var(--ink); font-size:15.5px; line-height:1.65; font-style:italic; }
        .thanks .signature { margin-top:10px; color:var(--yellow); font-weight:700; font-style:normal; font-size:14px; }
        label { font-weight:600; font-size:14px; color:var(--ink); }
        input[type=text], input[type=email], input[type=password], textarea { width:100%; padding:11px 12px; border:1px solid var(--line); border-radius:10px; font-size:15px; margin-top:4px; background:var(--input-bg); color:var(--ink); font-family:inherit; }
        .consent label { font-weight:400; font-size:13.5px; display:flex; gap:8px; align-items:flex-start; color:var(--muted); }
        .cta, .cta-danger, .cta-small { display:inline-flex; justify-content:center; align-items:center; min-height:48px; border:0; border-radius:12px; padding:12px 20px; background:var(--yellow); color:#0b1b29; text-decoration:none; font-weight:800; cursor:pointer; font-size:15px; margin-top:8px; }
        .cta:hover, .cta-small:hover { background:var(--yellow-dark); }
        .cta-danger { background:#7a1f1f; color:#fca5a5; }
        .cta-small { min-height:36px; padding:8px 14px; font-size:13.5px; margin-top:6px; }
        .msg { padding:10px 14px; border-radius:10px; font-size:14px; margin:0 0 14px; }
        .msg-error { background:rgba(220,38,38,.15); color:#fca5a5; }
        .msg-success { background:rgba(34,197,94,.15); color:#86efac; }
        .foot-link { font-size:14px; margin-top:6px; color:var(--muted); }
        .foot-link a { color:var(--link); }
        .sod-hp { position:absolute; left:-9999px; }
        .sod-comment-list { margin:10px 0; padding:0; list-style:none; }
        .sod-comment-list li { padding:8px 0; border-top:1px solid var(--line); font-size:13.5px; color:var(--muted); }
        .sod-comment-list strong { color:var(--ink); }
        .sod-member-update-video { width:100%; height:auto; display:block; border-radius:12px; }
        .sod-video-consent { padding:16px; border:1px dashed var(--line); border-radius:12px; text-align:center; margin-bottom:10px; }
        /* Eingebettete Inhalte externer Anbieter (z.B. Instagram/TikTok-oEmbeds) bringen oft
           eine eigene feste Mindestbreite mit - ohne diese Absicherung koennte das die ganze
           Seite horizontal ueberlaufen lassen statt nur das Embed selbst umzubrechen. */
        .card, .notice { overflow-x:auto; }
        .card iframe, .card blockquote, .card embed, .card object, .sod-video-embed > * { max-width:100%; }
        .sod-video-consent p { color:var(--muted); font-size:13.5px; }
    </style>
</head>
<body>
    <main class="app">
        <div class="logo-row">
            <img src="<?php echo esc_url(self::staff_icon_url('192')); ?>" alt="" width="40" height="40" style="border-radius:10px;">
            <h1><?php echo esc_html(self::org()['name']); ?></h1>
        </div>
        <a class="home-link" href="<?php echo esc_url(home_url('/')); ?>">← Zur Startseite</a>
        <?php $content(); ?>
    </main>
    <script>
        document.addEventListener('click', function (event) {
            var consentButton = event.target.closest('.sod-video-consent-load');
            if (!consentButton) return;
            var gate = consentButton.closest('.sod-video-consent');
            var template = gate ? gate.querySelector('.sod-video-embed-template') : null;
            if (gate && template) {
                gate.replaceChildren(template.content.cloneNode(true));
                gate.classList.remove('sod-video-consent');
                gate.classList.add('sod-video-embed');
            }
        });
    </script>
</body>
</html>
        <?php
    }

    private static function unique_member_login(string $email): string
    {
        $base = sanitize_user((string)current(explode('@', $email)), true);
        if ($base === '') {
            $base = 'pate';
        }
        $login = $base;
        $i = 1;
        while (username_exists($login)) {
            $i++;
            $login = $base . $i;
        }
        return $login;
    }

    private static function find_sponsor_by_email(string $email): int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 0;
        }
        $ids = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => 5,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => 'sod_sponsor_email', 'value' => $email, 'compare' => '='],
            ],
        ]);
        if ($ids) {
            return (int)$ids[0];
        }
        // Fallback: manche aeltere Eintraege koennen abweichende Gross-/Kleinschreibung
        // haben, die der exakte DB-Vergleich oben nicht faende.
        $all = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => 500,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        foreach ($all as $id) {
            if (strtolower(trim((string)get_post_meta((int)$id, 'sod_sponsor_email', true))) === $email) {
                return (int)$id;
            }
        }
        return 0;
    }

    private static function member_linked_sponsor_id(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }
        $ids = get_posts([
            'post_type' => 'sod_sponsor',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => 'sod_sponsor_user_id', 'value' => (string)$user_id, 'compare' => '='],
            ],
        ]);
        return $ids ? (int)$ids[0] : 0;
    }

    private static function send_member_verification_email(int $user_id, string $email, string $name): void
    {
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'sod_member_verify_token', $token);
        update_user_meta($user_id, 'sod_member_verify_expires', time() + self::MEMBER_VERIFY_TTL_HOURS * HOUR_IN_SECONDS);
        delete_user_meta($user_id, 'sod_member_verified_at');

        $link = self::member_verify_url($user_id, $token);
        $subject = self::org()['name'] . ' - Bitte E-Mail-Adresse bestätigen';
        $body = "Hallo " . $name . ",\n\n"
            . "bitte bestätigen Sie Ihre E-Mail-Adresse für den Shield-of-Dogs-Mitgliederbereich über diesen Link:\n"
            . $link . "\n\n"
            . "Der Link ist " . self::MEMBER_VERIFY_TTL_HOURS . " Stunden gültig.\n\n"
            . "Falls Sie sich nicht selbst registriert haben, können Sie diese E-Mail einfach ignorieren.\n\n"
            . self::org()['name'];
        wp_mail($email, $subject, $body);
    }

    private static function render_member_register(): void
    {
        nocache_headers();
        if (is_user_logged_in()) {
            wp_safe_redirect(self::member_area_url());
            exit;
        }
        $notice = sanitize_text_field((string)($_GET['sod_notice'] ?? ''));
        $messages = [
            'member_no_sponsor_match' => ['error', 'Wir konnten diese E-Mail-Adresse keiner bestehenden Patenschaft zuordnen. Falls Sie bereits Pate/Patin sind, kontaktieren Sie uns bitte über das Kontaktformular.'],
            'member_already_registered' => ['error', 'Für diese E-Mail-Adresse besteht bereits ein Konto. Bitte loggen Sie sich ein oder nutzen Sie „Passwort vergessen“.'],
            'member_check_email' => ['success', 'Fast geschafft: Bitte bestätigen Sie Ihre E-Mail-Adresse über den Link, den wir Ihnen gerade geschickt haben.'],
            'member_spam' => ['error', 'Die Registrierung konnte nicht verarbeitet werden. Bitte prüfen Sie Ihre Eingaben und versuchen Sie es erneut.'],
        ];
        [$notice_type, $notice_text] = $messages[$notice] ?? [null, null];
        self::render_member_shell('Mitgliederbereich - Registrierung', function () use ($notice_type, $notice_text): void {
            ?>
            <section class="notice">
                <h2>Als Pate/Patin registrieren</h2>
                <p>Die Registrierung ist für Personen gedacht, die bereits eine Patenschaft bei <?php echo esc_html(self::org()['name']); ?> haben. Bitte verwenden Sie dieselbe E-Mail-Adresse wie bei Ihrer Patenschaft.</p>
                <?php if ($notice_text) : ?>
                    <p class="msg msg-<?php echo esc_attr((string)$notice_type); ?>"><?php echo esc_html($notice_text); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sod_member_register">
                    <input type="hidden" name="started_at" value="<?php echo esc_attr((string)time()); ?>">
                    <p class="sod-hp"><label>Bitte freilassen<input type="text" name="website" value="" autocomplete="off" tabindex="-1"></label></p>
                    <p><label for="mreg_name">Name</label><br><input type="text" id="mreg_name" name="name" required maxlength="120"></p>
                    <p><label for="mreg_email">E-Mail-Adresse</label><br><input type="email" id="mreg_email" name="email" required maxlength="150"></p>
                    <p><label for="mreg_pw">Passwort</label><br><input type="password" id="mreg_pw" name="password" required minlength="8"></p>
                    <p><label for="mreg_pw2">Passwort wiederholen</label><br><input type="password" id="mreg_pw2" name="password2" required minlength="8"></p>
                    <p class="consent"><label><input type="checkbox" name="consent" value="1" required> Ich habe die <a href="<?php echo esc_url(self::page_url('datenschutz')); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und bin mit der Verarbeitung meiner Daten für den Mitgliederbereich einverstanden.</label></p>
                    <button type="submit" class="cta">Registrieren</button>
                </form>
                <p class="foot-link"><a href="<?php echo esc_url(wp_login_url(self::member_area_url())); ?>">Bereits registriert? Einloggen</a></p>
            </section>
            <?php
        });
    }

    public static function handle_member_register(): void
    {
        $redirect = self::member_register_url();

        if (!empty($_POST['website']) || (time() - (int)($_POST['started_at'] ?? 0)) < 3 || empty($_POST['consent'])) {
            wp_safe_redirect(add_query_arg('sod_notice', 'member_spam', $redirect));
            exit;
        }

        $name = self::limited_text(sanitize_text_field((string)($_POST['name'] ?? '')), 120);
        $email = sanitize_email((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');
        if ($name === '' || $email === '' || strlen($password) < 8 || $password !== $password2) {
            wp_safe_redirect(add_query_arg('sod_notice', 'member_spam', $redirect));
            exit;
        }

        if (email_exists($email)) {
            wp_safe_redirect(add_query_arg('sod_notice', 'member_already_registered', $redirect));
            exit;
        }

        $sponsor_id = self::find_sponsor_by_email($email);
        if ($sponsor_id <= 0) {
            wp_safe_redirect(add_query_arg('sod_notice', 'member_no_sponsor_match', $redirect));
            exit;
        }

        $user_id = wp_insert_user([
            'user_login' => self::unique_member_login($email),
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $name,
            'nickname' => $name,
            'role' => 'sod_member',
        ]);
        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('sod_notice', 'member_spam', $redirect));
            exit;
        }
        $user_id = (int)$user_id;

        update_post_meta($sponsor_id, 'sod_sponsor_user_id', $user_id);
        update_user_meta($user_id, 'sod_member_consent_at', gmdate('c'));
        update_user_meta($user_id, 'sod_member_consent_version', self::PRIVACY_NOTICE_VERSION);
        update_user_meta($user_id, 'sod_member_consent_source', 'member_registration');

        self::send_member_verification_email($user_id, $email, $name);

        wp_safe_redirect(add_query_arg('sod_notice', 'member_check_email', $redirect));
        exit;
    }

    public static function handle_member_resend_verify(): void
    {
        $email = sanitize_email((string)($_POST['email'] ?? ''));
        $user = $email !== '' ? get_user_by('email', $email) : false;
        if ($user instanceof WP_User && in_array('sod_member', $user->roles, true) && trim((string)get_user_meta($user->ID, 'sod_member_verified_at', true)) === '') {
            self::send_member_verification_email($user->ID, $email, $user->display_name);
        }
        // Bewusst immer dieselbe Meldung, egal ob die Adresse existiert - verhindert, dass
        // sich über dieses Formular herausfinden lässt, welche E-Mail-Adressen registriert sind.
        wp_safe_redirect(add_query_arg('sod_notice', 'member_check_email', self::member_register_url()));
        exit;
    }

    private static function render_member_verify(): void
    {
        nocache_headers();
        $uid = absint($_GET['uid'] ?? 0);
        $token = sanitize_text_field((string)($_GET['token'] ?? ''));
        $user = $uid > 0 ? get_user_by('id', $uid) : false;
        $stored_token = $user instanceof WP_User ? (string)get_user_meta($uid, 'sod_member_verify_token', true) : '';
        $expires = $user instanceof WP_User ? (int)get_user_meta($uid, 'sod_member_verify_expires', true) : 0;
        $valid = $user instanceof WP_User && $token !== '' && $stored_token !== '' && hash_equals($stored_token, $token) && $expires > time();

        if ($valid) {
            update_user_meta($uid, 'sod_member_verified_at', gmdate('c'));
            delete_user_meta($uid, 'sod_member_verify_token');
            delete_user_meta($uid, 'sod_member_verify_expires');
            wp_set_current_user($uid);
            wp_set_auth_cookie($uid);
            wp_safe_redirect(self::member_area_url());
            exit;
        }

        self::render_member_shell('Mitgliederbereich - Bestätigung', function () use ($user): void {
            ?>
            <section class="notice">
                <h2>Link ungültig oder abgelaufen</h2>
                <p>Dieser Bestätigungslink funktioniert leider nicht mehr. Sie können sich unten eine neue Bestätigungs-E-Mail zusenden lassen.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sod_member_resend_verify">
                    <p><label for="rv_email">E-Mail-Adresse</label><br><input type="email" id="rv_email" name="email" required maxlength="150" value="<?php echo esc_attr($user instanceof WP_User ? $user->user_email : ''); ?>"></p>
                    <button type="submit" class="cta">Neue Bestätigungs-E-Mail senden</button>
                </form>
            </section>
            <?php
        });
    }

    /**
     * Blockiert den Login fuer sod_member-Konten, deren Double-Opt-In noch nicht
     * abgeschlossen ist - native WP-Vorgehensweise ueber den wp_authenticate_user-Filter,
     * keine eigene Session-/Cookie-Logik.
     */
    public static function block_unverified_member_login($user, string $password)
    {
        if (is_wp_error($user) || !($user instanceof WP_User) || !in_array('sod_member', $user->roles, true)) {
            return $user;
        }
        if (trim((string)get_user_meta($user->ID, 'sod_member_verified_at', true)) === '') {
            return new WP_Error('sod_member_unverified', '<strong>Fehler:</strong> Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse über den Link aus unserer Bestätigungs-E-Mail.');
        }
        return $user;
    }

    private static function render_member_dashboard(): void
    {
        nocache_headers();
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(self::member_area_url()));
            exit;
        }
        $user = wp_get_current_user();
        if (!in_array('sod_member', $user->roles, true) && !current_user_can('manage_options')) {
            self::render_member_shell('Mitgliederbereich', function (): void {
                ?>
                <section class="notice">
                    <h2>Kein Mitgliederzugang</h2>
                    <p>Für dieses Konto ist kein Mitgliederbereich freigeschaltet.</p>
                </section>
                <?php
            });
            return;
        }
        $sponsor = self::member_linked_sponsor($user->ID);
        $dog_id = $sponsor instanceof WP_Post ? (int)get_post_meta($sponsor->ID, 'sod_sponsor_dog', true) : 0;
        $feed_page = max(1, absint($_GET['feed_page'] ?? 1));
        $delete_url = self::member_delete_url();

        self::render_member_shell('Mitgliederbereich', function () use ($user, $sponsor, $dog_id, $feed_page, $delete_url): void {
            ?>
            <section class="card">
                <h2>Willkommen, <?php echo esc_html($user->display_name); ?></h2>
            </section>
            <?php
            self::render_member_thank_you($dog_id);
            self::render_member_sponsorship_card($sponsor, $dog_id);
            self::render_member_extra_donation($dog_id);
            self::render_member_teaming_promo();
            if ($dog_id > 0) {
                self::render_member_news_feed($dog_id, $feed_page);
            }
            self::render_member_rank($sponsor);
            ?>
            <section class="card">
                <a href="<?php echo esc_url($delete_url); ?>" style="color:var(--muted);text-decoration:underline;font-size:13.5px;">Konto löschen</a>
            </section>
            <p class="foot-link"><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Abmelden</a></p>
            <?php
        });
    }

    private static function member_linked_sponsor(int $user_id): ?WP_Post
    {
        $sponsor_id = self::member_linked_sponsor_id($user_id);
        if ($sponsor_id <= 0) {
            return null;
        }
        $sponsor = get_post($sponsor_id);
        return $sponsor instanceof WP_Post ? $sponsor : null;
    }

    private static function dog_updates_query(int $dog_id, int $paged, bool $only_with_photo): WP_Query
    {
        $meta_query = [
            ['key' => 'sod_dog_update_dog', 'value' => (string)$dog_id, 'compare' => '='],
        ];
        if ($only_with_photo) {
            $meta_query[] = ['key' => 'sod_dog_update_photo_id', 'value' => '', 'compare' => '!='];
        }
        return new WP_Query([
            'post_type' => 'sod_dog_update',
            'post_status' => 'any',
            'posts_per_page' => $only_with_photo ? 12 : 10,
            'paged' => max(1, $paged),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $meta_query,
        ]);
    }

    /**
     * Persoenliche Danksagung der vertretungsberechtigten Person oben im
     * Dashboard, mit dem Namen des Patenhundes - bewusst warm/emotional formuliert, im
     * gleichen Ton wie das Zitat auf der Startseite.
     */
    private static function render_member_thank_you(int $dog_id): void
    {
        $dog_name = $dog_id > 0 ? self::dog_public_name($dog_id) : '';
        ?>
        <section class="card thanks">
            <?php if ($dog_name !== '') : ?>
                <p>„Ich sehe jeden Tag, wie viel Kraft es braucht, einem Hund wie <?php echo esc_html($dog_name); ?> ein sicheres Leben zu geben — und ich sehe auch, was Ihre Unterstützung dabei wirklich bewirkt. <?php echo esc_html($dog_name); ?> hat heute Futter, einen sicheren Platz und jemanden, der nicht wegschaut. Das ist kein Zufall, das sind Sie. Von ganzem Herzen: Danke, dass Sie an <?php echo esc_html($dog_name); ?>s Seite stehen.“</p>
            <?php else : ?>
                <p>„Jeden Tag sehe ich, was aus Mitgefühl werden kann, wenn jemand nicht wegschaut. Menschen wie Sie machen genau das möglich — für Hunde, die sich ihr Schicksal nie ausgesucht haben. Von ganzem Herzen: Danke.“</p>
            <?php endif; ?>
            <p class="signature">— <?php echo esc_html(self::org()['chairperson'] !== '' ? self::org()['chairperson'] : self::org()['name']); ?></p>
        </section>
        <?php
    }

    private static function render_member_sponsorship_card(?WP_Post $sponsor, int $dog_id): void
    {
        if (!$sponsor) {
            ?>
            <section class="notice">
                <h2>Noch keine Patenschaft verknüpft</h2>
                <p>Für Ihr Konto ist aktuell keine Patenschaft hinterlegt. Falls das nicht stimmt, kontaktieren Sie uns bitte über das Kontaktformular.</p>
            </section>
            <?php
            return;
        }
        $status = (string)get_post_meta($sponsor->ID, 'sod_sponsor_status', true);
        $status_labels = self::sponsor_status_options();
        $amount = (string)get_post_meta($sponsor->ID, 'sod_sponsor_amount', true);
        $interval = (string)get_post_meta($sponsor->ID, 'sod_sponsor_interval', true);
        $interval_labels = self::sponsor_interval_options();
        $since_raw = (string)get_post_meta($sponsor->ID, 'sod_sponsor_since', true);
        $since = $since_raw !== '' ? $since_raw : get_the_date('d.m.Y', $sponsor->ID);
        $dog_name = $dog_id > 0 ? self::dog_public_name($dog_id) : '';
        $dog_photo = $dog_id > 0 ? get_the_post_thumbnail($dog_id, 'medium') : '';
        ?>
        <section class="card">
            <h2>Ihre Patenschaft<?php echo $dog_name !== '' ? ' für ' . esc_html($dog_name) : ''; ?></h2>
            <?php if ($dog_photo) : ?>
                <div style="border-radius:14px;overflow:hidden;margin-bottom:12px;"><?php echo $dog_photo; ?></div>
            <?php endif; ?>
            <p>
                Status: <strong><?php echo esc_html($status_labels[$status] ?? 'Unbekannt'); ?></strong><br>
                <?php if ($amount !== '') : ?>
                    Betrag: <strong><?php echo esc_html($amount); ?> € <?php echo esc_html($interval_labels[$interval] ?? $interval); ?></strong><br>
                <?php endif; ?>
                Pate seit: <strong><?php echo esc_html($since); ?></strong>
            </p>
        </section>
        <?php
    }

    /**
     * Einmalspende zusaetzlich zur laufenden Patenschaft - nutzt bewusst dieselbe PayPal-
     * Einmalzahlung-Infrastruktur wie die oeffentliche Spendenseite (donation_options_shortcode(),
     * gleiche sod_paypal_email-Einstellung, gleicher IPN-Handler handle_paypal_ipn), damit die
     * Zahlung ganz normal ueber die bestehende Finanz-/Bestaetigungs-Logik verbucht wird.
     */
    private static function render_member_extra_donation(int $dog_id): void
    {
        $paypal_email = (string)get_option('sod_paypal_email', '');
        if ($paypal_email === '') {
            return;
        }
        $dog_name = $dog_id > 0 ? self::dog_public_name($dog_id) : self::org()['name'];
        $ipn_url = admin_url('admin-post.php?action=sod_paypal_ipn');
        ?>
        <section class="card">
            <h2>Zusätzlich spenden</h2>
            <p><?php echo esc_html($dog_name); ?> und die anderen Hunde freuen sich über jede zusätzliche Hilfe — für Futter, Tierarztkosten oder einen sicheren Platz, ganz unabhängig von Ihrer laufenden Patenschaft.</p>
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                <input type="hidden" name="cmd" value="_xclick">
                <input type="hidden" name="business" value="<?php echo esc_attr($paypal_email); ?>">
                <input type="hidden" name="item_name" value="<?php echo esc_attr('Einmalspende von Pate/Patin für ' . $dog_name); ?>">
                <input type="hidden" name="currency_code" value="EUR">
                <input type="hidden" name="no_shipping" value="1">
                <input type="hidden" name="no_note" value="1">
                <input type="hidden" name="charset" value="UTF-8">
                <input type="hidden" name="notify_url" value="<?php echo esc_attr($ipn_url); ?>">
                <label for="sod_member_extra_amount">Betrag (€)</label>
                <input type="number" min="1" step="1" name="amount" id="sod_member_extra_amount" value="20" required>
                <button type="submit" class="cta">Jetzt mit PayPal spenden</button>
            </form>
        </section>
        <?php
    }

    private static function render_member_teaming_promo(): void
    {
        $members = self::teaming_members();
        $logo_url = plugin_dir_url(__FILE__) . 'assets/images/teaming-logo.png';
        ?>
        <section class="card" style="text-align:center;">
            <img src="<?php echo esc_url($logo_url); ?>" alt="Teaming" style="height:32px;width:auto;margin-bottom:12px;">
            <h2>Noch mehr bewirken mit Teaming</h2>
            <p>Bei Teaming teilen sich viele Menschen 1 € im Monat — für niemanden spürbar, aber zusammen eine große, verlässliche Hilfe für die Hunde.<?php echo $members > 0 ? ' Schon ' . esc_html((string)$members) . ' Mitglieder machen mit.' : ''; ?></p>
            <a class="cta" href="<?php echo esc_url(self::org()['donation_url']); ?>" target="_blank" rel="noopener">Bei Teaming mitmachen</a>
        </section>
        <?php
    }

    private static function render_member_news_feed(int $dog_id, int $paged): void
    {
        $query = self::dog_updates_query($dog_id, $paged, false);
        ?>
        <section class="card">
            <h2>Neuigkeiten von <?php echo esc_html(self::dog_public_name($dog_id)); ?></h2>
            <?php if (!$query->have_posts()) : ?>
                <p>Noch keine Neuigkeiten vorhanden.</p>
            <?php else : ?>
                <?php foreach ($query->posts as $update) : ?>
                    <?php
                    $body = trim((string)get_post_meta($update->ID, 'sod_dog_update_body', true));
                    $photo_id = (int)get_post_meta($update->ID, 'sod_dog_update_photo_id', true);
                    $video_url = (string)get_post_meta($update->ID, 'sod_dog_update_video_url', true);
                    ?>
                    <div style="padding:12px 0;border-top:1px solid var(--line);">
                        <p style="font-size:12.5px;color:var(--muted);margin:0 0 6px;"><?php echo esc_html(get_the_date('d.m.Y', $update)); ?></p>
                        <?php if ($video_url !== '') : ?>
                            <div style="border-radius:12px;overflow:hidden;margin-bottom:8px;max-width:320px;">
                                <?php echo self::dog_video_embed($video_url, 'sod-member-update-video', $photo_id > 0 ? (string)wp_get_attachment_image_url($photo_id, 'medium') : ''); ?>
                            </div>
                        <?php elseif ($photo_id > 0) : ?>
                            <div style="border-radius:12px;overflow:hidden;margin-bottom:8px;max-width:280px;">
                                <?php echo wp_get_attachment_image($photo_id, 'medium', false, ['style' => 'width:100%;height:auto;display:block;']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($body !== '') : ?>
                            <p style="margin:0 0 10px;"><?php echo wp_kses_post($body); ?></p>
                        <?php endif; ?>
                        <?php if ($photo_id > 0) :
                            $comments = get_comments(['post_id' => $update->ID, 'status' => 'approve', 'order' => 'ASC']);
                            ?>
                            <?php if ($comments) : ?>
                                <ul class="sod-comment-list">
                                    <?php foreach ($comments as $comment) : ?>
                                        <li><strong><?php echo esc_html($comment->comment_author); ?>:</strong> <?php echo esc_html($comment->comment_content); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="sod_member_comment">
                                <input type="hidden" name="update_id" value="<?php echo esc_attr((string)$update->ID); ?>">
                                <?php wp_nonce_field('sod_member_comment_' . $update->ID, 'sod_member_comment_nonce'); ?>
                                <textarea name="comment_content" rows="2" placeholder="Kommentar zu diesem Foto hinterlassen…" required></textarea>
                                <button type="submit" class="cta-small">Kommentieren</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($query->max_num_pages > $paged) : ?>
                    <p class="foot-link"><a href="<?php echo esc_url(add_query_arg('feed_page', $paged + 1)); ?>">Mehr laden</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Ein Mitglied darf nur Fotos des EIGENEN Patenhundes kommentieren - deshalb wird hier
     * (nicht nur beim Anzeigen) nochmal serverseitig geprueft, welcher Hund zum Update gehoert
     * und ob er mit dem verknuepften Paten des angemeldeten Kontos uebereinstimmt.
     */
    public static function handle_member_comment(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(self::member_area_url()));
            exit;
        }
        $user_id = get_current_user_id();
        $update_id = absint($_POST['update_id'] ?? 0);
        if (!isset($_POST['sod_member_comment_nonce']) || !wp_verify_nonce((string)$_POST['sod_member_comment_nonce'], 'sod_member_comment_' . $update_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }
        if (get_post_type($update_id) !== 'sod_dog_update') {
            wp_safe_redirect(self::member_area_url());
            exit;
        }

        $sponsor = self::member_linked_sponsor($user_id);
        $member_dog_id = $sponsor instanceof WP_Post ? (int)get_post_meta($sponsor->ID, 'sod_sponsor_dog', true) : 0;
        $update_dog_id = (int)get_post_meta($update_id, 'sod_dog_update_dog', true);
        if ($member_dog_id <= 0 || $member_dog_id !== $update_dog_id) {
            wp_safe_redirect(self::member_area_url());
            exit;
        }

        $content = sanitize_textarea_field((string)($_POST['comment_content'] ?? ''));
        if ($content !== '') {
            $user = wp_get_current_user();
            wp_insert_comment([
                'comment_post_ID' => $update_id,
                'comment_content' => $content,
                'comment_author' => $user->display_name,
                'comment_author_email' => $user->user_email,
                'user_id' => $user_id,
                'comment_approved' => 1,
                'comment_type' => 'comment',
            ]);
        }

        wp_safe_redirect(self::member_area_url());
        exit;
    }

    /**
     * Bewusst nicht vergleichend/oeffentlich (kein Leaderboard gegen andere Paten) - der
     * Rang misst nur die eigene Treue ueber die Zeit und passt damit besser zum warmen,
     * nicht-kompetitiven Ton der Seite. Berechnungsgrundlage bewusst einfach gehalten:
     * volle Monate seit Patenschaftsbeginn, keine zusaetzliche Finanz-Verknuepfung noetig.
     */
    private static function member_rank_tiers(): array
    {
        return [
            ['months' => 0, 'label' => 'Beschützer/in', 'icon' => '🐾'],
            ['months' => 3, 'label' => 'Weggefährte/in', 'icon' => '🦴'],
            ['months' => 12, 'label' => 'Rudelmitglied', 'icon' => '🐕'],
            ['months' => 24, 'label' => 'Ehrenmitglied', 'icon' => '🏅'],
        ];
    }

    private static function member_sponsorship_months(WP_Post $sponsor): int
    {
        $since_raw = trim((string)get_post_meta($sponsor->ID, 'sod_sponsor_since', true));
        $start_ts = $since_raw !== '' ? (int)strtotime($since_raw) : 0;
        if ($start_ts <= 0) {
            $start_ts = (int)get_post_time('U', true, $sponsor->ID);
        }
        if ($start_ts <= 0) {
            return 0;
        }
        return max(0, (int)floor((time() - $start_ts) / (30 * DAY_IN_SECONDS)));
    }

    private static function render_member_rank(?WP_Post $sponsor): void
    {
        if (!$sponsor instanceof WP_Post) {
            ?>
            <section class="card">
                <h2>Ihr Rang</h2>
                <p>Sobald Ihre Patenschaft verknüpft ist, sehen Sie hier Ihren Rang.</p>
            </section>
            <?php
            return;
        }

        $tiers = self::member_rank_tiers();
        $months = self::member_sponsorship_months($sponsor);
        $current = $tiers[0];
        $next = null;
        foreach ($tiers as $index => $tier) {
            if ($months >= $tier['months']) {
                $current = $tier;
                $next = $tiers[$index + 1] ?? null;
            }
        }
        ?>
        <section class="card">
            <h2>Ihr Rang</h2>
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
                <span style="font-size:36px;line-height:1;"><?php echo esc_html($current['icon']); ?></span>
                <div>
                    <strong style="font-size:18px;color:var(--yellow);"><?php echo esc_html($current['label']); ?></strong><br>
                    <span style="color:var(--muted);font-size:13.5px;"><?php echo esc_html((string)$months); ?> Monat<?php echo $months === 1 ? '' : 'e'; ?> als Pate/Patin dabei</span>
                </div>
            </div>
            <?php if ($next) :
                $remaining = max(0, $next['months'] - $months);
                $span = max(1, $next['months'] - $current['months']);
                $progress = min(100, (int)round(($months - $current['months']) / $span * 100));
                ?>
                <p style="font-size:13.5px;color:var(--muted);margin:0 0 6px;">Noch <?php echo esc_html((string)$remaining); ?> Monat<?php echo $remaining === 1 ? '' : 'e'; ?> bis „<?php echo esc_html($next['label']); ?>“ <?php echo esc_html($next['icon']); ?></p>
                <div style="height:8px;border-radius:999px;background:var(--input-bg);overflow:hidden;">
                    <div style="height:100%;width:<?php echo esc_attr((string)$progress); ?>%;background:var(--yellow);"></div>
                </div>
            <?php else : ?>
                <p style="font-size:13.5px;color:var(--muted);margin:0;">Höchster Rang erreicht — danke für Ihre langjährige Treue! 🙏</p>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_member_delete_confirm(): void
    {
        nocache_headers();
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(self::member_delete_url()));
            exit;
        }
        $user_id = get_current_user_id();
        self::render_member_shell('Konto löschen', function () use ($user_id): void {
            ?>
            <section class="notice">
                <h2>Konto wirklich löschen?</h2>
                <p>Ihr Login-Konto wird dauerhaft gelöscht. Ihre Patenschaft selbst (inkl. Spendenhistorie) bleibt aus rechtlichen Aufbewahrungsgründen bestehen, wird aber nicht mehr mit einem Konto verknüpft, und Ihre öffentliche Zustimmung zur Anzeige wird widerrufen.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sod_member_delete_account">
                    <?php wp_nonce_field('sod_member_delete_account_' . $user_id, 'sod_member_delete_nonce'); ?>
                    <button type="submit" class="cta-danger">Ja, Konto endgültig löschen</button>
                </form>
                <p class="foot-link"><a href="<?php echo esc_url(self::member_area_url()); ?>">Abbrechen</a></p>
            </section>
            <?php
        });
    }

    public static function handle_member_delete_account(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }
        $user_id = get_current_user_id();
        if (!isset($_POST['sod_member_delete_nonce']) || !wp_verify_nonce((string)$_POST['sod_member_delete_nonce'], 'sod_member_delete_account_' . $user_id)) {
            wp_die('Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.');
        }

        $sponsor_id = self::member_linked_sponsor_id($user_id);
        if ($sponsor_id > 0) {
            delete_post_meta($sponsor_id, 'sod_sponsor_user_id');
            update_post_meta($sponsor_id, 'sod_sponsor_public_consent_withdrawn_at', gmdate('c'));
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_logout();
        wp_delete_user($user_id);

        wp_safe_redirect(add_query_arg('sod_member_deleted', '1', home_url('/')));
        exit;
    }
}

if (!function_exists('sod_home_image_url')) {
    function sod_home_image_url(string $key, string $fallback_url): string
    {
        return SOD_Plugin::home_image_url($key, $fallback_url);
    }
}

if (!function_exists('sod_teaming_members')) {
    function sod_teaming_members(): int
    {
        return SOD_Plugin::teaming_members();
    }
}

SOD_Plugin::init();
register_activation_hook(__FILE__, ['SOD_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SOD_Plugin', 'deactivate']);
