<?php
/**
 * Plugin Name: Agency Client Portal
 * Plugin URI: https://github.com/martinjude/agency-client-portal
 * Description: Client portal for creative agencies and freelances.
 * Version: 2.0.0
 * Author: Martin Jude
 * Author URI: https://martinjude.fr
 * Text Domain: agency-client-portal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('AGENCY_VERSION', '2.0.0');
define('AGENCY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AGENCY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AGENCY_PLUGIN_FILE', __FILE__);
define('AGENCY_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AGENCY_UPLOAD_DIR', WP_CONTENT_DIR . '/agency-uploads');
define('AGENCY_UPLOAD_URL', content_url() . '/agency-uploads');

// Autoloader
require_once AGENCY_PLUGIN_DIR . 'includes/class-agency-autoloader.php';
Agency_Autoloader::register();

// Classe principale du plugin
final class Agency_Plugin {
    /**
     * Instance unique du plugin (Singleton)
     */
    private static $instance = null;
    
    /**
     * Composants du plugin
     */
    public $database;
    public $roles;
    public $cpt;
    public $rewrite;
    public $api;
    public $security;
    public $assets;
    public $shortcodes;
    public $admin;
    public $frontend;
    public $cover_upload;
    public $extensions_manager;
    
    /**
     * Obtenir l'instance unique du plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Initialiser les hooks WordPress
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'), 0);
    }
    
    /**
     * Initialiser les composants du plugin.
     *
     * Composants chargés systématiquement : ceux qui exposent des hooks/AJAX/REST
     * pouvant être déclenchés depuis n'importe quel contexte.
     * Frontend est différé à `wp` pour ne pas s'instancier sur les requêtes admin-ajax.
     */
    private function init_components() {
        // Database
        $this->database = new Agency_Database();

        // Roles et capabilities
        $this->roles = new Agency_Roles();

        // Custom Post Types
        $this->cpt = new Agency_Post_Types();

        // Rewrite Rules
        $this->rewrite = new Agency_Rewrite_Rules();

        // REST API (constructeur léger ; ajoute juste rest_api_init)
        $this->api = new Agency_REST_API();

        // Sécurité et authentification (template_redirect priorité 9)
        $this->security = new Agency_Security();

        // Gestion des assets (constructeur léger ; ajoute init + AJAX handlers)
        $this->assets = new Agency_Assets_Manager();

        // Dossier d'upload pour les couvertures de projets
        $this->cover_upload = new Agency_Cover_Upload();

        // Shortcodes
        $this->shortcodes = new Agency_Shortcodes();

        // Confidentialité RGPD
        new Agency_Privacy();

        // Notifications email (hook commentaire + envoi manuel)
        Agency_Notifications::init();

        // Point d'extension pour les add-ons
        $this->extensions_manager = new Agency_Extensions_Manager();

        // Admin : instancié uniquement en contexte admin (inclut admin-ajax).
        if (is_admin()) {
            $this->admin = new Agency_Admin();
        }

        // Frontend : différé à `wp` (donc absent sur les hits admin-ajax,
        // cron, REST, et toute requête non-front).
        if (!is_admin()) {
            add_action('wp', array($this, 'maybe_init_frontend'), 1);
        }
    }

    /**
     * Instancie Agency_Frontend si on n'est pas en train de servir un endpoint
     * non-frontend (favicon, robots, sitemap, etc.).
     */
    public function maybe_init_frontend() {
        if ($this->frontend) {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        $this->frontend = new Agency_Frontend();
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        // Créer les tables personnalisées
        $db_created = $this->database->create_tables();
        if (!$db_created) {
            error_log('[Agency] Activation: failed to create/migrate tables.');
        }

        // Créer les rôles et capabilities
        $this->roles->create_roles();

        // Créer le dossier d'upload sécurisé
        $this->create_upload_directory();

        // Enregistrer les CPT et flush rewrite rules
        $this->cpt->register_post_types();
        $this->rewrite->add_rewrite_rules();
        flush_rewrite_rules();

        // Options par défaut
        add_option('agency_version', AGENCY_VERSION);
        add_option('agency_installed_date', current_time('mysql'));
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Charger les traductions
     */
    public function load_textdomain() {
        load_plugin_textdomain('agency-client-portal', false, dirname(AGENCY_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Initialisation WordPress
     */
	public function init() {
		do_action('agency_before_init');
		
		// Mettre à jour la base de données si nécessaire (migrations)
		$current_db_version = get_option('agency_db_version');
		if ($current_db_version !== Agency_Database::DB_VERSION) {
			// dbDelta gère les ALTER si colonnes manquantes
			$db_updated = $this->database->create_tables();
			if (!$db_updated) {
				error_log('[Agency] Init: database update failed (incomplete migrations).');
			}
		}

		// Rafraîchir les règles de réécriture si la version du plugin a changé
		$current_version = get_option( 'agency_version' );
		if ( false === $current_version ) {
			update_option( 'agency_version', AGENCY_VERSION );
		} elseif ( $current_version !== AGENCY_VERSION ) {
			add_action( 'init', array( $this, 'flush_rewrite_rules_on_update' ), 99 );
		}

		// Rafraîchir les règles si la version des rewrites ne correspond pas
		if ( get_option( 'agency_rewrite_rules_version' ) !== 'dashboard_v1' ) {
			add_action( 'init', array( $this, 'flush_rewrite_rules_on_update' ), 99 );
			update_option( 'agency_rewrite_rules_version', 'dashboard_v1' );
		}
		
		do_action('agency_after_init');
	}

	/**
	 * Rafraîchit les règles de réécriture après une mise à jour du plugin.
	 *
	 * @return void
	 */
	public function flush_rewrite_rules_on_update() {
		flush_rewrite_rules(false);
		update_option('agency_version', AGENCY_VERSION);
	}
    
    /**
     * Créer le dossier d'upload sécurisé
     */
    private function create_upload_directory() {
        if (!file_exists(AGENCY_UPLOAD_DIR)) {
            wp_mkdir_p(AGENCY_UPLOAD_DIR);
            
            // Protéger le dossier (.htaccess + index.php)
            Agency_Assets_Manager::protect_upload_directory();
        }
    }

    /**
     * Empêcher le clonage
     */
    private function __clone() {}
    
    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Fonction principale pour accéder à l'instance du plugin
 */
function agency() {
    return Agency_Plugin::get_instance();
}

// Démarrer le plugin
agency();
