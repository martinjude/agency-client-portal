<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Migration 2.8.0 — Passage open source : suppression du système de licence.
 *
 * Toutes les fonctionnalités sont désormais gratuites. On nettoie les options,
 * transients et tâches cron hérités du système de licence et des bannières
 * « plan gratuit ».
 */
class Agency_Migration_2_8_0 {

    public static $version = '2.8.0';

    /**
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function up() {
        $options = array(
            'agency_license_key',
            'agency_license_status',
            'agency_license_data',
            'agency_license_last_validated',
            'agency_license_activation_token',
            'agency_license_instance_id',
            'agency_license_grant',
            'agency_license_binding',
            'agency_free_banner_dismissed_at',
            'agency_frontend_manager_banner_dismissed_at',
        );
        foreach ($options as $option) {
            delete_option($option);
        }

        delete_transient('agency_license_validated');
        delete_transient('agency_license_rate_limit');

        wp_clear_scheduled_hook('agency_license_recheck');

        return true;
    }
}
