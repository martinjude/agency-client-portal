<?php
/**
 * Enregistrement des Custom Post Types
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Post_Types {
    /**
     * Synchroniser la création d'un post CPT projet avec la table personnalisée
     */
    public static function sync_create_project($post_id, $post, $update) {
        if ($post->post_type !== 'agency_project' || $update) return;

        // Empêcher la double création lorsque la requête provient de l'API REST.
        if (Agency_REST_API::is_creating_project_via_rest()) {
            return;
        }

        // Vérifier si déjà synchronisé
        if (get_post_meta($post_id, 'agency_project_id', true)) {
            return;
        }

        // Récupérer le client lié (via meta ou champ personnalisé si besoin)
        // Le nonce du formulaire d'édition est vérifié par WordPress avant save_post.
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!$client_id) {
            return;
        }
        
        $data = array(
            'client_id' => $client_id,
            'name' => $post->post_title,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        );
        $project_id = Agency_Project::create($data);
        if ($project_id) {
            update_post_meta($post_id, 'agency_project_id', $project_id);
        }
    }

    /**
     * Synchroniser la suppression d'un post CPT client avec la table personnalisée
     */
    public static function sync_delete_client($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'agency_client') {
            $client_id = get_post_meta($post_id, 'agency_client_id', true);
            if ($client_id) {
                Agency_Client::delete($client_id);
            }
        }
    }

    
    /**
     * Constructeur
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('save_post_agency_project', array(__CLASS__, 'sync_create_project'), 10, 3);
        add_action('before_delete_post', array(__CLASS__, 'sync_delete_client'));
        add_filter('manage_agency_project_posts_columns', array(__CLASS__, 'add_project_client_column'));
        add_action('manage_agency_project_posts_custom_column', array(__CLASS__, 'render_project_client_column'), 10, 2);
        add_action('admin_menu', array($this, 'remove_add_new_submenu'), 999);
    }
    
    /**
     * Remove "Add New" and "Clients" submenu items for agency_client
     */
    public function remove_add_new_submenu() {
        global $submenu;
        if (isset($submenu['agency-portal'])) {
            foreach ($submenu['agency-portal'] as $key => $item) {
                if ($item[2] === 'post-new.php?post_type=agency_client'
                    || $item[2] === 'edit.php?post_type=agency_client') {
                    unset($submenu['agency-portal'][$key]);
                }
            }
        }
    }

    /**
     * Ajouter une colonne "Client" dans la liste des projets (CPT)
     */
    public static function add_project_client_column($columns) {
        $columns['agency_client'] = __('Client', 'agency-client-portal');
        return $columns;
    }

    /**
     * Afficher le client dans la colonne personnalisée
     */
    public static function render_project_client_column($column, $post_id) {
        if ($column === 'agency_client') {
            $project_id = get_post_meta($post_id, 'agency_project_id', true);
            if ($project_id) {
                $project = Agency_Project::get_by_id($project_id);
                if ($project && $project->client_id) {
                    $client = Agency_Client::get_by_id($project->client_id);
                    if ($client) {
                        echo esc_html($client->name);
                    }
                }
            }
        }
    }
    
    /**
     * Enregistrer les Custom Post Types
     */
    public function register_post_types() {
        // CPT: Agency Client
        register_post_type('agency_client', array(
            'labels' => array(
                'name' => __('Clients', 'agency-client-portal'),
                'singular_name' => __('Client', 'agency-client-portal'),
                'add_new' => __('Add client', 'agency-client-portal'),
                'add_new_item' => __('Add new client', 'agency-client-portal'),
                'edit_item' => __('Edit client', 'agency-client-portal'),
                'new_item' => __('New client', 'agency-client-portal'),
                'view_item' => __('View client', 'agency-client-portal'),
                'search_items' => __('Search clients', 'agency-client-portal'),
                'not_found' => __('No clients found', 'agency-client-portal'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'agency-portal',
            'show_in_rest' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_agency_create_clients', // Custom cap to disable "Add New"
                'edit_post' => 'manage_agency_clients',
                'read_post' => 'manage_agency_clients',
                'delete_post' => 'manage_agency_clients',
                'edit_posts' => 'manage_agency_clients',
                'edit_others_posts' => 'manage_agency_clients',
                'publish_posts' => 'manage_agency_clients',
                'read_private_posts' => 'manage_agency_clients',
            ),
            'map_meta_cap' => false,
            'supports' => array('title', 'thumbnail'),
            'has_archive' => false,
            'rewrite' => false,
        ));
        // Le champ slug est masqué via admin.css (.post-type-agency_client #edit-slug-box)
        
        // CPT: Agency Project
        register_post_type('agency_project', array(
            'labels' => array(
                'name' => __('Projects', 'agency-client-portal'),
                'singular_name' => __('Project', 'agency-client-portal'),
                'add_new' => __('Add project', 'agency-client-portal'),
                'add_new_item' => __('Add new project', 'agency-client-portal'),
                'edit_item' => __('Edit project', 'agency-client-portal'),
                'new_item' => __('New project', 'agency-client-portal'),
                'view_item' => __('View project', 'agency-client-portal'),
                'search_items' => __('Search projects', 'agency-client-portal'),
                'not_found' => __('No projects found', 'agency-client-portal'),
            ),
            'public' => false,
            'show_ui' => false, // Hide from admin menu - projects are managed from frontend only
            'show_in_menu' => false,
            'show_in_rest' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'edit_post' => 'manage_agency_projects',
                'read_post' => 'manage_agency_projects',
                'delete_post' => 'manage_agency_projects',
                'edit_posts' => 'manage_agency_projects',
                'edit_others_posts' => 'manage_agency_projects',
                'publish_posts' => 'manage_agency_projects',
                'read_private_posts' => 'manage_agency_projects',
            ),
            'supports' => array('title', 'editor'),
            'has_archive' => false,
            'rewrite' => false,
        ));
    }
}