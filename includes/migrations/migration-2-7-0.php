<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.7.0 — Ajout d'une adresse email de contact client au projet.
 *
 * Sert de destinataire au bouton « Notifier le client » (envoi d'un lien à
 * jeton vers un bloc précis). L'email est défini par projet, modifiable depuis
 * la popin d'édition de projet.
 */
class Agency_Migration_2_7_0 {

    public static $version = '2.7.0';

    /**
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'agency_projects';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'email'",
            DB_NAME,
            $table
        ));

        if (!$col) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `name`");
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.7.0 — Error adding email: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
