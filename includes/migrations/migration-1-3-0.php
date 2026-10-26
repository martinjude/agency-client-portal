<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 1.3.0 – Génération des tokens pour les clients existants.
 */
class Agency_Migration_1_3_0 {

    public static $version = '1.3.0';

    /**
     * Exécute la migration.
     *
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        global $wpdb;

        $table_clients = $wpdb->prefix . 'agency_clients';

        $clients_without_tokens = $wpdb->get_results(
            "SELECT id FROM $table_clients WHERE token_hash IS NULL AND status = 'active'"
        );

        if ($wpdb->last_error) {
            error_log('Agency Migration 1.3.0 – Error while reading clients: ' . $wpdb->last_error);
            return false;
        }

        foreach ($clients_without_tokens as $client) {
            $generated_token = Agency_Security::agency_generate_client_access_token($client->id);
            if (!$generated_token) {
                error_log('Agency Migration 1.3.0 – Token generation failed for client ID ' . intval($client->id));
                return false;
            }
        }

        return true;
    }
}
