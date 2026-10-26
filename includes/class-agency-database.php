<?php
/**
 * Gestion de la base de données - Création et gestion des tables personnalisées
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Database {
    
    /**
     * Version de la base de données
     */
    const DB_VERSION = '2.8.0';

    /**
     * Option contenant le dernier message d'erreur de migration.
     */
    const OPTION_MIGRATION_LAST_ERROR = 'agency_migration_last_error';
    
    /**
     * Créer toutes les tables personnalisées
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table clients
        $table_clients = $wpdb->prefix . 'agency_clients';
        $sql_clients = "CREATE TABLE $table_clients (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            client_view_title varchar(255) DEFAULT NULL,
            slug varchar(255) NOT NULL,
            logo_url varchar(500) DEFAULT NULL,
            primary_color varchar(7) DEFAULT '#000000',
            secondary_color varchar(7) DEFAULT '#ffffff',
            token_hash varchar(64) DEFAULT NULL,
            token_secret varchar(255) DEFAULT NULL,
            token_created_at datetime DEFAULT NULL,
            token_expires_at datetime DEFAULT NULL,
            token_active tinyint(1) DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id),
            KEY status (status),
            KEY token_hash (token_hash),
            KEY token_active (token_active)
        ) $charset_collate;";
        
        // Table projects
        $table_projects = $wpdb->prefix . 'agency_projects';
        $sql_projects = "CREATE TABLE $table_projects (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT NULL,
            subtitle text DEFAULT NULL,
            cover_image_url varchar(500) DEFAULT NULL,
            cover_image_thumbnail_url varchar(500) DEFAULT NULL,
            cover_image_asset_id bigint(20) UNSIGNED DEFAULT NULL,
            emoji varchar(32) DEFAULT NULL,
            labels text DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            is_archived tinyint(1) DEFAULT 0,
            order_position int(11) DEFAULT 0,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_slug (client_id, slug),
            KEY client_id (client_id),
            KEY status (status),
            KEY is_archived (is_archived),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Table blocks
        $table_blocks = $wpdb->prefix . 'agency_blocks';
        $sql_blocks = "CREATE TABLE $table_blocks (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id bigint(20) UNSIGNED NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext DEFAULT NULL,
            settings longtext DEFAULT NULL,
            order_position int(11) DEFAULT 0,
            grid_width int(11) DEFAULT 3,
            grid_height int(11) DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY type (type),
            KEY status (status),
            KEY order_position (order_position)
        ) $charset_collate;";
        
        // Table comments
        $table_comments = $wpdb->prefix . 'agency_comments';
        $sql_comments = "CREATE TABLE $table_comments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            block_id bigint(20) UNSIGNED NOT NULL,
            asset_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            parent_id bigint(20) UNSIGNED DEFAULT 0,
            content text NOT NULL,
            status varchar(20) DEFAULT 'approved',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY block_id (block_id),
            KEY asset_id (asset_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Table assets
        $table_assets = $wpdb->prefix . 'agency_assets';
        $sql_assets = "CREATE TABLE $table_assets (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_asset_id bigint(20) UNSIGNED DEFAULT NULL,
            block_id bigint(20) UNSIGNED NOT NULL,
            filename varchar(255) NOT NULL,
            original_filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size bigint(20) UNSIGNED NOT NULL,
            mime_type varchar(100) NOT NULL,
            order_position int(11) DEFAULT 0,
            voiceover longtext DEFAULT NULL,
            version_number int(11) DEFAULT 1,
            version_label varchar(255) DEFAULT NULL,
            version_status varchar(50) DEFAULT 'draft',
            version_notes longtext DEFAULT NULL,
            suggested_by_client tinyint(1) DEFAULT 0,
            suggestion_text text DEFAULT NULL,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY block_id (block_id),
            KEY parent_asset_id (parent_asset_id),
            KEY file_type (file_type),
            KEY uploaded_by (uploaded_by),
            KEY order_position (order_position),
            KEY version_number (version_number),
            KEY version_status (version_status)
        ) $charset_collate;";
        
        // Table permissions (DEPRECATED 1.3.0 - Conservée pour compatibilité admin)
        // Cette table sera supprimée dans une version future
        // Les clients finaux utilisent maintenant l'authentification par tokens
        $table_permissions = $wpdb->prefix . 'agency_permissions';
        $sql_permissions = "CREATE TABLE $table_permissions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            client_id bigint(20) UNSIGNED NOT NULL,
            permission_level varchar(20) DEFAULT 'view',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_client (user_id, client_id),
            KEY client_id (client_id)
        ) $charset_collate;";
        
        // Table external_links
        $table_external_links = $wpdb->prefix . 'agency_external_links';
        $sql_external_links = "CREATE TABLE $table_external_links (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            block_id bigint(20) UNSIGNED DEFAULT NULL,
            project_id bigint(20) UNSIGNED DEFAULT NULL,
            url varchar(500) NOT NULL,
            text varchar(255) NOT NULL,
            emoji varchar(32) DEFAULT NULL,
            order_position int(11) DEFAULT 0,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY block_id (block_id),
            KEY project_id (project_id),
            KEY order_position (order_position)
        ) $charset_collate;";
        
        // Table block_presets
        $table_block_presets = $wpdb->prefix . 'agency_block_presets';
        $sql_block_presets = "CREATE TABLE $table_block_presets (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            data longtext NOT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Exécuter les requêtes
        dbDelta($sql_clients);
        dbDelta($sql_projects);
        dbDelta($sql_blocks);
        dbDelta($sql_comments);
        dbDelta($sql_assets);
        dbDelta($sql_permissions);
        dbDelta($sql_external_links);
        dbDelta($sql_block_presets);

        // Exécuter les migrations ; ne pas forcer la version finale en cas d'échec.
        $migrations_ok = $this->run_migrations();
        if (!$migrations_ok) {
            error_log('[Agency] Migrations interrupted: the database version was not finalized.');
            return false;
        }

        // Enregistrer la version finale seulement après succès complet.
        update_option('agency_db_version', self::DB_VERSION);
        delete_option(self::OPTION_MIGRATION_LAST_ERROR);

        return true;
    }
    
    /**
     * Force recreate tables (debug only).
     *
     * Garde-fou : ne s'exécute que si WP_DEBUG est actif OU si l'option
     * agency_developer_mode est activée. Empêche un usage accidentel en prod
     * qui détruirait toutes les données client.
     */
    public function force_recreate_tables() {
        $is_debug    = defined('WP_DEBUG') && WP_DEBUG;
        $is_dev_mode = (bool) get_option('agency_developer_mode', 0);

        if (!$is_debug && !$is_dev_mode) {
            error_log('[Agency] force_recreate_tables() refused: neither WP_DEBUG nor agency_developer_mode is enabled.');
            return false;
        }

        if (!current_user_can('manage_options')) {
            error_log('[Agency] force_recreate_tables() refused: insufficient capabilities.');
            return false;
        }

        global $wpdb;

        $tables = array(
            'agency_block_presets',
            'agency_permissions',
            'agency_external_links',
            'agency_assets',
            'agency_comments',
            'agency_blocks',
            'agency_projects',
            'agency_clients',
        );

        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS $full_table");
        }

        $this->create_tables();
        return true;
    }
    
    /**
     * Exécuter les migrations de base de données depuis les fichiers
     *
     * @return bool True si toutes les migrations ont réussi, false sinon
     */
    public function run_migrations() {
        $current_version = get_option('agency_db_version', '0.0.0');
        $migrations = $this->get_migration_files();

        foreach ($migrations as $migration) {
            // Ignorer les migrations déjà appliquées
            if (version_compare($current_version, $migration['version'], '>=')) {
                continue;
            }

            require_once $migration['file'];

            if (!class_exists($migration['class'])) {
                $error_message = sprintf(
                    '[Agency] Migration class not found: %s (file %s)',
                    $migration['class'],
                    $migration['file']
                );
                error_log($error_message);
                update_option(self::OPTION_MIGRATION_LAST_ERROR, $error_message, false);
                return false;
            }

            $instance = new $migration['class']();
            $result = $instance->up();

            if ($result === false) {
                $error_message = sprintf(
                    '[Agency] Migration %s failed',
                    $migration['version']
                );
                error_log($error_message);
                update_option(self::OPTION_MIGRATION_LAST_ERROR, $error_message, false);
                return false;
            }

            // Mettre à jour la version seulement après succès
            update_option('agency_db_version', $migration['version']);
            $current_version = $migration['version'];
        }

        delete_option(self::OPTION_MIGRATION_LAST_ERROR);
        return true;
    }

    /**
     * Récupérer et trier les fichiers de migration
     *
     * @return array Liste de migrations triées par version ascendante
     */
    private function get_migration_files() {
        $migrations = array();
        $pattern = AGENCY_PLUGIN_DIR . 'includes/migrations/migration-*.php';
        $files = glob($pattern);

        if (empty($files)) {
            return $migrations;
        }

        foreach ($files as $file) {
            $basename = basename($file, '.php');
            // migration-1-2-0 → 1.2.0
            $version_part = str_replace('migration-', '', $basename);
            $version = str_replace('-', '.', $version_part);
            // migration-1-2-0 → Agency_Migration_1_2_0
            $class = 'Agency_Migration_' . str_replace('-', '_', $version_part);

            $migrations[] = array(
                'file'    => $file,
                'version' => $version,
                'class'   => $class,
            );
        }

        // Trier par version ascendante
        usort($migrations, function ($a, $b) {
            return version_compare($a['version'], $b['version']);
        });

        return $migrations;
    }
    
    /**
     * Supprimer toutes les tables (désinstallation complète)
     */
    public function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'agency_block_presets',
            $wpdb->prefix . 'agency_external_links',
            $wpdb->prefix . 'agency_comments',
            $wpdb->prefix . 'agency_assets',
            $wpdb->prefix . 'agency_blocks',
            $wpdb->prefix . 'agency_projects',
            $wpdb->prefix . 'agency_permissions',
            $wpdb->prefix . 'agency_clients',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('agency_db_version');
    }
}
