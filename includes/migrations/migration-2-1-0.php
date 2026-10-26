<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.1.0 – Ajout de la colonne subtitle sur la table projects.
 */
class Agency_Migration_2_1_0 {

    public static $version = '2.1.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_projects = $wpdb->prefix . 'agency_projects';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'subtitle'",
            DB_NAME, $table_projects
        ));

        if (!$col) {
            $wpdb->query(
                "ALTER TABLE $table_projects ADD COLUMN subtitle text DEFAULT NULL AFTER description"
            );
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.1.0 – Error adding subtitle: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
