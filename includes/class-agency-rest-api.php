<?php
/**
 * REST API - Endpoints personnalisés
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_REST_API {

    /**
     * Namespace de l'API
     */
    const NAMESPACE = 'agency/v1';

    /**
     * Indique qu'une création de projet est en cours via la REST API.
     * Permet au sync CPT (Agency_Post_Types::sync_create_project) d'éviter
     * une double insertion sans mutation de $_POST.
     */
    private static $is_creating_project_via_rest = false;

    public static function is_creating_project_via_rest() {
        return self::$is_creating_project_via_rest;
    }

    /**
     * Constructeur
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Enregistrer les routes REST API
     */
    public function register_routes() {

        // Clients
        register_rest_route(self::NAMESPACE, '/clients', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_clients'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_client'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/clients/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_client'),
                'permission_callback' => array($this, 'check_client_access'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_client'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_client'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));
        
        // Projects
        register_rest_route(self::NAMESPACE, '/projects', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_projects'),
                'permission_callback' => array($this, 'check_project_access'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_project'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/projects/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_project'),
                'permission_callback' => array($this, 'check_project_access'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_project'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_project'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));

        // Projects reorder
        register_rest_route(self::NAMESPACE, '/projects/reorder', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reorder_projects'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));

        // Blocks
        register_rest_route(self::NAMESPACE, '/blocks', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_block'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/blocks/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_block'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_block'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));

        // Notification manuelle au client pour un bloc précis
        register_rest_route(self::NAMESPACE, '/blocks/(?P<id>\d+)/notify', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'notify_client'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));

        // Comments
        register_rest_route(self::NAMESPACE, '/comments', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_comment'),
                'permission_callback' => array($this, 'check_comment_permission'),
            ),
        ));
        
        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_comment'),
                'permission_callback' => array($this, 'check_comment_owner'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_comment'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));
        
        // Assets
        register_rest_route(self::NAMESPACE, '/assets/upload', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'upload_asset'),
            'permission_callback' => array($this, 'check_upload_permission'),
        ));
        
        register_rest_route(self::NAMESPACE, '/assets/reorder', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reorder_assets'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));
        
        register_rest_route(self::NAMESPACE, '/assets/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_asset'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_asset'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));
        
        // Blocks reorder
        register_rest_route(self::NAMESPACE, '/blocks/reorder', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reorder_blocks'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));
        
        // Block settings (partial update)
        register_rest_route(self::NAMESPACE, '/blocks/(?P<id>\d+)/settings', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_block_settings'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));
        
        // External Links
        register_rest_route(self::NAMESPACE, '/external-links', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_external_link'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));

        register_rest_route(self::NAMESPACE, '/external-links/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_external_link'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_external_link'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));

        // Archived projects
        register_rest_route(self::NAMESPACE, '/projects/archived', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_archived_projects'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));

        // Image assets d'un projet (pour le sélecteur de fond de block)
        register_rest_route(self::NAMESPACE, '/projects/(?P<id>\d+)/assets', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_project_image_assets'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));

        // Slide suggestion upload (clients)
        register_rest_route(self::NAMESPACE, '/assets/suggest', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'suggest_slide'),
            'permission_callback' => array($this, 'check_comment_permission'),
        ));

        // Slide placeholder creation (clients + managers)
        register_rest_route(self::NAMESPACE, '/assets/create-placeholder', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_placeholder_asset'),
            'permission_callback' => array($this, 'check_comment_permission'),
        ));

        // Upload client pour bloc dépôt de fichiers
        register_rest_route(self::NAMESPACE, '/assets/deposit-upload', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'deposit_upload'),
            'permission_callback' => array($this, 'check_comment_permission'),
        ));

        // Block presets
        register_rest_route(self::NAMESPACE, '/block-presets', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_block_presets'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_block_preset'),
                'permission_callback' => array($this, 'check_manage_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/block-presets/(?P<id>\d+)/import', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'import_block_preset'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));

        register_rest_route(self::NAMESPACE, '/block-presets/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_block_preset'),
            'permission_callback' => array($this, 'check_manage_permission'),
        ));
    }
    
    /**
     * Vérifier si l'utilisateur peut gérer le contenu.
     */
    public function check_manage_permission() {
        return current_user_can('manage_agency_projects');
    }
    
    /**
     * Vérifier l'accès client
     */
    public function check_client_access($request) {
        if (current_user_can('manage_agency_projects')) {
            return true;
        }

        $client_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$client_id) {
            return false;
        }

        return Agency_Security::can_access_client_by_token($client_id);
    }
    
    /**
     * Vérifier l'accès projet
     */
    public function check_project_access($request) {
        if (current_user_can('manage_agency_projects')) {
            return true;
        }

        // /projects/{id}
        if (isset($request['id'])) {
            $project = Agency_Project::get_by_id(intval($request['id']));
            if (!$project) {
                return false;
            }
            return Agency_Security::can_access_client_by_token($project->client_id);
        }

        // /projects?client_id=...
        $client_id = intval($request->get_param('client_id'));
        if (!$client_id) {
            return false;
        }

        return Agency_Security::can_access_client_by_token($client_id);
    }
    
    /**
     * Vérifier la permission de commentaire
     */
    public function check_comment_permission($request) {
        // Managers keep full access
        if (current_user_can('manage_agency_projects') || current_user_can('add_agency_comments')) {
            return true;
        }

        // Token-based client access: ensure the block (or asset) belongs to the authenticated client
        $data = $request->get_json_params();
        $block_id = isset($data['block_id']) ? intval($data['block_id']) : intval($request->get_param('block_id'));
        $asset_id = isset($data['asset_id']) ? intval($data['asset_id']) : intval($request->get_param('asset_id'));

        // If an asset is provided, resolve its block
        if (!$block_id && $asset_id && class_exists('Agency_Asset')) {
            $asset = Agency_Asset::get_by_id($asset_id);
            if ($asset) {
                $block_id = intval($asset->block_id);
            }
        }

        if (!$block_id) {
            return false;
        }

        $block = Agency_Block::get_by_id($block_id);
        if (!$block) {
            return false;
        }

        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project) {
            return false;
        }

        // Allow if current token corresponds to the client owning the project
        return Agency_Security::can_access_client_by_token($project->client_id);
    }
    
    /**
     * Vérifier la permission d'upload
     */
    public function check_upload_permission() {
        return current_user_can('upload_agency_assets');
    }
    
    /**
     * Vérifier si l'utilisateur est propriétaire du commentaire
     */
    public function check_comment_owner($request) {
        $comment_id = $request['id'];
        $comment = Agency_Comment::get_by_id($comment_id);
        
        if (!$comment) {
            return false;
        }
        
        if (current_user_can('manage_agency_projects')) {
            return true;
        }

        // Standard WP-authored comment ownership.
        if ((int) $comment->user_id > 0) {
            return get_current_user_id() === (int) $comment->user_id;
        }

        // Token-authored comment (user_id = 0): validate ownership through client token.
        $block = Agency_Block::get_by_id($comment->block_id);
        if (!$block) {
            return false;
        }

        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project) {
            return false;
        }

        return Agency_Security::can_access_client_by_token($project->client_id);
    }

    // ===== CALLBACKS (implémentations basiques) =====
    
    public function get_clients($request) {
        $clients = Agency_Client::get_all();
        $clients = array_map(array($this, 'format_client_for_rest'), $clients);
        return rest_ensure_response($clients);
    }
    
    public function get_client($request) {
        $client = Agency_Client::get_by_id($request['id']);
        if ($client) {
            $client = $this->format_client_for_rest($client);
        }
        return rest_ensure_response($client);
    }
    
    public function create_client($request) {
        $data = $request->get_json_params();
        $client_id = Agency_Client::create($data);
        return rest_ensure_response(array('id' => $client_id, 'success' => true));
    }
    
    public function update_client($request) {
        $data = $request->get_json_params();
        $updated = Agency_Client::update($request['id'], $data);
        $client = $updated ? $this->format_client_for_rest(Agency_Client::get_by_id($request['id'])) : null;
        return rest_ensure_response(array('success' => $updated, 'client' => $client));
    }
    
    public function delete_client($request) {
        $deleted = Agency_Client::delete($request['id']);
        return rest_ensure_response(array('success' => $deleted));
    }
    
    public function get_projects($request) {
        $client_id = $request->get_param('client_id');
        $projects = Agency_Project::get_by_client($client_id);
        $formatted_projects = array_map(array($this, 'format_project_for_rest'), $projects);
        return rest_ensure_response($formatted_projects);
    }
    
    public function get_project($request) {
        $project = Agency_Project::get_by_id($request['id']);
        if ($project) {
            $project = $this->format_project_for_rest($project);
        }
        return rest_ensure_response($project);
    }
    
    public function create_project($request) {
        $data = $request->get_json_params();

        // Validate input
        if (empty($data['client_id']) || empty($data['name'])) {
            return new WP_Error(
                'missing_fields',
                __('Client ID and Project Name are required.', 'agency-client-portal'),
                array('status' => 400)
            );
        }

        // Additional validation - check if client exists
        $client = Agency_Client::get_by_id($data['client_id']);
        if (!$client) {
            return new WP_Error(
                'invalid_client',
                __('Client does not exist.', 'agency-client-portal'),
                array('status' => 400)
            );
        }

        // Signal au sync CPT (Agency_Post_Types::sync_create_project) qu'il ne doit
        // pas dupliquer la création — nettoyé en sortie via try/finally.
        self::$is_creating_project_via_rest = true;

        try {
            $project_id = Agency_Project::create($data);

            if (!$project_id) {
                return new WP_Error(
                    'project_creation_failed',
                    __('Failed to create project. Check error logs for details.', 'agency-client-portal'),
                    array('status' => 500)
                );
            }

            $project = Agency_Project::get_by_id($project_id);
            $project_url = '';
            if ($project && $client) {
                $project_url = home_url("/client/{$client->slug}/project/{$project->slug}");
            }

            return rest_ensure_response(array('id' => $project_id, 'success' => true, 'data' => array('url' => $project_url)));

        } catch (Exception $e) {
            return new WP_Error(
                'project_creation_exception',
                /* translators: %s: exception message */
                sprintf(__('Exception during project creation: %s', 'agency-client-portal'), $e->getMessage()),
                array('status' => 500)
            );
        } finally {
            self::$is_creating_project_via_rest = false;
        }
    }
    
	public function update_project($request) {
		$data = $request->get_json_params();
		
		// Handle cover_image_asset_id explicitly
		if (isset($data['cover_image_asset_id'])) {
			$data['cover_image_asset_id'] = intval($data['cover_image_asset_id']);
		}

		// Sanitiser cover_image_thumbnail_url si présent
		if (array_key_exists('cover_image_thumbnail_url', $data)) {
			$data['cover_image_thumbnail_url'] = $data['cover_image_thumbnail_url']
				? esc_url_raw((string) $data['cover_image_thumbnail_url'])
				: '';
		}

		// Sanitiser le subtitle si présent
		if (isset($data['subtitle'])) {
			$data['subtitle'] = sanitize_textarea_field($data['subtitle']);
        }
        
        $updated = Agency_Project::update($request['id'], $data);
        return rest_ensure_response(array('success' => $updated));
    }
    
    public function delete_project($request) {
        $deleted = Agency_Project::delete($request['id']);
        return rest_ensure_response(array('success' => $deleted));
    }

    /**
     * Réorganiser les projets
     */
    public function reorder_projects($request) {
        $data = $request->get_json_params();
        $project_ids = isset($data['project_ids']) ? $data['project_ids'] : array();
        $client_id = isset($data['client_id']) ? intval($data['client_id']) : 0;

        if (empty($project_ids) || !$client_id) {
            return new WP_Error('no_projects', __('No project provided or client missing', 'agency-client-portal'), array('status' => 400));
        }

        $updated = Agency_Project::reorder($client_id, $project_ids);
        return rest_ensure_response(array('success' => $updated));
    }

    public function create_block($request) {
        $data = $request->get_json_params();
        $block_id = Agency_Block::create($data);
        return rest_ensure_response(array('id' => $block_id, 'success' => true));
    }
    
    public function update_block($request) {
        $data = $request->get_json_params();

        // Merge settings: fetch the existing block's settings and merge
        if (isset($data['settings'])) {
            $block = Agency_Block::get_by_id($request['id']);
            if ($block && !empty($block->settings)) {
                $existing_settings = json_decode($block->settings, true);
                if (is_array($existing_settings)) {
                    $data['settings'] = array_merge($existing_settings, $data['settings']);
                }
            }
        }

        $updated = Agency_Block::update($request['id'], $data);
        return rest_ensure_response(array('success' => $updated));
    }
    
    /**
     * Envoie une notification email manuelle au client pour un bloc précis.
     */
    public function notify_client($request) {

        $data = $request->get_json_params();

        $args = array(
            'subject' => isset($data['subject']) ? sanitize_text_field($data['subject']) : '',
            'name'    => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'message' => isset($data['message']) ? wp_kses_post($data['message']) : '',
            'email'   => isset($data['email']) ? sanitize_email($data['email']) : '',
        );

        $result = Agency_Notifications::send_manual_notification(intval($request['id']), $args);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Email sent to the client.', 'agency-client-portal'),
        ));
    }

    public function delete_block($request) {
        $deleted = Agency_Block::delete($request['id']);
        return rest_ensure_response(array('success' => $deleted));
    }
    
    public function create_comment($request) {
        $data = $request->get_json_params();
        $comment_id = Agency_Comment::create($data);
        return rest_ensure_response(array('id' => $comment_id, 'success' => true));
    }
    
    public function update_comment($request) {
        $data = $request->get_json_params();
        $updated = Agency_Comment::update($request['id'], $data);
        return rest_ensure_response(array('success' => $updated));
    }
    
    public function delete_comment($request) {
        $deleted = Agency_Comment::delete($request['id']);
        return rest_ensure_response(array('success' => $deleted));
    }
    
    public function upload_asset($request) {
        $files = $request->get_file_params();
        $block_id = $request->get_param('block_id');
        
        if (empty($block_id)) {
            return new WP_Error('no_block_id', __('Missing block ID', 'agency-client-portal'), array('status' => 400));
        }

        $block = Agency_Block::get_by_id($block_id);
        if (!$block) {
            return new WP_Error('block_not_found', __('Block not found', 'agency-client-portal'), array('status' => 404));
        }

        $meta = array(
            'version_label'   => $request->get_param('version_label'),
            'version_status'  => $request->get_param('version_status'),
            'version_notes'   => $request->get_param('version_notes'),
            'parent_asset_id' => $request->get_param('parent_asset_id') ? intval($request->get_param('parent_asset_id')) : null,
            'version_number'  => $request->get_param('version_number') ? intval($request->get_param('version_number')) : null,
            'after_asset_id'  => $request->get_param('after_asset_id') ? intval($request->get_param('after_asset_id')) : null,
        );
        
        // Check if files[] array exists (multiple files)
        if (isset($files['files']) && is_array($files['files'])) {
            $results = array();
            $file_count = count($files['files']['name']);
            $next_version = Agency_Asset::get_next_version_number($block_id);
            
            for ($i = 0; $i < $file_count; $i++) {
                // Reconstruct the file array for each file
                $single_file = array(
                    'name' => $files['files']['name'][$i],
                    'type' => $files['files']['type'][$i],
                    'tmp_name' => $files['files']['tmp_name'][$i],
                    'error' => $files['files']['error'][$i],
                    'size' => $files['files']['size'][$i],
                );
                
                $meta['version_number'] = $next_version + $i;
                $result = agency()->assets->upload_file($single_file, $block_id, $meta);
                
                if (is_wp_error($result)) {
                    $results[] = array('success' => false, 'error' => $result->get_error_message());
                } else {
                    $results[] = $result;
                }
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => sprintf(/* translators: %d: number of uploaded files */ __('%d file(s) uploaded', 'agency-client-portal'), count($results)),
                'results' => $results
            ));
        }
        
        // Single file upload
        if (!isset($files['file'])) {
            return new WP_Error('no_file', __('No file provided', 'agency-client-portal'), array('status' => 400));
        }

        $result = agency()->assets->upload_file($files['file'], $block_id, $meta);
        
        if (is_wp_error($result)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $result->get_error_message()
            ));
        }
        
        return rest_ensure_response($result);
    }
    
    public function delete_asset($request) {
        $deleted = Agency_Asset::delete($request['id']);
        return rest_ensure_response(array('success' => $deleted));
    }
    
    public function update_asset($request) {
        $data = $request->get_json_params();
        $updated = Agency_Asset::update($request['id'], $data);
        return rest_ensure_response(array('success' => $updated));
    }
    
    public function reorder_assets($request) {
        $data = $request->get_json_params();
        $asset_ids = isset($data['asset_ids']) ? $data['asset_ids'] : array();
        
        if (empty($asset_ids)) {
            return new WP_Error('no_assets', __('No asset provided', 'agency-client-portal'), array('status' => 400));
        }
        
        $updated = Agency_Asset::update_order($asset_ids);
        return rest_ensure_response(array('success' => $updated));
    }
    
    public function reorder_blocks($request) {
        $data = $request->get_json_params();
        $block_ids = isset($data['block_ids']) ? $data['block_ids'] : array();
        
        if (empty($block_ids)) {
            return new WP_Error('no_blocks', __('No block provided', 'agency-client-portal'), array('status' => 400));
        }
        
        $updated = Agency_Block::update_order($block_ids);
        return rest_ensure_response(array('success' => $updated));
    }
    
    /**
     * Met à jour partiellement les settings JSON d'un bloc (merge, pas remplacement).
     */
    public function update_block_settings($request) {
        $block_id = intval($request['id']);
        $block = Agency_Block::get_by_id($block_id);

        if (!$block) {
            return new WP_Error('not_found', __('Block not found.', 'agency-client-portal'), array('status' => 404));
        }

        // Lire les settings existants
        $current_settings = !empty($block->settings) ? json_decode($block->settings, true) : array();
        if (!is_array($current_settings)) {
            $current_settings = array();
        }

        // Clés autorisées à merger
        $allowed_keys = array('subtitle', 'google_slides_url', 'embed_urls');
        $new_settings = $request->get_json_params();

        foreach ($allowed_keys as $key) {
            if (array_key_exists($key, $new_settings)) {
                if ($key === 'google_slides_url') {
                    $value = esc_url_raw(sanitize_text_field($new_settings[$key]));
                    if (empty($value)) {
                        unset($current_settings[$key]);
                    } else {
                        $current_settings[$key] = $value;
                    }
                } elseif ($key === 'embed_urls') {
                    // Tableau d'URLs — chaque entrée a url et title
                    if (is_array($new_settings[$key])) {
                        $current_settings[$key] = array_map(function($item) {
                            return array(
                                'url'   => isset($item['url']) ? esc_url_raw($item['url']) : '',
                                'title' => isset($item['title']) ? sanitize_text_field($item['title']) : '',
                            );
                        }, $new_settings[$key]);
                    }
                } else {
                    $current_settings[$key] = sanitize_text_field($new_settings[$key]);
                }
            }
        }

        $result = Agency_Block::update($block_id, array(
            'settings' => $current_settings,
        ));

        if (!$result) {
            return new WP_Error('update_failed', __('Failed to update settings.', 'agency-client-portal'), array('status' => 500));
        }

        return rest_ensure_response(array(
            'success'  => true,
            'settings' => $current_settings,
        ));
    }
    
    /**
     * Créer un lien externe
     */
    public function create_external_link($request) {
        $data = $request->get_json_params();

        $url = isset($data['url']) ? esc_url_raw((string) $data['url']) : '';
        if (empty($url)) {
            return new WP_Error('invalid_url', __('Invalid URL', 'agency-client-portal'), array('status' => 400));
        }
        
        $link_data = array(
            'block_id' => isset($data['block_id']) ? intval($data['block_id']) : null,
            'project_id' => isset($data['project_id']) ? intval($data['project_id']) : null,
            'url' => $url,
            'text' => isset($data['text']) ? sanitize_text_field($data['text']) : '',
            'emoji' => isset($data['emoji']) ? sanitize_text_field((string) $data['emoji']) : '🔗',
            'created_by' => get_current_user_id(),
        );
        
        $link_id = Agency_External_Link::create($link_data);
        
        if (is_wp_error($link_id)) {
            return $link_id;
        }
        
        $link = Agency_External_Link::get_by_id($link_id);
        return rest_ensure_response(array(
            'success' => true,
            'link' => $link
        ));
    }
    
    /**
     * Mettre à jour un lien externe
     */
    public function update_external_link($request) {
        $link_id = $request['id'];
        $data = $request->get_json_params();
        
        $update_data = array();
        if (isset($data['url'])) {
            $sanitized_url = esc_url_raw((string) $data['url']);
            if (empty($sanitized_url)) {
                return new WP_Error('invalid_url', __('Invalid URL', 'agency-client-portal'), array('status' => 400));
            }
            $update_data['url'] = $sanitized_url;
        }
        if (isset($data['text'])) {
            $update_data['text'] = sanitize_text_field($data['text']);
        }
        if (isset($data['emoji'])) {
            $update_data['emoji'] = sanitize_text_field((string) $data['emoji']);
        }
        
        $updated = Agency_External_Link::update($link_id, $update_data);
        
        return rest_ensure_response(array(
            'success' => $updated,
            'link' => Agency_External_Link::get_by_id($link_id)
        ));
    }
    
    /**
     * Supprimer un lien externe
     */
    public function delete_external_link($request) {
        $link_id = $request['id'];
        $deleted = Agency_External_Link::delete($link_id);

        return rest_ensure_response(array('success' => $deleted));
    }

    /**
     * Obtenir les projets archivés d'un client
     */
    public function get_archived_projects($request) {
        $client_id = intval($request->get_param('client_id'));
        if (!$client_id) {
            return new WP_Error('missing_client_id', __('Missing client_id', 'agency-client-portal'), array('status' => 400));
        }
        $projects = Agency_Project::get_archived_by_client($client_id);
        $formatted_projects = array_map(array($this, 'format_project_for_rest'), $projects);
        return rest_ensure_response($formatted_projects);
    }

    /**
     * Formater un projet pour les réponses REST API
     * Ajoute les champs nécessaires comme l'URL de la miniature
     */
	private function format_project_for_rest($project) {
		// Si c'est déjà un objet formaté, le retourner tel quel
		if (is_array($project)) {
			return $project;
		}

        // Cloner l'objet pour éviter de modifier l'original
        $formatted = clone $project;

		// Ajouter l'URL de la miniature
		$formatted->cover_image_thumbnail_url = Agency_Project::get_cover_thumbnail_url($project);
		$formatted->cover_image_full_url      = Agency_Project::get_cover_full_url($project);

		return $formatted;
	}

    /**
     * Formater un client pour les réponses REST API
     * en retirant les secrets liés à l'authentification.
     */
    private function format_client_for_rest($client) {
        if (is_array($client)) {
            $client = (object) $client;
        }

        if (!is_object($client)) {
            return $client;
        }

        $formatted = (array) $client;
        unset($formatted['access_token'], $formatted['token_hash'], $formatted['token_secret']);

        return $formatted;
    }

    /**
     * Retourner les assets image d'un projet pour le sélecteur de fond de block
     */
    public function get_project_image_assets($request) {
        $project_id = intval($request['id']);
        if (!$project_id) {
            return new WP_Error('missing_project_id', __('Missing project ID', 'agency-client-portal'), array('status' => 400));
        }

        $assets = Agency_Asset::get_images_by_project($project_id);
        $result = array();
		foreach ($assets as $asset) {
			$result[] = array(
				'id'            => intval($asset->id),
				'filename'      => esc_html($asset->original_filename),
				'url'           => Agency_Asset::get_url($asset->id),
				'thumbnail_url' => Agency_Asset::get_thumbnail_url($asset->id),
				'type'          => 'image',
			);
		}
        return rest_ensure_response($result);
    }

    /**
     * Upload client vers un bloc dépôt de fichiers
     */
    public function deposit_upload($request) {
        $files    = $request->get_file_params();
        $block_id = intval($request->get_param('block_id'));

        if (!$block_id) {
            return new WP_Error('no_block_id', __('Missing block ID', 'agency-client-portal'), array('status' => 400));
        }

        $block = Agency_Block::get_by_id($block_id);
        if (!$block || $block->type !== 'file_deposit') {
            return new WP_Error('invalid_block', __('Deposit block not found or wrong type', 'agency-client-portal'), array('status' => 400));
        }

        if (!isset($files['file'])) {
            return new WP_Error('no_file', __('No file provided', 'agency-client-portal'), array('status' => 400));
        }

        $result = agency()->assets->upload_file(
            $files['file'],
            $block_id,
            array('suggested_by_client' => !current_user_can('manage_agency_projects')),
            true // skip_permission_check
        );

        if (is_wp_error($result)) {
            return new WP_REST_Response(array('success' => false, 'message' => $result->get_error_message()), 400);
        }

        return rest_ensure_response(array('success' => true, 'asset_id' => $result['asset_id']));
    }

    /**
     * Lister tous les presets
     */
    public function get_block_presets($request) {
        $presets = Agency_Block_Preset::get_all();
        $result  = array();
        foreach ($presets as $preset) {
            $result[] = array(
                'id'            => intval($preset->id),
                'name'          => esc_html($preset->name),
                'created_at'    => $preset->created_at,
                'thumbnail_url' => !empty($preset->thumbnail_url) ? esc_url($preset->thumbnail_url) : '',
            );
        }
        return rest_ensure_response($result);
    }

    /**
     * Créer un preset à partir de la sélection de l'utilisateur.
     *
     * Important : un preset capture uniquement la structure (blocs, grille,
     * liens externes, paramètres), JAMAIS les commentaires ni les assets
     * uploadés. Les commentaires restent attachés aux blocs d'origine.
     */
    public function create_block_preset($request) {
        $data = $request->get_json_params();

        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Missing preset name', 'agency-client-portal'), array('status' => 400));
        }
        if (empty($data['project_id'])) {
            return new WP_Error('missing_project_id', __('Missing project ID', 'agency-client-portal'), array('status' => 400));
        }

        $project_id        = intval($data['project_id']);
        $include_blocks         = !empty($data['include_blocks']);
        $include_grid           = !empty($data['include_grid']);
        $include_desc           = !empty($data['include_descriptions']);
        $include_links          = !empty($data['include_links']);
        $include_sep            = !empty($data['include_separators']);
        $include_thumbnail      = !empty($data['include_thumbnail']);
        $include_card_thumbnails = !empty($data['include_card_thumbnails']);
        $include_settings       = !empty($data['include_settings']);

        $blocks = Agency_Block::get_by_project($project_id);
        $preset_blocks = array();

        foreach ($blocks as $block) {
            // Séparateurs
            if ($block->type === 'separator') {
                if (!$include_sep) continue;
                $entry = array(
                    'type'           => 'separator',
                    'title'          => $block->title,
                    'order_position' => intval($block->order_position),
                );
                $preset_blocks[] = $entry;
                continue;
            }

            if (!$include_blocks) continue;

            $settings = !empty($block->settings) ? json_decode($block->settings, true) : array();

            // Retirer le contenu selon les options
            if (!$include_desc) {
                unset($settings['script_content']);
                $content = '';
            } else {
                $content = $block->content;
            }

            $entry = array(
                'type'           => $block->type,
                'title'          => $block->title,
                'content'        => $include_desc ? $content : '',
                'order_position' => intval($block->order_position),
            );

            if ($include_desc && !empty($settings['subtitle'])) {
                $entry['subtitle'] = $settings['subtitle'];
            }

            if ($include_card_thumbnails && !empty($settings['background_image_url'])) {
                $entry['background_image_url'] = esc_url_raw($settings['background_image_url']);
            }

            if ($include_grid) {
                $entry['grid_width']  = intval($block->grid_width);
                $entry['grid_height'] = intval($block->grid_height);
            }

            if ($include_links) {
                $links = Agency_External_Link::get_by_block($block->id);
                $entry['links'] = array();
                foreach ($links as $link) {
                    $entry['links'][] = array(
                        'emoji' => $link->emoji,
                        'text'  => $link->text,
                        'url'   => $link->url,
                    );
                }
            }

            if ($include_settings) {
                $entry['client_can_comment']   = Agency_Block::client_can_comment_allowed($block) ? 1 : 0;
                $entry['allow_media_download'] = Agency_Block::is_media_download_allowed($block) ? 1 : 0;
            }

            $preset_blocks[] = $entry;
        }

        // Miniature du projet si demandé
        $thumbnail_url = '';
        if ($include_thumbnail) {
            $project = Agency_Project::get_by_id($project_id);
            if ($project) {
                $thumbnail_url = Agency_Project::get_cover_thumbnail_url($project);
            }
        }

        $preset_id = Agency_Block_Preset::create(array(
            'name'          => sanitize_text_field($data['name']),
            'data'          => $preset_blocks,
            'thumbnail_url' => $thumbnail_url,
            'created_by'    => get_current_user_id(),
        ));

        if (!$preset_id) {
            return new WP_Error('create_failed', __('Error while creating the preset', 'agency-client-portal'), array('status' => 500));
        }

        return rest_ensure_response(array('success' => true, 'preset_id' => $preset_id));
    }

    /**
     * Importer un preset dans un projet (crée de nouveaux blocks)
     */
    public function import_block_preset($request) {
        $preset_id  = intval($request['id']);
        $data       = $request->get_json_params();
        $project_id = isset($data['project_id']) ? intval($data['project_id']) : 0;

        if (!$project_id) {
            return new WP_Error('missing_project_id', __('Missing project ID', 'agency-client-portal'), array('status' => 400));
        }

        $preset = Agency_Block_Preset::get_by_id($preset_id);
        if (!$preset) {
            return new WP_Error('preset_not_found', __('Preset not found', 'agency-client-portal'), array('status' => 404));
        }

        $blocks_data = json_decode($preset->data, true);
        if (!is_array($blocks_data)) {
            return new WP_Error('invalid_data', __('Invalid preset data', 'agency-client-portal'), array('status' => 400));
        }

        $created_ids = array();
        foreach ($blocks_data as $block_def) {
            // Garde-fou : un preset ne doit jamais transporter de commentaires
            // ni d'assets. On supprime explicitement toute clé inattendue avant
            // la création du bloc pour éviter toute fuite future.
            unset($block_def['comments'], $block_def['assets'], $block_def['asset_ids']);

            $new_block = array(
                'project_id'     => $project_id,
                'type'           => sanitize_text_field($block_def['type']),
                'title'          => sanitize_text_field($block_def['title']),
                'content'        => isset($block_def['content']) ? wp_kses_post($block_def['content']) : '',
                'order_position' => isset($block_def['order_position']) ? intval($block_def['order_position']) : 0,
                'grid_width'     => isset($block_def['grid_width']) ? intval($block_def['grid_width']) : 3,
                'grid_height'    => isset($block_def['grid_height']) ? intval($block_def['grid_height']) : 1,
                'status'         => 'active',
                'created_by'     => get_current_user_id(),
            );

            // Restore subtitle, image, and settings if present in preset
            $block_settings = array();
            if (!empty($block_def['subtitle'])) {
                $block_settings['subtitle'] = sanitize_text_field($block_def['subtitle']);
            }
            if (!empty($block_def['background_image_url'])) {
                $block_settings['background_image_url'] = esc_url_raw($block_def['background_image_url']);
            }
            if (isset($block_def['client_can_comment'])) {
                $block_settings['client_can_comment'] = intval($block_def['client_can_comment']);
            }
            if (isset($block_def['allow_media_download'])) {
                $block_settings['allow_media_download'] = intval($block_def['allow_media_download']);
            }
            if (!empty($block_settings)) {
                $new_block['settings'] = $block_settings;
            }

            if ($new_block['type'] === 'separator') {
                $new_block['grid_width'] = 12;
            }

            $block_id = Agency_Block::create($new_block);
            $created_ids[] = $block_id;

            // Recréer les liens si présents
            if (!empty($block_def['links']) && $block_id) {
                foreach ($block_def['links'] as $link_def) {
                    Agency_External_Link::create(array(
                        'block_id'   => $block_id,
                        'url'        => esc_url_raw($link_def['url']),
                        'text'       => sanitize_text_field($link_def['text']),
                        'emoji'      => sanitize_text_field($link_def['emoji']),
                        'created_by' => get_current_user_id(),
                    ));
                }
            }
        }

        // Appliquer la miniature au projet cible si le preset en contient une
        if (!empty($preset->thumbnail_url)) {
            Agency_Project::update($project_id, array(
                'cover_image_url'           => esc_url_raw($preset->thumbnail_url),
                'cover_image_thumbnail_url' => esc_url_raw($preset->thumbnail_url),
                'cover_image_asset_id'      => null,
            ));
        }

        return rest_ensure_response(array('success' => true, 'created' => count($created_ids)));
    }

    /**
     * Supprimer un preset
     */
    public function delete_block_preset($request) {
        $preset_id = intval($request['id']);
        $deleted   = Agency_Block_Preset::delete($preset_id);
        return rest_ensure_response(array('success' => $deleted));
    }

    /**
     * Suggestion de slide par un client (upload)
     */
    public function suggest_slide($request) {
        $files    = $request->get_file_params();
        $block_id = intval($request->get_param('block_id'));
        $parent_asset_id = intval($request->get_param('parent_asset_id'));
        $suggestion_text = sanitize_textarea_field($request->get_param('suggestion_text'));

        if (!$block_id) {
            return new WP_Error('no_block_id', __('Missing block ID', 'agency-client-portal'), array('status' => 400));
        }

        $block = Agency_Block::get_by_id($block_id);
        if (!$block) {
            return new WP_Error('block_not_found', __('Block not found', 'agency-client-portal'), array('status' => 404));
        }

        if (!isset($files['file'])) {
            return new WP_Error('no_file', __('No file provided', 'agency-client-portal'), array('status' => 400));
        }

        $meta = array(
            'suggested_by_client' => 1,
            'suggestion_text' => $suggestion_text,
            'parent_asset_id' => $parent_asset_id ?: null,
            'version_status' => 'pending',
        );

        $result = agency()->assets->upload_file($files['file'], $block_id, $meta, true);

        if (is_wp_error($result)) {
            return rest_ensure_response(array('success' => false, 'message' => $result->get_error_message()));
        }

        return rest_ensure_response(array('success' => true, 'asset' => $result));
    }

    /**
     * Créer une slide placeholder sans fichier (clients + managers)
     */
    public function create_placeholder_asset($request) {
        $data     = $request->get_json_params();
        $block_id = isset($data['block_id']) ? intval($data['block_id']) : 0;
        $after_asset_id = isset($data['after_asset_id']) ? intval($data['after_asset_id']) : null;

        if (!$block_id) {
            return new WP_Error('no_block_id', __('Missing block ID', 'agency-client-portal'), array('status' => 400));
        }

        $block = Agency_Block::get_by_id($block_id);
        if (!$block) {
            return new WP_Error('block_not_found', __('Block not found', 'agency-client-portal'), array('status' => 404));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        // Calculer l'order_position basé sur after_asset_id
        $order_position = $this->compute_insert_position($block_id, $after_asset_id);

        $wpdb->insert($table, array(
            'block_id'            => $block_id,
            'filename'            => '',
            'original_filename'   => __('Empty slide', 'agency-client-portal'),
            'file_path'           => '',
            'file_type'           => 'placeholder',
            'file_size'           => 0,
            'mime_type'           => '',
            'order_position'      => $order_position,
            'version_number'      => 1,
            'version_label'       => __('Version 1', 'agency-client-portal'),
            'version_status'      => 'draft',
            'parent_asset_id'     => null,
            'suggested_by_client' => 0,
            'uploaded_by'         => get_current_user_id(),
            'created_at'          => current_time('mysql'),
        ));

        $asset_id = $wpdb->insert_id;
        if (!$asset_id) {
            return rest_ensure_response(array('success' => false, 'message' => __('Error during creation', 'agency-client-portal')));
        }

        return rest_ensure_response(array('success' => true, 'asset_id' => $asset_id));
    }

    /**
     * Calculer la position d'insertion après un asset donné
     * Décale les assets suivants si nécessaire
     */
    private function compute_insert_position($block_id, $after_asset_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        if (!$after_asset_id) {
            // Insérer avant tout : position 0, décaler les autres
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET order_position = order_position + 1 WHERE block_id = %d AND (parent_asset_id IS NULL OR parent_asset_id = 0)",
                $block_id
            ));
            return 0;
        }

        // Trouver la position de l'asset cible
        $target_pos = $wpdb->get_var($wpdb->prepare(
            "SELECT order_position FROM $table WHERE id = %d",
            $after_asset_id
        ));

        if ($target_pos === null) {
            // Asset non trouvé, appender à la fin
            $max_pos = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(order_position) FROM $table WHERE block_id = %d AND (parent_asset_id IS NULL OR parent_asset_id = 0)",
                $block_id
            ));
            return intval($max_pos) + 1;
        }

        $new_pos = intval($target_pos) + 1;

        // Décaler tous les root assets après cette position
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET order_position = order_position + 1 WHERE block_id = %d AND (parent_asset_id IS NULL OR parent_asset_id = 0) AND order_position >= %d",
            $block_id,
            $new_pos
        ));

        return $new_pos;
    }
}
