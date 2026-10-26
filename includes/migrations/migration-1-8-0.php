<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 1.8.0 – Ajout de labels, is_archived sur projects ;
 * parent_asset_id, suggested_by_client, suggestion_text sur assets.
 */
class Agency_Migration_1_8_0 {

    public static $version = '1.8.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_projects = $wpdb->prefix . 'agency_projects';
        $table_assets   = $wpdb->prefix . 'agency_assets';

        // labels
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'labels'",
            DB_NAME, $table_projects
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE $table_projects ADD COLUMN labels text DEFAULT NULL AFTER cover_image_url");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.8.0 – Error adding labels: ' . $wpdb->last_error);
                return false;
            }
        }

        // is_archived
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'is_archived'",
            DB_NAME, $table_projects
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE $table_projects ADD COLUMN is_archived tinyint(1) DEFAULT 0 AFTER status");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.8.0 – Error adding is_archived: ' . $wpdb->last_error);
                return false;
            }

            $idx = $wpdb->get_var($wpdb->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'is_archived'",
                DB_NAME, $table_projects
            ));
            if (!$idx) {
                $wpdb->query("ALTER TABLE $table_projects ADD INDEX (is_archived)");
                if ($wpdb->last_error) {
                    error_log('Agency Migration 1.8.0 – Error adding index is_archived: ' . $wpdb->last_error);
                    return false;
                }
            }
        }

        // parent_asset_id
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'parent_asset_id'",
            DB_NAME, $table_assets
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE $table_assets ADD COLUMN parent_asset_id bigint(20) UNSIGNED DEFAULT NULL AFTER id");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.8.0 – Error adding parent_asset_id: ' . $wpdb->last_error);
                return false;
            }

            $idx = $wpdb->get_var($wpdb->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'parent_asset_id'",
                DB_NAME, $table_assets
            ));
            if (!$idx) {
                $wpdb->query("ALTER TABLE $table_assets ADD INDEX (parent_asset_id)");
                if ($wpdb->last_error) {
                    error_log('Agency Migration 1.8.0 – Error adding index parent_asset_id: ' . $wpdb->last_error);
                    return false;
                }
            }
        }

        // suggested_by_client
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'suggested_by_client'",
            DB_NAME, $table_assets
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE $table_assets ADD COLUMN suggested_by_client tinyint(1) DEFAULT 0 AFTER version_notes");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.8.0 – Error adding suggested_by_client: ' . $wpdb->last_error);
                return false;
            }
        }

        // suggestion_text
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'suggestion_text'",
            DB_NAME, $table_assets
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE $table_assets ADD COLUMN suggestion_text text DEFAULT NULL AFTER suggested_by_client");
            if ($wpdb->last_error) {
                error_log('Agency Migration 1.8.0 – Error adding suggestion_text: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
