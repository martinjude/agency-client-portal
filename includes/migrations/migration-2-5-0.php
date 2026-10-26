<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.5.0 — Suppression de la colonne plaintext access_token.
 *
 * À partir de cette version, seul le hash SHA-256 du token (token_hash)
 * est persisté. Le plaintext est uniquement transmis via transient courte
 * durée à l'admin (cf. Agency_Security::agency_generate_client_access_token).
 *
 * Conséquence pour les installations existantes : les tokens actifs avant
 * la migration restent valides côté cookie (validation par hash), mais le
 * lien d'accès original ne peut plus être ré-affiché dans l'admin. Une
 * régénération explicite produit un nouveau lien.
 */
class Agency_Migration_2_5_0 {

    public static $version = '2.5.0';

    /**
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'agency_clients';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'access_token'",
            DB_NAME,
            $table
        ));

        if ($col) {
            $wpdb->query("ALTER TABLE `$table` DROP COLUMN `access_token`");
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.5.0 — Error dropping access_token: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
