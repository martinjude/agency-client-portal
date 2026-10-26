<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 1.6.0 – Ajout des métadonnées de version sur la table assets.
 */
class Agency_Migration_1_6_0 {

    public static $version = '1.6.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_assets = $wpdb->prefix . 'agency_assets';

        // Ajout des colonnes de versionnage
        $columns = array(
            'version_number' => "INT(11) DEFAULT 1 AFTER voiceover",
            'version_label'  => "VARCHAR(255) DEFAULT NULL AFTER version_number",
            'version_status' => "VARCHAR(50) DEFAULT 'draft' AFTER version_label",
            'version_notes'  => "LONGTEXT DEFAULT NULL AFTER version_status",
        );

        foreach ($columns as $name => $definition) {
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $table_assets, $name
            ));

            if (!$column_exists) {
                $wpdb->query("ALTER TABLE $table_assets ADD COLUMN $name $definition");
                if ($wpdb->last_error) {
                    error_log('Agency Migration 1.6.0 – Error adding column ' . $name . ' : ' . $wpdb->last_error);
                    return false;
                }
            }
        }

        // Ajout des index
        $indexes = array('version_number', 'version_status');

        foreach ($indexes as $index) {
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                DB_NAME, $table_assets, $index
            ));

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE $table_assets ADD INDEX ($index)");
                if ($wpdb->last_error) {
                    error_log('Agency Migration 1.6.0 – Error adding index ' . $index . ' : ' . $wpdb->last_error);
                    return false;
                }
            }
        }

        return true;
    }
}
