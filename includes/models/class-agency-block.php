<?php
/**
 * Modèle Block
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Block {
    
    /**
     * Types de blocs disponibles
     */
    const TYPES = array(
        'text' => 'Text',
        'script' => 'Script',
        'storyboard' => 'Storyboard',
        'versioned_media' => 'Versioned media',
        'video' => 'Video',
        'video_youtube' => 'YouTube video',
        'video_dropbox' => 'Dropbox video',
        'pdf' => 'PDF document',
        'voiceover' => 'Voice-over',
        'image_gallery' => 'Image gallery',
        'embed' => 'Embed',
        'link' => 'Link',
        'separator' => 'Separator',
        'progress_bar' => 'Progress bar',
        'file_deposit' => 'File deposit',
        'code' => 'Code',
    );

    /**
     * Types de blocs visibles pour les clients (vue restreinte)
     */
    const CLIENT_TYPES = array(
        'script' => 'Script',
        'storyboard' => 'Storyboard',
        'versioned_media' => 'Versioned media',
        'video' => 'Video',
        'link' => 'Link',
        'separator' => 'Separator',
        'file_deposit' => 'File deposit',
        'code' => 'Code',
    );

    /**
     * Types de blocs supportant le réglage d'autorisation de téléchargement.
     */
    const MEDIA_DOWNLOAD_SETTING_TYPES = array(
        'video',
        'versioned_media',
        'storyboard',
        'script',
    );
    
    /**
     * Obtenir tous les blocs d'un projet
     */
    public static function get_by_project($project_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_blocks';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE project_id = %d AND status = 'active' ORDER BY order_position ASC, created_at DESC",
            $project_id
        ));
    }
    
    /**
     * Obtenir un bloc par ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_blocks';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Créer un nouveau bloc
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_blocks';
        
        // Valider le type
        if (!isset($data['type']) || !array_key_exists($data['type'], self::TYPES)) {
            return new WP_Error('invalid_type', __('Invalid block type', 'agency-client-portal'));
        }
        
        // Encoder les settings en JSON
        $settings = isset($data['settings']) ? json_encode(self::normalize_settings($data['settings'])) : null;
        
        $wpdb->insert($table, array(
            'project_id' => intval($data['project_id']),
            'type' => sanitize_text_field($data['type']),
            'title' => sanitize_text_field($data['title']),
            'content' => isset($data['content']) ? wp_kses_post($data['content']) : null,
            'settings' => $settings,
            'order_position' => isset($data['order_position']) ? intval($data['order_position']) : 0,
            'grid_width' => isset($data['grid_width']) ? intval($data['grid_width']) : 3,
            'grid_height' => isset($data['grid_height']) ? intval($data['grid_height']) : 1,
            'status' => 'active',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ));
        
        $block_id = $wpdb->insert_id;
        
        do_action('agency_block_created', $block_id, $data);
        
        return $block_id;
    }
    
    /**
     * Mettre à jour un bloc
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_blocks';
        
        // Vérifier si le titre a changé pour migrer les assets
        $title_changed = false;
        $old_block_dir = '';
        if (isset($data['title'])) {
            $old_block = self::get_by_id($id);
            if ($old_block && $old_block->title !== $data['title']) {
                $title_changed = true;

                // Capturer l'ancien dossier bloc avant la mise à jour du titre.
                $project = Agency_Project::get_by_id($old_block->project_id);
                if ($project) {
                    $client = Agency_Client::get_by_id($project->client_id);
                    if ($client && !empty($client->slug) && !empty($project->slug)) {
                        $old_block_folder = $old_block->id . '-' . sanitize_file_name($old_block->title);
                        $old_block_dir = AGENCY_UPLOAD_DIR . '/' . $client->slug . '/' . $project->slug . '/' . $old_block_folder;
                    }
                }
            }
        }
        
        $update_data = array();
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
        }
        
        if (isset($data['settings'])) {
            $update_data['settings'] = json_encode(self::normalize_settings($data['settings']));
        }
        
        if (isset($data['order_position'])) {
            $update_data['order_position'] = intval($data['order_position']);
        }
        
        if (isset($data['grid_width'])) {
            $update_data['grid_width'] = intval($data['grid_width']);
        }
        
        if (isset($data['grid_height'])) {
            $update_data['grid_height'] = intval($data['grid_height']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $id));
        
        // Si le titre a changé, migrer les assets vers le nouveau chemin
        if ($title_changed && $result !== false) {
            $assets_manager = agency()->assets;
            if (!empty($old_block_dir)) {
                $assets_manager->cleanup_directory_if_empty($old_block_dir);
            }
            $assets_manager->migrate_block_assets($id);
        }
        
        do_action('agency_block_updated', $id, $data);
        
        return $result !== false;
    }
    
    /**
     * Supprimer un bloc (cascade batché : assets, commentaires, liens externes).
     */
    public static function delete($id) {
        global $wpdb;
        $id = intval($id);
        if (!$id) {
            return false;
        }

        $table = $wpdb->prefix . 'agency_blocks';

        // Assets : suppression batchée (fichiers + DB) via assets manager.
        if (isset(agency()->assets)) {
            agency()->assets->delete_assets_for_block_ids(array($id));
        }

        // Commentaires associés à ce bloc.
        Agency_Comment::delete_by_block($id);

        // Liens externes attachés à ce bloc uniquement.
        if (class_exists('Agency_External_Link')) {
            Agency_External_Link::delete_by_project(0, array($id));
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        do_action('agency_block_deleted', $id);

        return $result !== false;
    }
    
    /**
     * Obtenir les commentaires d'un bloc
     */
    public static function get_comments($block_id) {
        return Agency_Comment::get_by_block($block_id);
    }
    
    /**
     * Obtenir les assets d'un bloc
     */
    public static function get_assets($block_id) {
        return Agency_Asset::get_by_block($block_id);
    }
    
    /**
     * Réorganiser les blocs d'un projet en une seule requête CASE WHEN.
     */
    public static function reorder($project_id, $block_ids) {
        $project_id = intval($project_id);
        $block_ids = array_values(array_filter(array_map('intval', (array) $block_ids)));
        if (!$project_id || empty($block_ids)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_blocks';

        $cases = '';
        $case_args = array();
        foreach ($block_ids as $position => $block_id) {
            $cases .= ' WHEN %d THEN %d';
            $case_args[] = $block_id;
            $case_args[] = $position;
        }
        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));
        $sql = "UPDATE {$table}
                SET order_position = CASE id{$cases} END
                WHERE project_id = %d AND id IN ({$placeholders})";
        $args = array_merge($case_args, array($project_id), $block_ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql ne contient que des placeholders %d construits ci-dessus.
        return $wpdb->query($wpdb->prepare($sql, $args)) !== false;
    }

    /**
     * Mettre à jour l'ordre de plusieurs blocs (une seule requête CASE WHEN).
     */
    public static function update_order($block_ids) {
        $block_ids = array_values(array_filter(array_map('intval', (array) $block_ids)));
        if (empty($block_ids)) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_blocks';

        $cases = '';
        $case_args = array();
        foreach ($block_ids as $position => $block_id) {
            $cases .= ' WHEN %d THEN %d';
            $case_args[] = $block_id;
            $case_args[] = $position;
        }
        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));
        $sql = "UPDATE {$table} SET order_position = CASE id{$cases} END WHERE id IN ({$placeholders})";
        $args = array_merge($case_args, $block_ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql ne contient que des placeholders %d construits ci-dessus.
        return $wpdb->query($wpdb->prepare($sql, $args)) !== false;
    }
    
    /**
     * Obtenir les types de blocs disponibles
     */
    public static function get_types() {
        return array(
            'text' => __('Text', 'agency-client-portal'),
            'script' => __('Script', 'agency-client-portal'),
            'storyboard' => __('Storyboard', 'agency-client-portal'),
            'versioned_media' => __('Versioned media', 'agency-client-portal'),
            'video' => __('Video', 'agency-client-portal'),
            'video_youtube' => __('YouTube video', 'agency-client-portal'),
            'video_dropbox' => __('Dropbox video', 'agency-client-portal'),
            'pdf' => __('PDF document', 'agency-client-portal'),
            'voiceover' => __('Voice-over', 'agency-client-portal'),
            'image_gallery' => __('Image gallery', 'agency-client-portal'),
            'embed' => __('Embed', 'agency-client-portal'),
            'link' => __('Link', 'agency-client-portal'),
            'separator' => __('Separator', 'agency-client-portal'),
            'progress_bar' => __('Progress bar', 'agency-client-portal'),
            'file_deposit' => __('File deposit', 'agency-client-portal'),
            'code' => __('Code', 'agency-client-portal'),
        );
    }

    /**
     * Libellé traduit d'un type de bloc.
     *
     * @param string $type
     * @return string
     */
    public static function get_type_label($type) {
        $types = self::get_types();
        return isset($types[$type]) ? $types[$type] : (string) $type;
    }
    
    /**
     * Obtenir l'icône emoji pour un type de bloc
     */
    public static function get_type_icon($type) {
        $icons = array(
            'text' => '📝',
            'script' => '📄',
            'storyboard' => '🎨',
            'versioned_media' => '🖼️',
            'video' => '🎬',
            'video_youtube' => '🎥',
            'video_dropbox' => '📹',
            'pdf' => '📑',
            'voiceover' => '🎙️',
            'image_gallery' => '🖼️',
            'embed' => '🔗',
            'link' => '🔗',
            'separator' => '➖',
            'progress_bar' => '📊',
        );

        return isset($icons[$type]) ? $icons[$type] : '📦';
    }

    /**
     * Retourne les settings d'un bloc sous forme de tableau.
     */
    public static function get_settings($block) {
        if (!$block || empty($block->settings)) {
            return array();
        }

        $settings = is_array($block->settings)
            ? $block->settings
            : json_decode($block->settings, true);

        // Rattrapage pour d'anciens enregistrements double-encodés.
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return is_array($settings) ? $settings : array();
    }

    /**
     * Vérifie si les commentaires clients sont autorisés pour ce bloc.
     */
    public static function client_can_comment_allowed($block) {
        $settings = self::get_settings($block);
        // Default to true - comments allowed by default for all blocks
        return isset($settings['client_can_comment']) ? self::normalize_bool_setting($settings['client_can_comment'], true) : true;
    }

    /**
     * Indique si le créateur du projet reçoit une notification email lors d'un
     * commentaire sur ce bloc. Activé par défaut (clé absente => true).
     */
    public static function notify_on_comment_enabled($block) {
        $settings = self::get_settings($block);
        return isset($settings['notify_on_comment']) ? self::normalize_bool_setting($settings['notify_on_comment'], true) : true;
    }

    /**
     * Vérifie si le type de bloc supporte le réglage de téléchargement média.
     */
    public static function supports_media_download_setting($block_type) {
        return in_array($block_type, self::MEDIA_DOWNLOAD_SETTING_TYPES, true);
    }

    /**
     * Indique si le téléchargement média est autorisé pour ce bloc.
     */
    public static function is_media_download_allowed($block) {
        if (!$block || !self::supports_media_download_setting($block->type)) {
            return true;
        }

        $settings = self::get_settings($block);
        if (!array_key_exists('allow_media_download', $settings)) {
            return true;
        }

        return self::normalize_bool_setting($settings['allow_media_download'], true);
    }

    /**
     * Normalise les settings d'un bloc avant stockage.
     */
    private static function normalize_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }

        if (array_key_exists('allow_media_download', $settings)) {
            $settings['allow_media_download'] = self::normalize_bool_setting($settings['allow_media_download'], true) ? 1 : 0;
        }

        if (array_key_exists('client_can_comment', $settings)) {
            $settings['client_can_comment'] = self::normalize_bool_setting($settings['client_can_comment'], true) ? 1 : 0;
        }

        if (array_key_exists('notify_on_comment', $settings)) {
            $settings['notify_on_comment'] = self::normalize_bool_setting($settings['notify_on_comment'], true) ? 1 : 0;
        }

        return $settings;
    }

    /**
     * Normalise une valeur booléenne souple.
     */
    private static function normalize_bool_setting($value, $default = true) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return intval($value) === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {
                return true;
            }
            if (in_array($value, array('0', 'false', 'no', 'off'), true)) {
                return false;
            }
        }

        return $default;
    }
}
