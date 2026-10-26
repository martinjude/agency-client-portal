<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.0.0 – Ajout de cover_image_asset_id sur la table projects.
 */
class Agency_Migration_2_0_0 {

    public static $version = '2.0.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_projects = $wpdb->prefix . 'agency_projects';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'cover_image_asset_id'",
            DB_NAME, $table_projects
        ));

        if (!$col) {
            $wpdb->query(
                "ALTER TABLE $table_projects ADD COLUMN cover_image_asset_id bigint(20) UNSIGNED DEFAULT NULL AFTER cover_image_url"
            );
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.0.0 – Error adding cover_image_asset_id: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
