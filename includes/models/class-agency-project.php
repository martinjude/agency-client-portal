<?php
/**
 * Modèle Project
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Project {
    
    /**
     * Obtenir tous les projets d'un client
     */
    public static function get_by_client($client_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE client_id = %d AND status = 'active' AND (is_archived = 0 OR is_archived IS NULL) ORDER BY order_position ASC, created_at DESC",
            $client_id
        ));
    }

    /**
     * Projets actifs de plusieurs clients en une requête, groupés par client_id.
     *
     * @param int[] $client_ids
     * @return array<int, object[]>
     */
    public static function get_by_clients(array $client_ids) {
        global $wpdb;
        $client_ids = array_values(array_filter(array_map('intval', $client_ids)));
        if (empty($client_ids)) {
            return array();
        }
        $table        = $wpdb->prefix . 'agency_projects';
        $placeholders = implode(',', array_fill(0, count($client_ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders ne contient que des %d.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE client_id IN ($placeholders) AND status = 'active' AND (is_archived = 0 OR is_archived IS NULL) ORDER BY order_position ASC, created_at DESC",
            $client_ids
        ));
        $grouped = array();
        foreach ($rows as $row) {
            $grouped[intval($row->client_id)][] = $row;
        }
        return $grouped;
    }

    /**
     * Obtenir les projets archivés d'un client
     */
    public static function get_archived_by_client($client_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE client_id = %d AND status = 'active' AND is_archived = 1 ORDER BY order_position ASC, created_at DESC",
            $client_id
        ));
    }
    
    /**
     * Obtenir un projet par ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Obtenir un projet par slug et client
     */
    public static function get_by_slug($client_id, $slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE client_id = %d AND slug = %s",
            $client_id,
            $slug
        ));
    }
    
    /**
     * Créer un nouveau projet
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            return false;
        }

        // Valider les champs requis
        if (empty($data['client_id']) || empty($data['name'])) {
            return false;
        }

        // Générer un slug unique pour ce client
        $slug = sanitize_title($data['name']);
        $slug = self::get_unique_slug($data['client_id'], $slug);

        if ($slug === false) {
            return false;
        }

    // FIX: Don't sanitize emoji at all - it's just a Unicode character passed from frontend
    // Emojis are safe and don't need sanitization, any processing can corrupt the multi-byte UTF-8 encoding
    $emoji = isset($data['emoji']) && !empty($data['emoji']) ? $data['emoji'] : '📋';

		$insert_data = array(
			'client_id' => intval($data['client_id']),
			'name' => sanitize_text_field($data['name']),
			'email' => isset($data['email']) ? sanitize_email($data['email']) : null,
			'slug' => $slug,
			'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
			'subtitle' => isset($data['subtitle']) ? sanitize_textarea_field($data['subtitle']) : null,
			'cover_image_url' => isset($data['cover_image_url']) ? esc_url_raw($data['cover_image_url']) : null,
			'cover_image_thumbnail_url' => isset($data['cover_image_thumbnail_url']) ? esc_url_raw($data['cover_image_thumbnail_url']) : null,
			'cover_image_asset_id' => isset($data['cover_image_asset_id']) ? intval($data['cover_image_asset_id']) : null,
			'emoji' => $emoji,
			'labels' => isset($data['labels']) ? sanitize_text_field($data['labels']) : null,
            'status' => 'active',
            'is_archived' => 0,
            'order_position' => isset($data['order_position']) ? intval($data['order_position']) : 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            return false;
        }

        $project_id = $wpdb->insert_id;

        do_action('agency_project_created', $project_id, $data);

        return $project_id;
    }
    
    /**
     * Mettre à jour un projet
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';
        
        // Vérifier si le nom/slug a changé pour migrer les assets
        $slug_changed = false;
        $old_project = null;
        $old_project_dir = '';
        if (isset($data['name'])) {
            $old_project = self::get_by_id($id);
            if ($old_project && $old_project->name !== $data['name']) {
                $slug_changed = true;

                // Capturer l'ancien dossier projet avant la mise à jour du slug.
                $client = Agency_Client::get_by_id($old_project->client_id);
                if ($client && !empty($client->slug) && !empty($old_project->slug)) {
                    $old_project_dir = AGENCY_UPLOAD_DIR . '/' . $client->slug . '/' . $old_project->slug;
                }
            }
        }
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            
            // Récupérer le projet pour obtenir le client_id
            $project = $old_project ? $old_project : self::get_by_id($id);
            if ($project) {
                $slug = sanitize_title($data['name']);
                $update_data['slug'] = self::get_unique_slug($project->client_id, $slug, $id);
            }
        }
        
        if (array_key_exists('email', $data)) {
            $update_data['email'] = $data['email'] ? sanitize_email($data['email']) : null;
        }

        if (isset($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
        }

        if (isset($data['subtitle'])) {
            $update_data['subtitle'] = sanitize_textarea_field($data['subtitle']);
        }

		if (array_key_exists('cover_image_url', $data)) {
			$update_data['cover_image_url'] = esc_url_raw((string) $data['cover_image_url']);
		}

		if (array_key_exists('cover_image_thumbnail_url', $data)) {
			$update_data['cover_image_thumbnail_url'] = $data['cover_image_thumbnail_url']
				? esc_url_raw((string) $data['cover_image_thumbnail_url'])
				: null;
		}

		if (array_key_exists('cover_image_asset_id', $data)) {
			$update_data['cover_image_asset_id'] = $data['cover_image_asset_id']
				? intval($data['cover_image_asset_id'])
				: null;
        }
        
        if (isset($data['emoji'])) {
            // FIX: Don't sanitize emoji - just pass it through as-is to preserve UTF-8 encoding
            $update_data['emoji'] = $data['emoji'];
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['labels'])) {
            $update_data['labels'] = sanitize_text_field($data['labels']);
        }

        if (isset($data['is_archived'])) {
            $update_data['is_archived'] = $data['is_archived'] ? 1 : 0;
        }

        if (isset($data['order_position'])) {
            $update_data['order_position'] = intval($data['order_position']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $id));
        
        // Si le slug a changé, migrer les assets vers le nouveau chemin
        if ($slug_changed && $result !== false) {
            $assets_manager = agency()->assets;
            if (!empty($old_project_dir)) {
                $assets_manager->cleanup_directory_if_empty($old_project_dir);
            }
            $assets_manager->migrate_project_assets($id);
        }
        
        do_action('agency_project_updated', $id, $data);
        
        return $result !== false;
    }
    
    /**
     * Supprimer un projet (cascade batché : blocs, assets, commentaires, liens).
     *
     * Avant ce refactor, la suppression d'un projet de 50 blocs avec 10 assets
     * chacun générait ~700 requêtes. Désormais ~6 requêtes SQL + N unlink fichiers.
     */
    public static function delete($id) {
        global $wpdb;
        $id = intval($id);
        if (!$id) {
            return false;
        }

        $blocks_table   = $wpdb->prefix . 'agency_blocks';
        $projects_table = $wpdb->prefix . 'agency_projects';

        // 1) Charger les IDs de blocs en une seule requête (inclut les inactifs).
        $block_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$blocks_table} WHERE project_id = %d",
            $id
        ));
        $block_ids = array_map('intval', (array) $block_ids);

        // 2) Supprimer assets (fichiers + DB) en lot pour tous les blocs concernés.
        if (!empty($block_ids) && isset(agency()->assets)) {
            agency()->assets->delete_assets_for_block_ids($block_ids);
        }

        // 3) Supprimer commentaires en lot.
        if (!empty($block_ids)) {
            Agency_Comment::delete_by_block_ids($block_ids);
        }

        // 4) Supprimer liens externes en lot (niveau projet + niveau bloc).
        if (class_exists('Agency_External_Link')) {
            Agency_External_Link::delete_by_project($id, $block_ids);
        }

        // 5) Supprimer les blocs.
        if (!empty($block_ids)) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$blocks_table} WHERE project_id = %d",
                $id
            ));
        }

        // 6) Supprimer le projet lui-même.
        $result = $wpdb->delete($projects_table, array('id' => $id), array('%d'));

        do_action('agency_project_deleted', $id);

        return $result !== false;
    }
    
    /**
     * Obtenir un slug unique pour un client
     */
    private static function get_unique_slug($client_id, $slug, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';

        $original_slug = $slug;
        $counter = 1;

        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE client_id = %d AND slug = %s AND id != %d",
                $client_id,
                $slug,
                intval($exclude_id)
            ));

            if ($exists === null) {
                return false;
            }

            if (!$exists) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
    
    /**
     * Obtenir les blocs d'un projet
     */
    public static function get_blocks($project_id) {
        return Agency_Block::get_by_project($project_id);
    }
    
    /**
     * Réorganiser les projets d'un client en une seule requête CASE WHEN.
     */
    public static function reorder($client_id, $project_ids) {
        $client_id   = intval($client_id);
        $project_ids = array_values(array_filter(array_map('intval', (array) $project_ids)));
        if (!$client_id || empty($project_ids)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_projects';

        $cases = '';
        $case_args = array();
        foreach ($project_ids as $position => $project_id) {
            $cases .= ' WHEN %d THEN %d';
            $case_args[] = $project_id;
            $case_args[] = $position;
        }
        $placeholders = implode(',', array_fill(0, count($project_ids), '%d'));
        $sql = "UPDATE {$table}
                SET order_position = CASE id{$cases} END
                WHERE client_id = %d AND id IN ({$placeholders})";
        $args = array_merge($case_args, array($client_id), $project_ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql ne contient que des placeholders %d construits ci-dessus.
        return $wpdb->query($wpdb->prepare($sql, $args)) !== false;
    }

    /**
     * Retourne l'URL de la vignette de couverture (thumbnail si asset ID disponible,
     * sinon fallback sur cover_image_url). Utilisé pour les cartes projet (client-view).
     */
	public static function get_cover_thumbnail_url($project): string {
		if (!$project || !is_object($project)) {
			return '';
		}
		if (!empty($project->cover_image_asset_id)) {
			return Agency_Asset::get_thumbnail_url(intval($project->cover_image_asset_id));
		}
		if (!empty($project->cover_image_thumbnail_url)) {
			return (string) $project->cover_image_thumbnail_url;
		}
		return !empty($project->cover_image_url) ? $project->cover_image_url : '';
	}

    /**
     * Retourne l'URL full-size de la couverture. Utilisé pour l'affichage pleine page
     * (ex : arrière-plan project-view) où la qualité maximale est requise.
     */
    public static function get_cover_full_url($project): string {
        if (!$project || !is_object($project)) {
            return '';
        }
        if (!empty($project->cover_image_asset_id)) {
            return Agency_Asset::get_url(intval($project->cover_image_asset_id));
        }
        return !empty($project->cover_image_url) ? $project->cover_image_url : '';
    }
}
