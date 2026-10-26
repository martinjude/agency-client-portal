<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.4.0 – Ajout de background_image_url sur la table agency_clients.
 */
class Agency_Migration_2_4_0 {

    public static $version = '2.4.0';

    /**
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'agency_clients';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'background_image_url'",
            DB_NAME, $table
        ));

        if (!$col) {
            $wpdb->query(
                "ALTER TABLE `$table` ADD COLUMN `background_image_url` TEXT DEFAULT NULL AFTER `secondary_color`"
            );
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.4.0 – Error adding background_image_url: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
