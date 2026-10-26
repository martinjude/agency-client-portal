<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.3.0 – Ajout de thumbnail_url sur la table block_presets.
 */
class Agency_Migration_2_3_0 {

    public static $version = '2.3.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'agency_block_presets';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'thumbnail_url'",
            DB_NAME, $table
        ));

        if (!$col) {
            $wpdb->query(
                "ALTER TABLE $table ADD COLUMN thumbnail_url varchar(500) DEFAULT NULL AFTER `data`"
            );
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.3.0 – Error adding thumbnail_url: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
