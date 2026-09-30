<?php
/**
 * Plugin Name: OpenPetRescue Dashboard-Schnellzugriff
 * Description: Fester Schnellzugriff fuer die OpenPetRescue-Verwaltung im WordPress-Dashboard.
 * Version: 1.0.0
 * Author: Peter Lehner / Shield of Dogs
 * License: GPL-3.0-or-later
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('all_admin_notices', static function (): void {
    if (class_exists('SOD_Plugin', false)) {
        // Das Hauptplugin zeigt den Schnellzugriff bereits selbst an (admin_notices-Hook).
        // Dieses MU-Modul ist nur ein Fallback, falls das Plugin deaktiviert oder defekt ist.
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'dashboard') {
        return;
    }

    $can_dogs = current_user_can('manage_options') || current_user_can('edit_sod_dogs');
    $can_items = current_user_can('manage_options') || current_user_can('edit_sod_items');
    $can_finances = current_user_can('manage_options') || current_user_can('edit_sod_finances');

    if (!$can_dogs && !$can_items && !$can_finances) {
        return;
    }

    $sections = [];
    if ($can_dogs) {
        $sections[] = [
            'Hunde & Vermittlung',
            [
                ['Tierheim-Uebersicht', admin_url('edit.php?post_type=sod_dog&page=sod-shelter-overview')],
                ['Hunde in Vermittlung', admin_url('edit.php?post_type=sod_dog')],
                ['Hund anlegen', admin_url('post-new.php?post_type=sod_dog')],
                ['Anfragen', admin_url('edit.php?post_type=sod_application')],
                ['Interessenten', admin_url('edit.php?post_type=sod_interest')],
                ['Vermittlungsakten', admin_url('edit.php?post_type=sod_case')],
                ['Aufgaben', admin_url('edit.php?post_type=sod_task')],
                ['Transporte', admin_url('edit.php?post_type=sod_transport')],
                ['Pflegestellen', admin_url('edit.php?post_type=sod_foster')],
                ['Paten', admin_url('edit.php?post_type=sod_sponsor')],
            ],
        ];
    }
    if ($can_items) {
        $inventory_url = $can_dogs
            ? admin_url('edit.php?post_type=sod_dog&page=sod-inventory-overview')
            : admin_url('admin.php?page=sod-inventory-standalone');
        $sections[] = [
            'Futter, Zubehoer & Lager',
            [
                ['Lageruebersicht', $inventory_url],
                ['Bestandsartikel', admin_url('edit.php?post_type=sod_inventory')],
                ['Artikel anlegen', admin_url('post-new.php?post_type=sod_inventory')],
            ],
        ];
    }
    if ($can_finances) {
        $sections[] = [
            'Spenden & Transparenz',
            [
                ['Spenden & Ausgaben', admin_url('edit.php?post_type=sod_finance')],
                ['Eintrag anlegen', admin_url('post-new.php?post_type=sod_finance')],
                ['Finanzbericht', admin_url('edit.php?post_type=sod_dog&page=sod-finance-report')],
            ],
        ];
    }
    if (current_user_can('manage_options')) {
        $sections[] = [
            'System & Website',
            [
                ['SOD Einstellungen', admin_url('edit.php?post_type=sod_dog&page=sod-settings')],
                ['JSON Import', admin_url('edit.php?post_type=sod_dog&page=sod-json-import')],
                ['Website ansehen', home_url('/')],
                ['Vermittlungsseite', home_url('/vermittlung/')],
                ['Kontaktformular', home_url('/kontakt/')],
            ],
        ];
    }

    echo '<div class="sod-mu-dashboard-panel">';
    echo '<div class="sod-mu-dashboard-head"><h2>' . esc_html(get_option('sod_org_name', 'OpenPetRescue')) . ' Verwaltung</h2><p>Alle wichtigen Funktionen direkt vom Dashboard oeffnen.</p></div>';
    echo '<div class="sod-mu-dashboard-sections">';
    foreach ($sections as [$title, $links]) {
        echo '<section class="sod-mu-dashboard-section">';
        printf('<h3>%s</h3>', esc_html((string)$title));
        echo '<div class="sod-mu-dashboard-links">';
        foreach ($links as [$label, $url]) {
            printf('<a href="%s">%s</a>', esc_url((string)$url), esc_html((string)$label));
        }
        echo '</div></section>';
    }
    echo '</div></div>';
});

add_action('admin_head-index.php', static function (): void {
    ?>
    <style>
        .sod-mu-dashboard-panel {
            margin: 16px 20px 18px 0;
            padding: 18px;
            border: 1px solid #c9d6df;
            border-left: 6px solid #f3c74f;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(32, 64, 96, .1);
        }
        .sod-mu-dashboard-head {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px 16px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid #edf0f2;
        }
        .sod-mu-dashboard-head h2 {
            margin: 0;
            color: #204060;
            font-size: 22px;
        }
        .sod-mu-dashboard-head p {
            margin: 0;
            color: #646970;
        }
        .sod-mu-dashboard-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
        }
        .sod-mu-dashboard-section {
            padding: 12px;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            background: #f8fafb;
        }
        .sod-mu-dashboard-section h3 {
            margin: 0 0 10px;
            color: #204060;
            font-size: 14px;
        }
        .sod-mu-dashboard-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
        }
        .sod-mu-dashboard-links a {
            display: block;
            padding: 9px 10px;
            border: 1px solid #d6dde2;
            border-radius: 6px;
            background: #fff;
            color: #204060;
            font-weight: 600;
            text-decoration: none;
        }
        .sod-mu-dashboard-links a:hover,
        .sod-mu-dashboard-links a:focus {
            border-color: #204060;
            box-shadow: 0 5px 14px rgba(32, 64, 96, .12);
        }
    </style>
    <?php
});
