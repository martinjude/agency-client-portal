<?php
/**
 * Modèle Client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Client {
    
    /**
     * Obtenir tous les clients
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        return $wpdb->get_results("SELECT * FROM $table WHERE status = 'active' ORDER BY name ASC");
    }
    
    /**
     * Obtenir un client par ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Obtenir un client par slug
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug));
    }
    
    /**
     * Obtenir un client par token d'accès
     */
    public static function get_by_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        // Lookup par hash : la colonne access_token n'existe plus.
        if (!is_string($token) || $token === '') {
            return null;
        }
        $token_hash = hash('sha256', $token);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token_hash = %s AND token_active = 1 AND status = 'active'",
            $token_hash
        ));
    }
    
    /**
     * Créer un nouveau client
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        // Générer un slug unique
        $slug = sanitize_title($data['name']);
        $slug = self::get_unique_slug($slug);
        
        // Insérer le client d'abord sans token
        $wpdb->insert($table, array(
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id(),
            'name' => sanitize_text_field($data['name']),
            'client_view_title' => isset($data['client_view_title']) ? sanitize_text_field($data['client_view_title']) : null,
            'slug' => $slug,
            'logo_url' => isset($data['logo_url']) ? esc_url_raw($data['logo_url']) : null,
            'primary_color' => isset($data['primary_color']) ? sanitize_hex_color($data['primary_color']) : '#000000',
            'secondary_color' => isset($data['secondary_color']) ? sanitize_hex_color($data['secondary_color']) : '#ffffff',
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ));
        
        $client_id = $wpdb->insert_id;
        
        // Générer automatiquement un token d'accès pour ce nouveau client
        if ($client_id) {
            Agency_Security::agency_generate_client_access_token($client_id);
        }
        
        // Legacy user-based permission removed (token-only access)
        
        do_action('agency_client_created', $client_id, $data);
        
        return $client_id;
    }
    
    /**
     * Mettre à jour un client
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        // Vérifier si le nom/slug a changé pour migrer les assets
        $slug_changed = false;
        if (isset($data['name'])) {
            $old_client = self::get_by_id($id);
            if ($old_client && $old_client->name !== $data['name']) {
                $slug_changed = true;
            }
        }
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            
            // Mettre à jour le slug si le nom change
            $slug = sanitize_title($data['name']);
            $update_data['slug'] = self::get_unique_slug($slug, $id);
        }
        
        if (isset($data['logo_url'])) {
            $update_data['logo_url'] = esc_url_raw($data['logo_url']);
        }

        if (isset($data['client_view_title'])) {
            $update_data['client_view_title'] = sanitize_text_field($data['client_view_title']);
        }
        
        if (isset($data['primary_color'])) {
            $update_data['primary_color'] = sanitize_hex_color($data['primary_color']);
        }
        
        if (isset($data['secondary_color'])) {
            $update_data['secondary_color'] = sanitize_hex_color($data['secondary_color']);
        }

        if (isset($data['background_image_url'])) {
            $update_data['background_image_url'] = esc_url_raw($data['background_image_url']);
        }

        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        // Legacy user_id updates removed
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $id));
        
        // Si le slug a changé, migrer les assets vers le nouveau chemin
        if ($slug_changed && $result !== false) {
            $assets_manager = agency()->assets;
            $assets_manager->migrate_client_assets($id);
        }
        
        do_action('agency_client_updated', $id, $data);
        
        return $result !== false;
    }
    
    /**
     * Supprimer un client
     */
    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';

        // Soft delete
        $result = $wpdb->update($table, array('status' => 'deleted'), array('id' => $id));

        do_action('agency_client_deleted', $id);

        return $result !== false;
    }

    /**
     * Suppression definitive d'un client avec cascade (projets, blocs, assets, fichiers)
     */
    public static function hard_delete($id) {
        global $wpdb;
        $id = intval($id);

        // 1. Supprimer tous les projets actifs du client (cascade blocs/assets/comments)
        $projects = Agency_Project::get_by_client($id);
        foreach ($projects as $project) {
            Agency_Project::delete($project->id);
        }

        // 2. Supprimer les projets archives aussi
        $archived = Agency_Project::get_archived_by_client($id);
        foreach ($archived as $project) {
            Agency_Project::delete($project->id);
        }

        // 3. Supprimer les permissions
        $wpdb->delete($wpdb->prefix . 'agency_permissions', array('client_id' => $id), array('%d'));

        // 4. Supprimer le dossier uploads du client
        $upload_dir = AGENCY_UPLOAD_DIR . '/' . $id;
        if (is_dir($upload_dir)) {
            self::recursive_rmdir($upload_dir);
        }

        // 5. Recuperer le post CPT associe avant de supprimer la ligne
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'agency_client_id' AND meta_value = %d LIMIT 1",
            $id
        ));

        // 6. Hard delete de la ligne client
        $wpdb->delete($wpdb->prefix . 'agency_clients', array('id' => $id), array('%d'));

        // 7. Supprimer le post CPT associe (force true = bypass trash)
        if ($post_id) {
            wp_delete_post($post_id, true);
        }

        do_action('agency_client_hard_deleted', $id);

        return true;
    }

    /**
     * Suppression recursive d'un repertoire
     */
    private static function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        Agency_Assets_Manager::filesystem()->delete($dir, true, 'd');
    }
    
    /**
     * Obtenir un slug unique
     */
    private static function get_unique_slug($slug, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        $original_slug = $slug;
        $counter = 1;
        
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE slug = %s AND id != %d",
                $slug,
                intval($exclude_id)
            ));
            
            if (!$exists) {
                break;
            }
            
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Ajouter une permission pour un utilisateur
     */
    public static function add_permission($user_id, $client_id, $level = 'view') {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_permissions';
        
        $wpdb->replace($table, array(
            'user_id' => $user_id,
            'client_id' => $client_id,
            'permission_level' => $level,
            'created_at' => current_time('mysql'),
        ));
    }
    
    /**
     * Supprimer une permission
     */
    public static function remove_permission($user_id, $client_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_permissions';
        
        $wpdb->delete($table, array(
            'user_id' => $user_id,
            'client_id' => $client_id,
        ));
    }
    
    /**
     * Obtenir les projets d'un client
     */
    public static function get_projects($client_id) {
        return Agency_Project::get_by_client($client_id);
    }
    
    /**
     * Obtenir l'URL d'accès direct d'un client avec token.
     *
     * Le plaintext du token n'est plus stocké en base. Il n'est donc visible
     * que :
     *   - dans l'heure qui suit sa génération (transient mémoire),
     *   - ou si aucun token actif n'existe (auto-génération).
     *
     * @param int  $client_id
     * @param bool $force_generate Force la génération d'un nouveau token,
     *                              invalidant ainsi tout lien précédemment partagé.
     * @return string|false URL d'accès ou false si plaintext indisponible.
     */
    public static function get_access_url($client_id, $force_generate = false) {
        $client_id = intval($client_id);
        if (!$client_id) {
            return false;
        }

        $client = self::get_by_id($client_id);
        if (!$client) {
            return false;
        }

        $has_active_token = !empty($client->token_hash) && (int) $client->token_active === 1;

        if ($force_generate || !$has_active_token) {
            $token = Agency_Security::agency_generate_client_access_token($client_id);
        } else {
            $token = Agency_Security::get_recent_token_plaintext($client_id);
        }

        if (empty($token)) {
            // Plaintext indisponible (TTL expirée). L'admin doit explicitement
            // re-générer, ce qui invalidera le lien précédemment envoyé au client.
            return false;
        }

        $base_url = home_url('/client-portal/');
        return add_query_arg(
            array('client_id' => $client_id, 'token' => $token),
            $base_url
        );
    }
    
    /**
     * Vérifier si un client a un token actif
     */
    public static function has_active_token($client_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_clients';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE id = %d 
             AND token_hash IS NOT NULL 
             AND token_active = 1 
             AND (token_expires_at IS NULL OR token_expires_at > NOW())",
            $client_id
        ));
        
        return $count > 0;
    }
}
