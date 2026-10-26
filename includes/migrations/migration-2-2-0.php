<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.2.0 – Ajout de cover_image_thumbnail_url sur la table projects.
 */
class Agency_Migration_2_2_0 {

    public static $version = '2.2.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_projects = $wpdb->prefix . 'agency_projects';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'cover_image_thumbnail_url'",
            DB_NAME, $table_projects
        ));

        if (!$col) {
            $wpdb->query(
                "ALTER TABLE $table_projects ADD COLUMN cover_image_thumbnail_url varchar(500) DEFAULT NULL AFTER cover_image_url"
            );
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.2.0 – Error adding cover_image_thumbnail_url: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
