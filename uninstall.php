<?php
/**
 * Fichier de désinstallation du plugin
 * Supprime toutes les données créées par le plugin
 *
 * @package Agency
 */

// Si la désinstallation n'est pas appelée depuis WordPress, quitter
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Charger les dépendances
require_once plugin_dir_path(__FILE__) . 'includes/class-agency-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-agency-roles.php';

// Supprimer les tables personnalisées
$agency_database = new Agency_Database();
$agency_database->drop_tables();

// Supprimer les rôles personnalisés
$agency_roles = new Agency_Roles();
$agency_roles->remove_roles();

// Supprimer les options de base
delete_option('agency_version');
delete_option('agency_db_version');
delete_option('agency_installed_date');

// Options de licence (versions antérieures à 2.0.0)
delete_option('agency_license_key');
delete_option('agency_license_status');
delete_option('agency_license_data');
delete_option('agency_license_last_validated');
delete_option('agency_license_activation_token');
delete_option('agency_license_instance_id');
delete_option('agency_license_grant');
delete_option('agency_license_binding');
delete_option('agency_free_banner_dismissed_at');
delete_option('agency_frontend_manager_banner_dismissed_at');

// Options de paramètres généraux
delete_option('agency_max_file_size');
delete_option('agency_allowed_file_types');
delete_option('agency_project_labels');

// Options de branding
delete_option('agency_brand_logo_url');
// Options de thème supprimées en 2.9.0 (sites non migrés)
delete_option('agency_theme');
delete_option('agency_theme_mode');
delete_option('agency_theme_preset');
delete_option('agency_theme_gradients');
delete_option('agency_theme_is_dark');
delete_option('agency_theme_accent');
delete_option('agency_ui_font');
delete_option('agency_card_border_width');
delete_option('agency_card_border_color');
delete_option('agency_card_border_radius');
delete_option('agency_bg_color_light');
delete_option('agency_bg_color_dark');
delete_option('agency_sidebar_bg_color');

// Options de textes par défaut des blocs
delete_option('agency_block_default_text_script');
delete_option('agency_block_default_text_video');
delete_option('agency_block_default_text_versioned_media');
delete_option('agency_block_default_text_storyboard');

// Options diverses
delete_option('agency_token_migration_notice_dismissed');
delete_option('agency_developer_mode');

// Transients de licence (versions antérieures à 2.0.0)
delete_transient('agency_license_validated');
delete_transient('agency_license_rate_limit');

// Dé-planifier le cron de licence (versions antérieures à 2.0.0)
wp_clear_scheduled_hook('agency_license_recheck');

// Supprimer les CPT et leurs métadonnées
global $wpdb;

// Supprimer tous les posts agency_client
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_type = %s", 'agency_client'));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_type = %s", 'agency_project'));

// Nettoyer les métadonnées orphelines
$wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL");

// Supprimer le dossier d'uploads (et l'ancien dossier d'extensions) si l'option est activée
// Par défaut, les fichiers uploadés sont conservés pour éviter toute perte de données.
// L'administrateur doit activer l'option « agency_delete_data_on_uninstall » pour
// que les dossiers agency-uploads et agency-extensions soient supprimés à la désinstallation.
// Pour l'activer manuellement : update_option('agency_delete_data_on_uninstall', true);
if (get_option('agency_delete_data_on_uninstall', false)) {
    $agency_dirs_to_remove = array(
        WP_CONTENT_DIR . '/agency-uploads',
        WP_CONTENT_DIR . '/agency-extensions',
    );

    foreach ($agency_dirs_to_remove as $agency_dir) {
        if (!is_dir($agency_dir)) {
            continue;
        }

        if (WP_Filesystem()) {
            global $wp_filesystem;

            if ($wp_filesystem && $wp_filesystem->is_dir($agency_dir) && !$wp_filesystem->delete($agency_dir, true)) {
                error_log('Agency: Failed to delete folder ' . basename($agency_dir) . ' via WP_Filesystem.');
            }
        }
    }
}

// Supprimer l'option elle-même
delete_option('agency_delete_data_on_uninstall');

// Flush rewrite rules
flush_rewrite_rules();
