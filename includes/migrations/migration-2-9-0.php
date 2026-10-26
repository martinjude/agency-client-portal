<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.9.0 — Simplification de l'interface : suppression de la personnalisation.
 *
 * Le portail adopte un design system unique (sombre, Inter + JetBrains Mono,
 * rayons fixes). On supprime les options de thème, de police, de rayon et de
 * couleurs globales. Seule la couleur d'accent par client est conservée.
 */
class Agency_Migration_2_9_0 {

    public static $version = '2.9.0';

    /**
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        $options = array(
            'agency_theme',
            'agency_theme_mode',
            'agency_theme_preset',
            'agency_theme_gradients',
            'agency_theme_is_dark',
            'agency_theme_accent',
            'agency_ui_font',
            'agency_card_border_width',
            'agency_card_border_color',
            'agency_card_border_radius',
            'agency_bg_color_light',
            'agency_bg_color_dark',
            'agency_sidebar_bg_color',
        );
        foreach ($options as $option) {
            delete_option($option);
        }

        // Grille des blocs : 3 → 4 colonnes. Une carte « pleine largeur »
        // (3 colonnes) occupe désormais les 4 colonnes.
        global $wpdb;
        $table  = $wpdb->prefix . 'agency_blocks';
        $result = $wpdb->query("UPDATE $table SET grid_width = 4, updated_at = updated_at WHERE grid_width = 3 AND type NOT IN ('separator', 'progress_bar')"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
        if ($result === false) {
            error_log('Agency migration 2.9.0: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }
}
