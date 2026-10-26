<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.6.0 — Ré-introduction d'un secret de token récupérable (chiffré).
 *
 * La migration 2.5.0 avait supprimé la colonne plaintext access_token, ne
 * conservant que le hash. Conséquence : le lien d'accès n'était plus copiable
 * par l'admin passé une heure (TTL du transient), l'obligeant à régénérer.
 *
 * On ajoute désormais une colonne token_secret contenant le plaintext chiffré
 * AES-256-CBC (cf. Agency_Security::encrypt_token). Le lien reste ainsi copiable
 * en permanence, sans exposer le token en clair en base.
 *
 * Note pour les installations existantes : le plaintext d'origine est perdu
 * (seul le hash subsiste). Chaque client antérieur nécessite une régénération
 * unique pour peupler token_secret ; ensuite c'est permanent.
 */
class Agency_Migration_2_6_0 {

    public static $version = '2.6.0';

    /**
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'agency_clients';

        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'token_secret'",
            DB_NAME,
            $table
        ));

        if (!$col) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `token_secret` varchar(255) DEFAULT NULL AFTER `token_hash`");
            if ($wpdb->last_error) {
                error_log('Agency Migration 2.6.0 — Error adding token_secret: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
