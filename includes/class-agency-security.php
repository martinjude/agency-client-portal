<?php
/**
 * Gestion de la sécurité et authentification
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Security {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Run access checks BEFORE rewrite template loading (priority 9 < 10)
        add_action('template_redirect', array($this, 'check_access'), 9);
        add_filter('agency_can_access_client', array($this, 'verify_client_access'), 10, 2);
        add_filter('agency_can_access_project', array($this, 'verify_project_access'), 10, 2);
    }
    
    /**
     * Vérifier l'accès aux pages Agency
     */
    public function check_access() {
        $client_slug = get_query_var('agency_client');

        if (empty($client_slug)) {
            return;
        }

        // Récupérer le client
        $client = Agency_Client::get_by_slug($client_slug);

        if (!$client) {
            self::render_access_denied(__('The requested client space does not exist.', 'agency-client-portal'));
            return;
        }

        // Allow admins and agency managers to bypass token requirement
        if (current_user_can('manage_agency_clients')) {
            return;
        }

        // Token-only access: require valid auth cookie for all client routes
        if (!self::can_access_client_by_token($client->id)) {
            // Distinguer cookie absent / cookie invalide
            if (!isset($_COOKIE['agency_client_auth'])) {
                self::render_access_denied(__('No access link detected. Please use the link provided by your agency.', 'agency-client-portal'));
            } else {
                self::render_access_denied(__('Your session has expired or your link is no longer valid. Please request a new access link.', 'agency-client-portal'));
            }
            return;
        }
    }

    /**
     * Afficher la page d'erreur d'accès refusé.
     *
     * Charge le template dédié templates/access-denied.php
     * avec un code HTTP 403 et des entêtes anti-cache.
     *
     * @param string $error_reason Message explicatif à afficher.
     * @return void
     */
    public static function render_access_denied($error_reason = '') {
        status_header(403);
        nocache_headers();

        $template = AGENCY_PLUGIN_DIR . 'templates/access-denied.php';

        if (file_exists($template)) {
            include $template;
        } else {
            wp_die(
                esc_html($error_reason ?: __('Access denied', 'agency-client-portal')),
                esc_html__('Access denied', 'agency-client-portal'),
                array('response' => 403)
            );
        }

        exit;
    }
    
    /**
     * Vérifier si l'utilisateur peut accéder au client
     */
    public function user_can_access_client($client_id) {
        // Deprecated: retain method signature, enforce token-only access
        return self::can_access_client_by_token($client_id);
    }
    
    /**
     * Vérifier l'accès client (filtrable)
     */
    public function verify_client_access($can_access, $client_id) {
        return $this->user_can_access_client($client_id);
    }
    
    /**
     * Vérifier l'accès projet (filtrable)
     */
    public function verify_project_access($can_access, $project_id) {
        $project = Agency_Project::get_by_id($project_id);
        
        if (!$project) {
            return false;
        }
        
        return self::can_access_client_by_token($project->client_id);
    }
    
    /**
     * Générer un token d'accès client sécurisé.
     *
     * Le hash SHA-256 (token_hash) sert à valider l'accès client. Le plaintext
     * est en plus persisté chiffré (token_secret, AES-256-CBC) afin que l'admin
     * puisse re-copier le lien indéfiniment sans devoir re-régénérer (ce qui
     * casserait un lien déjà envoyé au client).
     */
    public static function agency_generate_client_access_token($client_id) {
        global $wpdb;

        $client_id = intval($client_id);
        if (!$client_id) {
            return false;
        }

        // Token sécurisé (64 caractères hex = 32 bytes).
        $token = function_exists('random_bytes')
            ? bin2hex(random_bytes(32))
            : bin2hex(openssl_random_pseudo_bytes(32));

        // Le hash valide l'accès ; le plaintext chiffré permet de re-copier le lien.
        $token_hash = hash('sha256', $token);

        $table  = $wpdb->prefix . 'agency_clients';
        $result = $wpdb->update(
            $table,
            array(
                'token_hash'       => $token_hash,
                'token_secret'     => self::encrypt_token($token),
                'token_created_at' => current_time('mysql'),
                'token_expires_at' => null,
                'token_active'     => 1,
            ),
            array('id' => $client_id),
            array('%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );

        if ($result === false) {
            return false;
        }

        do_action('agency_client_token_generated', $client_id, $token_hash);

        return $token;
    }

    /**
     * Récupère le plaintext du token en déchiffrant token_secret.
     * Retourne null si aucun secret n'est stocké (clients antérieurs à la
     * migration 2.6.0 : une régénération unique est alors requise).
     */
    public static function get_recent_token_plaintext($client_id) {
        global $wpdb;

        $client_id = intval($client_id);
        if (!$client_id) {
            return null;
        }

        $table  = $wpdb->prefix . 'agency_clients';
        $secret = $wpdb->get_var($wpdb->prepare(
            "SELECT token_secret FROM $table WHERE id = %d AND token_active = 1",
            $client_id
        ));

        if (empty($secret)) {
            return null;
        }

        $plain = self::decrypt_token($secret);
        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    /**
     * Chiffre un token pour stockage en base (AES-256-CBC, clé dérivée d'AUTH_KEY).
     * Le format de sortie est base64(IV . cipher). Fallback gracieux sans OpenSSL.
     */
    private static function encrypt_token($value) {
        if (empty($value)) {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            return $value;
        }

        $key    = hash('sha256', AUTH_KEY, true);
        $iv     = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            return $value;
        }

        return base64_encode($iv . $cipher);
    }

    /**
     * Déchiffre un token stocké via encrypt_token. Retourne la valeur telle
     * quelle si elle n'apparaît pas chiffrée (robustesse).
     */
    private static function decrypt_token($encrypted) {
        if (empty($encrypted)) {
            return '';
        }

        if (!function_exists('openssl_decrypt')) {
            return $encrypted;
        }

        $raw = base64_decode($encrypted, true);

        if ($raw === false || strlen($raw) < 17) {
            return $encrypted;
        }

        $key    = hash('sha256', AUTH_KEY, true);
        $iv     = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $result = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return ($result !== false) ? $result : $encrypted;
    }
    
    /**
     * Obtenir un client par token (validation).
     *
     * Récupère le client par id, puis vérifie le token_hash en temps constant
     * via hash_equals pour éviter les attaques par timing.
     */
    public static function agency_get_client_by_token($client_id, $token) {
        global $wpdb;

        $client_id = intval($client_id);
        if (!$client_id || !is_string($token) || $token === '') {
            return null;
        }

        $expected_hash = hash('sha256', $token);

        $table  = $wpdb->prefix . 'agency_clients';
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table
             WHERE id = %d
               AND token_active = 1
               AND status = 'active'
               AND (token_expires_at IS NULL OR token_expires_at > NOW())",
            $client_id
        ));

        if (!$client || empty($client->token_hash)) {
            return null;
        }

        if (!hash_equals((string) $client->token_hash, $expected_hash)) {
            return null;
        }

        do_action('agency_client_token_used', $client_id, $expected_hash);

        return $client;
    }
    
    /**
     * Regénérer le token d'accès d'un client
     */
    public static function agency_regenerate_client_access_token($client_id) {
        global $wpdb;
        
        if (!$client_id) {
            return false;
        }
        
        // Vérifier que le client existe
        $client = Agency_Client::get_by_id($client_id);
        if (!$client) {
            return false;
        }
        
        // Hook avant régénération
        do_action('agency_client_token_before_regenerate', $client_id);
        
        // Générer un nouveau token
        $new_token = self::agency_generate_client_access_token($client_id);
        
        if ($new_token) {
            // Hook après régénération
            do_action('agency_client_token_regenerated', $client_id);
        }
        
        return $new_token;
    }
    
    /**
     * Révoquer le token d'un client
     */
    public static function agency_revoke_client_token($client_id) {
        global $wpdb;

        $client_id = intval($client_id);
        if (!$client_id) {
            return false;
        }

        $table = $wpdb->prefix . 'agency_clients';
        $result = $wpdb->update(
            $table,
            // Le secret chiffré disparaît avec la révocation : plus aucun lien copiable.
            array('token_active' => 0, 'token_secret' => null),
            array('id' => $client_id),
            array('%d', '%s'),
            array('%d')
        );

        if ($result !== false) {
            do_action('agency_client_token_revoked', $client_id);
        }

        return $result !== false;
    }
    
    /**
     * Sanitize les données d'entrée
     */
    public static function sanitize_input($data) {
        if (is_array($data)) {
            return array_map(array(__CLASS__, 'sanitize_input'), $data);
        }
        
        return sanitize_text_field($data);
    }
    
    /**
     * Vérifier le nonce
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Valider le cookie d'authentification client
     */
    public static function validate_auth_cookie() {
        if (!isset($_COOKIE['agency_client_auth'])) {
            return false;
        }
        
        $decoded_cookie = base64_decode(sanitize_text_field(wp_unslash($_COOKIE['agency_client_auth'])), true);
        if ($decoded_cookie === false) {
            self::clear_auth_cookie();
            return false;
        }

        $cookie_data = json_decode($decoded_cookie, true);
        
        if (!$cookie_data || !isset($cookie_data['client_id'], $cookie_data['token_hash'], $cookie_data['created'], $cookie_data['expires'], $cookie_data['sig'])) {
            self::clear_auth_cookie();
            return false;
        }

        $expected_sig = hash_hmac(
            'sha256',
            $cookie_data['client_id'] . '|' . $cookie_data['token_hash'] . '|' . $cookie_data['created'] . '|' . $cookie_data['expires'],
            wp_salt('auth')
        );

        if (!hash_equals($expected_sig, (string) $cookie_data['sig'])) {
            self::clear_auth_cookie();
            return false;
        }
        
        // Vérifier l'expiration
        if (isset($cookie_data['expires']) && $cookie_data['expires'] < time()) {
            self::clear_auth_cookie();
            return false;
        }
        
        // Vérifier que le token hash correspond à un client actif
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE id = %d 
             AND token_hash = %s 
             AND token_active = 1 
             AND status = 'active'
             AND (token_expires_at IS NULL OR token_expires_at > NOW())",
            $cookie_data['client_id'],
            $cookie_data['token_hash']
        ));
        
        if ($client) {
            return array(
                'client_id' => $client->id,
                'client' => $client,
                'cookie_data' => $cookie_data
            );
        }
        
        // Token invalide : nettoyer le cookie
        self::clear_auth_cookie();
        return false;
    }
    
    /**
     * Nettoyer le cookie d'authentification
     */
    public static function clear_auth_cookie() {
        $secure = is_ssl();
        $httponly = true;

        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie('agency_client_auth', '', array(
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => 'Strict',
            ));
        } else {
            setcookie('agency_client_auth', '', time() - 3600, '/; samesite=Strict', '', $secure, $httponly);
        }

        unset($_COOKIE['agency_client_auth']);
    }
    
    /**
     * Vérifier si l'utilisateur actuel peut accéder à un client via token
     */
    public static function can_access_client_by_token($client_id) {
        // Allow administrators and Agency Managers to bypass token requirement
        if (is_user_logged_in() && current_user_can('manage_agency_clients')) {
            return true;
        }
        
        $auth_data = self::validate_auth_cookie();
        
        if (!$auth_data) {
            return false;
        }
        
        return intval($auth_data['client_id']) === intval($client_id);
    }
    
    /**
     * Obtenir le client authentifié via token
     */
    public static function get_authenticated_client() {
        $auth_data = self::validate_auth_cookie();
        
        if (!$auth_data) {
            return null;
        }
        
        return $auth_data['client'];
    }
}
