<?php
/**
 * Administration backend
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Admin {
    
    /**
     * Constructeur
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_client_meta'));
        
        // Actions pour la gestion des tokens
        add_action('admin_post_agency_regenerate_token', array($this, 'regenerate_client_token'));
        add_action('admin_post_agency_revoke_token', array($this, 'revoke_client_token'));
        add_action('wp_ajax_agency_get_access_url', array($this, 'ajax_get_access_url'));
        // New AJAX endpoints for token actions to avoid full-page reloads
        add_action('wp_ajax_agency_regenerate_token', array($this, 'ajax_regenerate_token'));
        add_action('wp_ajax_agency_revoke_token', array($this, 'ajax_revoke_token'));
        
        // Vérification des prérequis système
        add_action('wp_ajax_agency_check_requirements', array($this, 'ajax_check_requirements'));
        
        // Action publique pour déconnexion token (clients non connectés WP)
        add_action('wp_ajax_nopriv_agency_token_logout', array($this, 'ajax_token_logout'));
        add_action('wp_ajax_agency_token_logout', array($this, 'ajax_token_logout'));
        
        // Notices d'information sur les changements
        add_action('admin_notices', array($this, 'show_migration_notices'));
        add_action('wp_ajax_agency_dismiss_migration_notice', array($this, 'ajax_dismiss_migration_notice'));

        // Redirect edit.php?post_type=agency_client vers agency-portal
        add_action('admin_init', array($this, 'redirect_clients_list'));

        // Redirect pages admin agency vers le dashboard frontend
        add_action('admin_init', array($this, 'redirect_to_frontend_dashboard'));

        // AJAX hard delete client
        add_action('wp_ajax_agency_hard_delete_client', array($this, 'ajax_hard_delete_client'));
    }

    /**
     * Ajouter le menu admin
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            __('Agency Portal', 'agency-client-portal'),
            __('Agency', 'agency-client-portal'),
            'manage_agency_clients',
            'agency-portal',
            array($this, 'render_dashboard'),
            'dashicons-groups',
            30
        );
        
        // Sous-menu : Dashboard
        add_submenu_page(
            'agency-portal',
            __('Dashboard', 'agency-client-portal'),
            __('Dashboard', 'agency-client-portal'),
            'manage_agency_clients',
            'agency-portal',
            array($this, 'render_dashboard')
        );
        
        // Sous-menu : Clients
        // (Retiré car déjà ajouté automatiquement par show_in_menu dans le CPT)
        
        // Sous-menu : Créer un client
        add_submenu_page(
            'agency-portal',
            __('New Client', 'agency-client-portal'),
            __('New Client', 'agency-client-portal'),
            'manage_agency_clients',
            'agency-new-client',
            array($this, 'render_new_client_form')
        );
        
        // Debug submenu (only for administrators with developer mode enabled)
        if (current_user_can('manage_options') && get_option('agency_developer_mode', 0)) {
            add_submenu_page(
                'agency-portal',
                __('Debug', 'agency-client-portal'),
                __('Debug', 'agency-client-portal'),
                'manage_options',
                'agency-debug',
                array($this, 'render_debug_page')
            );
            
        }

        // Sous-menu : Paramètres
        add_submenu_page(
            'agency-portal',
            __('Settings', 'agency-client-portal'),
            __('Settings', 'agency-client-portal'),
            'manage_options',
            'agency-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Redirect edit.php?post_type=agency_client vers agency-portal
     */
    public function redirect_clients_list() {
        global $pagenow;
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'agency_client') {
            wp_safe_redirect(admin_url('admin.php?page=agency-portal'));
            exit;
        }
    }

    /**
     * Redirige les pages admin agency-portal et agency-new-client vers le dashboard frontend
     */
    public function redirect_to_frontend_dashboard() {
        if (!current_user_can('manage_agency_clients')) {
            return;
        }
        global $pagenow;
        if ($pagenow !== 'admin.php') {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, sert uniquement au routage.
        if ($page === 'agency-portal') {
            wp_safe_redirect(home_url('/agency/dashboard/'));
            exit;
        }
        if ($page === 'agency-new-client') {
            wp_safe_redirect(home_url('/agency/dashboard/?action=new-client'));
            exit;
        }
    }

    /**
     * Charger les assets admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'agency') === false && get_post_type() !== 'agency_client') {
            return;
        }
        
        // Enqueue media uploader scripts for logo upload
        wp_enqueue_media();
        
        // Tokens + primitives UI partagés avec le frontend, chargés avant admin.css
        wp_enqueue_style('agency-tokens', AGENCY_PLUGIN_URL . 'assets/css/tokens.css', array(), AGENCY_VERSION);
        wp_enqueue_style('agency-ui-css', AGENCY_PLUGIN_URL . 'assets/css/ui.css', array('agency-tokens'), AGENCY_VERSION);
        wp_enqueue_style('agency-admin', AGENCY_PLUGIN_URL . 'assets/css/admin.css', array('agency-ui-css'), AGENCY_VERSION);
        wp_enqueue_script('agency-ui', AGENCY_PLUGIN_URL . 'assets/js/ui.js', array(), AGENCY_VERSION, true);
        wp_enqueue_script('agency-admin', AGENCY_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'agency-ui', 'wp-i18n'), AGENCY_VERSION, true);
        wp_set_script_translations('agency-admin', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');
        
        wp_localize_script('agency-admin', 'agencyAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('agency_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'agency-client-portal'),
            ),
        ));
    }
    
    /**
     * Ajouter les meta boxes
     */
    public function add_meta_boxes() {
        // Meta box pour les clients
        add_meta_box(
            'agency_client_details',
            __('Client Details', 'agency-client-portal'),
            array($this, 'render_client_meta_box'),
            'agency_client',
            'normal',
            'high'
        );
        
        add_meta_box(
            'agency_client_branding',
            __('Branding', 'agency-client-portal'),
            array($this, 'render_branding_meta_box'),
            'agency_client',
            'side',
            'default'
        );
        
        add_meta_box(
            'agency_client_access',
            __('Access & Security', 'agency-client-portal'),
            array($this, 'render_access_meta_box'),
            'agency_client',
            'side',
            'default'
        );
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        include AGENCY_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    /**
     * Render formulaire nouveau client
     */
    public function render_new_client_form() {
        include AGENCY_PLUGIN_DIR . 'templates/admin/new-client.php';
    }

    /**
     * Render settings
     */
    public function render_settings() {
        include AGENCY_PLUGIN_DIR . 'templates/admin/settings.php';
    }
    
    /**
     * Render client meta box
     */
    public function render_client_meta_box($post) {
        wp_nonce_field('agency_client_meta', 'agency_client_nonce');
        
        // Get the custom table client ID from post meta
        $client_id = get_post_meta($post->ID, 'agency_client_id', true);
        $client_data = $client_id ? Agency_Client::get_by_id($client_id) : null;
        
        include AGENCY_PLUGIN_DIR . 'templates/admin/meta-boxes/client-details.php';
    }
    
    /**
     * Render branding meta box
     */
    public function render_branding_meta_box($post) {
        $client_id = get_post_meta($post->ID, 'agency_client_id', true);
        $client_data = $client_id ? Agency_Client::get_by_id($client_id) : null;
        include AGENCY_PLUGIN_DIR . 'templates/admin/meta-boxes/branding.php';
    }
    
    /**
     * Render access meta box
     */
    public function render_access_meta_box($post) {
        $client_id = get_post_meta($post->ID, 'agency_client_id', true);
        $client_data = $client_id ? Agency_Client::get_by_id($client_id) : null;
        include AGENCY_PLUGIN_DIR . 'templates/admin/meta-boxes/access.php';
    }
    
    /**
     * Sauvegarder les métadonnées client
     */
    public function save_client_meta($post_id) {
        // Vérifications de sécurité
        if (!isset($_POST['agency_client_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['agency_client_nonce'])), 'agency_client_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('manage_agency_clients')) {
            return;
        }

        // Check if this is an agency_client post
        $post_type = get_post_type($post_id);
        if ($post_type !== 'agency_client') {
            return;
        }

        // Get the custom table client ID from post meta
        $client_id = get_post_meta($post_id, 'agency_client_id', true);

        if (!$client_id) {
            return;
        }

        // Prevent infinite loop: remove this hook temporarily
        remove_action('save_post', array($this, 'save_client_meta'));

        // Update post title if name changed
        if (isset($_POST['client_name'])) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field(wp_unslash($_POST['client_name'])),
            ));
        }

        // Re-add the hook
        add_action('save_post', array($this, 'save_client_meta'));

        // Update post meta
        if (isset($_POST['client_logo'])) {
            update_post_meta($post_id, 'logo_url', esc_url_raw(wp_unslash($_POST['client_logo'])));
        }
        if (isset($_POST['primary_color'])) {
            update_post_meta($post_id, 'primary_color', sanitize_hex_color(wp_unslash($_POST['primary_color'])));
        }
        if (isset($_POST['secondary_color'])) {
            update_post_meta($post_id, 'secondary_color', sanitize_hex_color(wp_unslash($_POST['secondary_color'])));
        }
        // Legacy user association removed: token-based access only

        // Update custom table
        $data = array(
            'name' => isset($_POST['client_name']) ? sanitize_text_field(wp_unslash($_POST['client_name'])) : '',
            'logo_url' => isset($_POST['client_logo']) ? esc_url_raw(wp_unslash($_POST['client_logo'])) : '',
            'primary_color' => isset($_POST['primary_color']) ? sanitize_hex_color(wp_unslash($_POST['primary_color'])) : '#000000',
            'secondary_color' => isset($_POST['secondary_color']) ? sanitize_hex_color(wp_unslash($_POST['secondary_color'])) : '#ffffff',
        );

        // Legacy user permissions removed

        // Handle status update in custom table
        if (isset($_POST['client_status'])) {
            $data['status'] = sanitize_text_field(wp_unslash($_POST['client_status']));
        }

        Agency_Client::update($client_id, $data);
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        global $wpdb;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Agency Plugin Debug', 'agency-client-portal')) . '</h1>';

        // Handle actions (avec vérification nonce)
        if (isset($_POST['agency_debug_action']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['agency_debug_nonce'])), 'agency_debug_action')) {
            if (isset($_POST['recreate_tables'])) {
                $database = agency()->database;
                $database->force_recreate_tables();
                echo '<div class="notice notice-success"><p>Tables recreated successfully!</p></div>';
            }

        }

        // Check if tables exist
        $tables = [
            'agency_clients',
            'agency_projects',
            'agency_blocks',
            'agency_comments',
            'agency_assets',
            'agency_permissions',
            'agency_external_links',
            'agency_block_presets'
        ];

        echo '<h2>Table Status</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr></thead>';
        echo '<tbody>';

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
            $row_count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM `$full_table_name`") : 0;

            echo '<tr>';
            echo '<td>' . esc_html($full_table_name) . '</td>';
            echo '<td>' . ($exists ? '<span style="color:green">EXISTS</span>' : '<span style="color:red">MISSING</span>') . '</td>';
            echo '<td>' . esc_html($row_count) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Show current user info
        echo '<h2>Current User & Environment Info</h2>';
        echo '<p><strong>User ID:</strong> ' . esc_html(get_current_user_id()) . '</p>';
        echo '<p><strong>User Login:</strong> ' . esc_html(wp_get_current_user()->user_login) . '</p>';
        echo '<p><strong>Can Manage Projects:</strong> ' . (current_user_can('manage_agency_projects') ? 'YES' : 'NO') . '</p>';

        // Show available clients for testing
        $clients = $wpdb->get_results("SELECT id, name, slug FROM {$wpdb->prefix}agency_clients ORDER BY id ASC LIMIT 5");
        echo '<p><strong>Available Clients for Testing:</strong></p>';
        if ($clients) {
            echo '<ul>';
            foreach ($clients as $client) {
                echo '<li>ID: ' . esc_html($client->id) . ', Name: ' . esc_html($client->name) . ', Slug: ' . esc_html($client->slug) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p><em>No clients found. You may need to create a client first.</em></p>';
        }

        // Action buttons — tous protégés par nonce
        $nonce = wp_create_nonce('agency_debug_action');

        echo '<div class="notice notice-error" style="border-left-color: #dc3545; padding: 12px 16px; margin-top: 20px;">';
        echo '<h3 style="color: #dc3545; margin-top: 0;">⚠️ ' . esc_html__('Danger zone', 'agency-client-portal') . '</h3>';
        echo '<p>' . esc_html__('These actions cannot be undone. Use them for debugging only.', 'agency-client-portal') . '</p>';
        
        echo '<form method="post">';
        echo '<input type="hidden" name="agency_debug_nonce" value="' . esc_attr($nonce) . '">';
        echo '<input type="hidden" name="agency_debug_action" value="1">';
        echo '<p><button type="submit" name="recreate_tables" class="button button-secondary" onclick="return confirm(\'This will drop and recreate all agency tables. Continue?\')">Recreate All Tables</button></p>';
        echo '</form>';
        
        echo '</div>';

        echo '</div>';
    }
    
    
    /**
     * Regénérer le token d'accès d'un client
     */
    public function regenerate_client_token() {
        // Vérifier les permissions
        if (!current_user_can('manage_agency_clients')) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'));
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'agency_regenerate_token')) {
            wp_die(esc_html__('Security error', 'agency-client-portal'));
        }
        
        $client_id = intval($_POST['client_id']);
        if (!$client_id) {
            wp_die(esc_html__('Invalid client ID', 'agency-client-portal'));
        }
        
        // Regénérer le token
        $new_token = Agency_Security::agency_regenerate_client_access_token($client_id);
        
        if ($new_token) {
            // Find CPT post ID linked to this client
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'agency_client_id' AND meta_value = %d LIMIT 1",
                $client_id
            ));
            // Redirect back to edit screen
            $redirect_url = $post_id ? get_edit_post_link($post_id, '') : admin_url('edit.php?post_type=agency_client');
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            wp_die(esc_html__('Error while regenerating the token', 'agency-client-portal'));
        }
    }
    
    /**
     * Révoquer le token d'accès d'un client
     */
    public function revoke_client_token() {
        // Vérifier les permissions
        if (!current_user_can('manage_agency_clients')) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'));
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'agency_revoke_token')) {
            wp_die(esc_html__('Security error', 'agency-client-portal'));
        }
        
        $client_id = intval($_POST['client_id']);
        if (!$client_id) {
            wp_die(esc_html__('Invalid client ID', 'agency-client-portal'));
        }
        
        // Révoquer le token
        $result = Agency_Security::agency_revoke_client_token($client_id);
        
        if ($result) {
            // Find CPT post ID linked to this client
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'agency_client_id' AND meta_value = %d LIMIT 1",
                $client_id
            ));
            // Redirect back to edit screen
            $redirect_url = $post_id ? get_edit_post_link($post_id, '') : admin_url('edit.php?post_type=agency_client');
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            wp_die(esc_html__('Error while revoking the token', 'agency-client-portal'));
        }
    }

    /**
     * AJAX : Suppression definitive d'un client avec cascade
     */
    public function ajax_hard_delete_client() {
        if (!current_user_can('manage_agency_clients')) {
            wp_send_json_error(__('Access denied', 'agency-client-portal'));
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_admin_nonce')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }

        $client_id = intval($_POST['client_id']);
        if (!$client_id) {
            wp_send_json_error(__('Invalid client ID', 'agency-client-portal'));
        }

        $client = Agency_Client::get_by_id($client_id);
        if (!$client) {
            wp_send_json_error(__('Client not found', 'agency-client-portal'));
        }

        Agency_Client::hard_delete($client_id);

        wp_send_json_success(array(
            'message' => sprintf(/* translators: %s: client name */ __('Client "%s" has been permanently deleted.', 'agency-client-portal'), $client->name),
            'redirect' => admin_url('admin.php?page=agency-portal'),
        ));
    }

    public function ajax_get_access_url() {
        // Vérifier les permissions
        if (!current_user_can('manage_agency_clients')) {
            wp_send_json_error(__('Access denied', 'agency-client-portal'));
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_admin_nonce')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }
        
        $client_id = intval($_POST['client_id']);
        if (!$client_id) {
            wp_send_json_error(__('Invalid client ID', 'agency-client-portal'));
        }
        
        // Vérifier que le client existe
        $client = Agency_Client::get_by_id($client_id);
        if (!$client) {
            wp_send_json_error(__('Client not found', 'agency-client-portal'));
        }

        $has_active_token = !empty($client->token_hash) && (int) $client->token_active === 1;

        $access_url = Agency_Client::get_access_url($client_id);

        if ($access_url) {
            wp_send_json_success(array(
                'access_url' => $access_url,
                'message'    => __('Access link generated successfully', 'agency-client-portal'),
            ));
        }

        // Cas distinctif : token actif d'avant la 2.6.0, dont le secret n'a jamais été stocké.
        if ($has_active_token) {
            wp_send_json_error(array(
                'code'    => 'token_plaintext_expired',
                'message' => __('This client\'s access link cannot be retrieved (created before the update). Click "Regenerate" once to produce a permanently copyable link — this will invalidate the old link.', 'agency-client-portal'),
            ));
        }

        error_log('[Agency] Failed to generate access URL for client ID: ' . $client_id);
        wp_send_json_error(__('Error while generating the link. Check that the client exists in the database.', 'agency-client-portal'));
    }
    
    /**
     * AJAX: Déconnexion token client
     */
    public function ajax_token_logout() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_token_logout')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }

        // Nettoyer le cookie d'authentification
        Agency_Security::clear_auth_cookie();
        
        wp_send_json_success(array(
            'message' => __('Logged out successfully', 'agency-client-portal'),
            'redirect' => home_url()
        ));
    }

    /**
     * AJAX: Regenerate client token
     */
    public function ajax_regenerate_token() {
        if (!current_user_can('manage_agency_clients')) {
            wp_send_json_error(__('Access denied', 'agency-client-portal'));
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_admin_nonce')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }
        $client_id = intval($_POST['client_id'] ?? 0);
        if (!$client_id) {
            wp_send_json_error(__('Invalid client ID', 'agency-client-portal'));
        }
        $token = Agency_Security::agency_regenerate_client_access_token($client_id);
        if ($token) {
            wp_send_json_success(array(
                'message' => __('Token regenerated', 'agency-client-portal')
            ));
        }
        wp_send_json_error(__('Failed to regenerate the token', 'agency-client-portal'));
    }

    /**
     * AJAX: Revoke client token
     */
    public function ajax_revoke_token() {
        if (!current_user_can('manage_agency_clients')) {
            wp_send_json_error(__('Access denied', 'agency-client-portal'));
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_admin_nonce')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }
        $client_id = intval($_POST['client_id'] ?? 0);
        if (!$client_id) {
            wp_send_json_error(__('Invalid client ID', 'agency-client-portal'));
        }
        $ok = Agency_Security::agency_revoke_client_token($client_id);
        if ($ok) {
            wp_send_json_success(array(
                'message' => __('Access revoked', 'agency-client-portal')
            ));
        }
        wp_send_json_error(__('Failed to revoke the token', 'agency-client-portal'));
    }
    
    /**
     * Afficher les notices de migration et d'information
     */
    public function show_migration_notices() {
        $screen = get_current_screen();
        
        // Afficher seulement sur les pages Agency
        if (!$screen || strpos($screen->id, 'agency') === false) {
            return;
        }

        $migration_error = get_option(Agency_Database::OPTION_MIGRATION_LAST_ERROR, '');
        if (!empty($migration_error)) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Incomplete database migration.', 'agency-client-portal'); ?></strong><br>
                    <?php echo esc_html($migration_error); ?>
                </p>
            </div>
            <?php
        }
        
        // Notice de migration vers le système de tokens (affichée une seule fois)
        if (!get_option('agency_token_migration_notice_dismissed', false)) {
            ?>
            <div class="notice notice-info is-dismissible" id="agency-migration-notice">
                <h3><?php esc_html_e('🎉 Agency Portal - New feature!', 'agency-client-portal'); ?></h3>
                <p><strong><?php esc_html_e('Simplified client access', 'agency-client-portal'); ?></strong></p>
                <p>
                    <?php esc_html_e('Your clients can now access their space without creating a WordPress account!', 'agency-client-portal'); ?><br>
                    <?php esc_html_e('➤ Use the "Generate Access Link" button on each client record.', 'agency-client-portal'); ?><br>
                    <?php esc_html_e('➤ The previous system remains available for your internal team.', 'agency-client-portal'); ?>
                </p>
                <p>
                    <a href="#" class="button button-primary" onclick="agency_dismiss_migration_notice(); return false;">
                        <?php esc_html_e('Got it', 'agency-client-portal'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=agency_client')); ?>" class="button">
                        <?php esc_html_e('View my clients', 'agency-client-portal'); ?>
                    </a>
                </p>
            </div>
            <?php ob_start(); ?>
            function agency_dismiss_migration_notice() {
                jQuery.post(ajaxurl, {
                    action: 'agency_dismiss_migration_notice',
                    nonce: '<?php echo esc_js(wp_create_nonce('agency_dismiss_notice')); ?>'
                }, function() {
                    jQuery('#agency-migration-notice').fadeOut();
                });
            }
            <?php wp_print_inline_script_tag(ob_get_clean()); ?>
            <?php
        }
        
        // Vérifier s'il y a des clients sans tokens
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        $clients_without_tokens = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE token_hash IS NULL AND status = 'active'"
        );
        
        if ($clients_without_tokens > 0) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Warning:', 'agency-client-portal'); ?></strong>
                    <?php echo esc_html(sprintf(
                        /* translators: %d: number of clients */
                        _n(
                            '%d client does not have an access link yet.',
                            '%d clients do not have an access link yet.',
                            $clients_without_tokens,
                            'agency-client-portal'
                        ),
                        $clients_without_tokens
                    )); ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=agency_client')); ?>" class="button button-small">
                        <?php esc_html_e('Fix now', 'agency-client-portal'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX: Fermer la notice de migration
     */
    public function ajax_dismiss_migration_notice() {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_dismiss_notice')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }
        
        update_option('agency_token_migration_notice_dismissed', true);
        wp_send_json_success();
    }

    /**
     * AJAX: Vérifier les prérequis système
     */
    public function ajax_check_requirements() {
        check_ajax_referer('agency_tests_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'agency-client-portal'));
        }
        
        require_once AGENCY_PLUGIN_DIR . 'includes/class-agency-requirements-check.php';
        $checker = new Agency_System_Requirements_Check();
        $summary = $checker->get_summary();
        
        wp_send_json_success($summary);
    }
    
    
}
