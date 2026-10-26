<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 1.2.0 – Ajout de la colonne emoji sur la table projects.
 */
class Agency_Migration_1_2_0 {

    public static $version = '1.2.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_projects = $wpdb->prefix . 'agency_projects';

        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'emoji'",
                DB_NAME,
                $table_projects
            )
        );

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_projects ADD COLUMN emoji varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER description");
        } else {
            $wpdb->query("ALTER TABLE $table_projects MODIFY COLUMN emoji varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        }

        if ($wpdb->last_error) {
            error_log('Agency Migration 1.2.0 – Error: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }
}
