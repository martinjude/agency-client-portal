<?php
/**
 * Gestion des règles de réécriture d'URL
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Rewrite_Rules {
    
    /**
     * Constructeur
     */
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_frontend_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_edit_client_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
    }
    
    /**
     * Ajouter les règles de réécriture
     */
    public function add_rewrite_rules() {
        // Route : /agency/edit-client/{id}
        add_rewrite_rule(
            '^agency/edit-client/([0-9]+)/?$',
            'index.php?agency_edit_client=$matches[1]',
            'top'
        );

        // Route : /agency/dashboard
        add_rewrite_rule(
            '^agency/dashboard/?$',
            'index.php?agency_dashboard=1',
            'top'
        );

        // Route : /client-portal/ (pour authentification par token)
        add_rewrite_rule(
            '^client-portal/?$',
            'index.php?agency_token_auth=1',
            'top'
        );
        
        // Route : /client/{slug}
        add_rewrite_rule(
            '^client/([^/]+)/?$',
            'index.php?agency_client=$matches[1]',
            'top'
        );
        
        // Route : /client/{slug}/project/{project-slug}
        add_rewrite_rule(
            '^client/([^/]+)/project/([^/]+)/?$',
            'index.php?agency_client=$matches[1]&agency_project=$matches[2]',
            'top'
        );
        
        // Route : /client/{slug}/project/{project-slug}/block/{block-id}
        add_rewrite_rule(
            '^client/([^/]+)/project/([^/]+)/block/([0-9]+)/?$',
            'index.php?agency_client=$matches[1]&agency_project=$matches[2]&agency_block=$matches[3]',
            'top'
        );
    }
    
    /**
     * Ajouter les query vars personnalisées
     */
    public function add_query_vars($vars) {
        $vars[] = 'agency_client';
        $vars[] = 'agency_project';
        $vars[] = 'agency_block';
        $vars[] = 'agency_token_auth';
        $vars[] = 'agency_edit_client';
        $vars[] = 'agency_dashboard';
        return $vars;
    }
    
    /**
     * Gérer les routes frontend
     */
    public function enqueue_edit_client_assets() {
        if (!get_query_var('agency_edit_client')) {
            return;
        }
        wp_enqueue_media();
        // agency-frontend CSS + branding variables sont injectés par Agency_Frontend::enqueue_frontend_assets()
        wp_enqueue_style('agency-edit-client', AGENCY_PLUGIN_URL . 'assets/css/edit-client.css', array('agency-frontend'), AGENCY_VERSION);
        wp_enqueue_script('agency-edit-client', AGENCY_PLUGIN_URL . 'assets/js/edit-client.js', array('jquery', 'agency-ui', 'wp-i18n'), AGENCY_VERSION, true);
        wp_set_script_translations('agency-edit-client', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');

        $client_id = intval(get_query_var('agency_edit_client'));
        $client = Agency_Client::get_by_id($client_id);

        wp_localize_script('agency-edit-client', 'agencyAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('agency_admin_nonce'),
        ));
        wp_localize_script('agency-edit-client', 'agencyData', array(
            'rest_url'     => rest_url('agency/v1/'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'dashboard_url' => home_url('/agency/dashboard/'),
        ));
        wp_localize_script('agency-edit-client', 'agencyEditClient', array(
            'client_id'            => $client_id,
            'client_name'          => $client ? $client->name : '',
            'client_slug'          => $client ? $client->slug : '',
            'logo_url'             => $client ? (string) $client->logo_url : '',
            'primary_color'        => $client ? (string) $client->primary_color : '#000000',
            'secondary_color'      => $client ? (string) $client->secondary_color : '#ffffff',
            'background_image_url' => $client ? (string) $client->background_image_url : '',
            'status'               => $client ? (string) $client->status : 'active',
            'has_token'            => $client ? (bool) Agency_Client::has_active_token($client_id) : false,
            'token_created_at'     => $client ? (string) $client->token_created_at : '',
            'portal_url'           => $client ? home_url('/client/' . $client->slug . '/') : '',
        ));
    }

    public function handle_frontend_routes() {
        $edit_client_id = get_query_var('agency_edit_client');
        if ($edit_client_id) {
            $this->handle_edit_client_route(intval($edit_client_id));
            return;
        }

        if (get_query_var('agency_dashboard')) {
            $this->handle_dashboard_route();
            return;
        }

        $client_slug = get_query_var('agency_client');
        $project_slug = get_query_var('agency_project');
        $block_id = get_query_var('agency_block');
        $token_auth = get_query_var('agency_token_auth');
        
        // Fallback: handle token auth even if rewrite var isn't set
        // This covers cases where a physical "client-portal" page exists
        // and WordPress doesn't map our custom query var.
        if (!$token_auth && !empty($_GET['client_id']) && !empty($_GET['token'])) {
            $token_auth = 1;
        }
        
        // Gestion de l'authentification par token
        if ($token_auth) {
            $this->handle_token_authentication();
            return;
        }
        
        // Si aucune variable agency n'est définie, continuer normalement
        if (empty($client_slug)) {
            return;
        }
        
        // Charger le template approprié
        if (!empty($block_id)) {
            $this->load_template('block-view');
        } elseif (!empty($project_slug)) {
            $this->load_template('project-view');
        } else {
            $this->load_template('client-view');
        }
        
        exit;
    }
    
    /**
     * Charger un template personnalisé
     */
    private function load_template($template_name) {
        $template_path = AGENCY_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(esc_html__('Template not found', 'agency-client-portal'));
        }
    }
    
    /**
     * Gérer l'authentification par token depuis URL
     */
    private function handle_token_authentication() {
        // Vérifier si on revient d'un redirect d'erreur (token masqué dans l'URL)
        if (!empty($_GET['access_error'])) {
            $transient_key = 'agency_access_error_' . $this->get_visitor_hash();
            $error_reason  = get_transient($transient_key);
            delete_transient($transient_key);

            Agency_Security::render_access_denied(
                $error_reason ?: __('Invalid or expired access link.', 'agency-client-portal')
            );
            return;
        }

        // Récupérer les paramètres
        $client_id = intval($_GET['client_id'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));

        if (!$client_id || !$token) {
            $this->redirect_access_error(__('Invalid access link. Missing parameters.', 'agency-client-portal'));
            return;
        }

        // Vérifier le token
        $client = Agency_Security::agency_get_client_by_token($client_id, $token);

        if (!$client) {
            $this->redirect_access_error(__('Invalid or expired access link.', 'agency-client-portal'));
            return;
        }

        // Token valide : créer le cookie d'authentification
        $this->create_auth_cookie($client_id, $token);

        // Vérifier si on doit rediriger vers un bloc spécifique
        $redirect = isset($_GET['redirect']) ? esc_url_raw(urldecode(wp_unslash($_GET['redirect']))) : '';
        if ($redirect) {
            // Valider le chemin de redirection
            $pattern = '#^client/[^/]+/project/[^/]+/block/[0-9]+$#';
            if (preg_match($pattern, ltrim($redirect, '/'))) {
                $redirect_url = home_url($redirect);
            } else {
                // Fallback si le chemin est invalide
                $redirect_url = home_url('/client/' . $client->slug . '/');
            }
        } else {
            // Rediriger vers l'URL propre du client
            $redirect_url = home_url('/client/' . $client->slug . '/');
        }
        
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Rediriger vers une URL propre pour afficher l'erreur d'accès.
     * Le token n'apparaît jamais dans la barre d'adresse finale.
     *
     * @param string $error_reason Message d'erreur à transmettre.
     * @return void
     */
    private function redirect_access_error($error_reason) {
        $transient_key = 'agency_access_error_' . $this->get_visitor_hash();
        set_transient($transient_key, $error_reason, 60);

        wp_safe_redirect(home_url('/client-portal/?access_error=1'));
        exit;
    }

    /**
     * Générer un identifiant visiteur anonyme pour les transients d'erreur.
     *
     * @return string Hash court basé sur l'IP et le User-Agent.
     */
    private function get_visitor_hash() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return substr(hash('sha256', $ip . $ua), 0, 16);
    }
    
    /**
     * Gérer la route d'édition frontend d'un client
     */
    private function handle_edit_client_route($client_id) {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url('/agency/edit-client/' . $client_id . '/')));
            exit;
        }

        if (!current_user_can('manage_agency_clients')) {
            wp_die(esc_html__('Access denied. You do not have permission to edit clients.', 'agency-client-portal'), 403);
        }

        $client = Agency_Client::get_by_id($client_id);
        if (!$client || $client->status === 'deleted') {
            wp_die(esc_html__('Client not found.', 'agency-client-portal'), 404);
        }

        $this->load_template('edit-client');
        exit;
    }

    /**
     * Charger les assets de la page tableau de bord frontend
     */
    public function enqueue_dashboard_assets() {
        if (!get_query_var('agency_dashboard')) {
            return;
        }
        wp_enqueue_media(); // Nécessaire pour le sélecteur de logo dans le modal
        wp_enqueue_style('agency-dashboard', AGENCY_PLUGIN_URL . 'assets/css/dashboard.css', array('agency-frontend'), AGENCY_VERSION);
        wp_enqueue_script('agency-dashboard', AGENCY_PLUGIN_URL . 'assets/js/dashboard.js', array('jquery', 'agency-emoji-picker', 'agency-ui', 'wp-i18n'), AGENCY_VERSION, true);
        wp_set_script_translations('agency-dashboard', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');
        // Localisation explicite : garantit agencyData même si agency-frontend ne se charge pas
        wp_localize_script('agency-dashboard', 'agencyData', array(
            'rest_url'    => rest_url('agency/v1/'),
            'nonce'       => wp_create_nonce('wp_rest'),
            'ajax_url'    => admin_url('admin-ajax.php'),
            'admin_nonce' => wp_create_nonce('agency_admin_nonce'),
        ));
    }

    /**
     * Gérer la route tableau de bord frontend
     */
    private function handle_dashboard_route() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url('/agency/dashboard/')));
            exit;
        }

        if (!current_user_can('manage_agency_clients')) {
            wp_die(esc_html__('Access denied. You do not have permission to access the dashboard.', 'agency-client-portal'), 403);
        }

        $this->load_template('dashboard');
        exit;
    }

    /**
     * Créer le cookie d'authentification
     */
    private function create_auth_cookie($client_id, $token) {
        $token_hash = hash('sha256', $token);
        
        $cookie_data = array(
            'client_id' => $client_id,
            'token_hash' => $token_hash,
            'created' => time(),
            'expires' => time() + (30 * DAY_IN_SECONDS) // 30 jours par défaut
        );
        $cookie_data['sig'] = hash_hmac(
            'sha256',
            $cookie_data['client_id'] . '|' . $cookie_data['token_hash'] . '|' . $cookie_data['created'] . '|' . $cookie_data['expires'],
            wp_salt('auth')
        );
        
        $cookie_value = base64_encode(json_encode($cookie_data));
        
        // Configuration sécurisée du cookie
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Strict';        

        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie('agency_client_auth', $cookie_value, array(
                'expires' => $cookie_data['expires'],
                'path' => '/',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ));
            return;
        }

        setcookie(
            'agency_client_auth',
            $cookie_value,
            $cookie_data['expires'],
            '/',
            '',
            $secure,
            $httponly
        );
    }
    
}
