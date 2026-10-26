<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 1.7.0 – Ajout de client_view_title sur clients et cover_image_url sur projects.
 */
class Agency_Migration_1_7_0 {

    public static $version = '1.7.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_clients  = $wpdb->prefix . 'agency_clients';
        $table_projects = $wpdb->prefix . 'agency_projects';

        // Ajout de client_view_title sur la table clients
        $client_title_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'client_view_title'",
            DB_NAME, $table_clients
        ));

        if (!$client_title_exists) {
            $wpdb->query("ALTER TABLE $table_clients ADD COLUMN client_view_title varchar(255) DEFAULT NULL AFTER name");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.7.0 – Error adding client_view_title: ' . $wpdb->last_error);
                return false;
            }
        }

        // Ajout de cover_image_url sur la table projects
        $cover_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'cover_image_url'",
            DB_NAME, $table_projects
        ));

        if (!$cover_exists) {
            $wpdb->query("ALTER TABLE $table_projects ADD COLUMN cover_image_url varchar(500) DEFAULT NULL AFTER description");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.7.0 – Error adding cover_image_url: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
